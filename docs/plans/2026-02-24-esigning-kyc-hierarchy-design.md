# Design: In-House E-Signing, KYC Packs & Visual Agreement Repository

**Date:** 2026-02-24
**Status:** Approved
**Author:** Claude (brainstorming session with Greg)

---

## 1. Scope

Replace the BoldSign e-signing dependency with an in-house signing engine. Add organizational jurisdiction configuration, a fully flexible KYC pack/checklist system, and a multi-view visual agreement repository.

### In Scope
- In-house e-signing engine (PDF viewer, signature capture, audit trail, sequential/parallel flows)
- Organizational setup: signing entities, legal jurisdictions, entity-jurisdiction mapping
- KYC pack/checklist system: admin-configurable per entity + jurisdiction, fully flexible field types
- Visual agreement repository: interactive tree with switchable groupings
- BoldSign deprecation (retain tables for historical data, remove active integration)

### Out of Scope
- Multiple branding / white-labeling
- External ID verification services (Onfido, Jumio)
- Notarization
- Payment collection during signing
- OTP/SMS signer authentication (email-only for known counterparties)
- Bulk send / PowerForms
- Legal compliance certification (ESIGN Act / eIDAS — internal use only)

---

## 2. In-House E-Signing Engine

### 2.1 Signing Flow

```
Contract ready for signing (workflow reaches signing/countersign stage)
    |
    v
CCRS creates signing_session with signer records
    |
    v
Each signer receives email with unique signed-URL token
    |
    v
Signer clicks link -> token validates -> session loads
    |
    v
PDF rendered in-browser via pdf.js with form field overlays
    |
    v
Signer fills required fields + places signature (draw/type/upload)
    |
    v
CCRS captures signature image + field values
    |
    v
Signature applied to PDF via FPDI/TCPDF + audit entry created
    |
    v
Sequential: next signer notified | Parallel: check if all done
    |
    v
All signers complete -> PDF sealed with SHA-256 hash chain
    |
    v
KYC pack verified complete -> contract marked executed
    |
    v
Counterparty notified, audit certificate appended to PDF
```

### 2.2 Technical Components

| Component | Technology | Purpose |
|---|---|---|
| PDF Viewer | pdf.js (Mozilla) | Render PDF in browser with zoom, pan, page navigation |
| Signature Capture | HTML5 Canvas + signature_pad.js | Draw signature; also type-to-sign with web fonts, image upload |
| PDF Manipulation | FPDI + TCPDF (PHP) | Flatten signatures and field values onto PDF pages |
| Signing Tokens | Laravel Signed URLs | Tamper-proof, time-limited tokens for signer authentication |
| Email Delivery | Laravel Mail | Signing invitation, reminders, completion notification |
| Queue Processing | Laravel Jobs (database queue) | Async: PDF generation, email sending, next-signer notification |
| Document Hashing | SHA-256 hash chain | Each signing action appends hash; final document includes full chain |
| Audit Certificate | TCPDF-generated page | Summary of all signers, timestamps, IPs, appended to signed PDF |

### 2.3 Data Model

```sql
-- Signing session: one per contract signing round
CREATE TABLE signing_sessions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    contract_id CHAR(36) NOT NULL,
    initiated_by CHAR(36) NOT NULL,         -- user who started the signing
    signing_order ENUM('sequential','parallel') DEFAULT 'sequential',
    status ENUM('draft','active','completed','cancelled','expired') DEFAULT 'draft',
    document_hash VARCHAR(64),               -- SHA-256 of original PDF
    final_document_hash VARCHAR(64),         -- SHA-256 of fully-signed PDF
    final_storage_path VARCHAR(500),         -- S3 path of sealed signed PDF
    expires_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (initiated_by) REFERENCES users(id),
    INDEX idx_contract (contract_id),
    INDEX idx_status (status)
);

-- Individual signer within a session
CREATE TABLE signing_session_signers (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    signing_session_id CHAR(36) NOT NULL,
    signer_name VARCHAR(255) NOT NULL,
    signer_email VARCHAR(255) NOT NULL,
    signer_type ENUM('internal','external') DEFAULT 'external',
    signing_order INT DEFAULT 0,             -- 0 = parallel, 1+ = sequential position
    token VARCHAR(255) UNIQUE,               -- unique URL token
    token_expires_at TIMESTAMP NULL,
    status ENUM('pending','sent','viewed','signed','declined') DEFAULT 'pending',
    signature_image_path VARCHAR(500),       -- S3 path to captured signature image
    signature_method ENUM('draw','type','upload') NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    signed_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    viewed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (signing_session_id) REFERENCES signing_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (signing_session_id),
    INDEX idx_token (token),
    INDEX idx_status (status)
);

-- Form fields placed on PDF pages for signers to fill
CREATE TABLE signing_fields (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    signing_session_id CHAR(36) NOT NULL,
    assigned_to_signer_id CHAR(36) NOT NULL, -- which signer fills this
    field_type ENUM('signature','initials','text','date','checkbox','dropdown') NOT NULL,
    label VARCHAR(255),
    page_number INT NOT NULL,
    x_position DECIMAL(8,2) NOT NULL,        -- PDF coordinate
    y_position DECIMAL(8,2) NOT NULL,
    width DECIMAL(8,2) NOT NULL,
    height DECIMAL(8,2) NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    options JSON NULL,                       -- dropdown options, date format, etc.
    value TEXT NULL,                          -- captured value after signing
    filled_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (signing_session_id) REFERENCES signing_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_signer_id) REFERENCES signing_session_signers(id),
    INDEX idx_session (signing_session_id)
);

-- Immutable audit log for every action in a signing session
CREATE TABLE signing_audit_log (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    signing_session_id CHAR(36) NOT NULL,
    signer_id CHAR(36) NULL,                -- null for system events
    event ENUM('created','sent','viewed','field_filled','signed','declined','cancelled','expired','completed','reminder_sent') NOT NULL,
    details JSON NULL,                       -- field_id, old/new values, etc.
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    FOREIGN KEY (signing_session_id) REFERENCES signing_sessions(id) ON DELETE CASCADE,
    INDEX idx_session_event (signing_session_id, event),
    INDEX idx_created (created_at)
);
```

