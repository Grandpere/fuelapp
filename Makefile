SHELL := /bin/sh

DOCKER_ENV_FILE := resources/docker/.env
DOCKER_ENV_FLAGS := --env-file $(DOCKER_ENV_FILE)
ifneq ("$(wildcard resources/docker/.env.local)","")
DOCKER_ENV_FLAGS := --env-file $(DOCKER_ENV_FILE) --env-file resources/docker/.env.local
endif

DC := docker compose -f resources/docker/compose.yml $(DOCKER_ENV_FLAGS)
DC_EXEC := $(DC) exec -T app
DC_OBS := $(DC) --profile observability

APP_SERVICES := app database redis rabbitmq mercure
OBS_SERVICES := clickhouse zookeeper-1 signoz-telemetrystore-migrator signoz otel-collector

.PHONY: help
help: ## Show help
	@echo "For global application, use Makefile at the root project."
	@echo "Please use 'make <target>' where <target> is one of"
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | \
		sed -e 's/\[32m##/[33m/'

##
## Container
##---------------------------------------------------------------------------

.PHONY: up
up: app-up ## Alias: start app stack

.PHONY: down
down: app-down ## Alias: stop app stack

.PHONY: app-up
app-up: ## Start app stack (without observability)
	$(DC) up -d $(APP_SERVICES)

.PHONY: app-down
app-down: ## Stop app stack (without observability)
	$(DC) stop $(APP_SERVICES)

.PHONY: observability-up
observability-up: ## Start observability stack (SigNoz + ClickHouse)
	$(DC_OBS) up -d $(OBS_SERVICES)

.PHONY: observability-down
observability-down: ## Stop observability stack
	$(DC_OBS) stop $(OBS_SERVICES)

.PHONY: full-up
full-up: ## Start full stack (app + observability)
	$(DC_OBS) up -d

.PHONY: full-down
full-down: ## Stop full stack
	$(DC_OBS) down --remove-orphans

.PHONY: up-rebuild
up-rebuild: ## Rebuild and start app stack
	$(DC) down --remove-orphans
	$(DC) up -d --build --force-recreate $(APP_SERVICES)

.PHONY: build
build: ## Build images
	$(DC) build

.PHONY: rebuild
rebuild: ## Rebuild images (no cache)
	$(DC) build --no-cache

.PHONY: logs
logs: ## Follow logs
	$(DC) logs -f

.PHONY: ps
ps: ## List containers
	$(DC) ps

.PHONY: restart-app
restart-app: ## Restart app container only
	$(DC) restart app
	$(MAKE) wait-app

.PHONY: wait-app
wait-app: ## Wait until app HTTP endpoint responds
	@echo "Waiting for app readiness (in-container /ui/login check) ..."; \
	for i in $$(seq 1 45); do \
		if $(DC_EXEC) php -r 'exit(@file_get_contents("http://127.0.0.1/ui/login") === false ? 1 : 0);' >/dev/null 2>&1; then \
			echo "App is ready."; \
			exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "App did not become ready within 45s."; \
	exit 1


.PHONY: observability-logs
observability-logs: ## Follow observability logs
	$(DC_OBS) logs -f signoz otel-collector clickhouse

.PHONY: observability-health
observability-health: ## Quick health check (services + trace/log ingestion counters)
	$(DC_OBS) ps signoz otel-collector clickhouse
	$(DC_OBS) exec -T clickhouse clickhouse-client -q "SELECT count() AS traces_last_15m FROM signoz_traces.signoz_index_v3 WHERE timestamp >= now() - INTERVAL 15 MINUTE"
	$(DC_OBS) exec -T clickhouse clickhouse-client -q "SELECT count() AS logs_last_15m FROM signoz_logs.logs_v2 WHERE toDateTime(timestamp/1000000000) >= now() - INTERVAL 15 MINUTE"

.PHONY: shell
shell: ## Open a shell in app container
	$(DC_EXEC) sh

##
## Composer
##---------------------------------------------------------------------------

.PHONY: composer-install
composer-install: ## Install composer dependencies
	$(DC_EXEC) composer install --no-progress

.PHONY: composer-autoload
composer-autoload: ## Rebuild composer autoload files
	$(DC_EXEC) composer dump-autoload

##
## Symfony
##---------------------------------------------------------------------------

.PHONY: cache-clear
cache-clear: ## Clear Symfony cache (dev)
	$(DC_EXEC) php bin/console cache:clear

.PHONY: user-create
user-create: ## Create local user (EMAIL=... PASSWORD=... [ADMIN=1])
	$(DC_EXEC) php bin/console app:user:create "$(EMAIL)" "$(PASSWORD)" $(if $(ADMIN),--admin)

.PHONY: receipts-claim-unowned
receipts-claim-unowned: ## Assign unowned receipts to a user (EMAIL=...)
	$(DC_EXEC) php bin/console app:receipts:claim-unowned "$(EMAIL)"

