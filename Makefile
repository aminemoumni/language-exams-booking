.DEFAULT_GOAL := help
.PHONY: help up down build restart logs shell-backend shell-frontend shell-mongo \
        jwt-keys schema fixtures setup reset test-backend test-frontend test

# Read credentials from .env so targets don't need hardcoded values.
# ?= means "only set if not already defined in the environment".
MONGO_ROOT_USER     ?= $(shell grep '^MONGO_ROOT_USER='     .env 2>/dev/null | cut -d= -f2)
MONGO_ROOT_PASSWORD ?= $(shell grep '^MONGO_ROOT_PASSWORD=' .env 2>/dev/null | cut -d= -f2)
MONGO_ROOT_USER     := $(or $(MONGO_ROOT_USER),root)
MONGO_ROOT_PASSWORD := $(or $(MONGO_ROOT_PASSWORD),rootpassword)

# ─── Help ─────────────────────────────────────────────────────────────────────
help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ─── Docker ───────────────────────────────────────────────────────────────────
up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Rebuild images (no cache)
	docker compose build --no-cache

restart: ## Restart all containers
	docker compose restart

logs: ## Tail logs from all containers
	docker compose logs -f

# ─── Shells ───────────────────────────────────────────────────────────────────
shell-backend: ## Open a shell inside the backend container
	docker compose exec backend sh

shell-frontend: ## Open a shell inside the frontend container
	docker compose exec frontend sh

shell-mongo: ## Open a mongosh session as root (reads credentials from .env)
	docker compose exec mongodb mongosh -u $(MONGO_ROOT_USER) -p $(MONGO_ROOT_PASSWORD)

# ─── JWT keys ─────────────────────────────────────────────────────────────────
jwt-keys: ## Generate RSA key pair for JWT using the bundle's own command
	docker compose exec -u root backend sh -c "mkdir -p config/jwt && chown www-data:www-data config/jwt"
	docker compose exec -u www-data backend php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction
	docker compose exec -u root backend chmod 600 config/jwt/private.pem

# ─── Database ─────────────────────────────────────────────────────────────────
schema: ## Create MongoDB indexes from ODM document annotations
	docker compose exec backend php bin/console doctrine:mongodb:schema:create

fixtures: ## Seed the database (users, sessions, reservations) — purges first
	docker compose exec backend php bin/console doctrine:mongodb:fixtures:load --purge-with-delete -n

setup: schema fixtures ## Fresh-DB bootstrap: create schema then load fixtures

reset: ## Full reset: drop schema → recreate → reload fixtures
	docker compose exec backend php bin/console doctrine:mongodb:schema:drop -n
	$(MAKE) setup

# ─── Tests ────────────────────────────────────────────────────────────────────
test-backend: ## Run PHPUnit test suite
	docker compose exec -e APP_ENV=test backend php bin/console cache:warmup --env=test -q || true
	docker compose exec -e APP_ENV=test backend php bin/phpunit --colors=always

test-frontend: ## Run Jest test suite
	docker compose exec frontend npm test -- --watchAll=false

test: test-backend test-frontend ## Run all tests (backend then frontend)
