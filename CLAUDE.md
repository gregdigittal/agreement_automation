# CCRS — Contract & Compliance Review System

## Project Overview
Laravel 12 + Filament 3 application for contract lifecycle management.
Deployed to Kubernetes sandbox at https://ccrs-sandbox.digittal.mobi

## Stack
- **Backend**: PHP 8.3, Laravel 12, Filament 3
- **Database**: MySQL 8.0 (sidecar in sandbox, operator in production)
- **Frontend**: Tailwind CSS v4 (via Vite plugin — no tailwind.config.js or postcss.config.js)
- **Runtime**: nginx + PHP-FPM via supervisord in Docker

---

## CRITICAL — READ THIS FIRST

### Files You MUST NOT Touch
The following files are **owned by the CTO** and control the sandbox infrastructure.
**Do NOT modify, delete, replace, or rewrite any of these files under any circumstances:**

| File/Directory | Purpose |
|---|---|
| `Jenkinsfile` | CI/CD pipeline — builds Docker, deploys to K8s, sends email alerts |
| `deploy/k8s/*` | ALL Kubernetes manifests — deployment, services, ingresses, PVC, phpMyAdmin |
| `Dockerfile` | Multi-stage Docker build for production image |
| `docker/` | All Docker config — entrypoint, nginx, supervisord, php.ini, opcache |
| `docker-compose.yml` | Local development environment with MySQL + phpMyAdmin |
| `.github/` | Do NOT create GitHub Actions workflows — Jenkins handles CI/CD |
| `CLAUDE.md` | This file — do NOT delete or modify |

**Why**: The sandbox runs on a specific Hetzner K8s cluster with:
- A MySQL sidecar container (not external MySQL)
- A phpMyAdmin sidecar for database access
- Cloudflare SSL termination (no TLS in K8s)
- A specific Docker registry (repo-de.digittal.mobi)
- Jenkins-based CI/CD with email notifications
- PersistentVolumeClaim with `local-path` StorageClass

If you rewrite these files for a "production" pattern (external MySQL, Redis, HPA, queue workers, AI workers, etc.), **it will break the sandbox** because none of that infrastructure exists.

### What To Do If You Need Infrastructure Changes
Describe what you need in a comment or message. The CTO will make the change. Examples:
- "I need a Redis sidecar for caching" → CTO adds it to deployment.yaml
- "I need a new environment variable" → CTO adds it to the K8s deployment
- "I need a different domain" → CTO sets up DNS + ingress

### Do NOT Create These Files
- `deploy/k8s/ai-worker.yaml` — no AI worker infrastructure exists
- `deploy/k8s/queue-worker.yaml` — no separate queue worker in sandbox
- `deploy/k8s/hpa.yaml` — no HPA in sandbox (single node)
- `deploy/k8s/pdb.yaml` — no PDB needed (1 replica)
- `deploy/k8s/secrets.yaml.template` — secrets are created by Jenkinsfile
- `.github/workflows/*.yml` — Jenkins handles CI/CD, not GitHub Actions

---

## Sandbox Environment Details

### Architecture (DO NOT CHANGE)
The sandbox pod runs 3 containers:
1. **App container** — Laravel + Filament (port 8080)
2. **MySQL sidecar** — MySQL 8.0 (port 3306, localhost access only)
3. **phpMyAdmin sidecar** — Database management UI (port 8888)

### URLs
- **App**: https://ccrs-sandbox.digittal.mobi
- **phpMyAdmin**: https://ccrs-pma-sandbox.digittal.mobi (root / ccrs-root-pass)

### Database Config (MUST USE THESE)
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ccrs
DB_USERNAME=ccrs
DB_PASSWORD=ccrs-sandbox-pass
```

### Cache / Session / Queue (MUST USE THESE)
```
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```
Do NOT switch to Redis — there is no Redis instance in the sandbox.

### Deployment
Push to `main` → Jenkins auto-builds and deploys within ~2 minutes.
Email notifications are sent to greg@digittal.io and mike@digittal.io.

---

## Local Development
```bash
docker-compose up --build
# App: http://localhost:8080
# phpMyAdmin: http://localhost:8888 (root / root)
```

## Health Checks (REQUIRED)
These endpoints MUST exist and return correctly — K8s probes depend on them:
- `GET /health` → return 200 with body `ok`
- `GET /health/ready` → optional readiness check

## Testing
- Write unit tests for models and services
- Write feature tests for HTTP endpoints and Filament pages
- Run tests: `php artisan test`

## Code Conventions
- Follow Laravel conventions for naming, structure, and patterns
- Use Filament components for admin UI
- All database changes must have migrations
- Keep routes in `routes/web.php` and `routes/api.php`
