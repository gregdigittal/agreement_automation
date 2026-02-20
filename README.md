# CCRS — Contract and Compliance Reporting System

Laravel application for contract lifecycle management, workflows, AI-assisted analysis, and e-signing integration.

## Prerequisites

- **Docker** and **Docker Compose**
- **Azure AD app registration** (for authentication and role mapping)

## Quick start

```bash
cp .env.example .env
# Set APP_KEY: docker compose run --rm app php artisan key:generate
docker compose up --build
```

Then open `http://localhost:8000/admin` (or the port exposed by your Compose setup). You will be redirected to Azure AD login.

## Environment variables

| Variable | Description |
|----------|-------------|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Laravel app config |
| `DB_*` | MySQL connection |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | Use `redis` for queue/cache/session |
| `REDIS_HOST`, `REDIS_PORT` | Redis connection |
| `MAIL_*` | SendGrid (smtp.sendgrid.net, port 587) |
| `AWS_*` | S3 for contract storage |
| `AZURE_AD_*` | Azure AD OAuth2 and group IDs for roles |
| `BOLDSIGN_*` | BoldSign e-signing and webhook HMAC |
| `AI_WORKER_URL`, `AI_WORKER_SECRET` | Python AI worker |
| `ANTHROPIC_API_KEY` | Used by the AI worker |

## Architecture

- **Laravel** — main app, API, and Filament admin
- **Filament** — admin UI and resources
- **MySQL** — primary database
- **Redis** — cache, sessions, queue
- **Laravel Horizon** — queue monitoring (system_admin only)
- **Python AI worker** — contract analysis (see ai-worker/)

Deploy by pushing to `laravel-migration`. Pipeline runs migrate and seed. Production: CTO K8s cluster (digittaldotio/digittal-ccrs).

## Azure AD setup

1. Register an app in Azure Portal. Create client secret; redirect URI: `{APP_URL}/auth/azure/callback`.
2. Scopes: OpenID, profile, email, User.Read, GroupMember.Read.All.
3. Map Azure AD group Object IDs to .env: AZURE_AD_GROUP_SYSTEM_ADMIN, AZURE_AD_GROUP_LEGAL, etc. Users must be in at least one group to log in.

## Kubernetes deployment

Production is deployed via the CTO pipeline. Push to laravel-migration; ensure env vars are set in the cluster.

## Migrating from FastAPI

`apps/api/` and `apps/web/` are the **legacy** FastAPI implementation. The current application is this Laravel stack.

## Running tests

```bash
php artisan test
```

Feature tests: Azure AD auth, BoldSign webhook HMAC, ProcessAiAnalysis job.
