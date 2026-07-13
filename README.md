# Okapi Leads

A lead management system for a solar leasing company. Leads come in through an API, get
qualified against business rules in the background, trigger notification emails, and are
managed by admins through a dashboard. Every change an admin makes is recorded in an audit log.

Built as a take home assessment for **Okapi Technologies**.

## Contents

- [Overview](#overview)
- [Tech stack](#tech-stack)
- [Architecture](#architecture)
- [Business rules](#business-rules-qualification)
- [Duplicate detection](#duplicate-detection)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Using the system](#using-the-system)
- [Deployment plan](#deployment-plan)
- [Key decisions and trade-offs](#key-decisions-and-trade-offs)

## Overview

How a lead moves through the system:

```
POST /api/leads  ->  202 Accepted (returned instantly)
                       |
                       └─ ProcessLeadJob (runs in the background)
                            1. qualify  -> LeadStatus
                            2. save      (in a DB transaction, duplicate-guarded)
                            3. notify    (internal + customer emails)

Admins  ->  /admin (Filament):  view leads, filter by status, change a status
                       |
                       └─ each change is written to the activity log
```

The API returns **202 immediately** and does the real work in a background job, so a burst of
submissions never blocks the caller.

## Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 11 | |
| Language | PHP 8.3 | `declare(strict_types=1)` in every file |
| Database | PostgreSQL 16 | |
| Admin UI | Filament 3 | list, filter by status, change status |
| Queue | `database` driver | background lead processing (Redis + Horizon in production) |
| Mail | `log` driver | emails are written to `storage/logs/laravel.log`, nothing is actually sent |
| Audit | `spatie/laravel-activitylog` | records status and customer-data changes |

## Architecture

The code follows **MVCS**: the usual MVC plus a **Service** layer, so controllers stay thin
and the business logic sits in one place that is easy to find.

| Layer | Classes | Responsibility |
|---|---|---|
| **Controller** | `LeadIngestionController`, `StoreLeadRequest` | HTTP only: validate, return 202, hand off to the job |
| **Service** | `ProcessLeadJob`, `LeadQualificationService` | Orchestration and the qualification rules |
| **Model** | `Lead`, `User`, `Activity`, enums | Eloquent models and the domain vocabulary |
| **View** | `LeadStatusInternal` / `LeadStatusCustomer` mailables, `LeadResource` (Filament) | The two emails and the admin screens |

**Why the logic is not in the controller.** Ingestion is asynchronous: the controller returns
202 and exits, and the actual work happens later in a queued job. So the qualification rules
live in a service that the job calls, not in the controller. The API and the admin panel both go
through the same `Lead` model, and both leave an entry in the activity log.

## Business rules (qualification)

A lead has: customer name, email, phone, monthly electricity bill (RM), property type, roof
type, and a Malaysian state. `LeadQualificationService::resolve()` turns those into a status.

**Hard rules** (all must pass to qualify):

- Monthly bill is **RM200 or more**
- Property type is **landed** or **commercial** (condo and apartment are out)
- Roof is **not flat**
- State is in **Peninsular Malaysia** (Sabah, Sarawak, and Labuan are out)

**Status resolution** (assuming the non-bill rules all pass):

| Bill | Status |
|---|---|
| RM200 or more | `qualified` |
| RM150 to RM199 | `under_review` (a human should look) |
| below RM150 | `disqualified` |
| any non-bill hard rule fails | `disqualified` |

**Precedence.** The hard disqualifiers (property, roof, state, bill below RM150) are checked
**first**, so they win over the review band. A lead with a flat roof and an RM160 bill is
`disqualified`, not `under_review`. The review band only applies when nothing else has already
failed. This ordering is deliberate.

## Duplicate detection

Email alone is not a unique key, because one landlord can legitimately submit several leads for
different properties. A lead's identity is **who + what kind of property + where**:

```sql
UNIQUE (email, phone, property_type, roof_type, state)   -- "leads_identity_unique"
```

A genuinely different property differs on at least one of type, roof, or state. An accidental
resubmit of the same property collides and is rejected.

**The database constraint is the real guard.** Because ingestion is asynchronous (202 now, the
row is inserted later in the job), any "check first, then insert" approach has a race condition:
two duplicate requests in the same burst can both pass the check before either row is saved. So
the rule is enforced by the unique constraint, and the job handles the failure: it attempts the
insert and catches the Postgres unique-violation error (SQLSTATE `23505`), then logs it and skips
(no duplicate emails). Any other database error is re-thrown so the queue can retry it.

## Getting started

### Option A: Docker (recommended, one command)

Only **Docker** is needed (Docker Desktop with WSL integration on Windows/WSL). No local PHP,
Postgres, or Composer required.

```bash
git clone <repo-url> && cd okapi-leads
docker compose -f docker-compose.local.yml up --build   # starts db + app + worker, migrates on boot
```

Then create an admin login, and load demo data by sending the requests in **`leads.http`**
(they POST through the real API, so there is no separate seeder):

```bash
docker compose -f docker-compose.local.yml exec app php artisan make:filament-user   # admin login
# then open leads.http and send the requests to http://localhost:8000
# (the worker container processes them: about 15 leads across all statuses)
```

- API: `POST http://localhost:8000/api/leads`
- Admin: `http://localhost:8000/admin`
- Demo data: **`leads.http`** (VS Code REST Client or JetBrains HTTP client), 15 leads plus a
  duplicate and an invalid request to show the dedup and validation behaviour.
- The Postgres in Docker is separate from any local Postgres. External tools like DBeaver can
  reach it at `localhost:5433`.

`docker-compose.local.yml` is for **local development only** (the app and worker read their
config from the committed `.env.docker`). Production is described in the
[Deployment plan](#deployment-plan).

### Option B: Local (WSL + native Postgres)

Prerequisites: PHP 8.3 with `intl`, `zip`, `gd`, `pdo_pgsql`; PostgreSQL 16; Composer.

```bash
# 1. install PHP dependencies
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

# 4. migrate and create an admin
php artisan migrate
php artisan make:filament-user
```

Run **three processes** while developing:

```bash
php artisan serve                 # API + /admin
php artisan queue:work            # background lead processing (restart it after code changes)
tail -f storage/logs/laravel.log  # "sent" emails + duplicate-skip log lines
```

Load demo data by sending the requests in **`leads.http`** (needs `serve` and `queue:work`
running). There is no lead seeder.

## Configuration

| Variable | Purpose | Default |
|---|---|---|
| `APP_KEY` | App encryption key (used for sessions and cookies) | generate with `php artisan key:generate` |
| `DB_*` | Postgres connection | `okapi_leads` / `okapi` / `okapi` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `MAIL_MAILER` | Mail transport | `log` |
| `LEADS_INTERNAL_RECIPIENT` | Team inbox for the internal lead email | `leads@okapi-solar.test` |

`LEADS_INTERNAL_RECIPIENT` is read through `config('mail.internal_recipient')`, not `env()`
directly, because `env()` returns `null` once `php artisan config:cache` runs in production.

**APP_KEY is different in each environment, and that is expected.** Local development uses the
key in your own `.env` (from `key:generate`). The local Docker setup uses a fixed throwaway key
in the committed `.env.docker`. Production uses a real secret key from the server's environment,
kept out of git. Each environment has its own separate data, so the keys do not need to match.

## Using the system

### Ingestion API

`POST /api/leads`:

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

**Response, `202 Accepted`** (returned instantly, before processing):

```json
{ "message": "Lead received and is being processed." }
```

Invalid input returns **`422`** with per-field errors. The enum fields are validated strictly:

| Field | Allowed values |
|---|---|
| `property_type` | `landed`, `condo`, `apartment`, `commercial` |
| `roof_type` | `tile`, `metal`, `flat`, `concrete` |
| `state` | the 16 Malaysian states and territories, exact spelling (for example `Pulau Pinang`, not `Penang`) |

### Admin dashboard

`/admin` (Filament) has two screens:

- **Leads**: view all leads with colored status badges, filter by status, and manually change a
  lead's status (or edit customer data). Every change is audited.
- **Activity Log**: a read-only view of each change, showing when it happened, the action
  (updated or deleted), which lead, who changed it, and the old-to-new values, with a detail view.

### Notifications

Two emails are sent per new lead. With the `log` mailer they are written to
`storage/logs/laravel.log` instead of actually being sent.

- **Internal**: lead details and status, for the team.
- **Customer**: warm and status-aware. A `disqualified` lead reads gently ("not a match just
  yet, here are some alternatives"), never as a flat rejection.

### Audit trail

Requirement 6 is about *changes*, so the audit records **updates and deletions only**. Lead
**creation is not logged**. Each `updated` or `deleted` event writes an `activity_log` row (the
field, its old and new values, when, and who) through `spatie/laravel-activitylog`, configured by
`$recordEvents` on the `Lead` model. Because leads only ever change through the admin panel, every
audit row is attributed to the logged-in admin. You can browse them on the Activity Log page.

## Deployment plan

> This section is a plan. Nothing here is deployed. `docker-compose.local.yml` is for local
> development only; production uses `docker-compose.prod.yml` as described below.

**Target.** One **GCP VM** running Docker, with **PostgreSQL** and **Redis** installed on the same
VM (or the managed equivalents, Cloud SQL and Memorystore). Postgres holds the permanent data;
Redis holds the job queue.

### 1. One-time server setup (on the VM)

Install Docker, Postgres, Redis, and nginx, then create the database and user:

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin postgresql redis-server nginx
sudo systemctl enable --now postgresql redis-server nginx

sudo -u postgres psql -c "CREATE DATABASE okapi_leads;"
sudo -u postgres psql -c "CREATE USER okapi WITH PASSWORD '<strong-password>';"
sudo -u postgres psql -c "ALTER DATABASE okapi_leads OWNER TO okapi;"
```

nginx is the reverse proxy: it terminates TLS and forwards requests to the app container (Octane)
on `127.0.0.1:8000`. A minimal server block:

```nginx
server {
    listen 443 ssl;
    server_name leads.example.com;
    # ssl_certificate ... (from certbot / your provider)

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Place the production `.env` on the VM (kept out of git), pointing at the local services and the
real mail provider:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<a real secret key, generated once>

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=okapi_leads
DB_USERNAME=okapi
DB_PASSWORD=<strong-password>

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

MAIL_MAILER=resend
RESEND_KEY=<resend-api-key>
```

### 2. Build and push (CI, on push to `main`)

CI (GitHub Actions or Cloud Build) builds the image from the `Dockerfile`'s **`production`**
stage, which adds Laravel Octane and the Swoole extension:

```bash
docker build --target production -t <registry>/okapi-leads:<sha> .
```

It tags the image with the git commit SHA, pushes it to Artifact Registry, then connects to the VM
over SSH and runs the deploy steps below.

### 3. Deploy on the VM (after the image is pushed)

```bash
# a. pull the new image
docker pull <registry>/okapi-leads:<sha>

# b. run migrations once, using a throwaway container against the host's Postgres
docker run --rm --network host --env-file /opt/okapi/.env \
  <registry>/okapi-leads:<sha> php artisan migrate --force

# c. (re)start the long-running containers with the new image
docker compose -f docker-compose.prod.yml up -d
```

`docker-compose.prod.yml` (committed in the repo, copied to `/opt/okapi/` on the VM) holds the run
commands. It defines two containers from the same image, both on host networking so they reach
Postgres and Redis on the VM, and both reading secrets from the VM `.env`:

- **app**: caches config and serves HTTP with Octane (`php artisan config:cache && php artisan
  octane:start --server=swoole`).
- **horizon**: caches config and runs `php artisan horizon`, which manages the queue workers that
  read jobs from Redis. This replaces `queue:work` from local dev.

Both use `restart: unless-stopped`, which is what keeps them (Horizon especially) running across
crashes and reboots without being started by hand. Step (c) recreates them on the new image, so a
deploy restarts both on the new code.

### Why Redis and Horizon, next to Postgres

In development the queue uses the `database` driver, which stores pending jobs in a `jobs` table
inside Postgres. That is simple, but Postgres is not built for the constant polling a busy queue
does. In production the pending jobs move to **Redis**, an in-memory store that hands jobs to
workers very quickly. Redis does not replace Postgres: Postgres stays the permanent home for
leads, users, and the audit log, while Redis only holds the jobs that are waiting to run.
**Horizon** is Laravel's manager for Redis queues; it runs and auto-scales the worker processes.
In short: Postgres stores the data, Redis holds the job queue, and Horizon runs the workers.

### Why Octane and nginx

Locally the app runs `php artisan serve`, which boots the whole framework fresh on every request.
In production the app runs **Laravel Octane** with the **Swoole** runtime instead: Octane boots the
framework once and keeps it in memory, reusing it across requests, which is much faster. Octane can
speak HTTP on its own, but **nginx still sits in front** as the reverse proxy: it terminates TLS
(HTTPS), serves static files, buffers slow clients, and forwards dynamic requests to Octane on port
8000. So the split is: nginx is the public front door, Octane is the fast app server behind it, and
Horizon (separately) runs the queue workers.

### Other differences from the local setup

- **Config:** `APP_DEBUG=false`, `APP_ENV=production`, config cached at container startup.
- **Secrets:** `APP_KEY`, the database password, and `RESEND_KEY` come from the VM `.env` or a
  secrets manager, never committed and never baked into the image.

## Key decisions and trade-offs

- **202 plus a background job.** Ingestion accepts fast and defers all work, so a burst of
  submissions never blocks the caller.
- **Qualification rules in one small service.** All the rules live in `LeadQualificationService`,
  so they are easy to find and change (this is called out as graded).
- **Deduplication at the database, handled in the job.** The unique constraint is the only guard
  that is safe under bursts; a "check first" approach would have a race condition.
- **Precedence: hard fails beat the review band.** A flat-roof RM160 lead is disqualified, not
  under review. This is deliberate.
- **Strict `MalaysianState` enum.** Trades input flexibility for clean data and a reliable
  Sabah/Sarawak exclusion (it rejects `"Penang"` and expects `"Pulau Pinang"`).
- **APP_KEY comes from the environment, never baked into the image.** It is different in local
  dev, local Docker, and production, which is fine because each has its own data.
- **Enums define their own display label and badge color** for the admin UI, so the friendly
  names and status colors are set once on the enum instead of repeated in the dashboard code.
- **Audit records changes only** (`updated` and `deleted`, not creation), since requirement 6 is
  about changes. Every audit row is an admin-attributed change.
- **Demo data via `leads.http`, not a seeder.** The leads are ingested through the real endpoint,
  so the demo exercises the whole pipeline (validation, qualification, save) instead of inserting
  ready-made rows. The file also doubles as manual API test cases.
