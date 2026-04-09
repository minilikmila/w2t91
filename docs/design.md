# Design Document

## Architecture Overview

EaglePoint is a monolithic Laravel 11 API backend with a layered internal structure, deployed as a single-machine Docker stack. The architecture favors simplicity and local-only operation — no external APIs, cloud services, or third-party integrations.

### Layers

- **HTTP Layer**: Controllers, form requests, middleware, JSON resources
- **Service Layer**: Business logic services (enrollment workflow, booking, deduplication, audit, reporting)
- **Data Layer**: Eloquent models with traits for encryption, soft deletes, and audit timestamps
- **Infrastructure**: Queue jobs, logging middleware, exception handling

### Design Decisions

- **Monolith over microservices**: Single Docker deployment eliminates cross-service orchestration. Laravel provides validation, ORM, middleware, jobs, and local file handling in one framework.
- **Token-based auth**: Internally signed session tokens with server-side tracking. No external JWT libraries or OAuth providers — tokens are SHA-256 hashed random strings stored in `api_tokens`.
- **State machine pattern**: Enrollment workflows use an explicit transition graph with guard clauses rather than a generic workflow engine.
- **Hash-chained audit log**: Append-only `audit_events` table with SHA-256 prior-hash linking for tamper evidence without external blockchain dependencies.

## Modules

### Authentication / Identity
- Local username/password login with password complexity enforcement (10+ chars, upper/lower/digit/special)
- 5-failure account lockout for 15 minutes with automatic reset on successful login
- Server-side token tracking with 24-hour fixed expiration, no refresh tokens
- Role-based access control: `admin`, `planner`, `reviewer`, `field_agent`
- 27 granular permissions mapped to roles via `role_permission` pivot table
- Middleware: `auth.token`, `role`, `permission`, `check.lockout`

### Learner Management
- CRUD for learner profiles with search, filtering, sorting, and pagination
- Data normalization: dates, phones, currency, coordinates, ratings, emails, names, gender
- Deterministic fingerprinting via SHA-256 of normalized name + DOB
- Identifier-level deduplication (email, phone) with candidate flagging — never auto-merged
- PII encryption at rest (email, phone, guardian_contact) via AES-256-CBC
- Response masking: admin/planner see full PII; reviewer/field_agent see masked values

### Enrollment Workflow
- State machine: draft → pending_review → in_review → approved → enrolled → completed
- Rejection loops back to draft for resubmission
- Configurable 1-3 level approval workflows with conditional branching
- Minor learners (age < 18) automatically require 2+ levels and guardian contact verification
- Guardian contact absence blocks workflow advancement
- Queue-based async approval processing via `ProcessEnrollmentApproval` job
- Refund eligibility gated by: cancelled status, payment received, cutoff date

### Scheduling / Booking
- Resource and schedule modeling with configurable slot duration (default 15 min)
- 15-minute time increment enforcement on start/end times
- 2-hour minimum lead time for new bookings
- Capacity-aware conflict detection across provisional and confirmed bookings
- Provisional holds expire after 5 minutes if not confirmed
- Idempotency keys prevent duplicate bookings
- Version tracking incremented on reschedule
- 24-hour reschedule window — blocked within 24 hours of start
- Late cancellations (within 24 hours) create `late_cancel` events
- Waitlist with position tracking, 10-minute offer expiry, and auto-backfill on cancellation

### Location / Geofencing
- Location CRUD with latitude/longitude (10,7 precision)
- Role-based coordinate disclosure: `locations.view_precise` → full coordinates; others → rounded to 2 decimal places + display address only
- Haversine distance calculation for proximity sorting (no external map APIs)
- Default 10 km geofence radius for nearby search
- Geofence point-in-radius check endpoint

### Security Training
- Configurable exercises with type, difficulty, max/passing score, and scoring rules JSON
- Cohort-based assignment publishing to groups of learners
- Exercise attempt lifecycle: start → record actions → submit → auto-score
- Replayable action trails stored as JSON arrays with timestamps
- Configurable scoring engine: per_answer, action_bonus, completion_bonus, fixed
- Cohort assignment status updated on attempt completion (passed/failed)

### Reporting and Audit
- Report definition CRUD with configurable filters, columns, and output format (CSV/JSON)
- Report types: learners, enrollments, bookings, audit events
- Local file export to `storage/app/reports/` with timestamped filenames
- Metadata tracking: last export path and generation timestamp
- Append-only audit events with SHA-256 hash chaining
- Audit model blocks updates/deletes via Eloquent boot hooks
- Chain verification endpoint validates prior_hash → event_hash integrity
- Entity-specific audit trail queries