### 2.4 Services

**SigningService** (replaces BoldsignService):
- `createSession(Contract, array $signers, string $order): SigningSession`
- `sendToSigner(SigningSessionSigner): void` — generates token, sends email
- `validateToken(string $token): SigningSessionSigner`
- `captureSignature(SigningSessionSigner, array $fieldValues, $signatureImage): void`
- `applySignatureToPdf(SigningSession): string` — returns S3 path of signed PDF
- `generateAuditCertificate(SigningSession): string` — returns PDF bytes
- `completeSession(SigningSession): void` — seal document, update contract status
- `cancelSession(SigningSession): void`
- `sendReminder(SigningSessionSigner): void`

**Signing Flow Controller** (public, no auth — token-based):
- `GET /sign/{token}` — validate token, show PDF viewer + signature UI
- `POST /sign/{token}/submit` — capture signature + field values
- `POST /sign/{token}/decline` — decline with reason

### 2.5 Filament Integration

Replace BoldSign actions on ContractResource:
- **"Send for Signing" action** — opens modal with signer configuration (name, email, order, type)
- **"Send for Countersigning" action** — pre-fills internal signers from signing authority
- **SigningSessionsRelationManager** — replaces BoldsignEnvelopesRelationManager
- **Signing status badges** — on contract list view

### 2.6 Effort Estimate: 10-14 weeks

---

## 3. Organizational Setup & Jurisdictions

### 3.1 Concept

Extend existing entities with legal details and parent-child hierarchy. Add jurisdictions as a first-class concept. Map entities to jurisdictions with licensing data.

### 3.2 Data Model

```sql
-- Extend entities table
ALTER TABLE entities ADD COLUMN legal_name VARCHAR(500) NULL;
ALTER TABLE entities ADD COLUMN registration_number VARCHAR(100) NULL;
ALTER TABLE entities ADD COLUMN registered_address TEXT NULL;
ALTER TABLE entities ADD COLUMN parent_entity_id CHAR(36) NULL;
ALTER TABLE entities ADD CONSTRAINT fk_parent_entity
    FOREIGN KEY (parent_entity_id) REFERENCES entities(id) ON DELETE SET NULL;

-- Jurisdictions
CREATE TABLE jurisdictions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL,              -- "UAE - DIFC", "UK - England & Wales"
    country_code CHAR(2) NOT NULL,           -- ISO 3166-1 alpha-2
    regulatory_body VARCHAR(255) NULL,       -- "DIFC Authority"
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY uq_name (name)
);

-- Entity-Jurisdiction pivot with licensing
CREATE TABLE entity_jurisdictions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    entity_id CHAR(36) NOT NULL,
    jurisdiction_id CHAR(36) NOT NULL,
    license_number VARCHAR(100) NULL,
    license_expiry DATE NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (jurisdiction_id) REFERENCES jurisdictions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_entity_jurisdiction (entity_id, jurisdiction_id)
);
```

### 3.3 Filament UI

- **JurisdictionResource** — CRUD (system_admin only)
- **EntityResource extended** — parent entity select, legal details section, EntityJurisdictionsRelationManager
- **Entity hierarchy view** — tree display showing parent-child entity relationships

