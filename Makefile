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
INFECTION_THREADS     ?= $(shell nproc 2>/dev/null || echo 8)
INFECTION_MIN_MSI     ?= 0
INFECTION_MIN_COVERED ?= 0
# Parallel test runner (ParaTest) defaults
PARATEST_BIN          ?= vendor/bin/paratest
USE_PARALLEL          ?= 1
PARA_PROCS            ?=

PHPSTAN_MEMORY        ?= 1G
PHPSTAN_PATHS_FAST    ?= src
PHPSTAN_LEVEL         ?= 5
PHPUNIT_MEMORY        ?= 1024M
PHPUNIT_ARGS          ?=
PHPSTAN_FLAGS_BASE    ?= -c phpstan.neon --memory-limit=$(PHPSTAN_MEMORY) --no-progress --level=$(PHPSTAN_LEVEL)
GIT_DIFF_BASE         ?= origin/main



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
	@printf "%b\n" "$(CYAN)==> Ensuring test database (APP_ENV=test)$(RESET)"
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:create --if-not-exists >/dev/null 2>&1 || true
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console dbal:run-sql 'SELECT 1' >/dev/null 2>&1 || true

.PHONY: panther-setup
panther-setup:
	@printf "%b\n" "$(CYAN)==> Ensuring Panther WebDriver binaries (bdi detect)$(RESET)"
	@$(PHP_EXEC) vendor/bin/bdi detect drivers >/dev/null

# ---------------- Help -------------------------------------------------------

.PHONY: help
help:
	@echo ""
	@echo "$(BOLD)Available targets$(RESET)"
	@echo "  make test                 - Run full test suite with coverage + shields JSON"
	@echo "  make test-unit            - Run only Unit tests (tests/Unit)"
	@echo "  make test-functional      - Run only Functional tests (tests/Functional)"
	@echo "  make test-panther         - Run only Panther browser tests (serial, with cleanup)"
	@echo "  make test-ci              - CI-style full suite (fail-fast, shows deprecations/warnings, no coverage)"
	@echo "  make coverage             - Run suite with coverage (needs Xdebug/PCOV)"
	@echo "  make panther-setup        - Install/update Panther WebDriver binaries"
	@echo "  make clean-panther        - Clean Panther cache/log/temp files"

	@echo "  make test-one FILE=path   - Run a single test file (serial)"
	@echo "  make test-one-filter FILE=path METHOD=name - Run a single test method"

	@echo "  make infection-baseline   - Infection run (manual metrics append)"
	@echo "  make stan                 - Full PHPStan (level=$(PHPSTAN_LEVEL))"
	@echo "  make stan-fast            - PHPStan on $(PHPSTAN_PATHS_FAST)/"
	@echo "  make stan-delta           - PHPStan on changed src/ files vs $(GIT_DIFF_BASE)"


	@echo "  make lint-datetime        - Enforce clock policy (forbidden new DateTime in disallowed layers)"
	@echo "  make doctor               - Environment / tool diagnostics"
	@echo "  make clean                - Clear caches (coverage, Infection, PHPStan)"
	@echo "  make clean-test-db        - Reset test database (drop/create/schema:update)"
	@echo "  make grant-test-db-privileges - Grant test user privileges on test_* databases"
	@echo "  make update-dev           - Update dev env (pull/build/up, composer update, importmap, stan, style, rector)"
	@echo ""
	@echo "$(BOLD)Variables (override)$(RESET)"
	@echo "  PHP_EXEC=php | FILTER=src/Security | PHPSTAN_LEVEL=5 | GIT_DIFF_BASE=main"
	@echo "  INFECTION_THREADS=8 | NO_COLOR=0 | USE_PARALLEL=1 | PARA_PROCS=8"
	@echo ""

# ---------------- Testing ----------------------------------------------------





