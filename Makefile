SHELL := /bin/sh

DC := docker compose -f resources/docker/compose.yml --env-file resources/docker/.env
DC_EXEC := $(DC) exec -T app

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
up: ## Start stack
	$(DC) up -d

.PHONY: up-rebuild
up-rebuild: ## Start stack (rebuild + force recreate)
	$(DC) down --remove-orphans
	$(DC) up -d --build --force-recreate

.PHONY: down
down: ## Stop all containers
	$(DC) down --remove-orphans

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
