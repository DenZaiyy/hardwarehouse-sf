ESC := $(shell printf '\033')
BOLD := $(ESC)[1m
INFO := $(ESC)[0;34m
NC := $(ESC)[0m

define banner
	@echo "$(BOLD)$(1)--------------------------------------------"
	@echo "$(2)"
	@echo "--------------------------------------------$(NC)"
endef

.DEFAULT_GOAL := help
.PHONY: help

# Common
APP_ENV ?= dev
APP_DEBUG ?= 1
CONSOLE = php bin/console --env=$(APP_ENV)

.PHONY: install start stop tw cache up down migration migrate translate-FR translate-EN quality tests \
	prod cache-prod twb assets imports sitemap vendor-prod migrate-prod

help: ## Show this help
	$(call banner,$(INFO),Available targets:)
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Development ————————————————————————————————————————————————————————————————
install: ## Install composer dependencies
	$(call banner,$(INFO),Installing dependencies...)
	composer install --optimize-autoloader

start: vendor ## Starting symfony server with logs
	$(call banner,$(INFO),Starting symfony server...)
	symfony server:start

stop: ## Stopping symfony server
	$(call banner,$(INFO),Shutdown symfony server...)
	symfony server:stop

tw: ## Building tailwind css with watch mode
	$(call banner,$(INFO),Starting watch mode to build tailwind css...)
	php bin/console tailwind:build --watch

cache: ## Clear the cache of symfony app
	$(call banner,$(INFO),Clearing cache...)
	php bin/console cache:clear

## —— Docker ————————————————————————————————————————————————————————————————
up: ## Starting docker container for db and mailer
	$(call banner,$(INFO),Starting docker containers...:)
	docker compose up -d

down: ## Stopping docker containers
	$(call banner,$(INFO),Stopping docker containers...)
	docker compose down

## —— Migration ————————————————————————————————————————————————————————————————
migration: ## Make new migration about current changes
	$(call banner,$(INFO),Generate new symfony migration...)
	php bin/console make:migration

migrate: ## Migrate last migration in database
	$(call banner,$(INFO),Migrate last migration on database...)
	php bin/console doctrine:migrations:migrate --no-interaction

## —— Translations ————————————————————————————————————————————————————————————————
translate-FR: ## Dump translations for french language
	$(call banner,$(INFO),Dump and extract translations keys for french language...)
	php bin/console translation:extract --force --format=yaml --as-tree=3 fr

translate-EN: ## Dump translations for english language
	$(call banner,$(INFO),Dump and extract translations keys for english language...)
	php bin/console translation:extract --force --format=yaml --as-tree=3 en

## —— Quality & Tests ————————————————————————————————————————————————————————————————
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
	XDEBUG_MODE=coverage php bin/phpunit

## —— Production ————————————————————————————————————————————————————————————————
prod: APP_ENV=prod
prod: APP_DEBUG=0
prod: vendor-prod assets cache-prod migrate-prod sitemap ## Execute all commands needed to prod env

cache-prod: APP_ENV=prod
cache-prod: APP_DEBUG=0
cache-prod: ## Clear and warmup cache for production
	$(call banner,$(INFO),Clearing and warming cache for production...)
	$(CONSOLE) cache:clear --no-warmup --no-debug
	$(CONSOLE) cache:warmup --no-debug

twb: APP_ENV=prod
twb: APP_DEBUG=0
twb: ## Building tailwind css and minify
	$(call banner,$(INFO),Starting minify build for tailwind css...)
	$(CONSOLE) tailwind:build --minify --no-debug

assets: APP_ENV=prod
assets: APP_DEBUG=0
assets: twb ## Command to build assets
	$(call banner,$(INFO),Building assets...)
	$(CONSOLE) asset-map:compile --no-debug

sitemap: APP_ENV=prod
sitemap: APP_DEBUG=0
sitemap: ## Dump sitemap files
	$(call banner,$(INFO),Dump all sitemap files...)
	$(CONSOLE) presta:sitemaps:dump --no-debug

migrate-prod: APP_ENV=prod
migrate-prod: APP_DEBUG=0
migrate-prod: ## Create DB (if needed) and run migrations
	$(call banner,$(INFO),Create database if not exists...)
	$(CONSOLE) doctrine:database:create --if-not-exists --no-debug
	$(call banner,$(INFO),Launch migration without interaction...)
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration --no-debug

vendor-prod: ## Install composer dependencies for production
	composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --prefer-dist
