# CCRS / DPP — Backlog

> Updated: 2026-03-17
> Branch: laravel-migration
> Latest commit: Phase C — F-1 Redlining + F-2 Regulatory Compliance enabled; 984 tests passing
> Source: /goal autonomous sprint 2026-03-17

---

## Current Status

| Area | Metric |
|------|--------|
| Test suite | **984 passed** (2676 assertions), 0 failed — PestPHP 3.5, SQLite in-memory |
| Test files | 109 test files across `tests/Feature/` and `tests/Unit/` |
| P0 issues | **7 of 7 resolved** — all merged to `laravel-migration` |
| Gap-closure tasks | **9 of 9 complete** (C-1 through C-9) |
| DPP v2 tasks | **5 of 8 complete** (F-1, F-2, F-3, F-5, F-7) + Phase B + Phase C done |
| CTO-blocked | 4 items (infra config, Azure AD permissions) |
| Pre-existing failures | 0 |
| Branch | `laravel-migration` (active development) |
| Deployment | K8s sandbox at https://ccrs-sandbox.digittal.mobi |

**Pre-existing failures (not regression):**
- `ContractAccessControlTest > it restricted contract is not in list for unauthorized user` — Filament HTTP render test; Livewire-level tests pass
- `UserManagement\UserResourceTest > it creates user with roles and sends invite email` — password validation issue in test setup

---

## Tier 1 — P0 Security & Stability Fixes

All P0 items resolved and merged (2026-03-15).

| # | Item | Files / Description | Effort |
|---|------|---------------------|--------|
| ~~**P0-1**~~ | ~~BoldSign cleanup — runtime guards in service + Filament action~~ | `app/Services/BoldsignService.php`, Filament action guards | ~~S~~ |
| ~~**P0-2**~~ | ~~Storage path scoping — contract + vendor auth in `StorageServeController`~~ | `app/Http/Controllers/StorageServeController.php` | ~~S~~ |
| ~~**P0-3**~~ | ~~TenantAwareJob wrapper + `TenantCache::key()` for unscoped cache keys~~ | `app/Jobs/TenantAwareJob.php`, `app/Helpers/TenantCache.php` | ~~S~~ |
| ~~**P0-4**~~ | ~~Per-tenant AI secret via `resolveSecret()` override point~~ | `app/Services/AiWorkerClient.php` | ~~S~~ |
| ~~**P0-5**~~ | ~~Azure AD group-to-role mapping on SSO callback~~ | `app/Http/Controllers/Auth/AzureController.php`, 11 tests | ~~S~~ |
| ~~**P0-6**~~ | ~~SQLite parity audit — all migrations already guarded~~ | `database/migrations/` (audit only, no changes) | ~~S~~ |
| ~~**P0-7**~~ | ~~SharePoint governance doc~~ | `docs/sharepoint-governance.md` | ~~S~~ |

---

## Tier 2 — Gap Closure (CCRS Completeness)

All gap-closure items complete (2026-03-15).

| # | Item | Files / Description | Effort |
|---|------|---------------------|--------|
| ~~**C-1**~~ | ~~Override Request: audit log on approve/reject~~ | `app/Filament/Resources/OverrideRequestResource.php` — already implemented, verified | ~~S~~ |
| ~~**C-2**~~ | ~~ExpiryHorizon widget: Expired + 30/60/90-day buckets with urgency colour-coding~~ | `app/Filament/Widgets/ExpiryHorizonWidget.php` — 5 buckets (danger/warning/primary/success) | ~~S~~ |
| ~~**C-3**~~ | ~~Vendor dashboard: active contracts, pending KYC stat, notifications in activity feed~~ | `app/Filament/Vendor/Pages/VendorDashboard.php`, `resources/views/filament/vendor/pages/dashboard.blade.php` | ~~M~~ |
| ~~**C-4**~~ | ~~Filament resource tests: ContractResource, CounterpartyResource, WorkflowTemplateResource CRUD + Shield auth~~ | `tests/Feature/ContractResourceCrudTest.php`, `tests/Feature/CounterpartyCrudTest.php` | ~~M~~ |
| ~~**C-5**~~ | ~~Scheduled job tests: `CheckSlaBreaches` escalation creation, `GenerateWeeklyReport` email dispatch~~ | `tests/Feature/CheckSlaBreachesJobTest.php`, `tests/Feature/GenerateWeeklyReportJobTest.php` | ~~M~~ |
| ~~**C-6**~~ | ~~Widget render tests: all 12 dashboard widgets, N+1 detection, feature flag gates~~ | `tests/Feature/DashboardWidgetTest.php` | ~~M~~ |
| ~~**C-7**~~ | ~~Teams notifications: Adaptive Cards v1.5 format (richer messages, upgrade from HTML table)~~ | `app/Services/TeamsNotificationService.php`, `tests/Feature/TeamsNotificationTest.php` | ~~S~~ |

---

## Tier 3 — Active Backlog

