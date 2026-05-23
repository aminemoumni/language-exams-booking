.PHONY: up down build restart logs shell-backend shell-frontend shell-mongo jwt-keys

# ─── Docker ───────────────────────────────────────────────────────────────────
up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

restart:
	docker compose restart

logs:
	docker compose logs -f

# ─── Shells ───────────────────────────────────────────────────────────────────
shell-backend:
	docker compose exec backend sh

shell-frontend:
	docker compose exec frontend sh

shell-mongo:
	docker compose exec mongodb mongosh -u root -p rootpassword

# ─── JWT keys generation ──────────────────────────────────────────────────────
jwt-keys:
	docker compose exec backend sh -c "\
		mkdir -p config/jwt && \
		openssl genpkey -out config/jwt/private.pem -aes256 \
		  -algorithm rsa -pkeyopt rsa_keygen_bits:4096 \
		  -pass pass:$${JWT_PASSPHRASE} && \
		openssl pkey -in config/jwt/private.pem \
		  -out config/jwt/public.pem -pubout \
		  -passin pass:$${JWT_PASSPHRASE} && \
		chmod 600 config/jwt/private.pem config/jwt/public.pem"

# ─── Tests ────────────────────────────────────────────────────────────────────
test-backend:
	docker compose exec backend php bin/phpunit

test-frontend:
	docker compose exec frontend npm test -- --watchAll=false

test:
	$(MAKE) test-backend
	$(MAKE) test-frontend