.PHONY: test
test: _ensure-vendor prepare-test-db panther-setup
	@printf "%b\n" "$(CYAN)==> Running full test suite$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --coverage-text --coverage-clover coverage.xml --no-test-tokens; \
	else \
		$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --coverage-text --coverage-clover coverage.xml; \
	fi
	@printf "%b\n" "$(CYAN)==> Generating shields.io coverage JSON (symfony/coverage.json)$(RESET)"
	@$(PHP_EXEC) coverage_to_shields.php --in=coverage.xml --out=coverage.json || { printf "%b\n" "$(RED)Failed to generate coverage.json$(RESET)"; exit 1; }
	@printf "%b\n" "$(GREEN)Wrote coverage.json$(RESET)"

.PHONY: test-unit
test-unit: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Running unit tests$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --testsuite=Unit; \
	else \
		$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage --testsuite=Unit; \
	fi

.PHONY: test-functional
test-functional: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Running functional tests$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --testsuite=Functional; \
	else \
		$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage --testsuite=Functional; \
	fi

.PHONY: clean-panther
clean-panther:
	@printf "%b\n" "$(CYAN)==> Cleaning Panther test artifacts$(RESET)"
	@rm -rf symfony/var/cache/panther_* 2>/dev/null || true
	@rm -rf symfony/var/log/panther_* 2>/dev/null || true
	@rm -f /tmp/test_panther_*.db /tmp/test_panther_*.db-shm /tmp/test_panther_*.db-wal /tmp/test_panther_*.db-journal 2>/dev/null || true
	@$(COMPOSE) exec -T $(PHP_FPM_SERVICE) sh -c 'rm -rf /var/www/symfony/var/cache/panther_* 2>/dev/null || true'
	@$(COMPOSE) exec -T $(PHP_FPM_SERVICE) sh -c 'rm -rf /var/www/symfony/var/log/panther_* 2>/dev/null || true'
	@$(COMPOSE) exec -T $(PHP_FPM_SERVICE) sh -c 'rm -f /tmp/test_panther_*.db /tmp/test_panther_*.db-* 2>/dev/null || true'

.PHONY: test-panther
test-panther: _ensure-vendor prepare-test-db clean-panther panther-setup
	@printf "%b\n" "$(CYAN)==> Running Panther (browser) tests (serial, no ParaTest)$(RESET)"
	@$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage --testsuite=Panther

.PHONY: test-ci
test-ci: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Running CI test suite (fail-fast, no coverage)$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --no-coverage --no-test-tokens --fail-on-warning --display-deprecations --display-errors; \
	else \
		$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --fail-on-warning --display-deprecations --display-errors $(PHPUNIT_ARGS); \
	fi

.PHONY: coverage
coverage: _ensure-vendor prepare-test-db panther-setup
	@printf "%b\n" "$(CYAN)==> Running tests with coverage (ensure PCOV enabled)$(RESET)"
	@PARA_BIN="$(PARATEST_BIN)"; \
	if [ "$(USE_PARALLEL)" = "1" ] && $(PHP_EXEC) $$PARA_BIN --version >/dev/null 2>&1; then \
		PROCS=$$( if [ -n "$(PARA_PROCS)" ]; then echo "$(PARA_PROCS)"; else $(PHP_EXEC) -r 'echo (int) ((($$n=shell_exec("nproc 2>/dev/null"))? $$n : shell_exec("getconf _NPROCESSORS_ONLN 2>/dev/null")) ?: 1);'; fi ); \
		$(PHP_EXEC) $$PARA_BIN -c $(PHPUNIT_CONFIG) -p $$PROCS --coverage-text --coverage-clover coverage.xml --no-test-tokens; \
	else \
		$(PHP_EXEC) -d memory_limit=$(PHPUNIT_MEMORY) $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --coverage-text --coverage-clover coverage.xml; \
	fi
	@printf "%b\n" "$(CYAN)==> Generating shields.io coverage JSON (symfony/coverage.json)$(RESET)"
	@$(PHP_EXEC) coverage_to_shields.php --in=coverage.xml --out=coverage.json || { printf "%b\n" "$(RED)Failed to generate coverage.json$(RESET)"; exit 1; }
	@printf "%b\n" "$(GREEN)Wrote coverage.json$(RESET)"

