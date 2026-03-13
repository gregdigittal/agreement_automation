# CCRS → DPP — Claude Code Context

## 1. Project Overview

**CCRS (Contract & Compliance Review System)** — Laravel 12 + Filament 3 contract lifecycle management platform for Digittal. Manages agreements from draft through execution with AI analysis, compliance checking, e-signing, vendor portal, and bulk operations. Deployed to Kubernetes sandbox at https://ccrs-sandbox.digittal.mobi.

This project is transitioning from a single-purpose CCRS application into a modular multi-tenant SaaS productivity platform called **DPP (Digittal Productivity Platform)**. CCRS becomes a module within the larger platform.

---

## 2. Current Architecture (as built)

### Stack
| Layer | Technology | Version |
|---|---|---|
| Backend | PHP + Laravel + Filament | PHP 8.4, Laravel 12, Filament 3.2 |
| Database | MySQL (prod/sandbox), SQLite in-memory (tests) | MySQL 8.0 |
| Auth | Azure AD SSO (Socialite) + Spatie laravel-permission | spatie/laravel-permission 6.7 |
| Frontend | Tailwind CSS v4 via Vite plugin (no tailwind.config.js) | Tailwind 4.0, Vite 7.0 |
| Queue | Redis + Laravel Horizon | Horizon 5.26 |
| Search | Laravel Scout + Meilisearch (feature-flagged, currently disabled) | Scout 10.24 |
| Observability | Laravel Telescope + OpenTelemetry | Telescope 5.2 |
| PDF/Docs | DomPDF, TCPDF, FPDI, PHPWord, Maatwebsite Excel | — |

### Auth & RBAC
- **Azure AD SSO**: Group-to-role mapping via `config/ccrs.php` `azure_ad.group_map`
- **6 roles** (Spatie): `system_admin`, `legal`, `commercial`, `finance`, `operations`, `audit`
- **2 auth guards**: `web` (User model), `vendor` (VendorUser model, magic-link auth)
- Roles seeded via `Database\Seeders\RoleSeeder`, auto-seeded in tests via `TestCase::setUp()`

### Storage
- **DatabaseAdapter** (`app/Storage/DatabaseAdapter.php`) — Flysystem adapter storing files as MySQL BLOBs in `file_storage` table
- Disk name: `database`. Config: `ccrs.contracts_disk` defaults to `database`
- Signed URL serving via `StorageServeController` at `/storage/serve/{path}`
- S3 disk is configured but **not active** — S3/SeaweedFS is planned for DPP

### AI Worker
- **Python FastAPI** sidecar in K8s pod, port 8001, localhost only
- Auth: `X-AI-Worker-Secret` header
- Client: `App\Services\AiWorkerClient` (singleton)
- Endpoints: `/analyze` (5 types: summary, extraction, risk, deviation, obligations), `/generate-workflow`, `/analyze-redline`, `/health`
- Model: `claude-sonnet-4-6` (configurable via `AI_MODEL` env)
- Budget cap: `AI_MAX_BUDGET_USD=5.0`

### Queue & Scheduling
- **Redis** with 4 databases: 0 (default), 1 (cache), 2 (queue), 3 (sessions)
- **Horizon**: supervisord process `queue-worker` running `php artisan horizon`
- **9 jobs**: ProcessAiAnalysis, ProcessSmartUpload, ProcessContractBatch, ProcessComplianceCheck, ProcessRedlineAnalysis, GenerateWeeklyReport, SendReminders, CheckSlaBreaches, SendPendingNotifications
- **Schedule** (routes/console.php): SendReminders daily@08:00, CheckSlaBreaches hourly, SendPendingNotifications every 5min, GenerateWeeklyReport weekly Monday@07:00

### Feature Flags
`App\Helpers\Feature` class reads `config/features.php`:
- `redlining` — false (Phase 2)
- `regulatory_compliance` — false (Phase 2)
- `advanced_analytics` — false (Phase 2)
- `vendor_portal` — true
- `meilisearch` — false
- `in_house_signing` — true (in `config/ccrs.php`)
- `exchange_room` — true (in `config/ccrs.php`)
- `sharepoint` — false (in `config/ccrs.php`)

### Tenancy
- **stancl/tenancy is NOT installed**. Not in composer.json or composer.lock. Multi-tenancy is a DPP target, not current state.

### Filament Panel
- **Single panel**: `admin`
- **21 Resources**: Contract, Counterparty, Entity, EntityShareholding, Region, Project, Jurisdiction, GoverningLaw, ContractType, WorkflowTemplate, SigningAuthority, WikiContract, MerchantAgreement, MerchantAgreementRequest, KycTemplate, OverrideRequest, AuditLog, Notification, RegulatoryFramework, VendorUser, User
- **18 Pages**: Dashboard, AgreementRepository, AiCostReport, AiDiscoveryReview, AnalyticsDashboard, AzureLogin, BulkContractUpload, BulkDataUpload, Escalations, HelpGuide, KeyDates, MySignatures, NotificationPreferences, Notifications, OrganisationStructure, OrgVisualization, Reminders, Reports
- **12 Widgets**: ContractStatus, ContractPipelineFunnel, ExpiryHorizon, PendingWorkflows, ComplianceOverview, RiskDistribution, ObligationTracker, WorkflowPerformance, ActiveEscalations, AiCost, AiUsageCost, AiProcessingBanner

