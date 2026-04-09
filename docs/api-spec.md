# API Specification

## Base URL

```
http://localhost:8000/api
```

Laravel also exposes a framework health route at `GET /up` (outside this prefix).

### GET /api/health

Public liveness check.

**Auth**: None  
**Response 200**: `{ "status": "ok" }`

## Authentication

All protected endpoints require a Bearer token in the `Authorization` header:

```
Authorization: Bearer <token>
```

Tokens are obtained via `POST /api/auth/login` and have a 24-hour fixed expiration with no refresh mechanism. Tokens are revoked on logout or account lockout.

## Error Response Format

All errors return a consistent JSON structure:

```json
{
  "error": "Error Type",
  "message": "Human-readable description",
  "errors": {
    "field": ["Validation message"]
  }
}
```

| Status | Error Type | When |
|--------|-----------|------|
| 401 | Unauthenticated | Missing/invalid/expired token |
| 403 | Forbidden | Insufficient role or permission |
| 404 | Not Found | Resource or endpoint not found |
| 405 | Method Not Allowed | Wrong HTTP method |
| 422 | Validation Error / Invalid Transition / Invalid Request | Bad input or business rule violation |
| 423 | Locked | Account locked out |
| 500 | Server Error | Unexpected server failure |

---

## Endpoints

### Auth

#### POST /api/auth/login
Login and receive an API token.

**Auth**: None  
**Body**: `{ "username": "string", "password": "string" }`  
**Response 200**:
```json
{
  "message": "Login successful.",
  "token": "64-char-random-string",
  "token_type": "Bearer",
  "expires_at": "2024-01-02T00:00:00+00:00",
  "user": { "id": 1, "username": "admin", "name": "Admin", "email": "...", "role": { "name": "Administrator", "slug": "admin", "permissions": ["..."] } }
}
```
**Error 401**: Invalid credentials  
**Error 423**: Account locked

#### POST /api/auth/register
Create a new user account.

**Auth**: `auth.token` + `users.manage`  
**Body**: `{ "username": "string", "name": "string", "email": "string", "password": "string", "role_id": int|null }`  
**Password Requirements**: Min 10 chars, uppercase, lowercase, digit, special character  
**Response 201**: User created

#### POST /api/auth/logout
Revoke the current token.

**Auth**: `auth.token`  
**Response 200**: `{ "message": "Logged out successfully." }`

#### GET /api/auth/me
Get authenticated user profile.

**Auth**: `auth.token`  
**Response 200**: User object with role and permissions

---

### Learners

#### GET /api/learners
List learners with optional filters.

**Auth**: `learners.view`  
**Query Params**: `search`, `status`, `nationality`, `is_minor`, `sort`, `direction`, `per_page` (max 100)  
**Response 200**: Paginated learner list

#### POST /api/learners
Create a learner profile.

**Auth**: `learners.create`  
**Body**: `{ "first_name": "string", "last_name": "string", "date_of_birth": "date|null", "email": "email|null", "phone": "string|null", "gender": "male|female|other|prefer_not_to_say|null", "nationality": "string|null", "language": "string|null", "address": "string|null", "guardian_name": "string|null", "guardian_contact": "string|null", "metadata": "object|null" }`  
**Response 201**: Created learner (PII masked based on role)

#### GET /api/learners/{id}
Get a single learner.

**Auth**: `learners.view`  
**Response 200**: Learner with identifiers

#### PUT /api/learners/{id}
Update a learner profile.

**Auth**: `learners.update`  
**Body**: Same as POST (all fields optional)  
**Response 200**: Updated learner

#### DELETE /api/learners/{id}
Soft-delete a learner.

**Auth**: `learners.update`  
**Response 200**: Deletion confirmed

---

### Bulk Import

#### POST /api/import/learners
Import learners from CSV/XLSX file.

