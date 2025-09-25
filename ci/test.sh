#!/usr/bin/env bash
# CI test runner for Tunkki-dev
# - Brings up DB and FPM containers
# - Prepares isolated test DB (never touches dev DB)
# - Runs migrations (or schema update) in test env
# - Loads Doctrine fixtures

# - Executes phpunit
set -euo pipefail

DC="docker compose"
DB_ROOT_FIX_ATTEMPTED=0

log() {
  printf "[test.sh] %s\n" "$*"
}

wait_for_db() {
  log "Ensuring Symfony can connect (APP_ENV=test)..."
  local LAST_SELECT_ERR="" LAST_CREATE_ERR="" out=""
  for i in {1..60}; do
    # Try a simple ping using dbal:run-sql and capture any error
    if out="$($DC exec -T -e APP_ENV=test fpm ./bin/console dbal:run-sql 'SELECT 1' 2>&1)"; then
      log "DB reachable via Symfony."
      return 0
    else
      LAST_SELECT_ERR="$out"
      if [[ $DB_ROOT_FIX_ATTEMPTED -eq 0 && ( "$LAST_SELECT_ERR" == *"Access denied"* || "$LAST_SELECT_ERR" == *"[1044]"* ) ]]; then
        DB_ROOT_FIX_ATTEMPTED=1
        log "Access denied for app user to test DB; attempting root-level DB create/grant via root..."
        ensure_test_db_via_root || log "Root-level DB create/grant attempt failed; will keep retrying..."
      fi
    fi

    # Attempt to create the database (if it doesn't exist), capture any output
    if out="$($DC exec -T -e APP_ENV=test fpm ./bin/console doctrine:database:create --if-not-exists 2>&1)"; then
      :
    else
      LAST_CREATE_ERR="$out"
    fi

    # Retry ping after (potential) create
    if out="$($DC exec -T -e APP_ENV=test fpm ./bin/console dbal:run-sql 'SELECT 1' 2>&1)"; then
      log "DB reachable via Symfony."
      return 0
    else
      LAST_SELECT_ERR="$out"
      if [[ $DB_ROOT_FIX_ATTEMPTED -eq 0 && ( "$LAST_SELECT_ERR" == *"Access denied"* || "$LAST_SELECT_ERR" == *"[1044]"* ) ]]; then
        DB_ROOT_FIX_ATTEMPTED=1
        log "Access denied for app user to test DB; attempting root-level DB create/grant via root..."
        ensure_test_db_via_root || log "Root-level DB create/grant attempt failed; will keep retrying..."
      fi
    fi

    sleep 1
  done

  echo "ERROR: DB not reachable in time via Symfony. Last SELECT 1 check failed even after attempting doctrine:database:create." >&2
  if [[ -n "$LAST_SELECT_ERR" ]]; then
    printf "[test.sh] Last dbal:run-sql error output:\n%s\n" "$LAST_SELECT_ERR" >&2
  fi
  if [[ -n "$LAST_CREATE_ERR" ]]; then
    printf "[test.sh] Last doctrine:database:create output:\n%s\n" "$LAST_CREATE_ERR" >&2
  fi
  return 1
}

prepare_test_db() {
  log "Creating test database if it does not exist via Symfony (APP_ENV=test)..."
  for i in {1..30}; do
    if $DC exec -T -e APP_ENV=test fpm ./bin/console doctrine:database:create --if-not-exists >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "ERROR: Could not create test database via Symfony." >&2
  return 1
}

