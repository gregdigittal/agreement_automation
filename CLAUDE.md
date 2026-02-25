# CCRS — Contract & Compliance Review System

## Project Overview
Laravel 12 + Filament 3 application for contract lifecycle management.
Deployed to Kubernetes sandbox at https://ccrs-sandbox.digittal.mobi

## Stack
- **Backend**: PHP 8.4, Laravel 12, Filament 3
- **Database**: MySQL 8.0 (sidecar in sandbox, operator in production)
- **Frontend**: Tailwind CSS v4 (via Vite plugin — no tailwind.config.js or postcss.config.js)
- **Runtime**: nginx + PHP-FPM via supervisord in Docker
- **CI/CD**: GitHub Actions (self-hosted runner) — deploys on push to `sandbox` branch

---

## CRITICAL — READ THIS FIRST

### Files You MUST NOT Touch
The following files are **owned by the CTO** and control the sandbox infrastructure.
**Do NOT modify, delete, replace, or rewrite any of these files under any circumstances:**

| File/Directory | Purpose |
|---|---|
| `.github/workflows/deploy.yml` | CI/CD pipeline — builds Docker, deploys to K8s |
| `Jenkinsfile` | Legacy CI/CD (being retired) — do NOT delete or modify |
| `deploy/k8s/*` | ALL Kubernetes manifests — deployment, services, ingresses, PVC, phpMyAdmin |
| `Dockerfile` | Multi-stage Docker build for production image |
| `docker/` | All Docker config — entrypoint, nginx, supervisord, php.ini, opcache |
| `docker-compose.yml` | Local development environment with MySQL + phpMyAdmin |
| `CLAUDE.md` | This file — do NOT delete or modify |

**Why**: The sandbox runs on a specific Hetzner K8s cluster with:
- A MySQL sidecar container (not external MySQL)
- A Redis sidecar for cache/session/queue (localhost:6379)
- A phpMyAdmin sidecar for database access
- Cloudflare SSL termination (no TLS in K8s)
- A specific Docker registry (repo-de.digittal.mobi)
- GitHub Actions CI/CD with self-hosted runner
- PersistentVolumeClaim with `local-path` StorageClass

If you rewrite these files for a "production" pattern (external MySQL, Redis, HPA, queue workers, AI workers, etc.), **it will break the sandbox** because none of that infrastructure exists.

### What To Do If You Need Infrastructure Changes
Describe what you need in a comment or message. The CTO will make the change. Examples:
- "I need a new environment variable" → CTO adds it to the K8s deployment
- "I need a different domain" → CTO sets up DNS + ingress
- "I need a new sidecar container" → CTO adds it to deployment.yaml

### Do NOT Create These Files
- `deploy/k8s/ai-worker.yaml` — no AI worker infrastructure exists
- `deploy/k8s/queue-worker.yaml` — no separate queue worker in sandbox
- `deploy/k8s/hpa.yaml` — no HPA in sandbox (single node)
- `deploy/k8s/pdb.yaml` — no PDB needed (1 replica)
- `deploy/k8s/secrets.yaml.template` — secrets are created by the deploy workflow
- `.github/workflows/*.yml` (other than deploy.yml) — do NOT create additional workflows

---

## Deployment Workflow

**Branch model:**
- `main` — development branch. Push code here freely. **Does NOT trigger a deploy.**
- `sandbox` — deployment branch. Merging into `sandbox` triggers a build + deploy.

**To deploy:** merge `main` into `sandbox` and push.

```bash
git checkout sandbox
git merge main
git push origin sandbox
# GitHub Actions builds, pushes image, deploys to K8s
```

Email notifications are sent to greg@digittal.io and mike@digittal.io on success or failure.

---

## Sandbox Environment Details

### Architecture (DO NOT CHANGE)
The sandbox pod runs 4 containers:
1. **App container** — Laravel + Filament (port 8080)
2. **MySQL sidecar** — MySQL 8.0 (port 3306, localhost access only)
3. **Redis sidecar** — Redis 7 Alpine (port 6379, localhost access only)
4. **phpMyAdmin sidecar** — Database management UI (port 8888)

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
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```
Redis is available as a sidecar container on localhost:6379. Horizon is installed for queue management.

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