# ---------------- Mutation Testing (Infection) --------------------------------

.PHONY: infection
infection: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Infection run (filter='$(FILTER)')$(RESET)"
	@cmd="$(PHP_EXEC) $(INFECTION_BIN) --threads=$(INFECTION_THREADS) --min-msi=$(INFECTION_MIN_MSI) --min-covered-msi=$(INFECTION_MIN_COVERED)"; \
	if [ -n "$(FILTER)" ]; then cmd="$$cmd --filter=$(FILTER)"; fi; \
	printf "%b\n" "$(YELLOW)$$cmd$(RESET)"; \
	$$cmd



# Debug helper: prints the exact Infection command and a hex dump of characters
# Useful for diagnosing Unicode dash issues (e.g. en-dash vs ASCII hyphen)
.PHONY: infection-debug
infection-debug: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Infection DEBUG (show raw command & hex)$(RESET)"
	@cmd="$(PHP_EXEC) $(INFECTION_BIN) --threads=$(INFECTION_THREADS) --min-msi=$(INFECTION_MIN_MSI) --min-covered-msi=$(INFECTION_MIN_COVERED)"; \
	if [ -n "$(FILTER)" ]; then cmd="$$cmd --filter=$(FILTER)"; fi; \
	printf "%b\n" "RAW CMD: $(YELLOW)$$cmd$(RESET)"; \
	printf '%s\n' "$$cmd" | od -An -tx1 | sed 's/^/HEX: /'; \
	printf "%b\n" "$(CYAN)==> Executing Infection (debug)$(RESET)"; \
	$$cmd

# ---------------- Test Profiling (Single Test Runner) ------------------------
.PHONY: test-one
test-one: _ensure-vendor prepare-test-db
	@if [ -z "$(FILE)" ]; then echo "$(RED)Usage: make test-one FILE=tests/Path/ToTest.php$(RESET)"; exit 2; fi; \
	PCOV_ENABLED=0 XDEBUG_MODE=off $(PHP_EXEC) -d memory_limit=1024M $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage $(if $(TEAMCITY_VERSION),--teamcity,$(if $(filter 1,$(USE_COMPACT_PRINTER)),--testdox,)) "$(FILE)"

.PHONY: test-one-filter
test-one-filter: _ensure-vendor prepare-test-db
	@if [ -z "$(FILE)" ] || [ -z "$(METHOD)" ]; then echo "$(RED)Usage: make test-one-filter FILE=tests/Path/ToTest.php METHOD=testMethodName$(RESET)"; exit 2; fi; \
	PCOV_ENABLED=0 XDEBUG_MODE=off $(PHP_EXEC) -d memory_limit=1024M $(PHPUNIT_BIN) -c $(PHPUNIT_CONFIG) --no-coverage $(if $(TEAMCITY_VERSION),--teamcity,$(if $(filter 1,$(USE_COMPACT_PRINTER)),--testdox,)) --filter "$(METHOD)" "$(FILE)"

# ---------------- Static Analysis (PHPStan) ----------------------------------

.PHONY: stan
stan: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> PHPStan (full) level=$(PHPSTAN_LEVEL)$(RESET)"
	@$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_FLAGS_BASE)

.PHONY: stan-fast
stan-fast: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> PHPStan (fast) paths=$(PHPSTAN_PATHS_FAST) level=$(PHPSTAN_LEVEL)$(RESET)"
	@$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_PATHS_FAST) $(PHPSTAN_FLAGS_BASE)

.PHONY: stan-delta
stan-delta: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> PHPStan (delta) base=$(GIT_DIFF_BASE) level=$(PHPSTAN_LEVEL)$(RESET)"
	@files=$$(git diff --name-only $(GIT_DIFF_BASE) -- 'src' | grep '\.php$$' || true); \
	if [ -z "$$files" ]; then \
		printf "%b\n" "$(YELLOW)No changed PHP files under src/ relative to $(GIT_DIFF_BASE).$(RESET)"; \
	else \
		printf "%b\n" "$(CYAN)Analyzing changed files:$(RESET) $$files"; \
		$(PHP_EXEC) $(PHPSTAN_BIN) analyse $(PHPSTAN_FLAGS_BASE) $$files; \
	fi