# Compute final test DB name and create/grant using DB root inside db container if needed
ensure_test_db_via_root() {
  set +e
  local DBURL
  DBURL="$($DC exec -T -e APP_ENV=test fpm php -r 'echo getenv("DATABASE_URL");' 2>/dev/null)"
  local DBUSER="" DBPASS="" DBHOST="" DBPORT="" DBNAME=""
  # Parse DATABASE_URL (e.g., mysql://user:pass@db:3306/dbname?params)
  local rest="${DBURL#*://}"
  local creds="${rest%%@*}"
  local hostpath="${rest#*@}"
  local hostport="${hostpath%%/*}"
  local path="${hostpath#*/}"
  DBNAME="${path%%\?*}"
  # Strip any quotes from DBNAME
  DBNAME="${DBNAME//\"/}"; DBNAME="${DBNAME//\'/}"
  DBUSER="${creds%%:*}"
  # Strip any quotes from DBUSER
  DBUSER="${DBUSER//\"/}"; DBUSER="${DBUSER//\'/}"
  DBPASS="${creds#*:}"
  DBPASS="${DBPASS%%@*}"
  DBHOST="${hostport%%:*}"
  DBPORT="${hostport#*:}"
  if [[ "$DBPORT" == "$hostport" || -z "$DBPORT" ]]; then DBPORT="3306"; fi

  # Try to determine dbname_suffix from doctrine config; default to _test
  local SUFFIX
  SUFFIX="$($DC exec -T -e APP_ENV=test fpm ./bin/console debug:config doctrine 2>/dev/null | awk -F: '/dbname_suffix/{print $2}' | tr -d ' \r')"
  # Default suffix when empty or null
  if [[ -z "$SUFFIX" || "$SUFFIX" == "~" ]]; then SUFFIX="_test"; fi
  # Trim any env placeholders from suffix (e.g., _test%env(default:TEST_TOKEN:))
  SUFFIX="${SUFFIX%%%*}"
  # Strip any quotes from suffix
  SUFFIX="${SUFFIX//\"/}"; SUFFIX="${SUFFIX//\'/}"
  local FINAL_DB="${DBNAME}${SUFFIX}"

  log "Parsed DB -> base='${DBNAME}' suffix='${SUFFIX}' final='${FINAL_DB}' user='${DBUSER}' host='${DBHOST}' port='${DBPORT}'"
  log "Root fallback: creating DB '${FINAL_DB}' and granting privileges to '${DBUSER}'@'%' via db container..."
  # Escape for embedding inside double quotes for remote shell
  local SQL="CREATE DATABASE IF NOT EXISTS \\\`$FINAL_DB\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \\\`$FINAL_DB\\\`.* TO \\\"$DBUSER\\\"@\\\"%\\\"; FLUSH PRIVILEGES;"
  # Prefer socket auth first, then TCP to avoid networking edge cases in container
  local MYSQL_TCP_FLAGS="-h127.0.0.1 -P 3306 --protocol=TCP"
  local ATTEMPTS_LOG=""

  # Detect SQL client inside db container (mariadb preferred, fallback to mysql)
  local MYSQL_CLIENT
  MYSQL_CLIENT="$($DC exec -T db sh -lc 'if command -v mariadb >/dev/null 2>&1; then echo mariadb; elif command -v mysql >/dev/null 2>&1; then echo mysql; else echo none; fi' 2>/dev/null || true)"
  # Fetch DB_ROOT_PASSWORD from host (preferred), fallback to fpm exec env
  local DBROOTPW
  DBROOTPW="${DB_ROOT_PASSWORD:-}"
  if [[ -z "$DBROOTPW" ]]; then
    DBROOTPW="$($DC exec -T -e APP_ENV=test fpm sh -lc 'printf %s "${DB_ROOT_PASSWORD:-}"' 2>/dev/null || true)"
  fi
  if [[ "$MYSQL_CLIENT" == "none" || -z "$MYSQL_CLIENT" ]]; then
    ATTEMPTS_LOG+=$'\n'"[client-detect] no mysql/mariadb client found in db container"
    set -e
    return 1
  fi

  # Socket attempts
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT -uroot -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[socket/no-pass] $out_mysql"
  fi
  if [[ -n "$DBROOTPW" ]]; then
    if out_mysql="$($DC exec -T db sh -lc "MYSQL_PWD=\"$DBROOTPW\" $MYSQL_CLIENT -uroot -e \"$SQL\"" 2>&1)"; then
      set -e
      return 0
    else
      ATTEMPTS_LOG+=$'\n'"[socket/DB_ROOT_PASSWORD] $out_mysql"
    fi
  fi
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[socket/MYSQL_ROOT_PASSWORD] $out_mysql"
  fi
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT -uroot -p\"\$MARIADB_ROOT_PASSWORD\" -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[socket/MARIADB_ROOT_PASSWORD] $out_mysql"
  fi

  # TCP attempts
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT $MYSQL_TCP_FLAGS -uroot -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[tcp/no-pass] $out_mysql"
  fi
  if [[ -n "$DBROOTPW" ]]; then
    if out_mysql="$($DC exec -T db sh -lc "MYSQL_PWD=\"$DBROOTPW\" $MYSQL_CLIENT $MYSQL_TCP_FLAGS -uroot -e \"$SQL\"" 2>&1)"; then
      set -e
      return 0
    else
      ATTEMPTS_LOG+=$'\n'"[tcp/DB_ROOT_PASSWORD] $out_mysql"
    fi
  fi
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT $MYSQL_TCP_FLAGS -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[tcp/MYSQL_ROOT_PASSWORD] $out_mysql"
  fi
  if out_mysql="$($DC exec -T db sh -lc "$MYSQL_CLIENT $MYSQL_TCP_FLAGS -uroot -p\"\$MARIADB_ROOT_PASSWORD\" -e \"$SQL\"" 2>&1)"; then
    set -e
    return 0
  else
    ATTEMPTS_LOG+=$'\n'"[tcp/MARIADB_ROOT_PASSWORD] $out_mysql"
  fi

  set -e
  log "Root fallback failed; unable to create/grant on '${FINAL_DB}'."
  if [[ -n "$ATTEMPTS_LOG" ]]; then
    printf "[test.sh] SQL client attempt errors:%s\n" "$ATTEMPTS_LOG"
  fi
  local HOST_HAS_DBROOT FPM_HAS_DBROOT HAS_ROOT_PW
  HOST_HAS_DBROOT="$([ -n "${DB_ROOT_PASSWORD:-}" ] && echo yes || echo no)"
  FPM_HAS_DBROOT="$($DC exec -T -e APP_ENV=test fpm sh -lc 'if [ -n "$DB_ROOT_PASSWORD" ]; then echo yes; else echo no; fi' 2>/dev/null || true)"
  HAS_ROOT_PW="$($DC exec -T db sh -lc 'if [ -n "$MYSQL_ROOT_PASSWORD" ] || [ -n "$MARIADB_ROOT_PASSWORD" ]; then echo yes; else echo no; fi' 2>/dev/null || true)"
  if [[ "$HOST_HAS_DBROOT" == "no" && "$HAS_ROOT_PW" == "no" ]]; then
    log "No DB root password available (host DB_ROOT_PASSWORD missing and MYSQL_ROOT_PASSWORD/MARIADB_ROOT_PASSWORD not set in db). For local-only, export DB_ROOT_PASSWORD in your shell or add compose.override.yaml."
  fi
  return 1
}

