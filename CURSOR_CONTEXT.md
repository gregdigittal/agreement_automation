# CCRS — Claude Code Context Document

> Upload this file to Claude Code (Cursor plugin) to restore full project context.
> Last updated: 2026-02-27 | Commit: a558687 | All branches synced (laravel-migration, main, sandbox)

---

## Project Overview

**CCRS (Contract & Compliance Review System)** — A Laravel 12 + Filament 3 application for enterprise contract lifecycle management. Manages contracts from draft through execution, with AI analysis, compliance checking, signing workflows, vendor portal, and bulk operations.

**Deployed sandbox**: https://ccrs-sandbox.digittal.mobi

---

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4+, Laravel 12, Filament 3 |
| Database | MySQL 8.0 (prod/sandbox), SQLite in-memory (tests) |
| Auth | Azure AD SSO via Socialite + Spatie Roles/Permissions |
| Frontend | Tailwind CSS v4 (Vite plugin — no tailwind.config.js) |
| Signing | BoldSign API integration |
| AI | OpenAI via AiWorkerClient service |
| Queue | Redis + Laravel Horizon |
| Storage | MySQL BLOB-backed Flysystem adapter (not S3) |
| Testing | Pest PHP 3.5+, 826 tests passing |
| CI/CD | GitHub Actions (self-hosted runner), deploys on push to `sandbox` |

---

## Branch Model

| Branch | Purpose | Deploys? |
|---|---|---|
| `laravel-migration` | Active development branch | No |
| `main` | Development integration branch | No |
| `sandbox` | Deployment branch | Yes — triggers build + deploy |

**Deploy flow**: `laravel-migration` → merge to `main` → merge `main` to `sandbox` → push `sandbox`

---

## RBAC — 6 Roles (Spatie)

| Role | Key Permissions |
|---|---|
| `system_admin` | Full access, manage users, workflow templates, merge counterparties |
| `legal` | Contract review, approve overrides, manage KYC, AI analysis |
| `commercial` | Create contracts/counterparties, submit override requests |
| `finance` | Financial reports, AI cost reports |
| `operations` | Operational tasks, contract access |
| `audit` | Read-only audit access, audit logs |

Roles are seeded via `Database\Seeders\RoleSeeder` and auto-seeded in tests via `TestCase::setUp()`.

---

## Key Architecture Patterns

### UUID Primary Keys
All models use `HasUuidPrimaryKey` trait — string UUIDs, non-incrementing. Factories and tests must account for this.

### Immutable Audit Log
`AuditLog` model throws `RuntimeException` on update/delete. Table name is `audit_log` (singular).

### ObligationsRegister
Table name is `obligations_register` (singular, not plural).

### Counterparty Status Enum
Values: `Active`, `Suspended`, `Blacklisted`, `Merged`. Contract creation blocks Suspended/Blacklisted counterparties via validation rule on `ContractResource`.

### Contract Workflow States
`draft` → `review` → `approval` → `signing` → `countersign` → `executed` → `archived`

### Contract Factory State Helper
`ContractFactory` has `->withState('draft'|'executed'|etc)` method using `afterCreating` callback to set `workflow_state`.

### Storage
Uses `DatabaseAdapter` (MySQL BLOB storage), not S3. Disk name: `database`. Config key: `ccrs.contracts_disk`.

---

## Models (52 total)

**Core**: Contract, Counterparty, CounterpartyContact, CounterpartyMerge, Entity, Region, Project, Jurisdiction, EntityJurisdiction

**Workflow**: WorkflowTemplate, WorkflowInstance, WorkflowStageAction, EscalationEvent, EscalationRule

**Signing**: SigningSession, SigningSessionSigner, SigningAuthority, SigningField, SigningAuditLog, BoldsignEnvelope, StoredSignature, TemplateSigningField

**AI**: AiAnalysisResult, AiExtractedField, RedlineSession, RedlineClause

**Compliance**: ComplianceFinding, RegulatoryFramework, ObligationsRegister, KycPack, KycPackItem, KycTemplate, KycTemplateItem

