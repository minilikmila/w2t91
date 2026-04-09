# Learning Management System API

Monolithic Laravel 11 API backend for learner management, enrollment workflows, scheduling/booking, location services, security training, and audit/reporting. Designed for single-machine Docker deployment with no external service dependencies.

## Requirements

- Docker and Docker Compose
- No local PHP or Composer installation required

## Quick Start

```bash
# Optional: create .env from the example (the app entrypoint does this automatically
# if .env is missing and .env.example exists)
# cp .env.example .env

# Build and start services (app, webserver, mysql). The app container runs
# composer install on boot, generates APP_KEY if missing, runs migrations, and
# seeds roles/permissions when the roles table is empty (set AUTORUN_MIGRATIONS=0
# or AUTORUN_SEED=0 to skip those steps).
docker compose up -d --build

# Verify the application is running
curl http://localhost:8000/api/health
# Expected: {"status":"ok"}
```

Use the **`app`** service for Composer or Artisan (not `webserver`). Example: `docker compose exec app php artisan …`.

If you change the Dockerfile, rebuild with `docker compose up -d --build`.

## Docker Services

| Service   | Container            | Port            | Description                          |
| --------- | -------------------- | --------------- | ------------------------------------ |
| app       | eaglepoint-app       | 9000 (internal) | PHP 8.2-FPM application              |
| worker    | eaglepoint-worker    | —               | Queue worker (processes approval jobs)|
| webserver | eaglepoint-webserver | 8000            | Nginx reverse proxy                  |
| mysql     | eaglepoint-mysql     | 3306            | MySQL 8.0 database                   |

### Useful Docker Commands

```bash
# View logs
docker compose logs -f app

# Open a shell in the app container
docker compose exec app bash

# Run artisan commands
docker compose exec app php artisan <command>

# Stop all services
docker compose down

# Rebuild after Dockerfile changes
docker compose up -d --build
```

## Running Tests

```bash
# Run the full test suite (auto-detects Docker, starts services if needed)
./run_tests.sh

# Or run explicitly inside a running container
docker compose exec app bash run_tests.sh

# Run all PHPUnit suites (includes custom suites registered in phpunit.xml)
docker compose exec app php artisan test

# Run specific test suites by name
docker compose exec app php artisan test --testsuite=CustomUnit
docker compose exec app php artisan test --testsuite=CustomAPI

# Or run by directory
docker compose exec app php vendor/bin/phpunit unit_tests/
docker compose exec app php vendor/bin/phpunit API_tests/

# Run a single test file
docker compose exec app php vendor/bin/phpunit unit_tests/DataNormalizationTest.php
```

`./run_tests.sh` works both locally and in CI. When run outside Docker it detects `docker compose` (with fallback to `docker-compose`), ensures services are up, and executes tests inside the app container automatically.

### Test Suite Summary

| Suite       | Location      | Tests | Covers                                                                                                                                              |
| ----------- | ------------- | ----- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Custom Unit | `unit_tests/` | 151   | Normalization, deduplication, enrollment states, booking rules/versioning, audit chaining/tamper detection, encryption/masking, geofencing, password policy, approval role enforcement, waitlist lifecycle, log redaction |
| Custom API  | `API_tests/`  | 111   | Auth, permissions, authorization, learner CRUD/search/import, enrollment workflow/transitions, approvals, booking conflicts, location disclosure, resources/schedules/routes, reports, object authorization, waitlist lifecycle/backfill |

## API Overview

Base URL: `http://localhost:8000/api`

All protected endpoints require `Authorization: Bearer <token>`.