### Data Quality / Deduplication
- `DataNormalizationService`: 9 normalization methods applied before persistence and import
- `DeduplicationService`: learner fingerprinting + identifier fingerprinting + multi-field candidate search
- Duplicate candidates flagged for manual review with resolution workflow (confirmed/not_duplicate/merged)
- Bulk import processes up to 10,000 rows with row-level validation and structured failure reporting

## Data Flow

### Authentication Flow
1. Client submits username/password to `POST /api/auth/login`
2. Backend validates credentials, checks lockout status
3. On success: resets failed attempts, issues 64-char token (stored as SHA-256 hash), returns user profile with role and permissions
4. On failure: increments failed attempts; locks account after 5 failures for 15 minutes
5. Logout revokes the stored token record

### Learner Import Flow
1. Client uploads CSV/XLSX to `POST /api/import/learners`
2. Backend parses file, normalizes each row via `DataNormalizationService`
3. Each row validated independently; valid rows persisted, invalid rows skipped
4. `DeduplicationService` computes fingerprints and registers identifiers
5. Duplicate candidates flagged; structured import report returned

### Enrollment Workflow Flow
1. Enrollment created in `draft` via `POST /api/enrollments`
2. `ApprovalWorkflow::buildDefault()` evaluates learner age and guardian contact
3. Submit → pending_review → in_review (creates approval record)
4. Reviewer decides via sync or async (queue job) endpoint
5. Multi-level: approved at current level → next level created; all approved → `approved` status
6. Approved → enrolled or waitlisted; enrolled → completed or cancelled; cancelled → refunded (if eligible)

### Booking Flow
1. `POST /api/bookings` creates provisional hold (5-min expiry)
2. Validates: 15-min increments, 2-hour lead time, capacity conflicts
3. `POST /api/bookings/{id}/confirm` converts to final booking
4. Cancellation triggers waitlist backfill (10-min offer expiry)
5. Reschedules blocked within 24 hours; late cancels generate `late_cancel` event

### Location Disclosure Flow
1. Client requests location data
2. Middleware authenticates and checks permissions
3. `locations.view_precise` → precise coordinates; otherwise → rounded to 2 decimal places + display address
4. Nearby search uses Haversine distance, defaults to 10 km radius

## Database Schema

### Core Tables
- `users` — authentication, role FK, lockout fields, soft deletes
- `roles` — admin, planner, reviewer, field_agent
- `permissions` — 27 granular permissions
- `role_permission` — pivot table
- `api_tokens` — server-side token storage with expiry

### Domain Tables
- `learners` — profiles with encrypted PII, fingerprint, soft deletes
- `learner_identifiers` — type/value pairs with duplicate candidate tracking
- `enrollments` — state machine status, workflow metadata JSON, payment/refund fields
- `approvals` — per-level review records
- `resources` — bookable entities with capacity
- `schedules` — time slots per resource
- `bookings` — provisional/confirmed with idempotency key, version, hold expiry
- `waitlist_entries` — position-ordered with offer expiry
- `locations` — lat/lng with display address, soft deletes
- `routes` / `route_versions` — versioned route data with prior values
- `security_exercises` — configurable exercises with scoring rules
- `exercise_attempts` — learner attempts with action trail JSON
- `cohorts` / `cohort_assignments` — group-based exercise assignment
- `audit_events` — append-only with hash chaining
- `report_definitions` — saved report configs with export metadata

## Security Considerations

- **PII Encryption**: Email, phone, guardian_contact encrypted at rest using AES-256-CBC via Laravel's `Crypt` facade
- **Response Masking**: Non-privileged roles see masked email (`jo***@example.com`) and phone (`***4567`)
- **Coordinate Obfuscation**: Unauthorized roles receive coordinates rounded to ~1 km precision
- **Password Security**: 10+ characters with complexity requirements; bcrypt hashing
- **Account Lockout**: 5 failed attempts → 15-minute lockout
- **Token Security**: 64-char random tokens stored as SHA-256 hashes; 24-hour expiry; server-side revocation
- **Audit Integrity**: Hash-chained append-only log; model-level block on update/delete
- **Soft Deletes**: Data preserved for legal compliance; never permanently removed via API
- **Input Validation**: Form requests on all endpoints; structured error responses
- **Logging**: All API requests logged with sanitized parameters; passwords/tokens redacted
