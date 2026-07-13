# Okapi Leads

A lead-management system for a solar-leasing company. Leads arrive via an API,
are qualified against business rules in the background, trigger internal + customer
notifications, and are managed by admins through a dashboard — with a full audit trail
of every change.

Built as a take-home assessment for **Okapi Technologies**.

---

## Contents

- [Overview](#overview)
- [Tech stack](#tech-stack)
- [Architecture](#architecture)
- [Business rules](#business-rules-qualification)
- [Duplicate detection](#duplicate-detection)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Using the system](#using-the-system)
- [Testing](#testing)
- [Deployment plan](#deployment-plan)
- [Key decisions & trade-offs](#key-decisions--trade-offs)

---

## Overview

The flow of a lead through the system:

```
POST /api/leads ──▶ 202 Accepted (instantly)
                      │
                      └─▶ ProcessLeadJob (queued, background)
                            ├─ qualify → LeadStatus
                            ├─ persist (DB transaction, dedup-guarded)
                            └─ notify (internal + customer emails)

Admins ──▶ /admin (Filament) ── view · filter by status · change status
                      │
                      └─ every create/change ──▶ activity_log (audit)
```

The API returns **202 immediately** and does all real work in a background job, so bursts
of submissions never block the caller.

## Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 11 | |
| Language | PHP 8.3 | `declare(strict_types=1)` in every file |
| Database | PostgreSQL 16 | |
| Admin UI | Filament 3 | list / filter / manual status change |
| Queue | `database` driver (dev) | background lead processing; Redis + Horizon in prod |
| Mail | `log` driver | emails render to `storage/logs/laravel.log` (nothing actually sent) |
| Audit | `spatie/laravel-activitylog` | tracks status + customer-data changes |

## Architecture

Code is organised in **MVCS** layers — MVC plus a dedicated **Service** layer — so
controllers stay thin and business logic lives in one findable place.

| Layer | Classes | Responsibility |
|---|---|---|
| **Controller** | `LeadIngestionController`, `StoreLeadRequest` | HTTP: validate → 202 → dispatch. No business logic. |
| **Service** | `ProcessLeadJob`, `LeadQualificationService` | Orchestration + the qualification rules. |
| **Model** | `Lead`, `User`, `Activity` + enums | Eloquent (Active Record) + domain vocabulary. |
| **View** | `LeadStatusInternal` / `LeadStatusCustomer` (+ Blade), `LeadResource` (Filament) | The two emails and the admin screens. |

**Why the logic isn't in the controller:** ingestion is asynchronous — the controller
returns `202` and exits, and the real work runs *later* in a queued job. The qualification
logic therefore lives in a service that the **job** calls, not the controller. This also
keeps the rules pure and trivially unit-testable.

Both entry points (the public API and the admin panel) funnel through the same `Lead`
model, and both leave an `activity_log` trail.

## Business rules (qualification)

A lead carries: customer name, email, phone, monthly electricity bill (RM), property type,
roof type, and a Malaysian state. `LeadQualificationService::resolve()` maps those to a status:

**Hard rules** (all must pass to qualify):
- Monthly bill **≥ RM200**
- Property type is **landed** or **commercial** (condo/apartment excluded)
- Roof is **not flat**
- State is in **Peninsular Malaysia** (Sabah, Sarawak, Labuan excluded)

**Status resolution:**

| Condition (assuming the non-bill rules pass) | Status |
|---|---|
| bill ≥ RM200 | `qualified` |
| bill RM150–199 | `under_review` (a human should look) |
| bill < RM150 | `disqualified` |
| any non-bill hard rule fails | `disqualified` |

**Precedence decision:** hard disqualifiers (property, roof, state, bill < RM150) are
checked **first**, so they beat the review band. A flat-roof lead with an RM160 bill is
`disqualified`, **not** `under_review`. The review band only applies when nothing else has
already failed. This ordering is deliberate and is the single source of truth for the rules.

## Duplicate detection

Email alone is **not** a unique key — one landlord can legitimately submit several leads for
different properties. A lead's identity is **who + what kind of property + where**:

```sql
UNIQUE (email, phone, property_type, roof_type, state)   -- "leads_identity_unique"
```

A genuinely different property differs on at least one of type/roof/state; an accidental
resubmit of the same property collides and is rejected.

**Enforcement — the DB constraint is the only atomic guarantee.** Because ingestion is
asynchronous (202 now, insert later in the job), any read-then-write pre-check — a FormRequest
`unique` rule, a lookup service, even `firstOrCreate` — is a **TOCTOU race**: two burst
duplicates both pass the check before either row commits. So we enforce at the database and
**handle the violation in the job**: attempt the insert, catch `QueryException` with Postgres
SQLSTATE **`23505`** (`unique_violation`) → log and skip (no duplicate emails). Any other DB
error is re-thrown so the queue can retry it.

## Getting started

### Option A — Docker (recommended, one command)

Requires only **Docker** (Docker Desktop + WSL integration on Windows/WSL). No local PHP,
Postgres, or Composer needed.

```bash
git clone <repo-url> && cd okapi-leads
docker compose up --build          # builds the image; starts db + app + worker; migrates on boot
```

Then create an admin login, and load demo data by firing the requests in **`leads.http`**
(they POST through the real API — no separate seeder):

```bash
docker compose exec app php artisan make:filament-user   # create an admin for /admin
# then open leads.http and send the requests to http://localhost:8000
# (the worker container processes them; ~15 leads across all statuses)
```

- API: `POST http://localhost:8000/api/leads`
- Admin: `http://localhost:8000/admin`
- Demo data: **`leads.http`** (VS Code REST Client / JetBrains HTTP client) — 15 leads plus
  a duplicate and an invalid request to demonstrate dedup and validation.
- The containerized Postgres is separate from any local Postgres; external tools (e.g. DBeaver)
  can reach it at `localhost:5433`.

> `docker-compose.yml` is for **local development only** — see [Deployment plan](#deployment-plan)
> for production.

### Option B — Local (WSL + native Postgres)

Prerequisites: PHP 8.3 with `intl`, `zip`, `gd`, `pdo_pgsql`; PostgreSQL 16; Composer.

```bash
# 1. install PHP deps
composer install

# 2. create the database
sudo -u postgres psql -c "CREATE DATABASE okapi_leads;"
sudo -u postgres psql -c "CREATE USER okapi WITH PASSWORD 'okapi';"
sudo -u postgres psql -c "ALTER DATABASE okapi_leads OWNER TO okapi;"

# 3. configure env
cp .env.example .env
php artisan key:generate
#   set DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=5432,
#       DB_DATABASE=okapi_leads, DB_USERNAME=okapi, DB_PASSWORD=okapi
#       QUEUE_CONNECTION=database, MAIL_MAILER=log

# 4. migrate + create an admin
php artisan migrate
php artisan make:filament-user
```

Run **three processes** while developing:

```bash
php artisan serve                 # API + /admin
php artisan queue:work            # background lead processing (RESTART after code changes)
tail -f storage/logs/laravel.log  # "sent" emails + duplicate-skip logs
```

**Load demo data** by firing the requests in **`leads.http`** (needs `serve` + `queue:work`
running) — they ingest ~15 leads through the real API. There is no lead seeder.

## Configuration

| Variable | Purpose | Default |
|---|---|---|
| `APP_KEY` | app encryption key (sessions/cookies). **Injected via env, never baked into the image.** | — (generate with `key:generate`) |
| `DB_*` | Postgres connection | `okapi_leads` / `okapi` / `okapi` |
| `QUEUE_CONNECTION` | queue driver | `database` |
| `MAIL_MAILER` | mail transport | `log` |
| `LEADS_INTERNAL_RECIPIENT` | team inbox for the internal lead email | `leads@okapi-solar.test` |

`LEADS_INTERNAL_RECIPIENT` is read via `config('mail.internal_recipient')` — never `env()`
directly, which returns `null` once `php artisan config:cache` runs in production.

## Using the system

### Ingestion API

`POST /api/leads` — send `Accept: application/json`.

```json
{
  "customer_name": "Ahmad bin Ismail",
  "email": "ahmad@example.com",
  "phone": "012-3456789",
  "monthly_bill_rm": 350,
  "property_type": "landed",
  "roof_type": "tile",
  "state": "Selangor"
}
```

**Response — `202 Accepted`** (returned instantly, before processing):

```json
{ "message": "Lead received and is being processed." }
```

Invalid input returns **`422`** with per-field errors. Enum fields are validated strictly:

| Field | Allowed values |
|---|---|
| `property_type` | `landed`, `condo`, `apartment`, `commercial` |
| `roof_type` | `tile`, `metal`, `flat`, `concrete` |
| `state` | the 16 Malaysian states/territories, exact canonical spelling (e.g. `Pulau Pinang`, not `Penang`) |

### Admin dashboard

`/admin` (Filament) — two screens:

- **Leads** — view all leads with colored status badges, **filter by status**, and **manually
  change a lead's status** (or edit customer data). Every change is audited.
- **Activity Log** — a **read-only** audit view listing each change: *when*, *action*
  (updated/deleted), *which lead*, *changed by*, and the *old → new* diff, with a detail view.

### Notifications

Two emails per new lead, rendered to `storage/logs/laravel.log` (log driver):

- **Internal** — lead details + status, for the team.
- **Customer** — warm and status-aware; a `disqualified` lead reads gently ("not a match
  just yet… happy to suggest alternatives"), never as a rejection.

### Audit trail

Requirement 6 is about *changes*, so the audit records **updates and deletions only** — lead
**creation is not logged**. Each `updated` / `deleted` event writes an `activity_log` row (field,
old → new, when, causer) via `spatie/laravel-activitylog` (`$recordEvents` on the `Lead` model).
Because leads only ever change through the Filament admin, **every audit row is attributed to the
logged-in admin**. Browse them on the Activity Log page.

## Testing

**Manual test plan** — the whole system can be exercised by hand; each requirement maps to a
verifiable scenario (ingestion 202, validation, async processing, qualification + precedence,
duplicate handling, notifications, dashboard, audit, Docker onboarding). See the manual test
plan for the exact commands and expected results.

**Automated tests** live in `tests/Feature` and cover the parts that matter most:

```bash
php artisan test
```

- **Qualification** — `LeadQualificationService::resolve()` across every rule and boundary
  (RM149/150/199/200) plus the flat-roof precedence case. Pure function, no HTTP/DB needed.
- **Duplicate detection** — the `23505` path skips gracefully; a different property from the
  same person is not a duplicate.
- **Ingestion** — the endpoint returns `202` and dispatches the job; bad input returns `422`.

## Deployment plan

> **`docker-compose.yml` is for local development only.** Production differs as described below.
> This section is a plan — nothing here is deployed.

**Target:** a single **GCP VM** with **native PostgreSQL** on the host (or a managed DB such as
Cloud SQL for backups/HA).

**Pipeline (push to `main` → live):**

1. **CI** (GitHub Actions / Cloud Build) builds the app image from the `Dockerfile`, tags it with
   the git SHA, and **pushes to Artifact Registry**.
2. A deploy step SSHes into the VM, runs `docker pull <image>:<sha>`, then:
   - `php artisan migrate --force` — a single controlled release step (not per-container boot),
   - restarts **both** the app and the queue **worker** containers (the worker caches code in
     memory — it must restart, or use `php artisan queue:restart`).

**Configuration & secrets:**
- `APP_KEY`, DB credentials, and mail credentials come from the **VM's env / a secrets manager**,
  never committed and never baked into the image. The image is environment-agnostic.
- Container → native Postgres: point `DB_HOST` at the host (`--network host`, or
  `host.docker.internal` via `--add-host`).

**Production differences from the local stack:**
- **Web:** php-fpm + nginx (or Octane), **not** `php artisan serve`.
- **Queue:** **Redis + Horizon** instead of the `database` driver, for throughput and monitoring.
- **Mail:** a real transport (SES / Postmark), not the `log` driver.
- **Config:** `APP_DEBUG=false`, `APP_ENV=production`, `php artisan config:cache`.
- **Monitoring/logging:** centralised logs (Cloud Logging), Horizon dashboard for queue health,
  uptime checks on `/up` (the built-in health endpoint), alerting on failed jobs.

## Key decisions & trade-offs

- **202 + background job** — ingestion accepts fast and defers all work, so bursts never block.
- **Rules in one pure service** — `LeadQualificationService` has zero framework/DB dependencies,
  so the graded logic is easy to find, change, and unit-test.
- **Dedup at the DB, handled in the job** — the unique constraint is the only race-free guarantee;
  a pre-check would be a TOCTOU race under bursts.
- **Precedence: hard fails beat the review band** — documented and tested.
- **Strict `MalaysianState` enum** — trades input flexibility for clean data and a reliable
  Sabah/Sarawak exclusion (rejects `"Penang"`, wants `"Pulau Pinang"`).
- **`APP_KEY` injected, never baked** — same model locally (compose env) and in prod (host env);
  rebuilds don't churn the key.
- **Enums implement Filament `HasLabel`/`HasColor`** — keeps display vocabulary with the enum and
  the resource DRY, at the cost of a light UI-framework dependency on the domain enums.
- **Audit records changes only** (`updated` + `deleted`, not creation) — requirement 6 is about
  *changes*, so lead creation isn't logged; every audit row is an admin-attributed change.
- **Demo data via `leads.http`, not a seeder** — leads are ingested through the real endpoint, so
  the demo exercises the full pipeline (validation → qualification → persist) instead of inserting
  pre-set rows; the file doubles as manual API test cases.
