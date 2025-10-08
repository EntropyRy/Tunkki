################################################################################
# Project Makefile
#
# Developer ergonomics for:
#  - PHPUnit (full / unit / functional subsets)
#  - Mutation testing (Infection) baseline & focused runs
#  - Static analysis (PHPStan) full / fast / delta modes
#  - Metrics & housekeeping helpers
#
# All heavy commands default to running inside the Docker FPM container.
# Override PHP_EXEC to run locally if host PHP version & extensions match.
#
# Examples:
#   make test PHP_EXEC=php
#   make infection FILTER=src/Security
#   make stan-fast PHPSTAN_LEVEL=5
################################################################################

# ---------------- Variables (override via environment / CLI) ------------------

COMPOSE               ?= docker compose
PHP_FPM_SERVICE       ?= fpm
# Enforce APP_ENV=test for all PHP/Composer executions inside the container to ensure
# test config (services_test, doctrine test db) and clock overrides are active.
PHP_EXEC              ?= $(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) php
COMPOSER_EXEC         ?= $(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) composer
# Dev-mode execution (used by update-dev and other dev-only flows)
PHP_EXEC_DEV          ?= $(COMPOSE) exec -T -e APP_ENV=dev $(PHP_FPM_SERVICE) php
COMPOSER_EXEC_DEV     ?= $(COMPOSE) exec -T -e APP_ENV=dev $(PHP_FPM_SERVICE) composer

# Project layout note:
# - Host repository root contains "symfony/" where composer.json & vendor/ live.
# - Inside the FPM container, /var/www/symfony is the working dir (already mapped to that subdirectory).
# Adjust tooling paths to point to symfony/vendor on host to prevent false "vendor not found".
VENDOR_DIR            ?= vendor
PHPUNIT_BIN           ?= $(VENDOR_DIR)/bin/phpunit
PHPUNIT_CONFIG        ?= phpunit.dist.xml
INFECTION_BIN         ?= vendor/bin/infection
PHPSTAN_BIN           ?= vendor/bin/phpstan

FILTER                ?=
INFECTION_THREADS     ?= 4
INFECTION_MIN_MSI     ?= 0
INFECTION_MIN_COVERED ?= 0
# Parallel test runner (ParaTest) defaults
PARATEST_BIN          ?= vendor/bin/paratest
USE_PARALLEL          ?= 1
PARA_PROCS            ?=
EXCLUDE_GROUPS        ?= heavy


PHPSTAN_MEMORY        ?= 1G
PHPSTAN_PATHS_FAST    ?= src
PHPSTAN_LEVEL         ?= 5
PHPSTAN_FLAGS_BASE    ?= -c phpstan.neon --memory-limit=$(PHPSTAN_MEMORY) --no-progress --level=$(PHPSTAN_LEVEL)
GIT_DIFF_BASE         ?= origin/main

METRICS_DIR           ?= metrics

