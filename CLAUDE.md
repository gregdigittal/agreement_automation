# CCRS — Contract & Compliance Review System

## Project Overview
Laravel 12 + Filament 3 application for contract lifecycle management.
Deployed to Kubernetes sandbox at https://ccrs-sandbox.digittal.mobi

## Stack
- **Backend**: PHP 8.3, Laravel 12, Filament 3
- **Database**: MySQL 8.0 (sidecar in sandbox, operator in production)
- **Frontend**: Tailwind CSS v4 (via Vite plugin — no tailwind.config.js or postcss.config.js)
- **Runtime**: nginx + PHP-FPM via supervisord in Docker

## DO NOT MODIFY — Infrastructure Files
The following files are managed by the CTO and must NOT be changed:
- `Jenkinsfile` — CI/CD pipeline (builds Docker image, deploys to K8s, sends email alerts)
- `deploy/k8s/*` — All Kubernetes manifests (deployment, service, ingress, PVC, phpMyAdmin)
- `Dockerfile` — Multi-stage Docker build
- `docker/` — All Docker config files (entrypoint, nginx, supervisord, php.ini)
- `docker-compose.yml` — Local development environment
- `.github/` — Do not create GitHub Actions workflows

If you need changes to any of these files, describe what you need and the CTO will make the change.

## Local Development
```bash
docker-compose up --build
# App: http://localhost:8080
# phpMyAdmin: http://localhost:8888 (root / root)
```

## Sandbox Deployment
Push to `main` branch → Jenkins auto-builds and deploys within ~2 minutes.
- App: https://ccrs-sandbox.digittal.mobi
- phpMyAdmin: https://ccrs-pma-sandbox.digittal.mobi (root / ccrs-root-pass)
- Email notifications sent to greg@digittal.io and mike@digittal.io on deploy success/failure

## Key Conventions
- Health check endpoint: `GET /health` must return 200 with body "ok" (required by K8s probes)
- Health ready endpoint: `GET /health/ready` — optional readiness check
- Database: MySQL with `DB_CONNECTION=mysql` — do NOT switch to SQLite or Redis
- Cache/Session/Queue: Use `database` driver (no Redis in sandbox)
- Use `envsubst` variables (`${APP_NAME}`, `${DOMAIN}`, etc.) in K8s manifests only

## Testing
- Write unit tests for models and services
- Write feature tests for HTTP endpoints and Filament pages
- Run tests: `php artisan test`
- E2E browser testing: not yet configured (no Playwright)

## Database
- Migrations: `php artisan migrate`
- Seeders: `php artisan db:seed`
- MySQL sidecar resets on pod restart (emptyDir was replaced with PVC for persistence)
