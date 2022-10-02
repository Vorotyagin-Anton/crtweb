.DEFAULT_GOAL := help

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

rebuild: down build up composer-install ## rebuild docker containers with dependencies

down:
	docker-compose down --remove-orphans

build:
	docker-compose build

up:
	docker-compose up -d

composer-install:
	docker exec crtweb_app_1 composer install

db-init: db-create-schema db-fill-in ## fill in the database

db-create-schema:
	docker exec crtweb_app_1 php bin/console orm:schema-tool:update --complete --force

db-fill-in:
	docker exec crtweb_app_1 php bin/console fetch:trailers

test-run: ## run phpunit tests
	docker exec crtweb_app_1 php vendor/bin/phpunit