### Models (59)
See `CURSOR_CONTEXT.md` for full categorised list. Key groups: Core (Contract, Counterparty, Entity, Region, Project, Jurisdiction), Workflow, Signing, AI, Compliance, Operations, Vendor Portal, System.

### Services (25)
AiWorkerClient, AiDiscoveryService, AuditService, BoldsignService, BulkDataImportService, CalendarService, ContractFileService, ContractLinkService, CounterpartyService, EscalationService, ExchangeRoomService, KycService, MerchantAgreementService, NotificationService, PdfService, RedlineService, RegulatoryComplianceService, ReminderService, SearchService, SharePointService, SigningService, TeamsNotificationService, TelemetryService, VendorNotificationService, WorkflowService

### Notifications
- **In-app**: Notification model + NotificationsPage
- **Email**: SMTP via ramnode.digittal.io:465 (SSL)
- **Teams**: Microsoft Graph API via TeamsNotificationService

### Testing
- **PestPHP 3.5** with pest-plugin-laravel
- **101 test files**, ~826+ tests passing
- **Database**: SQLite in-memory via RefreshDatabase trait
- **Filament tests**: Must call `Filament::setCurrentPanel(Filament::getPanel('admin'))` before testing
- Run: `php artisan test` or `php -d memory_limit=512M artisan test` for full suite

### Deployment (K8s)
Single pod with 5 containers on Hetzner cluster:
1. **App** — Laravel + nginx + PHP-FPM via supervisord (port 8080)
2. **MySQL** — MySQL 8.0 sidecar (port 3306, localhost)
3. **Redis** — Redis 7 Alpine (port 6379, localhost)
4. **AI Worker** — FastAPI (port 8001, localhost)
5. **phpMyAdmin** — (port 8888)

Cloudflare SSL termination. PVC with `local-path` StorageClass. Single GitHub Actions workflow (`deploy.yml`) on self-hosted runner. Deploys on push to `sandbox` branch.

---

## 3. Target Architecture (DPP v2)

| Current (CCRS) | Target (DPP v2) |
|---|---|
| Single Laravel monolith | Laravel monolith (dpp-core) + 4 sidecars |
| MySQL BLOB storage | SeaweedFS (S3-compatible object store) |
| Direct Claude API calls | dpp-ai Python service (AI provider abstraction) |
| No WebSockets | dpp-realtime (Soketi/Node.js) |
| Laravel job chains | Temporal.io (dpp-workflows) |
| No vector search | PostgreSQL + pgvector |
| Redis cache/queue only | Redis Streams (event bus) + cache + queue |
| Single-tenant | Multi-tenant (stancl/tenancy database-per-tenant) |
| No semantic search | dpp-trawler (Python discovery agent) |

See `docs/build-plan.md` for the 6-stage development plan. See `docs/cto-infrastructure-brief.md` for Kubernetes deployment details.

---

## 4. Module Structure (target)

```
app/
├── Modules/
│   ├── Agreements/    # Existing CCRS code (reorganised here)
│   ├── Notes/         # Module 1: Intelligent Note Capture
│   ├── Meetings/      # Module 2: Meeting Intelligence
│   ├── Discovery/     # Module 3: Knowledge Graph
│   ├── Tasks/         # Module 4: Task Management
│   ├── Email/         # Module 5: Email Integration
│   ├── Chat/          # Module 6: Communication Bridge
│   └── Automation/    # Section 15.13: Automation Engine
├── Platform/          # Shared services (AuthService, EventBus, VaultEngine, etc.)
└── (existing app/ structure remains until module reorganisation)
database/migrations/
├── (existing CCRS migrations — do not move)
└── platform/          # New DPP + module migrations (namespaced)
```

> This restructuring has NOT happened yet. Current code is in the standard Laravel layout. Module reorganisation happens during the v2 branch work.

---

## 5. Branch Strategy

| Branch | Purpose |
|---|---|
| `main` / `develop` | CCRS production. Do not break. Bug fixes and P0 patches go here. |
| `laravel-migration` | Active CCRS development branch (current) |
| `sandbox` | Deployment branch. Merge `main` → `sandbox` → push triggers deploy. |
| `platform/v2-dpp` | Long-lived DPP branch (future). Forked from develop after P0 fixes. |
| `platform/module-*` | Per-module branches. Merge into `platform/v2-dpp`. |
| `v2/integration` | Merge of develop + `platform/v2-dpp` for testing. |