**Operations**: BulkUpload, BulkUploadRow, ContractLink, ContractKeyDate, ContractLanguage, ContractUserAccess, MerchantAgreement, MerchantAgreementInput, OverrideRequest

**Notifications**: Notification, Reminder

**Vendor Portal**: VendorUser, VendorDocument, VendorLoginToken, VendorNotification

**System**: User, AuditLog, FileStorage, WikiContract

---

## Filament Resources (17)

ContractResource, CounterpartyResource, EntityResource, RegionResource, ProjectResource, JurisdictionResource, WorkflowTemplateResource, SigningAuthorityResource, WikiContractResource, MerchantAgreementResource, MerchantAgreementRequestResource, KycTemplateResource, OverrideRequestResource, AuditLogResource, NotificationResource, RegulatoryFrameworkResource, VendorUserResource

---

## Filament Pages (16)

Dashboard, AgreementRepositoryPage, AnalyticsDashboardPage, AiCostReportPage, BulkContractUploadPage, BulkDataUploadPage, EscalationsPage, HelpGuidePage, KeyDatesPage, MySignaturesPage, NotificationPreferencesPage, NotificationsPage, OrgVisualizationPage, RemindersPage, ReportsPage, AzureLoginPage

---

## Services (22)

AiWorkerClient, AuditService, BoldsignService, BulkDataImportService, CalendarService, ContractFileService, ContractLinkService, CounterpartyService, EscalationService, KycService, MerchantAgreementService, NotificationService, PdfService, RedlineService, RegulatoryComplianceService, ReminderService, SearchService, SigningService, TeamsNotificationService, TelemetryService, VendorNotificationService, WorkflowService

---

## Jobs (8)

CheckSlaBreaches, GenerateWeeklyReport, ProcessAiAnalysis, ProcessComplianceCheck, ProcessContractBatch, ProcessRedlineAnalysis, SendPendingNotifications, SendReminders

---

## Test Suite — 826 Tests Passing

### TDD Feature Tests (19 new files, ~281 tests)

| File | Coverage |
|---|---|
| `tests/Feature/Auth/AzureAdLoginTest.php` | Azure AD SSO flow, role mapping, rejection |
| `tests/Feature/Auth/RoleAccessTest.php` | RBAC matrix for all 6 roles across all resources/pages |
| `tests/Feature/OrgStructure/OrgStructureTest.php` | Region → Entity → Project hierarchy, signing authorities |
| `tests/Feature/Counterparties/CounterpartyTest.php` | CRUD, status changes, merge, KYC, override requests |
| `tests/Feature/Contracts/ContractCrudTest.php` | Contract CRUD via Filament Livewire forms |
| `tests/Feature/Contracts/ContractLifecycleTest.php` | Workflow state transitions, signing flow |
| `tests/Feature/Contracts/LinkedContractTest.php` | Parent-child, amendment, renewal links |
| `tests/Feature/Contracts/RestrictedContractTest.php` | Restricted flag, access control |
| `tests/Feature/Workflows/WorkflowTemplateTest.php` | Template CRUD, publishing, versioning, priority scoping, SLA escalation |
| `tests/Feature/Signing/SigningSessionTest.php` | Session creation, signer management, BoldSign integration |
| `tests/Feature/Signing/SigningCompletionTest.php` | Webhook processing, countersign, completion flow |
| `tests/Feature/AI/AiAnalysisTest.php` | All 5 analysis types, cost reports, redline sessions |
| `tests/Feature/MerchantAgreements/MerchantAgreementTest.php` | Template-based generation, PDF output |
| `tests/Feature/Notifications/NotificationTest.php` | In-app, email, Teams channels, preferences |
| `tests/Feature/Reports/ReportsTest.php` | Reports page access, filters, export |
| `tests/Feature/BulkOps/BulkOperationsTest.php` | CSV upload, validation, async processing, error handling |
| `tests/Feature/VendorPortal/VendorPortalTest.php` | Vendor auth, document upload, portal access |
| `tests/Feature/Compliance/ComplianceTest.php` | Regulatory frameworks, compliance checks, findings |
| `tests/Feature/Search/GlobalSearchTest.php` | Scout-based search across contracts |

