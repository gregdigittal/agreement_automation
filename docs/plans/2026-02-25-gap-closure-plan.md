# CCRS Gap Closure Plan — 2026-02-25

## Current State

- **Branch**: `laravel-migration` (pushed to `ccrs` remote as `main`)
- **Stack**: PHP 8.4, Laravel 12, Filament 3, MySQL 8.0, Redis 7
- **Tests**: 158 passing (445 assertions)
- **Models**: 48 | **Resources**: 17 | **Services**: 21 | **Jobs**: 8 | **Mail**: 6

### What's Solid
- All 48 Eloquent models with relationships and UUID PKs
- 78 database migrations covering all tables
- Full e-signing engine (sequential + parallel, PDF overlay, audit trail)
- Workflow state machine with escalation rules
- KYC pack management with template matching
- Contract amendments, renewals, side letters
- Azure AD SSO + Filament Shield RBAC (6 roles)
- Vendor portal with magic-link auth
- TiTo validation API
- Bulk CSV upload + batch processing
- Calendar ICS reminders
- Excel + PDF report exports
- Rate limiting on all public endpoints
- All scheduled jobs (reminders, SLA breach checks, notifications, weekly report)

### What the CTO Needs to Do (Infrastructure)

| Item | Status | Action |
|---|---|---|
| AI Worker deployment | Not deployed | See `docs/AI-Worker-Deployment-Instructions.md` |
| `origin` repo push blocked | GitHub secret scanning | Allow or rotate Azure AD secret in Jenkinsfile:71 |
| Horizon dashboard access | Installed, needs routing | Verify `/horizon` accessible to system_admin |
| OpenTelemetry collector | No collector | Deploy Jaeger/Tempo sidecar when ready |
| Meilisearch | Disabled by flag | Deploy Meilisearch container when needed |

---

## Phase 1: Critical Gaps (Do First)