---

## 6. Commands

```bash
# Dev server (concurrent: server + queue + logs + vite)
composer dev

# Tests
php artisan test                              # standard
php -d memory_limit=512M artisan test         # full suite

# Horizon (queue dashboard at /horizon)
php artisan horizon

# Frontend assets
npm run dev      # watch mode
npm run build    # production build

# Migrations
php artisan migrate

# AI Worker (from ai-worker/ directory)
cd ai-worker && uvicorn main:app --host 0.0.0.0 --port 8001

# Local environment
docker-compose up --build
# App: http://localhost:8080 | phpMyAdmin: http://localhost:8888

# Deploy
git checkout sandbox && git merge main && git push origin sandbox
```

---

## 7. Known Issues (P0)

| ID | Issue | Branch |
|---|---|---|
| P0-1 | BoldSign cleanup | `feat/p0-boldsign-cleanup` |
| P0-2 | Storage path scoping | `feat/p0-storage-scoping` |
| P0-3 | TenantAwareJob wrapper | `feat/p0-tenant-aware-jobs` |
| P0-4 | Per-tenant AI secret | `feat/p0-ai-worker-tenant` |
| P0-5 | Azure AD group map | `feat/p0-azure-group-map` |
| P0-6 | SQLite parity | `feat/p0-sqlite-parity` |
| P0-7 | SharePoint governance | `docs/sharepoint-governance` |

**Cache bugs**: `org_structure_tree_data`, `contract_types.options`, `sharepoint_graph_token` — all need tenant-scoped keys.

**Security**: `APP_DEBUG=true` in deployment.yaml (line 45), hardcoded DB credentials in deployment.yaml (lines 58-59, 84, 155-161, 210).

---

## 8. Security Requirements

DPP has 50 security requirements (R-SEC-001 to R-SEC-050) defined in `docs/reference/DPP_Security_Amendment_v1_1.md`. These are non-negotiable and integrated into each development stage. When working on any tenant-related, auth, encryption, or API code, consult the security amendment.

---

## 9. Reference Documents

| Document | Purpose |
|---|---|
| `docs/reference/DPP_Requirements_v1_2.pdf` | Full functional requirements (all R-* IDs) |
| `docs/reference/DPP_Security_Amendment_v1_1.md` | 50 security requirements (R-SEC-*) |
| `docs/reference/DPP_RSEC_Bedrock_AWS_Coverage_Matrix_v1_0.md` | AWS/Bedrock mapping |
| `docs/build-plan.md` | 6-stage development plan |
| `docs/branch-strategy.md` | Branch and merge strategy |
| `docs/cto-infrastructure-brief.md` | K8s deployment guide for CTO |
| `docs/pre-flight-audit.md` | Codebase audit findings |
| `docs/security-tracker.md` | R-SEC implementation status |
| `docs/data-migration.md` | CCRS → DPP data migration playbook |
| `docs/aws-bedrock-future.md` | Bedrock integration guide (future) |
| `CURSOR_CONTEXT.md` | Detailed model/resource/service inventory |
| `docs/plans/` | Implementation plans and design documents |

> Not all reference docs exist yet. They are created during the v2 build plan execution.

---

## 10. Conventions

- **Tests**: PestPHP. Every new feature needs tests. Tenant isolation tests for any tenant-scoped code.
- **Migrations**: New DPP migrations go in `database/migrations/platform/`. Existing CCRS migrations stay in place.
- **Feature flags**: Use the `Feature` class pattern (`config/features.php`) for new module toggles.
- **Filament**: Follow existing Resource/Page patterns. Single `admin` panel.
- **Queue jobs**: Must extend TenantAwareJob (after P0-3 is merged).
- **Cache keys**: Must be tenant-scoped (use `TenantCache::key()` pattern).
- **AI calls**: Route through dpp-ai service, not direct API calls (after Stage 2).
- **File storage**: Use SeaweedFS S3 disk (after Stage 1), never MySQL BLOBs for new code.
- **UUIDs**: All models use `HasUuidPrimaryKey` trait — string UUIDs, non-incrementing.
- **Audit**: `AuditLog` model is immutable (throws on update/delete). Table name: `audit_log` (singular).
- **SQLite gotchas**: Enums use CHECK constraints. Migrations must handle both MySQL and SQLite for test compat.

---

## 11. Protected Files (CTO-owned — DO NOT MODIFY)

| File/Directory | Purpose |
|---|---|
| `.github/workflows/deploy.yml` | CI/CD pipeline |
| `Jenkinsfile` | Legacy CI/CD (being retired) |
| `deploy/k8s/*` | All Kubernetes manifests |
| `Dockerfile` | Multi-stage Docker build |
| `docker/` | Docker config (entrypoint, nginx, supervisord, php.ini) |
| `docker-compose.yml` | Local dev environment |

If you need infrastructure changes, describe what you need — the CTO will make the change.
