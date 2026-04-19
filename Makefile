.PHONY: up down build rebuild logs ps ws sh art composer npm test pint stan migrate fresh

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

logs:
	docker compose logs -f

ps:
	docker compose ps

# Shell into workspace
ws:
	docker compose exec workspace bash

sh: ws

# artisan shortcut: make art CMD="migrate --seed"
art:
	docker compose exec workspace php artisan $(CMD)

composer:
	docker compose exec workspace composer $(CMD)

npm:
	docker compose exec workspace npm $(CMD)

test:
	docker compose exec workspace php artisan test

pint:
	docker compose exec workspace ./vendor/bin/pint

stan:
	docker compose exec workspace ./vendor/bin/phpstan analyse

migrate:
	docker compose exec workspace php artisan migrate

fresh:
	docker compose exec workspace php artisan migrate:fresh --seed