**Auth**: `learners.import`  
**Content-Type**: `multipart/form-data`  
**Body**: `file` (CSV/XLSX/XLS, max 20MB, max 10,000 rows)  
**Response 200**:
```json
{
  "success": true,
  "total_rows": 500,
  "imported": 485,
  "failed": 15,
  "duplicate_flags": 3,
  "imported_records": [{ "row": 2, "learner_id": 101 }],
  "failed_records": [{ "row": 5, "data": {...}, "errors": {...} }],
  "duplicate_records": [{ "row": 10, "learner_id": 105, "candidate_count": 1 }]
}
```

---

### Enrollments

#### GET /api/enrollments
List enrollments.

**Auth**: `enrollments.view`  
**Query Params**: `status`, `learner_id`, `program_name`, `per_page`

#### POST /api/enrollments
Create enrollment in draft status.

**Auth**: `enrollments.create`  
**Body**: `{ "learner_id": int, "program_name": "string", "approval_levels": 1-3, "payment_amount": decimal|null, "refund_cutoff_at": "datetime|null", "notes": "string|null" }`

#### GET /api/enrollments/{id}
Get enrollment with workflow status.

**Auth**: `enrollments.view`

#### PUT /api/enrollments/{id}/transition
Generic state transition.

**Auth**: `enrollments.update`  
**Body**: `{ "status": "string", "reason_code": "string|null", "notes": "string|null" }`

#### POST /api/enrollments/{id}/submit
Submit for review (draft → `submitted`).

**Auth**: `enrollments.update`

#### POST /api/enrollments/{id}/review
Begin review (`submitted` → `under_review`).

**Auth**: `enrollments.approve`

#### GET /api/enrollments/{id}/workflow
Get workflow status summary.

**Auth**: `enrollments.view`

#### POST /api/enrollments/{id}/cancel
Cancel enrollment with refund eligibility check.

**Auth**: `enrollments.cancel`  
**Body**: `{ "reason": "string|null" }`

#### POST /api/enrollments/{id}/refund
Process refund for cancelled enrollment.

**Auth**: `enrollments.cancel`  
**Body**: `{ "reason": "string|null" }`

#### GET /api/enrollments/{id}/refund-eligibility
Check refund eligibility without acting.

**Auth**: `enrollments.view`  
**Response 200**: `{ "enrollment_id", "status", "payment_amount", "payment_received", "refund_cutoff_at", "cancelled_at", "eligible", "reasons" }`

Enrollment statuses used by the API include: `draft`, `submitted`, `under_review`, `approved`, `rejected`, `enrolled`, `waitlisted`, `cancelled`, `completed`, `refunded`. Valid transitions are enforced server-side (`PUT /api/enrollments/{id}/transition` and the dedicated action routes above).

---

### Approvals

#### GET /api/approvals
List approvals.

**Auth**: `enrollments.approve`  
**Query Params**: `enrollment_id`, `status`, `reviewer_id`, `my_queue=true`, `per_page`

#### GET /api/approvals/{id}
Show approval details.

**Auth**: `enrollments.approve`

#### POST /api/approvals/{id}/decide
Queue-based approval decision (async).

**Auth**: `enrollments.approve`  
**Body**: `{ "decision": "approved|rejected", "comments": "string|null", "reason_code": "string|null" }`  
**Response 202**: Decision queued

#### POST /api/approvals/{id}/decide-sync
Synchronous approval decision.

**Auth**: `enrollments.approve`  
**Body**: Same as async decide  
**Response 200**: Decision processed with updated workflow

#### POST /api/approvals/{id}/claim
Claim a pending approval for review.

**Auth**: `enrollments.approve`

---

### Resources

Bookable resources (capacity, type, metadata) used by schedules and bookings.

#### GET /api/resources
List resources.

**Auth**: `resources.view`  
**Query Params**: `type`, `is_active`, `per_page` (max 100)

#### POST /api/resources
Create resource.

**Auth**: `resources.manage`  
**Body**: `{ "name": "string", "type": "string", "description": "string|null", "capacity": int|null, "is_active": bool|null, "metadata": object|null }`

#### GET /api/resources/{id}
Show resource.

**Auth**: `resources.view`

#### PUT /api/resources/{id}
Update resource.

**Auth**: `resources.manage`

#### DELETE /api/resources/{id}
Soft-delete resource.