ifndef NO_COLOR
GREEN  := \033[32m
YELLOW := \033[33m
CYAN   := \033[36m
RED    := \033[31m
BOLD   := \033[1m
RESET  := \033[0m
endif

NOW := $(shell date -u +"%Y-%m-%dT%H:%M:%SZ")

# ---------------- Internal Helpers -------------------------------------------

.PHONY: _ensure-vendor
_ensure-vendor:
	# Detect vendor presence either at repo root (vendor/) or in symfony/ subdir (symfony/vendor/)
	@if [ ! -f symfony/$(VENDOR_DIR)/autoload.php ] && [ ! -f $(VENDOR_DIR)/autoload.php ]; then \
		echo "$(YELLOW)Vendor autoload not found (checked: $(VENDOR_DIR)/autoload.php, symfony/$(VENDOR_DIR)/autoload.php) – running composer install inside container.$(RESET)"; \
		$(COMPOSER_EXEC) install --no-interaction --prefer-dist; \
	else \
		: "Vendor already present – skipping composer install"; \
	fi

.PHONY: prepare-test-db
prepare-test-db:
	@echo "$(CYAN)==> Ensuring test database (APP_ENV=test)$(RESET)"
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:create --if-not-exists >/dev/null 2>&1 || true
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console dbal:run-sql 'SELECT 1' >/dev/null 2>&1 || true

# Create per-process test databases for ParaTest (db suffix: _test<TEST_TOKEN>)
# Requires DB root password to be available to the db service (see compose.override.yaml).
.PHONY: prepare-paratest-dbs
prepare-paratest-dbs:
	@if [ "$(USE_PARALLEL)" = "1" ]; then \
		echo "$(CYAN)==> Preparing per-process test databases for ParaTest$(RESET)"; \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		( $(COMPOSE) exec -T db sh -lc '\
		set -e; \
		if command -v mysql >/dev/null 2>&1; then \
		  ROOT_PW="$${MYSQL_ROOT_PASSWORD:-$$MARIADB_ROOT_PASSWORD}"; \
		  if [ -z "$$ROOT_PW" ]; then echo "Root password not set in db service; skipping per-process DB creation"; exit 0; fi; \
		  for i in $$(seq 1 $$PROCS); do \
		    DB="$${MYSQL_DATABASE}_test$$i"; \
		    echo "Ensuring database $$DB via mysql client"; \
		      mysql -uroot -p"$$ROOT_PW" -e "CREATE DATABASE IF NOT EXISTS \`$$DB\`; CREATE USER IF NOT EXISTS '\$${MYSQL_USER}'@'%' IDENTIFIED BY '\$${MYSQL_PASSWORD}'; GRANT ALL PRIVILEGES ON \`$$DB\`.* TO '\$${MYSQL_USER}'@'%'; FLUSH PRIVILEGES;"; \
		  done; \
		elif command -v mariadb >/dev/null 2>&1; then \
		  ROOT_PW="$${MYSQL_ROOT_PASSWORD:-$$MARIADB_ROOT_PASSWORD}"; \
		  if [ -z "$$ROOT_PW" ]; then echo "Root password not set in db service; skipping per-process DB creation"; exit 0; fi; \
		  for i in $$(seq 1 $$PROCS); do \
		    DB="$${MYSQL_DATABASE}_test$$i"; \
		    echo "Ensuring database $$DB via mariadb client"; \
		      mariadb -uroot -p"$$ROOT_PW" -e "CREATE DATABASE IF NOT EXISTS \`$$DB\`; CREATE USER IF NOT EXISTS '\$${MYSQL_USER}'@'%' IDENTIFIED BY '\$${MYSQL_PASSWORD}'; GRANT ALL PRIVILEGES ON \`$$DB\`.* TO '\$${MYSQL_USER}'@'%'; FLUSH PRIVILEGES;"; \
		  done; \
		else \
		  exit 99; \
		        fi' ) || ( \
		          echo "$(YELLOW)db container lacks mysql/mariadb client or root password; skipping per-process DB creation. Using shared _test database (disable test tokens).$(RESET)"; \
		        ); \
	else \
		echo "$(CYAN)==> Skipping per-process DB prep (USE_PARALLEL!=1)$(RESET)"; \
	fi


# ---------------- Help -------------------------------------------------------

.PHONY: help
help:
	@echo ""
	@echo "$(BOLD)Available targets$(RESET)"
	@echo "  make test                 - Run full test suite"
	@echo "  make test-unit            - Run only Unit tests (tests/Unit)"
	@echo "  make test-functional      - Run only Functional tests (tests/Functional)"
	@echo "  make test-ci              - CI-style full suite (fail-fast, shows deprecations/warnings, no coverage)"
	@echo "  make coverage             - Run suite with coverage (needs Xdebug/PCOV)"
	@echo "  make test-profile         - Run tests one-by-one and record timings -> metrics/test-times.txt"

	@echo "  make test-one FILE=path   - Run a single test file (serial)"
	@echo "  make test-one-filter FILE=path METHOD=name - Run a single test method"

	@echo "  make infection-baseline   - Infection run (manual metrics append)"
	@echo "  make stan                 - Full PHPStan (level=$(PHPSTAN_LEVEL))"
	@echo "  make stan-fast            - PHPStan on $(PHPSTAN_PATHS_FAST)/"
	@echo "  make stan-delta           - PHPStan on changed src/ files vs $(GIT_DIFF_BASE)"
	@echo "  make stan-json            - PHPStan JSON -> $(METRICS_DIR)/phpstan-report.json"
	@echo "  make metrics-snapshot     - Create timestamped metrics stub"
	@echo "  make lint-datetime        - Enforce clock policy (forbidden new DateTime in disallowed layers)"
	@echo "  make doctor               - Environment / tool diagnostics"
	@echo "  make clean                - Clear caches (coverage, Infection, PHPStan)"
	@echo "  make update-dev           - Update dev env (pull/build/up, composer update, importmap, stan, style, rector)"
	@echo ""
	@echo "$(BOLD)Variables (override)$(RESET)"
	@echo "  PHP_EXEC=php | FILTER=src/Security | PHPSTAN_LEVEL=5 | GIT_DIFF_BASE=main"
	@echo "  INFECTION_THREADS=8 | NO_COLOR=1 | USE_PARALLEL=1 | PARA_PROCS=8 | EXCLUDE_GROUPS=heavy"
	@echo ""

# ---------------- Testing ----------------------------------------------------





.PHONY: test
test: _ensure-vendor prepare-test-db prepare-paratest-dbs
	@echo "$(CYAN)==> Running full test suite$(RESET)"

	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --exclude-group $(EXCLUDE_GROUPS); \
	else \
		$(PHP_EXEC) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --exclude-group $(EXCLUDE_GROUPS); \
	fi

.PHONY: test-unit
test-unit: _ensure-vendor prepare-test-db prepare-paratest-dbs
	@echo "$(CYAN)==> Running unit tests$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --testsuite=Unit --exclude-group $(EXCLUDE_GROUPS); \
	else \
		$(PHP_EXEC) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --exclude-group $(EXCLUDE_GROUPS) --testsuite=Unit; \
	fi

.PHONY: test-functional
test-functional: _ensure-vendor prepare-test-db prepare-paratest-dbs
	@echo "$(CYAN)==> Running functional tests$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --testsuite=Functional --exclude-group $(EXCLUDE_GROUPS); \
	else \
		$(PHP_EXEC) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --exclude-group $(EXCLUDE_GROUPS) --testsuite=Functional; \
	fi

.PHONY: test-ci
test-ci: _ensure-vendor prepare-test-db prepare-paratest-dbs
	@echo "$(CYAN)==> Running CI test suite (fail-fast, no coverage)$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --exclude-group $(EXCLUDE_GROUPS) -- --fail-on-warning --display-deprecations --display-errors=stderr; \
	else \
		$(PHP_EXEC) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --exclude-group $(EXCLUDE_GROUPS) --fail-on-warning --display-deprecations --display-errors=stderr; \
	fi

.PHONY: coverage
coverage: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Running tests with coverage (ensure Xdebug/PCOV enabled)$(RESET)"
	@$(PHP_EXEC) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --coverage-text --coverage-clover coverage.xml

# ---------------- Mutation Testing (Infection) --------------------------------

.PHONY: infection
infection: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Infection run (filter='$(FILTER)')$(RESET)"
	@cmd="$(PHP_EXEC) $(INFECTION_BIN) --threads=$(INFECTION_THREADS) --min-msi=$(INFECTION_MIN_MSI) --min-covered-msi=$(INFECTION_MIN_COVERED)"; \
	if [ -n "$(FILTER)" ]; then cmd="$$cmd --filter=$(FILTER)"; fi; \
	echo "$(YELLOW)$$cmd$(RESET)"; \
	$$cmd

.PHONY: infection-baseline
infection-baseline: infection
	@echo "$(CYAN)==> (Manual) Append results to metrics/mutation-baseline.md$(RESET)"

# Debug helper: prints the exact Infection command and a hex dump of characters
# Useful for diagnosing Unicode dash issues (e.g. en-dash vs ASCII hyphen)
.PHONY: infection-debug
infection-debug: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Infection DEBUG (show raw command & hex)$(RESET)"
	@cmd="$(PHP_EXEC) $(INFECTION_BIN) --threads=$(INFECTION_THREADS) --min-msi=$(INFECTION_MIN_MSI) --min-covered-msi=$(INFECTION_MIN_COVERED)"; \
	if [ -n "$(FILTER)" ]; then cmd="$$cmd --filter=$(FILTER)"; fi; \
	echo "RAW CMD: $(YELLOW)$$cmd$(RESET)"; \
	printf '%s\n' "$$cmd" | od -An -tx1 | sed 's/^/HEX: /'; \
	echo "$(CYAN)==> Executing Infection (debug)$(RESET)"; \
	$$cmd

# ---------------- Parallel Test Debug ----------------------------------------
.PHONY: test-parallel-debug
test-parallel-debug: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> ParaTest parallel debug$(RESET)"
	@echo "$(BOLD)ParaTest binary$(RESET)"
	@$(PHP_EXEC) $(PARATEST_BIN) --version || echo "$(YELLOW)ParaTest not found (falling back to phpunit).$(RESET)"
	@echo ""
	@echo "$(BOLD)Parallel config$(RESET)"
	@echo -n "USE_PARALLEL: "; echo "$(USE_PARALLEL)"
	@echo -n "PARA_PROCS: "; echo "$(PARA_PROCS)"
	@echo "$(BOLD)CPU detection (inside container)$(RESET)"
	@echo -n "nproc: "; $(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) sh -lc 'nproc 2>/dev/null || echo "n/a"'
	@echo -n "getconf _NPROCESSORS_ONLN: "; $(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) sh -lc 'getconf _NPROCESSORS_ONLN 2>/dev/null || echo "n/a"'
	@PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
	 echo "Computed processes: $$PROCS (USE_PARALLEL=$(USE_PARALLEL), PARA_PROCS=$(PARA_PROCS))"
	@echo ""
	@echo "$(BOLD)Sample command$(RESET)"
	@PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
	 echo "$(YELLOW)$(PHP_EXEC) $(PARATEST_BIN) -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --testsuite=Functional --exclude-group $(EXCLUDE_GROUPS)$(RESET)"
	@echo ""
# ---------------- Test Profiling (Single Test Runner) ------------------------
.PHONY: test-profile
test-profile: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Profiling tests one-by-one (serial)$(RESET)"
	@mkdir -p $(METRICS_DIR)
	@OUT="$(METRICS_DIR)/test-times.txt"; \
	echo "# Test timings (ms) — $$(date -u +\"%Y-%m-%dT%H:%M:%SZ\")" > $$OUT; \
	$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) sh -lc '\
	set -e; \
	for f in $$(find tests -type f -name "*Test.php" | sort); do \
		start=$$(date +%s%3N 2>/dev/null || date +%s000); \
		php vendor/bin/phpunit -c phpunit.dist.xml "$$f" >/dev/null 2>&1 || true; \
		end=$$(date +%s%3N 2>/dev/null || date +%s000); \
		dur=$$((end-start)); \
		printf "%8d ms  %s\n" "$$dur" "$$f" >> $(METRICS_DIR)/test-times.txt; \
	done; \
	echo ""; echo "Top 30 slowest tests:"; \
	sort -nr $(METRICS_DIR)/test-times.txt | head -n 30 \
	'

.PHONY: test-one
test-one: _ensure-vendor prepare-test-db
	@if [ -z "$(FILE)" ]; then echo "$(RED)Usage: make test-one FILE=tests/Path/ToTest.php$(RESET)"; exit 2; fi; \
	PCOV_ENABLED=0 XDEBUG_MODE=off $(PHP_EXEC) -d memory_limit=1024M $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage $(if $(TEAMCITY_VERSION),--teamcity,$(if $(filter 1,$(USE_COMPACT_PRINTER)),--testdox,)) "$(FILE)"

.PHONY: test-one-filter
test-one-filter: _ensure-vendor prepare-test-db
	@if [ -z "$(FILE)" ] || [ -z "$(METHOD)" ]; then echo "$(RED)Usage: make test-one-filter FILE=tests/Path/ToTest.php METHOD=testMethodName$(RESET)"; exit 2; fi; \
	PCOV_ENABLED=0 XDEBUG_MODE=off $(PHP_EXEC) -d memory_limit=1024M $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage $(if $(TEAMCITY_VERSION),--teamcity,$(if $(filter 1,$(USE_COMPACT_PRINTER)),--testdox,)) --filter "$(METHOD)" "$(FILE)"

.PHONY: test-slowest
test-slowest:
	@echo "$(CYAN)==> Top 30 slowest tests from $(METRICS_DIR)/test-times.txt$(RESET)"
	@sort -nr $(METRICS_DIR)/test-times.txt | head -n 30 || echo "$(YELLOW)No timings file found. Run: make test-profile$(RESET)"

# ---------------- Static Analysis (PHPStan) ----------------------------------

.PHONY: stan
stan: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> PHPStan (full) level=$(PHPSTAN_LEVEL)$(RESET)"
	@$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_FLAGS_BASE)

.PHONY: stan-fast
stan-fast: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> PHPStan (fast) paths=$(PHPSTAN_PATHS_FAST) level=$(PHPSTAN_LEVEL)$(RESET)"
	@$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_PATHS_FAST) $(PHPSTAN_FLAGS_BASE)

.PHONY: stan-delta
stan-delta: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> PHPStan (delta) base=$(GIT_DIFF_BASE) level=$(PHPSTAN_LEVEL)$(RESET)"
	@files=$$(git diff --name-only $(GIT_DIFF_BASE) -- 'src' | grep '\.php$$' || true); \
	if [ -z "$$files" ]; then \
		echo "$(YELLOW)No changed PHP files under src/ relative to $(GIT_DIFF_BASE).$(RESET)"; \
	else \
		echo "$(CYAN)Analyzing changed files:$(RESET) $$files"; \
		$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_FLAGS_BASE) $$files; \
	fi

.PHONY: stan-json
stan-json: _ensure-vendor prepare-test-db
	@mkdir -p $(METRICS_DIR)
	@echo "$(CYAN)==> PHPStan JSON report -> $(METRICS_DIR)/phpstan-report.json$(RESET)"
	@$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_FLAGS_BASE) --error-format=json > $(METRICS_DIR)/phpstan-report.json || true
	@echo "$(YELLOW)Non-zero exit tolerated for JSON export. Review the file.$(RESET)"

.PHONY: lint-datetime
lint-datetime: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Lint (clock policy) scanning for forbidden new DateTime instantiations$(RESET)"
	@bash ci/check_datetime.sh

.PHONY: update-dev
update-dev:
	@echo "$(CYAN)==> Updating local dev environment (pull, build, up, deps, code style, rector)$(RESET)"
	@$(COMPOSE) pull
	@$(COMPOSE) build --pull
	@$(COMPOSE) up -d
	@$(COMPOSER_EXEC_DEV) update
	@$(PHP_EXEC_DEV) ./bin/console cache:clear --env=dev
	@$(PHP_EXEC_DEV) ./bin/console cache:warmup --env=dev
	@$(PHP_EXEC_DEV) ./bin/console importmap:update
	@$(PHP_EXEC_DEV) $(PHPSTAN_BIN) analyse -c phpstan.dev.neon src --memory-limit=$(PHPSTAN_MEMORY) --no-progress --level=$(PHPSTAN_LEVEL) || true
	@$(PHP_EXEC_DEV) vendor/bin/twig-cs-fixer fix --fix templates/
	@$(PHP_EXEC_DEV) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --using-cache=no
	@$(PHP_EXEC_DEV) vendor/bin/rector process src

# ---------------- Symfony Scripts (Dev Helpers) -------------------------------
.PHONY: scripts-debug-baseline scripts-route-debug scripts-seed-baseline

scripts-debug-baseline: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Sonata CMS baseline debug (APP_ENV=test)$(RESET)"
	@$(PHP_EXEC) scripts/debug_baseline.php

scripts-route-debug: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> ChainRouter route debug for '/' and '/en/' (APP_ENV=test)$(RESET)"
	@$(PHP_EXEC) scripts/route_debug.php

scripts-seed-baseline: _ensure-vendor prepare-test-db
	@echo "$(CYAN)==> Seeding/normalizing CMS baseline once (APP_ENV=test)$(RESET)"
	@$(PHP_EXEC) scripts/seed_baseline_once.php

# ---------------- Metrics / Scaffolding --------------------------------------

.PHONY: metrics-snapshot
metrics-snapshot:
	@mkdir -p $(METRICS_DIR)
	@f="$(METRICS_DIR)/$(NOW)-snapshot.md"; \
	if [ -f "$$f" ]; then echo "$(RED)Refusing to overwrite existing $$f$(RESET)"; exit 1; fi; \
	echo "# Metrics Snapshot ($(NOW))\n\n(Describe changes, runtime, coverage deltas, structural shifts.)\n" > "$$f"; \
	echo "$(GREEN)Created $$f$(RESET)"

# ---------------- Environment Diagnostics ------------------------------------

.PHONY: doctor
doctor:
	@echo "$(CYAN)==> Environment diagnostics$(RESET)"
	@echo "$(BOLD)Docker Compose Services$(RESET)"
	@$(COMPOSE) ps --status=running || true
	@echo ""
	@echo "$(BOLD)PHP Version (container)$(RESET)"
	@$(PHP_EXEC) -v | head -n1 || true
	@echo ""
	@echo "$(BOLD)Composer Dependencies (excerpt)$(RESET)"
	@$(COMPOSER_EXEC) show --no-interaction | head -n20 || true
	@echo ""
	@echo "$(BOLD)Key Tool Presence$(RESET)"
	@for bin in $(PHPUNIT_BIN) $(INFECTION_BIN) $(PHPSTAN_BIN); do \
		if $(PHP_EXEC) $$bin --version >/dev/null 2>&1; then \
			echo "  [OK] $$bin"; \
		else \
			echo "  [MISSING] $$bin"; \
		fi; \
	done

# ---------------- Clean / Utility --------------------------------------------

.PHONY: clean-coverage
clean-coverage:
	@echo "$(CYAN)==> Removing coverage artifacts$(RESET)"
	@rm -f coverage.xml
	@find . -type d -name coverage -prune -exec rm -rf {} \; 2>/dev/null || true

.PHONY: clean-mutation-cache
clean-mutation-cache:
	@echo "$(CYAN)==> Removing Infection cache$(RESET)"
	@rm -rf .infection-cache

.PHONY: clean-phpstan-cache
clean-phpstan-cache:
	@echo "$(CYAN)==> Removing PHPStan cache$(RESET)"
	@rm -rf var/phpstan

.PHONY: clean
clean: clean-coverage clean-mutation-cache clean-phpstan-cache
	@echo "$(GREEN)All caches cleared$(RESET)"

.PHONY: clean-test-db
clean-test-db:
	@echo "$(CYAN)==> Resetting test database (drop/create/schema:update)$(RESET)"
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:drop --force || true
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:create
	@$(COMPOSE) exec -T $(PHP_FPM_SERVICE) ./bin/console doctrine:schema:update --force



# ---------------- Default -----------------------------------------------------

.DEFAULT_GOAL := help
