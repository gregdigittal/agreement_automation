# AI Discovery Review Refactoring — Design Document

**Date**: 2026-03-09
**Status**: Approved
**Approach**: Contract-Grouped Accordion with Dedup & Duplicate Detection

## Problem Statement

The AI Discovery Review page currently displays a flat table of all pending `AiDiscoveryDraft` rows across all contracts. This makes it difficult to work on one contract at a time. Additionally:

1. No deduplication — re-running AI Analysis on a contract creates duplicate discovery drafts
2. No duplicate contract detection — two users can upload the same file without warning
3. Contract Title and Reference are not filterable fields

## Requirements

1. **Filterable contract fields**: Contract Title and Reference must be filterable in the review page
2. **Grouped collapsible hierarchy**: AI-extracted fields grouped by contract, collapsible per contract
3. **Discovery deduplication**: Re-running AI Analysis on a contract with existing pending discoveries must not create duplicate drafts
4. **Duplicate contract detection**: SHA-256 content hash + filename matching on upload, with soft warning to user

## Design

### 1. Database Changes

**New migration**: `add_file_hash_to_contracts_table`

```sql
ALTER TABLE contracts ADD COLUMN file_hash VARCHAR(64) NULLABLE;
CREATE INDEX idx_contracts_file_hash ON contracts(file_hash);
```

- `file_hash`: SHA-256 hex digest of uploaded file content
- Nullable (existing contracts won't have it until re-uploaded)
- Indexed for fast duplicate lookups

No changes to `ai_discovery_drafts` table.

### 2. Duplicate Contract Detection

**Trigger**: File upload via `FileUpload` component in `ContractResource` form and `BulkContractUploadPage`

**Flow**:
1. On `afterStateUpdated` of FileUpload, compute SHA-256 of temporary uploaded file
2. Store hash in Livewire property `$fileHash`
3. Query contracts for matches: `WHERE file_hash = ? OR file_name = ?`
4. If matches found, show Filament warning notification listing matching contracts
5. User can still proceed — soft warning only
6. Hash persisted via `mutateFormDataBeforeCreate` or equivalent

**Scope**: Both single-contract create (`CreateContract.php`) and bulk upload (`BulkContractUploadPage.php`).

### 3. Discovery Deduplication

**Two layers**:

**Layer 1 — UI warning** (`ContractResource.php` AI Analysis action):
- Before dispatching `discovery` type, check for existing pending drafts
- Show info notification if pending drafts exist: "This contract already has N pending discoveries"
- Job still dispatches (user may want to re-analyze with updated AI)

**Layer 2 — Service-level dedup** (`AiDiscoveryService::processDiscoveryResults()`):
- Before creating each draft, query for existing pending draft with same `contract_id`, `draft_type`, and matching identity fields
- Identity field matching by type:
  - `counterparty`: `legal_name` or `registration_number` in `extracted_data` JSON
  - `entity`: `name` or `registration_number` in `extracted_data` JSON
  - `jurisdiction`: `name` in `extracted_data` JSON
  - `governing_law`: `name` in `extracted_data` JSON
- If match found in pending status, skip creation (log as "duplicate skipped")
- New/different discoveries still created normally

### 4. Contract-Grouped Review Page

**Approach**: Filament table `->groups()` with `Group::make('contract.contract_ref')` for collapsible contract grouping.

**Page structure**:
- Query: `AiDiscoveryDraft::where('status', 'pending')->with('contract')`
- Grouping: `Tables\Grouping\Group::make('contract.contract_ref')` with `collapsible()` and description showing contract title
- Group header: Contract ref, title, pending count
- Filters:
  - `SelectFilter` on `contract_id` with contract ref + title options (searchable)
  - Existing draft_type filter kept
- Columns: Same as current (draft_type, extracted_data, confidence, match status, created_at)
- Actions: Same approve/reject per row
- Bulk actions on group: "Approve All" and "Reject All" for a contract's drafts

**Navigation badge**: Unchanged (count of pending drafts)

### 5. Files Modified

| File | Nature of Change |
|------|-----------------|
| New migration | Add `file_hash` to `contracts` |
| `app/Models/Contract.php` | Add `file_hash` to `$fillable` |
| `app/Filament/Resources/ContractResource.php` | File hash on upload + dedup warning on AI Analysis |
| `app/Filament/Resources/ContractResource/Pages/CreateContract.php` | Pass `file_hash` in form data |
| `app/Services/AiDiscoveryService.php` | Dedup logic in `processDiscoveryResults()` |
| `app/Filament/Pages/AiDiscoveryReviewPage.php` | Grouped table with filters |
| `app/Filament/Pages/BulkContractUploadPage.php` | File hash on bulk upload |

### 6. Not In Scope

- Retroactively computing hashes for existing contracts (can be a future background job)
- Hard-blocking duplicate uploads (intentional duplicates are valid)
- Changing the AI worker API or discovery response format
- Modifying the approval/rejection flow (unchanged)