### 3.4 Effort Estimate: 1-2 weeks

---

## 4. KYC Pack/Checklist System

### 4.1 Concept

Admin-configurable KYC templates per entity + jurisdiction + contract type. When a contract is created, the most specific matching template is instantiated as an immutable checklist pack. All required items must be completed before the contract can proceed to signing.

### 4.2 Field Types

| Type | UI Component | Value Storage |
|---|---|---|
| `file_upload` | Filament FileUpload | S3 path in `file_path` column |
| `text` | TextInput | `value` column |
| `textarea` | Textarea | `value` column |
| `number` | TextInput (numeric) | `value` column |
| `date` | DatePicker | `value` column (Y-m-d) |
| `yes_no` | Toggle / Radio | `value` column ("yes"/"no") |
| `select` | Select dropdown | `value` column (selected option key) |
| `attestation` | Checkbox + user stamp | `attested_by` + `attested_at` columns |

### 4.3 Data Model

```sql
-- Admin-defined KYC templates
CREATE TABLE kyc_templates (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    name VARCHAR(255) NOT NULL,
    entity_id CHAR(36) NULL,                 -- NULL = applies to all entities
    jurisdiction_id CHAR(36) NULL,           -- NULL = applies to all jurisdictions
    contract_type_pattern VARCHAR(100) DEFAULT '*',  -- "Merchant", "Commercial", "*"
    version INT DEFAULT 1,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE SET NULL,
    FOREIGN KEY (jurisdiction_id) REFERENCES jurisdictions(id) ON DELETE SET NULL,
    INDEX idx_matching (entity_id, jurisdiction_id, contract_type_pattern, status)
);

-- Items within a KYC template
CREATE TABLE kyc_template_items (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    kyc_template_id CHAR(36) NOT NULL,
    sort_order INT DEFAULT 0,
    label VARCHAR(500) NOT NULL,             -- "Certificate of Incorporation"
    description TEXT NULL,                   -- help text / instructions
    field_type ENUM('file_upload','text','textarea','number','date','yes_no','select','attestation') NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    options JSON NULL,                       -- select: choices; file: accepted mimes; date: min/max
    validation_rules JSON NULL,              -- regex, min/max length, must_be_future, etc.
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (kyc_template_id) REFERENCES kyc_templates(id) ON DELETE CASCADE,
    INDEX idx_template_order (kyc_template_id, sort_order)
);

-- Instantiated pack per contract (immutable snapshot)
CREATE TABLE kyc_packs (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    contract_id CHAR(36) NOT NULL,
    kyc_template_id CHAR(36) NOT NULL,
    template_version INT NOT NULL,           -- version at time of creation
    status ENUM('incomplete','complete','expired') DEFAULT 'incomplete',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (kyc_template_id) REFERENCES kyc_templates(id),
    UNIQUE KEY uq_contract_pack (contract_id),
    INDEX idx_status (status)
);

-- Individual checklist items per pack
CREATE TABLE kyc_pack_items (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    kyc_pack_id CHAR(36) NOT NULL,
    kyc_template_item_id CHAR(36) NULL,      -- reference to source (may be null if template deleted)
    sort_order INT DEFAULT 0,
    label VARCHAR(500) NOT NULL,             -- copied from template
    description TEXT NULL,
    field_type ENUM('file_upload','text','textarea','number','date','yes_no','select','attestation') NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    options JSON NULL,
    validation_rules JSON NULL,
    value TEXT NULL,                          -- text/number/date/yes_no/select answer
    file_path VARCHAR(500) NULL,             -- S3 path for file_upload
    attested_by CHAR(36) NULL,               -- user_id for attestation
    attested_at TIMESTAMP NULL,
    status ENUM('pending','completed','not_applicable') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    completed_by CHAR(36) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (kyc_pack_id) REFERENCES kyc_packs(id) ON DELETE CASCADE,
    FOREIGN KEY (attested_by) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id),
    INDEX idx_pack_order (kyc_pack_id, sort_order)
);
```

### 4.4 Template Matching Logic

When a contract is created, find the most specific active KYC template:

1. Match `entity_id` + `jurisdiction_id` + `contract_type` (most specific)
2. Match `entity_id` + `jurisdiction_id` + `*` (any contract type)
3. Match `entity_id` + `NULL` + `contract_type` (any jurisdiction)
4. Match `NULL` + `jurisdiction_id` + `contract_type` (any entity)
5. Match `entity_id` + `NULL` + `*` (entity-wide wildcard)
6. Match `NULL` + `jurisdiction_id` + `*` (jurisdiction-wide wildcard)
7. Match `NULL` + `NULL` + `contract_type` (global for type)
8. Match `NULL` + `NULL` + `*` (global fallback)