.PHONY: lint-datetime
lint-datetime: _ensure-vendor prepare-test-db
	@printf "%b\n" "$(CYAN)==> Lint (clock policy) scanning for forbidden new DateTime instantiations$(RESET)"
	@bash ci/check_datetime.sh

.PHONY: update-dev
update-dev:
	@printf "%b\n" "$(CYAN)==> Updating local dev environment (pull, build, up, deps, code style, rector)$(RESET)"
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





# ---------------- Environment Diagnostics ------------------------------------

.PHONY: doctor
doctor:
	@printf "%b\n" "$(CYAN)==> Environment diagnostics$(RESET)"
	@printf "%b\n" "$(BOLD)Docker Compose Services$(RESET)"
	@$(COMPOSE) ps --status=running || true
	@echo ""
	@printf "%b\n" "$(BOLD)PHP Version (container)$(RESET)"
	@$(PHP_EXEC) -v | head -n1 || true
	@echo ""
	@printf "%b\n" "$(BOLD)Composer Dependencies (excerpt)$(RESET)"
	@$(COMPOSER_EXEC) show --no-interaction | head -n20 || true
	@echo ""
	@printf "%b\n" "$(BOLD)Key Tool Presence$(RESET)"
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
	@printf "%b\n" "$(CYAN)==> Removing coverage artifacts$(RESET)"
	@rm -f coverage.xml
	@find . -type d -name coverage -prune -exec rm -rf {} \; 2>/dev/null || true

.PHONY: clean-mutation-cache
clean-mutation-cache:
	@printf "%b\n" "$(CYAN)==> Removing Infection cache$(RESET)"
	@rm -rf .infection-cache

.PHONY: clean-phpstan-cache
clean-phpstan-cache:
	@printf "%b\n" "$(CYAN)==> Removing PHPStan cache$(RESET)"
	@rm -rf var/phpstan

.PHONY: clean
clean: clean-coverage clean-mutation-cache clean-phpstan-cache
	@printf "%b\n" "$(GREEN)All caches cleared$(RESET)"

.PHONY: clean-test-db
clean-test-db:
	@printf "%b\n" "$(CYAN)==> Resetting test database (drop/create/schema:update)$(RESET)"
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:drop --force || true
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:database:create
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console doctrine:schema:update --force
	@printf "%b\n" "$(CYAN)==> Seeding CMS baseline for tests$(RESET)"
	@$(COMPOSE) exec -T -e APP_ENV=test $(PHP_FPM_SERVICE) ./bin/console entropy:cms:seed

.PHONY: grant-test-db-privileges
grant-test-db-privileges:
	@printf "%b\n" "$(CYAN)==> Granting test user privileges on test_* databases (for parallel test execution)$(RESET)"
	@if [ ! -f .env ]; then \
		echo "$(RED)ERROR: .env file not found in project root$(RESET)"; \
		exit 1; \
	fi
	@DB_ROOT_PW=$$(grep '^DB_ROOT_PASSWORD=' .env | cut -d= -f2 | tr -d '"'); \
	if [ -z "$$DB_ROOT_PW" ]; then \
		echo "$(RED)ERROR: DB_ROOT_PASSWORD not found in .env$(RESET)"; \
		exit 1; \
	fi; \
	$(COMPOSE) exec -T db mariadb -uroot -p"$$DB_ROOT_PW" \
		-e "GRANT ALL PRIVILEGES ON \`test_%\`.* TO 'test'@'%'; FLUSH PRIVILEGES;" 2>&1 | grep -v "mariadb: \[Warning\]" || true
	@printf "%b\n" "$(GREEN)Test user now has privileges on test_* databases$(RESET)"



# ---------------- Default -----------------------------------------------------

.DEFAULT_GOAL := help
