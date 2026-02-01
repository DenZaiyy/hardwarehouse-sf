.DEFAULT_GOAL := help
ESC := $(shell printf '\033')
BOLD := $(ESC)[1m
INFO := $(ESC)[0;34m
NC := $(ESC)[0m

define banner
	@echo "$(BOLD)$(1)--------------------------------------------"
	@echo "$(2)"
	@echo "--------------------------------------------$(NC)"
endef

.PHONY: help
help: ## Show this help
	$(call banner,$(INFO),Available targets:)
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: prod
prod: ## Execute all commands needed to prod env
	$(call banner,$(INFO),Composer install with no-dev dependencies...)
	composer install --no-dev --optimize-autoloader
	$(call banner,$(INFO),Start build for assets...)
	php bin/console asset-map:compile
	$(call banner,$(INFO),Start importmap install...)
	php bin/console importmap:install
	$(call banner,$(INFO),Create database if not exists...)
	php bin/console doctrine:database:create --if-not-exists
	$(call banner,$(INFO),Launch migration without interaction...)
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(call banner,$(INFO),Cleaning the cache for production...)
	php bin/console cache:clear --env=prod

.PHONY: start
start: ## Starting symfony server with logs
	$(call banner,$(INFO),Starting symfony server...)
	symfony server:start

.PHONY: stop
stop: ## Stopping symfony server
	$(call banner,$(INFO),Shutdown symfony server...)
	symfony server:stop

.PHONY: up
up: ## Starting docker container for db and mailer
	$(call banner,$(INFO),Starting docker containers...:)
	docker compose up -d

.PHONY: down
down: ## Stopping docker containers
	$(call banner,$(INFO),Stopping docker containers...)
	docker compose down

.PHONY: migration
migration: ## Make new migration about current changes
	$(call banner,$(INFO),Generate new symfony migration...)
	php bin/console make:migration

.PHONY: migrate
migrate: ## Migrate last migration in database
	$(call banner,$(INFO),Migrate last migration on database...)
	php bin/console doctrine:migration:migrate --no-interaction

.PHONY: install
install: ## Install composer dependencies
	$(call banner,$(INFO),Installing dependencies...)
	composer install --optimize-autoloader

.PHONY: cache
cache: ## Clear the cache of symfony app
	$(call banner,$(INFO),Clearing cache...)
	php bin/console cache:clear

.PHONY: tw
tw: ## Building tailwind css with watch mode
	$(call banner,$(INFO),Starting watch mode to build tailwind css...)
	php bin/console tailwind:build --watch

.PHONY: twb
twb: ## Building tailwind css and minify
	$(call banner,$(INFO),Starting minify build for tailwind css...)
	php bin/console tailwind:build --minify

.PHONY: translate-FR
translate-FR: ## Dump translations for french language
	$(call banner,$(INFO),Dump and extract translations keys for french language...)
	php bin/console translation:extract --force --format=yaml --as-tree=3 fr

.PHONY: translate-EN
translate-EN: ## Dump translations for english language
	$(call banner,$(INFO),Dump and extract translations keys for english language...)
	php bin/console translation:extract --force --format=yaml --as-tree=3 en

.PHONY: quality
quality: ## Running quality code check using Rector, ECS PHP-CS and PHPStan
	$(call banner,$(INFO),Running PHP-CS with autofix...)
	php ./vendor/bin/php-cs-fixer fix
	$(call banner,$(INFO),Running ECS with autofix...)
	php ./vendor/bin/ecs check --fix
	$(call banner,$(INFO),Running rector with autofix...)
	php ./vendor/bin/rector
	$(call banner,$(INFO),Linting yaml configs...)
	php bin/console lint:yaml config --parse-tags
	$(call banner,$(INFO),Linting twig templates...)
	php bin/console lint:twig templates
	$(call banner,$(INFO),Linting containers...)
	php bin/console lint:container
	$(call banner,$(INFO),Running PHPStan with max level...)
	php ./vendor/bin/phpstan analyse --memory-limit=-1 --level max

.PHONY: tests
tests: ## Running tests using PHPUnit
	$(call banner,$(INFO),Running tests in test environment...)
	$(call banner,$(INFO),Drop database if already exists...)
	php bin/console --env=test doctrine:database:drop --force --if-exists
	$(call banner,$(INFO),Creating database...)
	php bin/console --env=test doctrine:database:create
	$(call banner,$(INFO),Running migrations...)
	php bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(call banner,$(INFO),Loading fixtures...)
	php bin/console --env=test doctrine:fixtures:load --no-interaction
	$(call banner,$(INFO),Clearing cache...)
	php bin/console --env=test cache:clear
	$(call banner,$(INFO),Running tests...)
	php bin/phpunit