If no template matches, no KYC pack is created (signing proceeds without KYC gate).

### 4.5 Signing Gate

In `WorkflowService::recordAction()`, before allowing a signing/countersign stage:

```php
if ($kycPack && $kycPack->status !== 'complete') {
    $missing = $kycPack->items()->where('is_required', true)->where('status', 'pending')->get();
    throw new \RuntimeException(
        "KYC pack incomplete. {$missing->count()} required items pending: " .
        $missing->pluck('label')->implode(', ')
    );
}
```

### 4.6 Filament UI

- **KycTemplateResource** — admin CRUD with inline items repeater (sort_order, label, field_type, options)
- **KycPackRelationManager** — on ContractResource, shows checklist with completion status per item
- **KYC completion widget** — progress bar on contract view (e.g., "7/10 items complete")
- **Inline item editing** — users fill values directly from the relation manager

### 4.7 Effort Estimate: 3-4 weeks

---

## 5. Visual Agreement Repository

### 5.1 Concept

An interactive, switchable tree visualization on a custom Filament page. Users toggle between four groupings of the same data:

1. **By Entity** — Entity -> Project -> Contracts
2. **By Counterparty** — Counterparty -> Entity -> Contracts
3. **By Jurisdiction** — Jurisdiction -> Entity -> Contracts
4. **By Project** — Project -> Entity -> Contracts

### 5.2 Technical Approach

**Livewire + Alpine.js collapsible tree** (recommended):
- Server-rendered HTML with nested `<ul>` elements
- Alpine.js `x-show` / `x-collapse` for expand/collapse
- Livewire `$wire.loadChildren(nodeId)` for lazy-loading subtrees
- Fits native Filament aesthetic
- No heavy JS dependencies

### 5.3 Node Summary Data

Each node in the tree shows count badges:

| Badge | Meaning |
|---|---|
| Templates | WikiContracts linked to this node |
| Draft | Contracts in draft state |
| In Progress | Contracts in review/approval/signing |
| Executed | Fully signed contracts |
| Expired | Past expiry date |
| Total | Sum of all contracts under this node (recursive) |

### 5.4 Implementation

- **AgreementRepositoryPage** — custom Filament page at `/admin/agreement-repository`
- **View switcher** — Filament Tabs component (Entity / Counterparty / Jurisdiction / Project)
- **Tree component** — `AgreementTree` Livewire component accepting `groupBy` parameter
- **Search bar** — text search filters the tree in real-time (debounced)
- **Status filter** — dropdown to filter by contract status
- **Click-through** — clicking a contract navigates to ContractResource view page

### 5.5 Effort Estimate: 2-3 weeks

---

## 6. BoldSign Deprecation

### 6.1 Migration Strategy

1. Retain `boldsign_envelopes` table and model for historical data (read-only)
2. Remove `BoldsignService` active methods (sendToSign, createCountersignEnvelope)
3. Remove webhook controller and route
4. Remove BoldSign config values from `ccrs.php` (keep env vars for reference)
5. Replace Filament actions with new SigningService-based actions
6. Replace BoldsignEnvelopesRelationManager with SigningSessionsRelationManager
7. Update WorkflowService to call SigningService instead of BoldsignService

### 6.2 Effort Estimate: 1 week

---

## 7. Phasing

| Phase | Component | Weeks | Dependencies |
|---|---|---|---|
| **Phase 1** | Org setup + Jurisdictions | 1-2 | None |
| **Phase 2** | KYC template + pack system | 3-4 | Phase 1 (jurisdictions) |
| **Phase 3** | In-house e-signing engine | 10-14 | Phase 2 (KYC gate) |
| **Phase 4** | Visual agreement repository | 2-3 | Phase 1 (entity hierarchy) |
| **Phase 5** | BoldSign deprecation + integration | 1 | Phase 3 (signing engine) |
| **Phase 6** | Testing + integration | 2-3 | All above |
| | **Total** | **~20-27 weeks** | |

Phase 4 can run in parallel with Phase 3.

---

## 8. Risk Register

| Risk | Severity | Mitigation |
|---|---|---|
| PDF manipulation complexity (coordinate mapping, multi-page) | High | Use FPDI for reading + TCPDF for writing; extensive test coverage with sample PDFs |
| Signature legal validity without ESIGN certification | Medium | Internal use only — legal team accepts risk for known counterparties |
| Browser PDF rendering inconsistency | Medium | pdf.js is the industry standard; test across Chrome, Firefox, Safari, Edge |
| KYC template versioning edge cases | Low | Immutable snapshot pattern prevents template changes affecting in-flight packs |
| Performance of visual tree with 50,000+ contracts | Medium | Lazy-load subtrees; cache count aggregates; paginate leaf nodes |
