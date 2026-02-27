> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/00-shared-setup.md and execute the instructions inside it`
> 
> Run FIRST before all other prompts.

# CCRS TDD — 00: Shared Setup (Run First)

Paste this into Claude Code before any test prompts.

---

```
@workspace Before writing any tests, ensure the following test infrastructure exists for our Laravel 12 / Filament 3 / Pest PHP app:

1. Pest PHP is configured (pestphp/pest, pestphp/pest-plugin-laravel)
2. TestCase base class uses RefreshDatabase trait
3. Factories exist for all core models with sensible defaults:
   - User (with role states: system_admin, legal, commercial, finance, operations, audit)
   - Region, Entity, Project
   - Counterparty, Contact
   - Contract (with state traits: draft, review, approval, signing, countersign, executed, archived, cancelled)
   - WorkflowTemplate, WorkflowStage, WorkflowInstance, EscalationRule
   - SigningSession, Signer, StoredSignature, SigningAuditLog
   - AiAnalysisResult, AiExtractedField, RedlineSession
   - WikiContract
   - OverrideRequest, ContractLink, ContractUserAccess
   - KeyDate, Reminder
   - AuditLog, RegulatoryFramework, ComplianceFinding
   - VendorUser, VendorNotification
   - Jurisdiction, SigningAuthority
   - KycTemplate, KycPack, KycPackItem
   - BulkUpload, BulkUploadRow

4. Filament test helpers are available (Livewire::test())
5. Storage fake configured for S3 in test environment
6. Queue fake available for async job testing
7. Mail fake available for email testing
8. HTTP fake available for Teams webhook and AI Worker testing

Create the factory files if they don't exist. Each factory should have named states for common variations (e.g., Contract::factory()->executed(), User::factory()->legal()).
```


---
> **Next: @workspace Read ccrs-tdd-prompts/01-authentication.md and execute the instructions inside it**