.PHONY: messenger-consume-async
messenger-consume-async: ## Consume async messenger queue (VERBOSITY=-vv optional)
	$(DC_EXEC) php bin/console messenger:consume async $(VERBOSITY)

.PHONY: maintenance-reminders-dispatch
maintenance-reminders-dispatch: ## Dispatch maintenance reminder evaluation message
	$(DC_EXEC) php bin/console app:maintenance:reminders:dispatch

.PHONY: analytics-receipts-refresh
analytics-receipts-refresh: ## Refresh receipt analytics projection synchronously
	$(DC_EXEC) php bin/console app:analytics:receipts:refresh

.PHONY: analytics-receipts-dispatch
analytics-receipts-dispatch: ## Dispatch receipt analytics refresh message
	$(DC_EXEC) php bin/console app:analytics:receipts:refresh --async

.PHONY: analytics-demo-seed
analytics-demo-seed: ## Seed demo analytics account data (EMAIL=... PASSWORD=... optional)
	$(DC_EXEC) php bin/console app:analytics:demo-seed $(if $(EMAIL),--email "$(EMAIL)") $(if $(PASSWORD),--password "$(PASSWORD)")

.PHONY: messenger-failed-show
messenger-failed-show: ## Show failed messenger messages stats
	$(DC_EXEC) php bin/console messenger:failed:show --stats

.PHONY: messenger-failed-retry-all
messenger-failed-retry-all: ## Retry all failed messenger messages
	$(DC_EXEC) php bin/console messenger:failed:retry --force --all

.PHONY: import-debug-parse
import-debug-parse: ## Debug-parse an import OCR payload (JOB_ID=... or FILENAME=...)
	@if [ -n "$(JOB_ID)" ]; then \
		$(DC_EXEC) php bin/console app:import:debug-parse "$(JOB_ID)" --pretty; \
	elif [ -n "$(FILENAME)" ]; then \
		$(DC_EXEC) php bin/console app:import:debug-parse --filename "$(FILENAME)" --pretty; \
	else \
		echo "Usage: make import-debug-parse JOB_ID=<uuid> OR make import-debug-parse FILENAME=<file>"; \
		exit 1; \
	fi

##
## Database
##---------------------------------------------------------------------------

.PHONY: db-init
db-init: ## Drop schema, create schema, run migrations, setup messenger transports
	$(DC_EXEC) php bin/console doctrine:schema:drop --force --full-database
	$(DC_EXEC) php bin/console doctrine:database:create --if-not-exists
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --no-interaction
	-$(DC_EXEC) php bin/console messenger:setup-transports 2>/dev/null

.PHONY: db-migrate
db-migrate: ## Run migrations (no interaction)
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: db-diff
db-diff: ## Generate migration (only if DB is up to date)
	$(DC_EXEC) php bin/console doctrine:migrations:up-to-date
	$(DC_EXEC) php bin/console doctrine:migrations:diff

.PHONY: db-test-init
db-test-init: ## Create/migrate test database
	-$(DC_EXEC) php bin/console doctrine:database:drop --env=test --if-exists --force --no-interaction
	$(DC_EXEC) php bin/console doctrine:database:create --env=test --if-not-exists
	$(DC_EXEC) php bin/console doctrine:migrations:migrate --env=test --no-interaction

##
## Tests
##---------------------------------------------------------------------------

.PHONY: phpunit-all
phpunit-all: ## Run all PHPUnit tests
	$(DC_EXEC) vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit,Functional,Integration

.PHONY: phpunit-unit
phpunit-unit: ## Run PHPUnit Unit suite
	$(DC_EXEC) vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit

.PHONY: phpunit-integration
phpunit-integration: db-test-init ## Run PHPUnit Integration suite
	$(DC_EXEC) vendor/bin/phpunit --configuration phpunit.xml --testsuite Integration

.PHONY: phpunit-functional
phpunit-functional: db-test-init ## Run PHPUnit Functional suite
	$(DC_EXEC) vendor/bin/phpunit --configuration phpunit.xml --testsuite Functional

##
## Quality
##---------------------------------------------------------------------------

.PHONY: phpstan
phpstan: ## Run PHPStan
	$(DC_EXEC) vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit 2G

.PHONY: phpstan-baseline
phpstan-baseline: ## Generate PHPStan baseline
	$(DC_EXEC) vendor/bin/phpstan analyse -c phpstan.dist.neon --generate-baseline --memory-limit 2G

.PHONY: php-cs-fixer
php-cs-fixer: ## Run PHP-CS-Fixer (apply fixes)
	$(DC_EXEC) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

.PHONY: php-cs-fixer-check
php-cs-fixer-check: ## Run PHP-CS-Fixer (dry-run)
	$(DC_EXEC) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff

.PHONY: psalm
psalm: ## Run Psalm
	$(DC_EXEC) vendor/bin/psalm --config=psalm.xml

.PHONY: lint
lint: phpstan phpat psalm php-cs-fixer-check ## Run phpstan(phpat) + psalm + cs-check