**Auth**: `resources.manage`

---

### Schedules

Per-resource calendar windows and slot configuration (default slot duration 15 minutes).

#### GET /api/schedules
List schedules.

**Auth**: `resources.view`  
**Query Params**: `resource_id`, `date`, `per_page` (max 100)

#### POST /api/schedules
Create schedule.

**Auth**: `resources.manage`  
**Body**: `{ "resource_id": int, "date": "date", "start_time": "HH:MM", "end_time": "HH:MM", "slot_duration_minutes": int|null, "capacity_per_slot": int|null, "is_active": bool|null, "metadata": object|null }`

#### GET /api/schedules/{id}
Show schedule (includes related resource when loaded).

**Auth**: `resources.view`

#### PUT /api/schedules/{id}
Update schedule.

**Auth**: `resources.manage`

#### DELETE /api/schedules/{id}
Soft-delete schedule.

**Auth**: `resources.manage`

#### GET /api/schedules/{id}/slots
Computed slot list for the schedule.

**Auth**: `resources.view`  
**Response 200**: `{ "data": [ ... ] }`

---

### Routes (logistics)

Versioned route definitions (waypoints JSON). Each update appends a `route_versions` row with prior snapshot.

#### GET /api/routes
List routes.

**Auth**: `resources.view`  
**Query Params**: `status`, `per_page` (max 100)

#### POST /api/routes
Create route (creates initial version 1).

**Auth**: `resources.manage`  
**Body**: `{ "name": "string", "description": "string|null", "waypoints": array|null, "metadata": object|null }`

#### GET /api/routes/{id}
Show route with versions.

**Auth**: `resources.view`

#### PUT /api/routes/{id}
Update route (creates next version).

**Auth**: `resources.manage`  
**Body**: `{ "name", "description", "waypoints", "metadata", "status", "change_reason" }` (partial updates supported via validation rules)

#### DELETE /api/routes/{id}
Soft-delete route.

**Auth**: `resources.manage`

#### GET /api/routes/{id}/versions
List versions newest-first.

**Auth**: `resources.view`

---

### Route packages

Curated bundles of route IDs for publishing to target groups. Lifecycle: `draft` → `published` → `archived`. Only `draft` packages may be edited.

#### GET /api/packages
List packages.

**Auth**: `resources.view`  
**Query Params**: `status`, `per_page` (max 100)

#### POST /api/packages
Create draft package.

**Auth**: `resources.manage`  
**Body**: `{ "name": "string", "description": "string|null", "route_ids": [int, ...], "target_group": "string|null", "metadata": object|null }`

#### GET /api/packages/{id}
Show package.

**Auth**: `resources.view`

#### PUT /api/packages/{id}
Update draft package only.

**Auth**: `resources.manage`

#### POST /api/packages/{id}/publish
Publish draft (sets `published_by`, `published_at`).

**Auth**: `resources.manage`

#### POST /api/packages/{id}/archive
Archive published package.

**Auth**: `resources.manage`

---

### Field placements

Learner assignments to locations. Listing is scoped: non-admin/non-planner users only see placements where they are `assigned_by`. Object-level rules in `AuthorizesRecordAccess` further restrict show/update/cancel for reviewers and field agents.

#### GET /api/placements
List placements.

**Auth**: `placements.view`  
**Query Params**: `learner_id`, `location_id`, `status`, `per_page` (max 100)

#### POST /api/placements
Create placement (`status` starts as `pending`).

**Auth**: `placements.manage`  
**Body**: `{ "learner_id": int, "location_id": int, "start_date": "date", "end_date": "date|null", "notes": "string|null", "metadata": object|null }`

#### GET /api/placements/{id}
Show placement.

**Auth**: `placements.view`

#### PUT /api/placements/{id}
Update placement.

**Auth**: `placements.manage`  
**Body**: `{ "status": "pending|active|completed|cancelled|null", "start_date", "end_date", "notes" }`

#### POST /api/placements/{id}/cancel
Cancel placement (idempotent guard if already cancelled).

**Auth**: `placements.manage`  
**Body**: `{ "notes": "string|null" }`

