# ETS Global EMEA — Language Exam Booking

A full-stack exam session booking platform built as a technical test.  
Users browse and reserve language exam sessions; admins manage the session catalogue.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 · Symfony 7.4 · Doctrine ODM |
| Database | MongoDB 7 |
| Auth | LexikJWT (RS256, 1 h TTL) |
| Frontend | Next.js 15 (App Router) · React 19 · TypeScript |
| Styling | Tailwind CSS |
| HTTP client | Axios (JWT interceptor + 401 redirect guard) |
| Tests | PHPUnit 11 (backend) · Jest 29 + React Testing Library (frontend) |
| Infrastructure | Docker Compose · Nginx 1.25 · PHP-FPM 8.3 |

---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) ≥ 24 with Compose v2
- [Make](https://www.gnu.org/software/make/)

No local PHP or Node.js installation required — everything runs inside containers.

---

## Performance tips

### Run the project files inside WSL (Windows only)

If you are on Windows with **Docker Desktop**, performance depends heavily on where the project files live:

| File location | I/O speed | Recommendation |
|---|---|---|
| Windows filesystem (`C:\Users\...`) | Slow — every file access crosses the WSL boundary | ❌ Avoid |
| WSL 2 filesystem (`~/` inside Ubuntu) | Native Linux speed | ✅ Recommended |

Clone and work on the project from inside your WSL distribution:

```bash
# Inside WSL terminal
cd ~
git clone <repo-url>
cd language_exams_test_technique
make up
```

### Allocate enough memory to WSL 2

Docker Desktop's containers run inside the WSL 2 VM. By default WSL 2 is limited to
50 % of your RAM (capped at 8 GB). This stack runs PHP-FPM + MongoDB + Next.js, so
**at least 4 GB** is recommended, 6–8 GB for a comfortable experience.

Create or edit `%USERPROFILE%\.wslconfig` (i.e. `C:\Users\<you>\.wslconfig`):

```ini
[wsl2]
memory=6GB      # Total RAM available to the WSL 2 VM
processors=4    # Number of virtual CPU cores (optional but helpful)
swap=2GB        # Optional swap space
```

Then restart the WSL VM for the settings to take effect:

```powershell
# In PowerShell or CMD
wsl --shutdown
```

Reopen Docker Desktop (it restarts WSL automatically) and run `make up` again.

---

## First-run setup

```bash
# 1. Build Docker images and start all containers
#    Dependencies (Composer + npm) are installed inside the Dockerfiles —
#    no separate install step needed.
make up

# 2. Generate the RSA key pair used for JWT signing
make jwt-keys

# 3. Create MongoDB indexes and seed demo data
make setup
```

The application is now available at **http://localhost:8001**.

> **Subsequent starts.** `make up` re-uses cached layers so it is fast after the first build.  
> Use `make build` to force a full rebuild without cache (e.g. after changing a Dockerfile).

> **Changing the port.** Edit `HTTP_PORT` in `.env`,
> then restart with `make up`.

---

## URLs

| Service | URL |
|---|---|
| Application (frontend) | http://localhost:8001 |
| REST API | http://localhost:8001/api |
| MongoDB | mongodb://localhost:27017 |

Nginx is the single entry point on port 80:
- `/api/*` → Symfony PHP-FPM (port 9000)
- `/*` → Next.js dev server (port 3000)

---

## Demo accounts

Seeded by `make setup` from `UserFixtures`:

| Role | Email | Password |
|---|---|---|
| Admin | admin@ets.com | 0123456789 |
| User | user@ets.com | 0123456789 |

The login page also shows these credentials as a hint.

---

## Make targets

Run `make help` to list all available targets with descriptions.

```
make up              Start all containers
make down            Stop all containers
make build           Rebuild images (no cache)
make install         Re-install Composer + npm dependencies (after adding a package)
make jwt-keys        Generate RSA key pair for JWT
make setup           Create MongoDB indexes + load fixtures
make reset           Drop schema → recreate indexes → reload fixtures
make test-backend    Run the PHPUnit test suite
make test-frontend   Run the Jest test suite
make test            Run both test suites
make logs            Tail logs from all containers
make shell-backend   Open a shell inside the backend container
make shell-frontend  Open a shell inside the frontend container
make shell-mongo     Open a mongosh session (reads credentials from .env)
```

---

## Environment variables

The root `.env` is committed and contains safe development defaults.  
Override values in a local `.env.local` (gitignored) for your environment.

| Variable | Default | Description |
|---|---|---|
| `HTTP_PORT` | `80` | Host port exposed by Nginx |
| `MONGO_ROOT_USER` | `root` | MongoDB root username |
| `MONGO_ROOT_PASSWORD` | `root` | MongoDB root password |
| `MONGO_APP_USER` | `ets_user` | Application DB user |
| `MONGO_APP_PASSWORD` | `ets_password` | Application DB password |
| `MONGO_DB` | `ets_language_exams` | Database name |
| `JWT_PASSPHRASE` | *(set)* | Passphrase for the private RSA key |
| `JWT_TTL` | `3600` | JWT lifetime in seconds |
| `NEXT_PUBLIC_API_URL` | `http://localhost/api` | API base URL used by the frontend |
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | Allowed CORS origins |

---

## REST API reference

All endpoints are prefixed with `/api`. Authenticated routes require:
```
Authorization: Bearer <jwt>
```

### Authentication

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/auth/register` | — | Create a new user account |
| `POST` | `/api/auth/login` | — | Obtain a JWT token |

### Profile

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/me` | User | Get the authenticated user's profile |
| `PUT` | `/api/me` | User | Update name and/or email |

### Sessions

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/sessions` | User | Paginated session list (`page`, `limit`, `language`, `location`) |
| `GET` | `/api/sessions/{id}` | User | Single session |
| `POST` | `/api/sessions` | **Admin** | Create a session |
| `PUT` | `/api/sessions/{id}` | **Admin** | Update a session |
| `DELETE` | `/api/sessions/{id}` | **Admin** | Soft-delete a session |

### Reservations

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/reservations/me` | User | My reservations (`?active=true` or `?active=false` for all) |
| `POST` | `/api/reservations` | User | Book a session (`{ "sessionId": "..." }`) |
| `DELETE` | `/api/reservations/{id}` | User | Cancel a reservation |

---

## Project structure

```
.
├── backend/                    # Symfony 7.4 REST API
│   ├── src/
│   │   ├── Controller/         # AuthController, SessionController, ReservationController, UserController
│   │   ├── Document/           # Doctrine ODM documents (User, Session, Reservation)
│   │   ├── DTO/                # Request validation (RegisterRequest, SessionRequest…)
│   │   ├── Repository/         # MongoDB repositories with pagination & filtering
│   │   ├── Service/            # Business logic (UserService, SessionService, ReservationService)
│   │   ├── Security/           # JWT UserProvider
│   │   ├── EventListener/      # ApiExceptionListener — normalises error responses
│   │   └── Exception/          # AppException base class
│   ├── config/                 # Symfony bundles, security, CORS, JWT, serializer
│   ├── tests/                  # PHPUnit integration tests (controllers + services)
│   └── phpunit.xml.dist
│
├── frontend/                   # Next.js 15 App Router
│   ├── src/
│   │   ├── app/                # Pages: login, sessions, reservations, account
│   │   ├── components/         # Navbar, SessionCard, ReservationCard, SessionFormModal, Spinner
│   │   ├── contexts/           # AuthContext (JWT cookie + user hydration)
│   │   ├── lib/                # axios client, formatDate (UTC-safe)
│   │   ├── middleware.ts        # Route protection (redirect unauthenticated users)
│   │   └── types/              # Shared TypeScript interfaces
│   └── tests/                  # Jest + React Testing Library
│       ├── components/         # SessionCard, ReservationCard, SessionFormModal
│       └── pages/              # login, sessions, reservations
│
├── docker/
│   ├── php/Dockerfile          # PHP 8.3-FPM (dev + prod targets)
│   ├── node/Dockerfile         # Node 20 (dev + prod targets)
│   ├── nginx/default.conf      # Reverse proxy config
│   └── mongodb/init.js         # One-time user creation script
│
├── docker-compose.yml
├── Makefile
└── .env                        # Root environment (safe dev defaults)
```

---

## Running the tests

```bash
# Backend — PHPUnit (integration tests, isolated test database)
make test-backend

# Frontend — Jest + React Testing Library
make test-frontend

# Both at once
make test
```

Backend tests run against a separate MongoDB database (`ets_language_exams_test` as defined in `backend/.env.test`).  
Each test class resets the relevant collections before running.

---

## Key design decisions

- **Single Nginx entry point** — both the API and the Next.js app are served on port 80. No CORS issues between frontend and backend in development.
- **Doctrine ODM proxy initialisation** — `ReferenceOne` lazy proxies are explicitly initialised in the repository layer before serialization so that embedded session data is always fully hydrated in reservation responses.
- **Past date/time validation at two levels** — the frontend blocks submission with a local error before the request leaves the browser; the backend validates again via a `#[Assert\Callback]` on `SessionRequest` so the rule is enforced regardless of the client.
- **JWT decoded client-side** — `isAdmin` is derived by decoding the JWT payload (public, not encrypted) in `AuthContext`, avoiding an extra `/api/me` round-trip just to know the role.
- **`noValidate` on forms with custom validation** — prevents the browser's native constraint popups from conflicting with the application's styled error messages.
