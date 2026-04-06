.PHONY: install dev test lint fresh

install:
	composer install --no-interaction --prefer-dist
	cp .env.example .env 2>/dev/null || true
	php artisan key:generate 2>/dev/null || true

dev:
	php artisan serve

test:
	php artisan test 2>/dev/null || vendor/bin/phpunit

lint:
	find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;

fresh:
	php artisan migrate:fresh --seed

docker-up:
	docker compose up -d

docker-down:
	docker compose down
