# CCRS TDD Test Prompts — README

## What This Is

19 individual prompt files, each designed to be pasted into **Claude Code in Cursor** to generate TDD-style Pest PHP feature tests for the CCRS application.

## How to Use

1. Open your CCRS project in Cursor
2. Open Claude Code (Cmd+L)
3. Copy the content of one `.md` file at a time
4. Paste into Claude Code — it generates the test file
5. Run `php artisan test --filter=<TestClass>` to confirm red (failing)
6. Implement the feature to make tests green
7. Move to the next prompt

## Execution Order

Run in this order (respects model dependencies):

| # | File | Module |
|---|------|--------|
| 00 | `00-shared-setup.md` | Factories & test infrastructure |
| 01 | `01-authentication.md` | Azure AD SSO login |
| 02 | `02-rbac.md` | Role-based access control |
| 03 | `03-org-structure.md` | Regions, Entities, Projects, Jurisdictions, Signing Authorities |
| 04 | `04-counterparties.md` | Counterparty CRUD, duplicates, status, overrides, merging, KYC |
| 05 | `05-contract-crud.md` | Contract creation, reading, updating, deletion |
| 06 | `06-contract-lifecycle.md` | State machine transitions & immutability |
| 07 | `07-workflow-templates.md` | Workflow stages, publishing, matching, escalation |
| 08a | `08a-signing-sessions.md` | Signing sessions, sequential/parallel, page enforcement |
| 08b | `08b-signing-completion.md` | Signature submission, completion, audit trail |
| 09 | `09-ai-analysis.md` | AI analysis types, cost tracking, redline review |
| 10 | `10-linked-contracts.md` | Amendments, renewals, side letters |
| 11 | `11-merchant-agreements.md` | Template-based merchant agreement generation |
| 12 | `12-notifications.md` | Multi-channel notifications & reminders |
| 13 | `13-reports.md` | Reports, analytics dashboard, AI cost report |
| 14 | `14-bulk-operations.md` | Bulk data & contract uploads |
| 15 | `15-vendor-portal.md` | Magic-link vendor portal |
| 16 | `16-restricted-contracts.md` | Restricted access control |
| 17 | `17-compliance-audit.md` | Audit logging, compliance frameworks, document integrity |
| 18 | `18-global-search.md` | Global search |

## Test File Structure

```
tests/Feature/
├── Auth/
│   ├── AzureAdLoginTest.php
│   └── RoleAccessTest.php
├── Contracts/
│   ├── ContractCrudTest.php
│   ├── ContractLifecycleTest.php
│   ├── RestrictedContractTest.php
│   └── LinkedContractTest.php
├── Counterparties/
│   └── CounterpartyTest.php
├── Signing/
│   ├── SigningSessionTest.php
│   └── SigningCompletionTest.php
├── AI/
│   └── AiAnalysisTest.php
├── Workflows/
│   └── WorkflowTemplateTest.php
├── Reports/
│   └── ReportsTest.php
├── BulkOps/
│   └── BulkOperationsTest.php
├── Notifications/
│   └── NotificationTest.php
├── OrgStructure/
│   └── OrgStructureTest.php
├── VendorPortal/
│   └── VendorPortalTest.php
├── MerchantAgreements/
│   └── MerchantAgreementTest.php
├── Compliance/
│   └── ComplianceTest.php
└── Search/
    └── GlobalSearchTest.php
```

## Total: ~200 individual test cases across 19 prompt files