### Pre-existing Tests (~545 tests)
85+ additional test files covering individual resources, services, jobs, integrations, and edge cases.

---

## Test Conventions

```php
// Pest PHP syntax
it('does something', function () {
    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Livewire form testing
    Livewire::test(ContractResource\Pages\CreateContract::class)
        ->fillForm([...])
        ->call('create')
        ->assertHasNoFormErrors();

    // Direct model assertions
    expect($model->field)->toBe('value');
    $this->assertDatabaseHas('table', ['column' => 'value']);
});
```

- **RefreshDatabase**: Auto-applied to all Feature tests via `tests/Pest.php`
- **RoleSeeder**: Auto-seeded in `TestCase::setUp()` when RefreshDatabase is used
- **Memory**: Run with `php -d memory_limit=512M` for full suite
- **Filament panel**: Must call `Filament::setCurrentPanel(Filament::getPanel('admin'))` before testing Filament components

---

## Recent Implementation Fixes (Commit a558687)

1. **Migration `2026_02_27_000011_add_merged_to_counterparty_status.php`** — Adds `Merged` to counterparty status enum. MySQL uses `ALTER TABLE MODIFY COLUMN`. SQLite uses table recreation (backup → drop → create with new CHECK → restore) because `PRAGMA writable_schema` doesn't work with in-memory SQLite.

2. **`ContractResource.php`** — Added validation rule on `counterparty_id` field that blocks contract creation when counterparty status is `Suspended` or `Blacklisted`.

3. **`ReportsPage.php`** — Added `canAccess()` restricting to `system_admin`, `legal`, `finance`, `audit` roles.

4. **`BulkOperationsTest.php`** — Explicit `registration_number` on counterparty factory (avoids null from `fake()->optional()`). Try/catch around `dispatchSync` for error-path test (job re-throws after marking row failed).

---

## Known Patterns & Gotchas

| Issue | Detail |
|---|---|
| SQLite enum constraints | SQLite uses CHECK constraints for enums. Migrations must handle both MySQL and SQLite for test compatibility. |
| `optional()` in factories | `CounterpartyFactory` uses `fake()->optional()->numerify()` for `registration_number`. Tests doing lookups by this field must provide explicit values. |
| SigningAuthority requires user_id | `signing_authority.user_id` is NOT NULL. Tests must create a User and pass `user_id`. |
| Boolean comparison in SQLite | Pivot booleans return integer (0/1), not true/false. Cast with `(bool)` in assertions. |
| AuditLog immutability | `AuditLog` model throws RuntimeException on update/delete attempts. |
| Table name quirks | `audit_log` (singular), `obligations_register` (singular) — not standard Laravel plural convention. |
| AI analysis types | Enum: `summary`, `extraction`, `risk`, `deviation`, `obligations` (not `risk_assessment`, not `financial`) |
| Obligation types | Enum: `reporting`, `sla`, `insurance`, `deliverable`, `payment`, `other` (not `financial`) |
| NotificationService::create() | Returns `Notification` model — mocks must `->andReturn(new Notification())` |
| WorkflowTemplate delete | Uses `canDelete()` check, not a table delete action |

---

## Protected Files (DO NOT MODIFY)

Per CTO instructions in `CLAUDE.md`:
- `.github/workflows/deploy.yml`
- `Jenkinsfile`
- `deploy/k8s/*`
- `Dockerfile`
- `docker/`
- `docker-compose.yml`
- `CLAUDE.md`

---

## File Structure Quick Reference

```
app/
├── Filament/
│   ├── Pages/          # 16 custom pages
│   ├── Resources/      # 17 resources with Pages/ subdirs
│   └── Widgets/        # Dashboard widgets
├── Jobs/               # 8 queue jobs
├── Models/             # 52 Eloquent models
├── Services/           # 22 service classes
├── Http/Controllers/   # API + web controllers
└── Traits/             # HasUuidPrimaryKey, etc.
database/
├── factories/          # 23 model factories
├── migrations/         # 61 migration files
└── seeders/            # RoleSeeder + others
tests/
├── Feature/            # 86 test files (826 tests total)
└── Pest.php            # Configures RefreshDatabase for Feature tests
```