### 1.1 — Horizon Queue Worker Activation
**Priority**: High | **Effort**: Small
**Why**: Redis is now the queue driver but Horizon isn't running as a process. The app container needs `php artisan horizon` in supervisord.
**CTO Action**: Add Horizon to `docker/supervisord.conf` (or confirm it's already there).
**Code Action**: None — Horizon config is already complete.

### 1.2 — Smoke-Test AI Worker Integration End-to-End
**Priority**: High | **Effort**: Medium (after CTO deploys worker)
**Why**: The `AiWorkerClient`, `ProcessAiAnalysis` job, and all AI endpoints are built but haven't been tested against a live AI worker.
**Depends on**: CTO deploying AI worker (1.0 above).
**Code Action**:
- Verify the Filament "Run AI Analysis" action dispatches correctly
- Verify results appear in the AI Analysis relation manager tab
- Test redline analysis flow end-to-end
- Test compliance check flow end-to-end

### 1.3 — OverrideRequest Resource Polish
**Priority**: Medium | **Effort**: Small
**Why**: The override request form/table is sparse. Needs approve/reject actions with comment modals matching the requirements spec.
**Code Action**:
- Add approve/reject table actions with modal forms (reason, comments)
- Add status badge column (pending/approved/rejected)
- Add audit log entry on status change

---

## Phase 2: Dashboard & Widget Completeness

### 2.1 — ExpiryHorizon Widget Enhancement
**Priority**: Medium | **Effort**: Small
**Why**: Basic query exists but could show 30/60/90-day horizon buckets.
**Code Action**:
- Add contract expiry date buckets (expiring in 30d, 60d, 90d, expired)
- Color-code by urgency

### 2.2 — ComplianceOverview Widget
**Priority**: Low | **Effort**: Small
**Why**: Currently returns basic count. Should show pass/fail/pending breakdown.
**Code Action**:
- Query `ComplianceFinding` grouped by status
- Display as colored stat cards
**Depends on**: Compliance feature flag being enabled + AI worker

---

## Phase 3: Vendor Portal Polish

### 3.1 — Vendor Dashboard Content
**Priority**: Medium | **Effort**: Medium
**Why**: The vendor dashboard page exists but could show more useful data — active contracts, pending documents, recent notifications.
**Code Action**:
- Add contract summary stats (active, expiring, total)
- Add pending KYC document requests
- Add recent notification feed

### 3.2 — Vendor Profile Editing
**Priority**: Medium | **Effort**: Small
**Why**: Vendor profile page exists but may need field validation and save confirmation.
**Code Action**:
- Verify profile form saves correctly
- Add form validation rules
- Add success notification on save

---

## Phase 4: Feature-Gated Features (Enable When Ready)

### 4.1 — Enable Redlining
**Priority**: Medium | **Effort**: Config only (after AI worker deployed)
**Action**: Set `FEATURE_REDLINING=true` in sandbox env
**Verify**: Upload a contract, start a redline session, review clauses

### 4.2 — Enable Regulatory Compliance
**Priority**: Medium | **Effort**: Config only (after AI worker deployed)
**Action**: Set `FEATURE_REGULATORY_COMPLIANCE=true` in sandbox env
**Verify**: Create a RegulatoryFramework, run compliance check on a contract

### 4.3 — Enable Advanced Analytics
**Priority**: Low | **Effort**: Config only
**Action**: Set `FEATURE_ADVANCED_ANALYTICS=true` in sandbox env
**Verify**: Analytics dashboard page renders with widgets, weekly report job runs

---

## Phase 5: Test Coverage Expansion

### 5.1 — Filament Resource Tests
**Priority**: Medium | **Effort**: Medium
**Why**: No tests for Filament resource CRUD operations (create/edit/delete via admin panel).
**Code Action**:
- Add tests for ContractResource create/edit
- Add tests for CounterpartyResource create/edit
- Add tests for WorkflowTemplateResource create/edit
- Verify Shield permissions block unauthorized access

### 5.2 — Widget Rendering Tests
**Priority**: Low | **Effort**: Small
**Why**: Widgets have real queries but no tests verify they render without errors.
**Code Action**:
- Add render tests for each dashboard widget
- Verify no N+1 queries in widget data loading

### 5.3 — Scheduled Job Tests
**Priority**: Medium | **Effort**: Small
**Why**: `CheckSlaBreaches` and `GenerateWeeklyReport` need coverage verification.
**Code Action**:
- Add test for CheckSlaBreaches escalation creation
- Add test for GenerateWeeklyReport email dispatch

---

## Phase 6: Nice-to-Have Polish

### 6.1 — Agreement Repository Page
**Priority**: Low | **Effort**: Medium
**Why**: Page shell exists and routes to Livewire `AgreementTree` component (which works). The page just needs to be wired up properly.
**Code Action**: Verify the Livewire component renders correctly within the Filament page.

### 6.2 — Org Visualization Page
**Priority**: Low | **Effort**: Medium
**Why**: Page exists with Livewire `OrgHierarchyViewer`. Needs verification that the tree renders entities and their parent/child relationships.
**Code Action**: Verify rendering and add expand/collapse functionality if missing.

### 6.3 — Teams Notification Formatting
**Priority**: Low | **Effort**: Small
**Why**: Basic Teams webhook posting works but message templates could be richer (Adaptive Cards format).
**Code Action**: Enhance message payload with structured Adaptive Card JSON.

### 6.4 — Accessibility (WCAG 2.1 AA)
**Priority**: Low | **Effort**: Large
**Why**: Deferred from Phase M. Signing pages and vendor portal need aria labels, keyboard navigation, and contrast checks.
**Code Action**: Audit signing flow views and vendor portal for WCAG compliance.

---

## Implementation Order

```
CTO deploys AI worker ──────────────────────┐
                                             │
Phase 1 (1.1, 1.3)         ← Can start now  │
Phase 2 (2.1, 2.2)         ← Can start now  │
Phase 3 (3.1, 3.2)         ← Can start now  │
Phase 5 (5.1, 5.2, 5.3)    ← Can start now  │
                                             │
Phase 1 (1.2)               ← Needs AI worker ←┘
Phase 4 (4.1, 4.2, 4.3)    ← Needs AI worker
Phase 6                     ← Low priority
```

**Estimated scope**: Phases 1-3 + 5 = ~2-3 sessions of work. Phase 4 is config toggles. Phase 6 is polish.