| # | Item | Files / Description | Effort |
|---|------|---------------------|--------|
| ~~**C-8**~~ | ~~WCAG 2.1 AA baseline — signing pages + vendor portal accessibility~~ | `signing/show.blade.php`, `signing.js`, `tests/Feature/AccessibilityTest.php` — 11 ARIA/keyboard fixes + 22 regression tests | ~~XL~~ |
| ~~**C-9**~~ | ~~Playwright E2E test infrastructure + functional tests~~ | `playwright.config.js`, `.env.e2e`, `e2e/global-setup.js`, `e2e/global-teardown.js`, `e2e/smoke/pages.spec.js`, `e2e/functional/signing.spec.js`, `e2e/functional/vendor-login.spec.js`, `app/Console/Commands/E2ESeedCommand.php`, `app/Console/Commands/E2ETeardownCommand.php` — 40 E2E tests passing | ~~L~~ |

---

## Tier 4 — CTO-Blocked (Infrastructure — Do Not Implement Without CTO Action)

These items require changes to Kubernetes manifests, Azure AD App Registration, or deployment config. Code changes are not possible until infrastructure is updated.

| # | Item | Blocker | Resolution |
|---|------|---------|------------|
| **I-1** | `APP_DEBUG=true` in production deployment | `deploy/k8s/deployment.yaml` line 45 — CTO-owned file | Set `APP_DEBUG=false` in K8s manifest |
| **I-2** | Hardcoded MySQL credentials in deployment manifest | `deploy/k8s/deployment.yaml` lines 58–59, 84, 155–161, 210 — CTO-owned | Move to K8s `Secret` resources, reference via `secretKeyRef` |
| **I-3** | Azure AD App Registration: `groupMembershipClaims` not configured | Azure portal config — P0-5 code is ready, waiting on claim to appear in JWT | Set `groupMembershipClaims: "SecurityGroup"` in App Registration manifest |
| **I-4** | Microsoft Graph API permissions: `Sites.Read.All` and `Files.Read.All` not granted | Azure portal — required for SharePoint integration (`sharepoint` feature flag) | Admin consent for Graph permissions in App Registration |

---

## Tier 5 — DPP v2 Feature Work (Future Sprints)

These items are gated behind the DPP v2 build plan (`docs/build-plan.md`). They require new infrastructure, significant refactoring, or are explicitly Phase 2+ scope. Do not begin without a dedicated branch and plan sign-off.

| # | Item | Feature Flag / Gate | Effort |
|---|------|---------------------|--------|
| ~~**F-1**~~ | ~~Redlining module — AI-assisted contract redline analysis, compare versions~~ | `FEATURE_REDLINING=true` — enabled 2026-03-17 | ~~XL~~ |
| ~~**F-2**~~ | ~~Regulatory Compliance module — compliance checking against regulatory frameworks~~ | `FEATURE_REGULATORY_COMPLIANCE=true` — enabled 2026-03-17 | ~~XL~~ |
| | **Phase B bug fixes resolved (2026-03-16):** | | |
| | ~~TD-C1~~ — `ProcessComplianceCheck` reads extracted text from `ai_analysis_results` (not non-existent `contract.extracted_text`) | `app/Jobs/ProcessComplianceCheck.php` | ~~S~~ |
| | ~~TD-C2~~ — AI worker call routed through `AiWorkerClient::checkCompliance()` (no more raw `Http::` in job) | `app/Services/AiWorkerClient.php` | ~~S~~ |
| | ~~TD-M3~~ — `failed()` method added to `ProcessComplianceCheck` for permanent failure logging | `app/Jobs/ProcessComplianceCheck.php` | ~~S~~ |
| | ~~TD-H1~~ — All 13 raw `config('features.*')` call sites replaced with `Feature::enabled()` across 9 files | 9 files | ~~S~~ |
| | ~~TD-H2~~ — `AuditService::log()` added to `RegulatoryComplianceService::reviewFinding()` (REQ-1.3.2, REQ-14.1.2) | `app/Services/RegulatoryComplianceService.php` | ~~S~~ |
| | ~~TD-M1~~ — Hardcoded `'database'` fallback removed from all 30 `config('ccrs.contracts_disk', 'database')` call sites | 30 files | ~~S~~ |
| ~~**B.7**~~ | ~~Enable `FEATURE_REGULATORY_COMPLIANCE=true` in sandbox and smoke-test compliance check feature~~ | Enabled by default in config/features.php — 2026-03-17 | ~~Ready~~ |
| ~~**F-3**~~ | ~~Advanced Analytics module — portfolio-level contract analytics, trend analysis~~ | `FEATURE_ADVANCED_ANALYTICS=false` | ~~L~~ |
| **F-4** | SharePoint integration end-to-end — document sync, governance workflows | `sharepoint=false` in `config/ccrs.php` + I-4 CTO gate | L |
| ~~**F-5**~~ | ~~Meilisearch full-text search — Scout integration already installed, just disabled~~ | `meilisearch=false` in `config/ccrs.php` | ~~M~~ |
| **F-6** | Multi-tenancy (stancl/tenancy) — database-per-tenant isolation | DPP v2 Stage 3 prerequisite | XL |
| ~~**F-7**~~ | ~~SeaweedFS S3 storage — replace MySQL BLOB storage with S3-compatible object store~~ | DPP v2 Stage 1; Flysystem S3 adapter already installed | ~~L~~ |
| **F-8** | DPP v2 module restructuring — `app/Modules/` layout, Redis Streams event bus, Temporal workflows | DPP v2 Stages 2–6; see `docs/build-plan.md` | XL |

---

## Effort Key

| Label | Approximate Scope |
|-------|-------------------|
| S | < 2 hours |
| M | 2–8 hours |
| L | 1–3 days |
| XL | 1–2 weeks |