| Group       | Endpoints                                                                                  | Key Operations                                      |
| ----------- | ------------------------------------------------------------------------------------------ | --------------------------------------------------- |
| Auth        | `/auth/login`, `/auth/logout`, `/auth/me`, `/auth/register`                                | Login, token issuance, profile                      |
| Learners    | `/learners`                                                                                | CRUD, search, filter, paginate                      |
| Import      | `/import/learners`                                                                         | Bulk CSV/XLSX import (up to 10,000 rows)            |
| Enrollments | `/enrollments`, `/enrollments/{id}/transition`, `/submit`, `/review`, `/cancel`, `/refund` | State machine workflow with approval levels         |
| Approvals   | `/approvals`, `/approvals/{id}/decide`, `/claim`                                           | Review queue with sync/async processing             |
| Bookings    | `/bookings`, `/bookings/{id}/confirm`, `/cancel`, `/reschedule`                            | Provisional holds, conflict detection, late cancel  |
| Waitlist    | `/waitlist`, `/waitlist/{id}/accept`                                                       | Position-based waitlist with offer expiry           |
| Locations   | `/locations`, `/locations/nearby`, `/locations/{id}/geofence`                              | Role-based coordinate disclosure, Haversine sorting |
| Exercises   | `/exercises`, `/cohorts`, `/cohorts/assign`                                                | Training exercise management, cohort publishing     |
| Attempts    | `/attempts`, `/attempts/{id}/action`, `/submit`                                            | Exercise attempts with action trails, auto-scoring  |
| Placements  | `/placements`, `/placements/{id}`, `/placements/{id}/cancel`                               | Field placement assignment and lifecycle            |
| Packages    | `/packages`, `/packages/{id}/publish`, `/packages/{id}/archive`                            | Route/group package publishing workflow             |
| Analytics   | `/analytics/overview`, `/enrollments`, `/bookings`, `/placements`, `/operations`           | Operational metrics and back-office dashboard       |
| Audit       | `/audit`, `/audit/verify`, `/audit/entity/{type}/{id}`                                     | Hash-chained append-only audit log                  |
| Reports     | `/reports`, `/reports/{id}/generate`, `/download`                                          | Report definitions with CSV/JSON export             |

## Project Structure

```
app/
├── Console/              # Artisan commands
├── Exceptions/           # Structured JSON error handling
├── Http/
│   ├── Controllers/      # 13 API controllers
│   ├── Middleware/        # Token auth, role/permission, lockout, request logging
│   ├── Requests/         # Form request validation
│   └── Resources/        # JSON resource transformers with PII masking
├── Jobs/                 # Queue jobs (enrollment approval processing)
├── Models/               # 18 Eloquent models with traits
│   └── Traits/           # EncryptsPii, AuditsTimestamps
├── Providers/            # Service providers
└── Services/             # 8 business logic services
config/                   # Application configuration
database/
├── factories/            # Model factories
├── migrations/           # 26 migration files
└── seeders/              # Role, permission, and database seeders
docker/
└── nginx/                # Nginx configuration
routes/
└── api.php               # All API route definitions
tests/                    # PHPUnit base classes
unit_tests/               # 14 unit test files (151 tests)
API_tests/                # 17 API test files (111 tests)
run_tests.sh              # Test runner with summary output
```

## Roles and Permissions

| Role          | Description                                                         |
| ------------- | ------------------------------------------------------------------- |
| `admin`       | Full system access (27 permissions)                                 |
| `planner`     | Learner management, scheduling, locations, reports (21 permissions) |
| `reviewer`    | Enrollment approval, audit viewing (10 permissions)                 |
| `field_agent` | Field-level learner and booking access (11 permissions)             |

## Verification Checklist

1. **Start the application**

   ```bash
   docker compose up -d --build
   # .env is created from .env.example on first boot if missing
   ```

2. **Verify health endpoint**

   ```bash
   curl http://localhost:8000/api/health
   # Expected: {"status":"ok"}
   ```

3. **Run all tests**

   ```bash
   ./run_tests.sh
   # Expected: ALL PASSED with 262+ tests
   ```

4. **Test authentication flow**

   ```bash
   # Login
   curl -X POST http://localhost:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"username":"admin","password":"YourPassword1!"}'

   # Use the returned token for subsequent requests
   curl http://localhost:8000/api/auth/me \
     -H "Authorization: Bearer <token>"
   ```

5. **Verify role-based access**
   - Admin can access all endpoints
   - Field agent cannot access `/api/import/learners` (403)
   - Field agent sees obfuscated coordinates on location endpoints