---

### Bookings

#### GET /api/bookings
List bookings.

**Auth**: `bookings.view`  
**Query Params**: `resource_id`, `learner_id`, `status`, `date`, `per_page`

#### POST /api/bookings
Create provisional hold (5-minute expiry).

**Auth**: `bookings.create`  
**Body**: `{ "resource_id": int, "learner_id": int, "start_time": "datetime", "end_time": "datetime", "idempotency_key": "string|null" }`  
**Validation**: 15-min increments, 2-hour lead time, capacity check

#### GET /api/bookings/{id}
Show booking.

**Auth**: `bookings.view`

#### POST /api/bookings/{id}/confirm
Confirm provisional hold.

**Auth**: `bookings.create`

#### POST /api/bookings/{id}/cancel
Cancel booking (auto-detects late cancel within 24 hours).

**Auth**: `bookings.cancel`  
**Body**: `{ "notes": "string|null" }`

#### PUT /api/bookings/{id}/reschedule
Reschedule confirmed booking.

**Auth**: `bookings.update`  
**Body**: `{ "start_time": "datetime", "end_time": "datetime" }`  
**Constraint**: Must be 24+ hours before original start

#### GET /api/waitlist
List waitlist entries.

**Auth**: `bookings.view`  
**Query Params**: `resource_id`, `status`, `per_page`

#### POST /api/waitlist
Add to waitlist.

**Auth**: `bookings.create`  
**Body**: `{ "resource_id": int, "learner_id": int, "start_time": "datetime", "end_time": "datetime" }`

#### POST /api/waitlist/{id}/accept
Accept waitlist offer (creates confirmed booking).

**Auth**: `bookings.create`

---

### Locations

#### GET /api/locations
List locations (coordinates based on role).

**Auth**: `locations.view`  
**Query Params**: `type`, `is_active`, `search`, `per_page`

#### POST /api/locations
Create location.

**Auth**: `locations.manage`  
**Body**: `{ "name": "string", "type": "string", "description": "string|null", "address": "string|null", "display_address": "string|null", "latitude": float, "longitude": float, "is_active": bool, "metadata": object|null }`

#### GET /api/locations/nearby
Find locations within radius, sorted by distance.

**Auth**: `locations.view`  
**Query Params**: `latitude` (required), `longitude` (required), `radius_km` (default 10)

#### GET /api/locations/{id}
Get single location.

**Auth**: `locations.view`

#### PUT /api/locations/{id}
Update location.

**Auth**: `locations.manage`

#### DELETE /api/locations/{id}
Soft-delete location.

**Auth**: `locations.manage`

#### GET /api/locations/{id}/geofence
Check if point is within location's geofence.

**Auth**: `locations.view`  
**Query Params**: `latitude`, `longitude`, `radius_km` (default 10)  
**Response**: `{ "within_geofence": bool, "distance_km": float }`

---

### Security Training

#### GET /api/exercises
List exercises.

**Auth**: `exercises.view`  
**Query Params**: `type`, `difficulty`, `is_published`, `per_page`

#### POST /api/exercises
Create exercise.

**Auth**: `exercises.manage`

#### GET /api/exercises/{id}
Show exercise.

**Auth**: `exercises.view`

#### PUT /api/exercises/{id}
Update exercise.

**Auth**: `exercises.manage`

#### GET /api/cohorts
List cohorts.

**Auth**: `exercises.view`

#### POST /api/cohorts
Create cohort.

**Auth**: `exercises.manage`

#### POST /api/cohorts/assign
Publish exercise assignment to cohort members.

**Auth**: `exercises.manage`  
**Body**: `{ "cohort_id": int, "security_exercise_id": int, "learner_ids": [int], "due_at": "datetime|null" }`

#### GET /api/attempts
List exercise attempts.

**Auth**: `exercises.view`  
**Query Params**: `security_exercise_id`, `learner_id`, `cohort_id`, `status`, `per_page`

#### POST /api/attempts
Start an exercise attempt.

**Auth**: `exercises.attempt`  
**Body**: `{ "security_exercise_id": int, "learner_id": int, "cohort_id": int|null }`