migrate_or_update_schema() {
  log "Running database migrations (or schema update) in test env..."
  if $DC exec -T -e APP_ENV=test fpm ./bin/console list doctrine:migrations:migrate >/dev/null 2>&1; then
    $DC exec -T -e APP_ENV=test fpm ./bin/console doctrine:migrations:migrate --no-interaction
  else
    $DC exec -T -e APP_ENV=test fpm ./bin/console doctrine:schema:update --force
  fi
}

load_fixtures() {
  log "Loading Doctrine fixtures (purge with TRUNCATE)..."
  if $DC exec -T -e APP_ENV=test fpm ./bin/console list doctrine:fixtures:load >/dev/null 2>&1; then
    $DC exec -T -e APP_ENV=test fpm ./bin/console doctrine:fixtures:load --no-interaction --purge-with-truncate
  else
    log "DoctrineFixturesBundle not available, skipping fixtures load."
  fi
}









run_phpunit() {
  log "Running PHPUnit..."
  # Pass through any extra args to phpunit
  $DC exec -T -e APP_ENV=test fpm ./vendor/bin/phpunit -c phpunit.dist.xml "$@"
}

main() {
  # Bring up minimal services
  log "Starting docker services (db, fpm)..."
  $DC up -d db fpm

  # Install PHP dependencies
  log "Ensuring composer dependencies (only if vendor/ missing)..."
  $DC exec -T fpm sh -lc 'if [ ! -d vendor ]; then composer install --no-interaction --prefer-dist; else echo "vendor/ exists, skipping composer install"; fi'

  wait_for_db
  prepare_test_db

  # Quick connectivity check from Symfony in test env
  log "Checking DB connectivity from Symfony (APP_ENV=test)..."
  $DC exec -T -e APP_ENV=test fpm ./bin/console dbal:run-sql 'SELECT 1' >/dev/null

  migrate_or_update_schema
  load_fixtures


  run_phpunit "$@"
}

main "$@"