#### GET /api/attempts/{id}
Show attempt with action trail.

**Auth**: `exercises.view`

#### POST /api/attempts/{id}/action
Record action in attempt trail.

**Auth**: `exercises.attempt`  
**Body**: `{ "action": "string", "data": object|null }`

#### POST /api/attempts/{id}/submit
Submit and auto-score attempt.

**Auth**: `exercises.attempt`  
**Body**: `{ "answers": object|null }`

---

### Audit

#### GET /api/audit
Query audit events.

**Auth**: `audit.view`  
**Query Params**: `event_type`, `entity_type`, `entity_id`, `actor_id`, `from`, `to`, `per_page`

#### GET /api/audit/verify
Verify audit hash chain integrity.

**Auth**: `audit.view`  
**Query Params**: `limit`  
**Response**: `{ "total_events": int, "valid_links": int, "invalid_links": int, "chain_intact": bool, "errors": [] }`

#### GET /api/audit/{id}
Show single audit event.

**Auth**: `audit.view`

#### GET /api/audit/entity/{entityType}/{entityId}
Get full audit trail for a specific entity.

**Auth**: `audit.view`

---

### Reports

#### GET /api/reports
List report definitions.

**Auth**: `reports.view`  
**Query Params**: `type`, `per_page`

#### POST /api/reports
Create report definition.

**Auth**: `reports.manage`  
**Body**: `{ "name": "string", "description": "string|null", "type": "learners|enrollments|bookings|audit", "filters": object|null, "columns": ["string"]|null, "output_format": "csv|json", "metadata": object|null }`

#### GET /api/reports/{id}
Show report definition.

**Auth**: `reports.view`

#### PUT /api/reports/{id}
Update report definition.

**Auth**: `reports.manage`

#### DELETE /api/reports/{id}
Soft-delete report definition.

**Auth**: `reports.manage`

#### POST /api/reports/{id}/generate
Generate export file.

**Auth**: `reports.manage`  
**Response**: `{ "data": { "report_id": int, "format": "csv|json", "path": "string", "row_count": int, "generated_at": "datetime" } }`

#### GET /api/reports/{id}/download
Download generated export file.

**Auth**: `reports.view`  
**Response**: File download (CSV or JSON)

---

### Analytics

Read-only aggregates for back-office dashboards. Same permission as viewing reports.

#### GET /api/analytics/overview
Counts: learners, active enrollments, confirmed bookings, active placements, pending approvals.

**Auth**: `reports.view`  
**Response 200**: `{ "data": { ... } }`

#### GET /api/analytics/enrollments
Enrollment totals and counts grouped by `status`.

**Auth**: `reports.view`  
**Query Params**: `date_from`, `date_to` (optional, filter on `enrollments.created_at`)

#### GET /api/analytics/bookings
Booking totals and counts grouped by `status`.

**Auth**: `reports.view`  
**Query Params**: `date_from`, `date_to` (optional, filter on `bookings.start_time`), `resource_id` (optional)

#### GET /api/analytics/placements
Placement totals; counts by `status` and by `location_id`.

**Auth**: `reports.view`

#### GET /api/analytics/operations
Combined operational snapshot: enrollment pipeline, booking utilization, placement coverage, approval queue counts.

**Auth**: `reports.view`

---

## Role-Permission Matrix

| Domain | admin | planner | reviewer | field_agent |
|--------|-------|---------|----------|-------------|
| User management | full (manage + view) | view | view | - |
| Learners | full | full | view | create/view/update |
| Learner import / duplicates | yes / yes | yes / yes | - | - |
| Enrollments | full | create/view/update/cancel | view/approve | create/view |
| Bookings | full | full | view | create/view/update |
| Resources / schedules / routes / packages | full | full | view | view |
| Field placements | full | full | - | view/manage (scoped) |
| Locations (precise) | yes | yes | no | no |
| Locations (obfuscated) | - | - | yes | yes |
| Exercises | full | view | view | view/attempt |
| Reports & analytics | full | full | view | - |
| Audit trail | yes | - | yes | - |
