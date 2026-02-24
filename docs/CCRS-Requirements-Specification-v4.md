# CCRS Requirements Specification v4

**Contract & Compliance Review System**
**Digittal Group**

**Version:** 4.0
**Date:** 2026-02-24
**Status:** Draft
**Supersedes:** CCRS Requirements v3 Board Edition 4

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Overview](#2-system-overview)
3. [Technology Stack](#3-technology-stack)
4. [Epic 1: Foundation & Infrastructure](#epic-1-foundation--infrastructure)
5. [Epic 2: Core Contract Repository](#epic-2-core-contract-repository)
6. [Epic 3: AI Contract Intelligence](#epic-3-ai-contract-intelligence)
7. [Epic 4: Workflow Engine](#epic-4-workflow-engine)
8. [Epic 5: Visual Workflow Builder](#epic-5-visual-workflow-builder)
9. [Epic 6: LLM Workflow Generator](#epic-6-llm-workflow-generator)
10. [Epic 7: Organizational Structure & Authority Matrix](#epic-7-organizational-structure--authority-matrix)
11. [Epic 8: Counterparty Management](#epic-8-counterparty-management)
12. [Epic 9: Commercial Contract Workflow](#epic-9-commercial-contract-workflow)
13. [Epic 10: Merchant Agreement Automation](#epic-10-merchant-agreement-automation)
14. [Epic 11: Monitoring, Alerts & Reminders](#epic-11-monitoring-alerts--reminders)
15. [Epic 12: Reporting & Analytics](#epic-12-reporting--analytics)
16. [Epic 13: Multi-Language Support](#epic-13-multi-language-support)
17. [Epic 14: Security & Compliance](#epic-14-security--compliance)
18. [Epic 15: Amendments, Renewals & Side Letters](#epic-15-amendments-renewals--side-letters)
19. [Epic 16: Escalation Framework](#epic-16-escalation-framework)
20. [Epic 17: Counterparty Due Diligence](#epic-17-counterparty-due-diligence)
21. [Epic 18: In-House E-Signing Engine](#epic-18-in-house-e-signing-engine)
22. [Epic 19: KYC Pack & Checklist System](#epic-19-kyc-pack--checklist-system)
23. [Epic 20: Visual Agreement Repository](#epic-20-visual-agreement-repository)
24. [Epic 21: Jurisdictions & Entity Configuration](#epic-21-jurisdictions--entity-configuration)
25. [Phase 2: Clause Redlining & AI Negotiation](#phase-2-clause-redlining--ai-negotiation)
26. [Phase 2: Regulatory Compliance Checking](#phase-2-regulatory-compliance-checking)
27. [Phase 2: Advanced Analytics Dashboard](#phase-2-advanced-analytics-dashboard)
28. [Phase 2: Vendor Portal](#phase-2-vendor-portal)
29. [Data Model](#data-model)
30. [Integrations](#integrations)
31. [Non-Functional Requirements](#non-functional-requirements)
32. [Deployment Architecture](#deployment-architecture)
33. [Feature Flags](#feature-flags)
34. [Delivery Phases](#delivery-phases)

---

## 1. Executive Summary

CCRS is a centralized, cloud-based platform for storing, managing, generating, executing, and monitoring the complete lifecycle of business agreements across Digittal Group. The system handles both **Commercial Contracts** with third parties and **Merchant Agreements** with vendors.

**Key capabilities:**
- Full contract lifecycle management (draft through execution to archival)
- In-house e-signing engine with PDF viewing, signature capture, and audit trails
- AI-powered contract analysis (risk scoring, obligation extraction, deviation detection)
- Configurable workflow engine with visual builder and LLM generation
- KYC pack/checklist system configurable per entity, jurisdiction, and contract type
- Visual agreement repository with multi-view hierarchy
- Multi-jurisdiction support with entity licensing configuration
- TiTo platform integration for merchant agreement enforcement
- Vendor self-service portal
- Microsoft 365 integration (Azure AD SSO, SharePoint, Teams)

**Target scale:** 200+ concurrent users, 50,000+ contracts.

---

## 2. System Overview

### 2.1 Users & Roles

| Role | Responsibilities |
|---|---|
| **System Admin** | Full system access, org structure management, user management, KYC template configuration |
| **Legal** | Contract review, approval, signing, KYC attestation, compliance checking, redlining |
| **Commercial** | Contract creation, counterparty management, merchant agreement generation |
| **Finance** | Financial obligation tracking, payment-related contract review |
| **Operations** | Operational contract monitoring, vendor management |
| **Audit** | Read-only access to all contracts, audit logs, and compliance data |

### 2.2 Contract Types

- **Commercial Contracts** — agreements with third-party service providers, partners, and vendors
- **Merchant Agreements** — standardized agreements with merchants for TiTo POS deployment

### 2.3 Contract Lifecycle

```
Draft -> Review -> Approval -> KYC Verification -> Signing -> Countersigning -> Executed -> Archived
```

Each stage is configurable per workflow template. KYC verification must be complete before signing can begin.

---

## 3. Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.3, Laravel 11 |
| **Admin UI** | Laravel Filament 3 (Livewire) |
| **Database** | MySQL 8+ |
| **Cache/Queue/Session** | Database (sandbox), Redis (production) |
| **Document Storage** | AWS S3 with pre-signed URLs |
| **AI Service** | Python FastAPI microservice with Anthropic Claude SDK |
| **Authentication** | Microsoft Entra ID (Azure AD) via Laravel Socialite |
| **RBAC** | Spatie Laravel Permission + Filament Shield |
| **PDF Processing** | pdf.js (viewer), FPDI + TCPDF (manipulation) |
| **Document Generation** | PhpOffice/PhpWord (DOCX), Barryvdh/DomPDF (PDF) |
| **Search** | MySQL FULLTEXT (default), Meilisearch (optional, Phase 2) |
| **Deployment** | Kubernetes with Jenkins CI/CD |

---

## Epic 1: Foundation & Infrastructure

### 1.1 Authentication

- **REQ-1.1.1** Microsoft Entra ID (OIDC) single sign-on for all internal users
- **REQ-1.1.2** Azure AD group-to-role mapping: each AD group maps to one CCRS role
- **REQ-1.1.3** First login auto-provisions user record with mapped role
- **REQ-1.1.4** Login attempt rejected if user has no matching AD group
- **REQ-1.1.5** Session management via Laravel with configurable timeout

### 1.2 Authorization

- **REQ-1.2.1** Role-based access control with 6 roles (System Admin, Legal, Commercial, Finance, Operations, Audit)
- **REQ-1.2.2** Filament Shield integration: per-resource, per-action permissions
- **REQ-1.2.3** Least-privilege enforcement — users see only what their role permits
- **REQ-1.2.4** API endpoints protected by auth middleware with role checks

### 1.3 Audit Trail

- **REQ-1.3.1** Immutable audit log table (no update/delete operations)
- **REQ-1.3.2** Every create, update, delete, approve, sign, status change, and override logged
- **REQ-1.3.3** Each log entry records: actor email, actor IP, action, resource type, resource ID, timestamp, JSON details
- **REQ-1.3.4** Audit log viewable by Audit, Legal, and System Admin roles only
- **REQ-1.3.5** CSV export of filtered audit log data

### 1.4 Configuration

- **REQ-1.4.1** All secrets via environment variables (no hardcoded credentials)
- **REQ-1.4.2** Feature flags for optional modules (see Section 33)
- **REQ-1.4.3** Application configuration cached for production performance

---

## Epic 2: Core Contract Repository

### 2.1 Contract Records

- **REQ-2.1.1** Each contract record stores: type (Commercial/Merchant), title, classification (region, entity, project, counterparty), workflow state, signing status, storage path, file version
- **REQ-2.1.2** Document upload: PDF and DOCX files stored on S3 with versioning
- **REQ-2.1.3** Executed contracts are immutable — no edits after execution
- **REQ-2.1.4** Contracts are classified by Region, Entity, Division, Product, Project, and Counterparty
- **REQ-2.1.5** Full-text search on contract title and metadata fields

### 2.2 Template Library (WikiContracts)

- **REQ-2.2.1** WikiContracts serve as standard templates with version control
- **REQ-2.2.2** Status lifecycle: draft -> published -> deprecated
- **REQ-2.2.3** Templates scoped by region, entity, and contract type
- **REQ-2.2.4** Used as comparison baseline for AI deviation analysis

### 2.3 Document Management

- **REQ-2.3.1** S3 storage with pre-signed download URLs (time-limited)
- **REQ-2.3.2** File versioning: each upload increments version number
- **REQ-2.3.3** SharePoint URL and version tracking for collaborative drafting
- **REQ-2.3.4** Multi-language versions per contract (see Epic 13)

---

## Epic 3: AI Contract Intelligence

### 3.1 Analysis Pipeline

- **REQ-3.1.1** Multiple analysis types: summary, extraction, risk scoring, deviation detection, obligations extraction
- **REQ-3.1.2** Analysis runs asynchronously via queue job dispatched to Python AI microservice
- **REQ-3.1.3** Each analysis records: status (pending/processing/completed/failed), processing time, token usage, cost

### 3.2 Structured Extraction

- **REQ-3.2.1** Extract key dates: expiry, renewal notice window, breach cure period, dispute resolution deadline, jurisdiction
- **REQ-3.2.2** Extract parties: names, roles, obligations
- **REQ-3.2.3** Each extracted field includes: clause reference, page number, confidence score (0-1)
- **REQ-3.2.4** Extracted fields have verification status (unverified/verified/rejected) for Legal review

### 3.3 Risk Scoring

- **REQ-3.3.1** Overall risk classification: High / Medium / Low
- **REQ-3.3.2** Risk factors: unusual clauses, missing protections, jurisdiction risk, unlimited liability
- **REQ-3.3.3** Risk summary with specific clause references

### 3.4 Template Deviation

- **REQ-3.4.1** Compare contract against WikiContracts standard clause-by-clause
- **REQ-3.4.2** Flag additions, deletions, and modifications with severity rating
- **REQ-3.4.3** Show delta as percentage deviation from standard

### 3.5 Obligation Extraction

- **REQ-3.5.1** Identify ongoing obligations: reporting, SLA, insurance, deliverables, payments
- **REQ-3.5.2** Each obligation has: type, description, recurrence, responsible party, due date, status
- **REQ-3.5.3** Obligations tracked in dedicated register with reminder integration

---

## Epic 4: Workflow Engine

### 4.1 Workflow Templates

- **REQ-4.1.1** Workflow definitions stored as versioned JSON with stage configurations
- **REQ-4.1.2** Each stage defines: name, type (review/approval/signing/countersign), approver role, SLA hours, required artifacts
- **REQ-4.1.3** Templates scoped by contract type, region, entity, project
- **REQ-4.1.4** Template lifecycle: draft -> published -> deprecated
- **REQ-4.1.5** Version increment on each publish

### 4.2 Workflow Execution

- **REQ-4.2.1** Workflow instance created per contract, bound to specific template version
- **REQ-4.2.2** State machine validates transitions — only valid actions permitted per stage
- **REQ-4.2.3** Actions: approve, reject, rework, skip (with authorization)
- **REQ-4.2.4** Each action logged with: actor, timestamp, comment, artifacts
- **REQ-4.2.5** Rejection returns workflow to previous stage or specified rework stage
- **REQ-4.2.6** Signing/countersign stages enforce signing authority check (see Epic 7)
- **REQ-4.2.7** Signing stages enforce KYC pack completion check (see Epic 19)

---

## Epic 5: Visual Workflow Builder

- **REQ-5.1** Interactive drag-and-drop editor for stage reordering and configuration
- **REQ-5.2** Card-based stage layout with role assignments, SLA duration, approval flags
- **REQ-5.3** Real-time validation: must include at least one approval stage and one signing stage
- **REQ-5.4** Stage configuration panel: edit name, type, role, duration, is_approval toggle
- **REQ-5.5** Built as Livewire component integrated into Filament WorkflowTemplate form

---

## Epic 6: LLM Workflow Generator

- **REQ-6.1** Admin describes desired workflow in natural language
- **REQ-6.2** AI generates structured workflow template (JSON) with stages, roles, SLAs
- **REQ-6.3** Generated workflow presented for review in visual builder before publishing
- **REQ-6.4** AI shows reasoning for each inferred stage
- **REQ-6.5** Admin can edit AI-generated workflow before publishing

---

## Epic 7: Organizational Structure & Authority Matrix

### 7.1 Organization Hierarchy

- **REQ-7.1.1** Three-level hierarchy: Region -> Entity -> Project
- **REQ-7.1.2** Each level has: name, code (unique), description
- **REQ-7.1.3** Entities support parent-child relationships for group hierarchy (see Epic 21)
- **REQ-7.1.4** Admin CRUD for all organizational levels

### 7.2 Signing Authority Matrix

- **REQ-7.2.1** Define who can sign at each entity/project level
- **REQ-7.2.2** Authority rules specify: entity, project (optional), user, contract type pattern
- **REQ-7.2.3** Contract type pattern supports wildcard (`*`) and specific types (e.g., "Merchant")
- **REQ-7.2.4** Authority validated at workflow signing stage — RuntimeException if no matching authority
- **REQ-7.2.5** Authority checks entity_id match and optionally project_id (null = entity-wide authority)

---

## Epic 8: Counterparty Management

### 8.1 Master Records

- **REQ-8.1.1** Counterparty record: legal name, registration number, address, jurisdiction, status, preferred language
- **REQ-8.1.2** Contact management: name, email, role, is_signer flag per counterparty
- **REQ-8.1.3** Status tracking: Active / Suspended / Blacklisted with mandatory reason
- **REQ-8.1.4** Status change notifications sent to all users with active contracts for that counterparty

### 8.2 Duplicate Detection

- **REQ-8.2.1** On create/edit, system checks for potential duplicates by legal name + registration number
- **REQ-8.2.2** Fuzzy matching (SOUNDEX or LIKE) on legal name
- **REQ-8.2.3** Manual merge capability with audit trail

### 8.3 Entity Selection for Contracts

- **REQ-8.3.1** When creating a contract, user selects which Digittal legal entity is the contracting party
- **REQ-8.3.2** The counterparty is the external party to the contract
- **REQ-8.3.3** Entity selection determines which signing authority matrix and KYC template apply

---

## Epic 9: Commercial Contract Workflow

### 9.1 Drafting & Collaboration

- **REQ-9.1.1** Contracts linked to SharePoint for tracked changes and version control
- **REQ-9.1.2** SharePoint URL and version stored on contract record

### 9.2 Approval Process

- **REQ-9.2.1** Configurable sequential or parallel approvals per workflow template
- **REQ-9.2.2** Approval actions captured with timestamp, approver identity, comments

### 9.3 Digital Signing (In-House)

- **REQ-9.3.1** In-house e-signing engine replaces BoldSign (see Epic 18)
- **REQ-9.3.2** Support for internal (Digittal) and external (counterparty) signers
- **REQ-9.3.3** Sequential and parallel signing order configurable per session
- **REQ-9.3.4** Countersigning workflow: external party signs first, then internal signers countersign
- **REQ-9.3.5** Signer authentication via email link (signed URL token) — no OTP required
- **REQ-9.3.6** Executed document stored as immutable copy on S3

---

## Epic 10: Merchant Agreement Automation

### 10.1 Template-Based Generation

- **REQ-10.1.1** WikiContracts serve as regional templates for merchant agreements
- **REQ-10.1.2** Automated DOCX generation with variable substitution (vendor name, fee, region, terms)
- **REQ-10.1.3** Generated document attached to contract record and sent through workflow

### 10.2 TiTo Enforcement

- **REQ-10.2.1** Public API endpoint: `GET /api/tito/validate` with API key authentication
- **REQ-10.2.2** Query parameters: vendor_id (required), entity_id/region_id/project_id (optional filters)
- **REQ-10.2.3** Response: `{valid, status, contract_id, signed_at}` — valid only if fully signed merchant agreement exists
- **REQ-10.2.4** Results cached for 5 minutes
- **REQ-10.2.5** All validation calls logged to audit trail
- **REQ-10.2.6** POS device deployment blocked unless TiTo returns valid=true

### 10.3 Amendment Handling

- **REQ-10.3.1** Amendments inherit parent contract's classification
- **REQ-10.3.2** Each amendment follows independent workflow
- **REQ-10.3.3** Executed amendment stored alongside parent with traceability

---

## Epic 11: Monitoring, Alerts & Reminders

### 11.1 Reminders

- **REQ-11.1.1** Configurable lead times: 90/60/30 days (or custom) ahead of key dates
- **REQ-11.1.2** Channels: email, Microsoft Teams, calendar (ICS attachment)
- **REQ-11.1.3** ICS calendar file generation with event details
- **REQ-11.1.4** Daily reminder processing at 08:00
- **REQ-11.1.5** Deduplication: same reminder not sent twice

### 11.2 Key Dates

- **REQ-11.2.1** AI-extracted dates plus manual entry
- **REQ-11.2.2** Date types: expiry, renewal notice, payment, breach cure, custom
- **REQ-11.2.3** Verification status per date (verified by Legal, confidence score)

### 11.3 SLA Monitoring

- **REQ-11.3.1** Hourly check for workflow stage SLA breaches
- **REQ-11.3.2** Automatic escalation event creation when SLA exceeded
- **REQ-11.3.3** Carbon-compatible time difference calculation (absolute values for signed diff)

---

## Epic 12: Reporting & Analytics

### 12.1 Dashboards

- **REQ-12.1.1** Contract status dashboard split by Commercial vs Merchant
- **REQ-12.1.2** Drill-down filters: region, entity, project, counterparty, status, expiry horizon
- **REQ-12.1.3** Key metrics: total contracts, expiring in 30/60/90 days, unsigned, SLA breaches
- **REQ-12.1.4** Signing status reports
- **REQ-12.1.5** Escalation dashboard with drill-down

### 12.2 Export

- **REQ-12.2.1** Excel export with multiple sheets (contracts, obligations, key dates)
- **REQ-12.2.2** PDF report generation
- **REQ-12.2.3** CSV export of filtered contract lists

---

## Epic 13: Multi-Language Support

- **REQ-13.1** Attach separate PDF/DOCX files per language to same contract
- **REQ-13.2** Supported languages: en, fr, ar, es, pt, zh, de, it, ru, ja
- **REQ-13.3** One version marked as primary
- **REQ-13.4** Unique constraint: one document per language per contract
- **REQ-13.5** AI analysis respects primary language; avoids double-counting in bilingual documents

---

## Epic 14: Security & Compliance

### 14.1 Audit

- **REQ-14.1.1** Immutable audit log — no UPDATE or DELETE on audit_log table
- **REQ-14.1.2** All actions logged comprehensively
- **REQ-14.1.3** Export restricted to Audit/Legal/System Admin

### 14.2 Encryption

- **REQ-14.2.1** TLS 1.2+ for all data in transit
- **REQ-14.2.2** S3 encryption at rest for contract documents
- **REQ-14.2.3** No plaintext secrets in codebase

### 14.3 Data Protection

- **REQ-14.3.1** Data retention policies with configurable periods
- **REQ-14.3.2** Personal data export capability
- **REQ-14.3.3** Audit record anonymization for right-to-be-forgotten requests

### 14.4 Accessibility

- **REQ-14.4.1** WCAG 2.1 AA compliance for all admin interfaces
- **REQ-14.4.2** Keyboard navigation, screen reader support, sufficient color contrast

---

## Epic 15: Amendments, Renewals & Side Letters

- **REQ-15.1** Parent-child contract linking with type: amendment, renewal, side_letter, addendum
- **REQ-15.2** Child contracts inherit parent's region, entity, project, counterparty
- **REQ-15.3** Each linked document follows independent workflow
- **REQ-15.4** Renewal options: extend existing dates OR create new linked contract
- **REQ-15.5** Parent contract view shows complete history of all linked documents
- **REQ-15.6** Side letters linked to master with independent versioning and signing

---

## Epic 16: Escalation Framework

- **REQ-16.1** Escalation rules defined per workflow template and stage
- **REQ-16.2** Multi-tier escalation paths with configurable target roles
- **REQ-16.3** SLA-based triggers: if stage exceeds defined hours, escalation event created
- **REQ-16.4** All escalations logged with tier level, timestamp, trigger reason
- **REQ-16.5** Automatic resolution when underlying action completed
- **REQ-16.6** Executive dashboard for all currently escalated items

---

## Epic 17: Counterparty Due Diligence

- **REQ-17.1** Counterparty status: Active / Suspended / Blacklisted with mandatory reason
- **REQ-17.2** Supporting documentation upload for status changes
- **REQ-17.3** Non-Active counterparty blocks new contract creation
- **REQ-17.4** Override path: Commercial user requests override, System Admin/Legal reviews
- **REQ-17.5** All status changes and overrides fully audited

---

## Epic 18: In-House E-Signing Engine

### 18.1 Signing Sessions

- **REQ-18.1.1** System creates a signing session per contract signing round
- **REQ-18.1.2** Session tracks: contract, initiator, signing order (sequential/parallel), status, document hashes
- **REQ-18.1.3** Session statuses: draft, active, completed, cancelled, expired
- **REQ-18.1.4** Configurable expiration period per session

### 18.2 Signers

- **REQ-18.2.1** Each signer has: name, email, type (internal/external), sequential order position
- **REQ-18.2.2** Signer receives email with unique signed-URL token (time-limited)
- **REQ-18.2.3** Authentication via token only — no OTP, no login required (counterparties are known)
- **REQ-18.2.4** Signer statuses: pending, sent, viewed, signed, declined
- **REQ-18.2.5** Signer IP address and user agent captured on all actions

### 18.3 PDF Viewing & Signature Capture

- **REQ-18.3.1** PDF rendered in-browser via pdf.js with zoom, pan, and page navigation
- **REQ-18.3.2** Signature capture methods: draw on canvas, type with web font, upload image
- **REQ-18.3.3** Form fields placeable on PDF pages: signature, initials, text, date, checkbox, dropdown
- **REQ-18.3.4** Each field assigned to a specific signer
- **REQ-18.3.5** Required fields must be completed before signer can submit

### 18.4 PDF Manipulation

- **REQ-18.4.1** Captured signatures and field values flattened onto PDF using FPDI + TCPDF
- **REQ-18.4.2** Original PDF hash (SHA-256) recorded before any modifications
- **REQ-18.4.3** Each signing action extends a hash chain for tamper evidence
- **REQ-18.4.4** Final signed PDF stored on S3 as immutable copy

### 18.5 Audit Certificate

- **REQ-18.5.1** Auto-generated PDF page appended to signed document
- **REQ-18.5.2** Contains: all signer names, emails, signing timestamps, IP addresses, document hash
- **REQ-18.5.3** Certificate page generated via TCPDF

### 18.6 Sequential & Parallel Flows

- **REQ-18.6.1** Sequential: signers notified one at a time in configured order
- **REQ-18.6.2** Parallel: all signers notified simultaneously
- **REQ-18.6.3** On signer completion, system checks if next action needed (notify next signer or complete session)
- **REQ-18.6.4** Automated reminders for signers who haven't acted

### 18.7 Countersigning

- **REQ-18.7.1** Support countersign workflow: external signers first, then internal Digittal signers
- **REQ-18.7.2** Internal signers pre-filled from signing authority matrix
- **REQ-18.7.3** Signing authority validation before countersign session begins

### 18.8 Signing Routes (Public, Token-Based)

- **REQ-18.8.1** `GET /sign/{token}` — validate token, render PDF viewer + signing UI
- **REQ-18.8.2** `POST /sign/{token}/submit` — capture signature + field values
- **REQ-18.8.3** `POST /sign/{token}/decline` — decline with optional reason
- **REQ-18.8.4** Invalid/expired tokens return appropriate error page

### 18.9 BoldSign Deprecation

- **REQ-18.9.1** BoldSign envelope table retained for historical data (read-only)
- **REQ-18.9.2** BoldSign service, webhook controller, and routes removed
- **REQ-18.9.3** All new signing flows use in-house SigningService
- **REQ-18.9.4** Existing contracts with BoldSign envelopes retain their audit history

---

## Epic 19: KYC Pack & Checklist System

### 19.1 KYC Templates

- **REQ-19.1.1** Admin-configurable templates with fully flexible field types
- **REQ-19.1.2** Templates scoped by: entity (optional), jurisdiction (optional), contract type pattern
- **REQ-19.1.3** Template lifecycle: draft -> active -> archived
- **REQ-19.1.4** Versioning: version incremented on each edit to active template
- **REQ-19.1.5** Matching priority: most specific (entity + jurisdiction + type) to least specific (global wildcard)

### 19.2 Field Types

- **REQ-19.2.1** `file_upload` — document upload to S3 with configurable accepted MIME types
- **REQ-19.2.2** `text` — single-line text input with optional regex validation
- **REQ-19.2.3** `textarea` — multi-line text input
- **REQ-19.2.4** `number` — numeric input with optional min/max
- **REQ-19.2.5** `date` — date picker with optional validation (e.g., must_be_future for license expiry)
- **REQ-19.2.6** `yes_no` — boolean toggle
- **REQ-19.2.7** `select` — dropdown with admin-defined options
- **REQ-19.2.8** `attestation` — checkbox requiring sign-off by a specific user (records user_id + timestamp)

### 19.3 KYC Packs (Per Contract)

- **REQ-19.3.1** When a contract is created, system finds the most specific matching KYC template
- **REQ-19.3.2** Template items copied into an immutable KYC pack (snapshot pattern)
- **REQ-19.3.3** If template changes later, existing packs keep their original checklist
- **REQ-19.3.4** If no template matches, no KYC pack created (signing proceeds without KYC gate)
- **REQ-19.3.5** Pack status: incomplete -> complete (when all required items completed)
- **REQ-19.3.6** Individual items: pending -> completed / not_applicable

### 19.4 Signing Gate

- **REQ-19.4.1** Workflow engine checks KYC pack status before allowing signing/countersign stage
- **REQ-19.4.2** If KYC incomplete, action blocked with message listing missing required items
- **REQ-19.4.3** Items with date fields and `must_be_future` validation block signing if date has passed

### 19.5 Expiry Awareness

- **REQ-19.5.1** Date fields with `must_be_future` validation rule are re-checked at signing time
- **REQ-19.5.2** If a previously-completed date field has expired, KYC pack status reverts to incomplete
- **REQ-19.5.3** Pack status recalculated on each check, not just on item completion

### 19.6 Filament UI

- **REQ-19.6.1** KycTemplateResource: admin CRUD with inline sortable items repeater
- **REQ-19.6.2** KycPackRelationManager on ContractResource: shows checklist with fill-in capability
- **REQ-19.6.3** Progress bar widget showing X/Y items complete
- **REQ-19.6.4** Items editable directly from the relation manager (file upload, text entry, attestation checkbox)

---

## Epic 20: Visual Agreement Repository

### 20.1 Multi-View Hierarchy

- **REQ-20.1.1** Interactive tree visualization on a custom Filament page
- **REQ-20.1.2** Four switchable views:
  - By Entity: Entity -> Project -> Contracts
  - By Counterparty: Counterparty -> Entity -> Contracts
  - By Jurisdiction: Jurisdiction -> Entity -> Contracts
  - By Project: Project -> Entity -> Contracts
- **REQ-20.1.3** View switching via tab navigation, no page reload

### 20.2 Node Summary

- **REQ-20.2.1** Each node shows count badges: Templates, Draft, In Progress, Executed, Expired, Total
- **REQ-20.2.2** Counts are recursive — parent nodes aggregate child counts
- **REQ-20.2.3** Both WikiContracts (templates) and active contracts included in counts

### 20.3 Interaction

- **REQ-20.3.1** Expand/collapse nodes with click or keyboard
- **REQ-20.3.2** Lazy-load subtrees on expand (performance optimization for large datasets)
- **REQ-20.3.3** Click-through: clicking a contract navigates to ContractResource view page
- **REQ-20.3.4** Text search filters tree in real-time (debounced)
- **REQ-20.3.5** Status filter dropdown to show only specific contract states

### 20.4 Technical Implementation

- **REQ-20.4.1** Livewire + Alpine.js collapsible tree component
- **REQ-20.4.2** Server-rendered with Alpine.js expand/collapse
- **REQ-20.4.3** Cached count aggregates for performance at scale (50,000+ contracts)

---

## Epic 21: Jurisdictions & Entity Configuration

### 21.1 Jurisdictions

- **REQ-21.1.1** Jurisdictions as first-class entity: name, country code (ISO 3166-1 alpha-2), regulatory body, notes
- **REQ-21.1.2** Active/inactive status for jurisdictions
- **REQ-21.1.3** Admin CRUD (System Admin only)

### 21.2 Entity Extensions

- **REQ-21.2.1** Entities extended with: legal_name, registration_number, registered_address
- **REQ-21.2.2** Parent-child entity hierarchy via parent_entity_id self-referential FK
- **REQ-21.2.3** Entity hierarchy displayed in org structure views

### 21.3 Entity-Jurisdiction Mapping

- **REQ-21.3.1** Many-to-many relationship: each entity can operate in multiple jurisdictions
- **REQ-21.3.2** Pivot data: license_number, license_expiry, is_primary flag
- **REQ-21.3.3** One jurisdiction marked as primary per entity
- **REQ-21.3.4** License expiry tracking with reminder capability

---

## Phase 2: Clause Redlining & AI Negotiation

*Feature flag: `FEATURE_REDLINING`*

- **REQ-P2R.1** AI-powered comparison of contract against WikiContracts templates
- **REQ-P2R.2** Clause-level diff: unchanged, addition, deletion, modification
- **REQ-P2R.3** AI-generated suggested text with business/legal rationale per clause
- **REQ-P2R.4** Confidence scoring (0-1) per suggestion
- **REQ-P2R.5** Interactive review: Legal users accept/reject/modify each suggestion
- **REQ-P2R.6** Progress tracking: reviewed clauses / total clauses with percentage
- **REQ-P2R.7** Final document generation with all accepted changes applied

---

## Phase 2: Regulatory Compliance Checking

*Feature flag: `FEATURE_REGULATORY_COMPLIANCE`*

- **REQ-P2C.1** Multi-jurisdiction regulatory framework library
- **REQ-P2C.2** Requirement categories: Data Protection, Financial, Employment, IP, Dispute Resolution, Liability, Confidentiality, Termination
- **REQ-P2C.3** Severity levels: Critical, High, Medium, Low
- **REQ-P2C.4** Per-contract compliance assessment against selected frameworks
- **REQ-P2C.5** Finding statuses: compliant, non_compliant, unclear, not_applicable
- **REQ-P2C.6** Evidence: clause reference, page number, AI rationale, confidence
- **REQ-P2C.7** Manual review/override by Legal with timestamp

---

## Phase 2: Advanced Analytics Dashboard

*Feature flag: `FEATURE_ADVANCED_ANALYTICS`*

- **REQ-P2A.1** Contract distribution by risk level and status
- **REQ-P2A.2** Trend analysis over time
- **REQ-P2A.3** Weekly scheduled compliance and expiry reports
- **REQ-P2A.4** Approval cycle time analysis and bottleneck identification
- **REQ-P2A.5** Multi-sheet Excel export with analytics data

---

## Phase 2: Vendor Portal

*Feature flag: `FEATURE_VENDOR_PORTAL`*

- **REQ-P2V.1** Separate Filament panel at `/vendor` with vendor-specific branding
- **REQ-P2V.2** Magic-link authentication: vendor enters email, receives signed URL, no password
- **REQ-P2V.3** Vendor document upload: supporting documents scoped to their counterparty
- **REQ-P2V.4** Read-only contract view: vendors see only their contracts
- **REQ-P2V.5** Notification inbox: status change alerts, document requests
- **REQ-P2V.6** Dashboard: active contracts, upcoming dates, pending actions
- **REQ-P2V.7** Admin management: VendorUserResource for System Admin

---

## Data Model

### Complete Table Inventory

**Organization (6 tables):**

| Table | Purpose |
|---|---|
| `regions` | Top-level geographic regions |
| `entities` | Legal entities within regions (extended: legal_name, registration_number, parent_entity_id) |
| `projects` | Projects within entities |
| `jurisdictions` | Legal jurisdictions (country_code, regulatory_body) |
| `entity_jurisdictions` | Entity-jurisdiction mapping with license data |
| `signing_authority` | Authority matrix: who can sign what |

**Contracts (7 tables):**

| Table | Purpose |
|---|---|
| `contracts` | Core contract records |
| `contract_links` | Parent-child relationships (amendment, renewal, side_letter) |
| `contract_languages` | Multi-language document versions |
| `contract_key_dates` | Extracted/manual key dates with reminders |
| `wiki_contracts` | Template library |
| `merchant_agreement_inputs` | Generated merchant agreement metadata |
| `contract_classifications` | Flexible classification tags |

**Counterparty (4 tables):**

| Table | Purpose |
|---|---|
| `counterparties` | Vendor/partner master records |
| `counterparty_contacts` | Signatories per counterparty |
| `counterparty_merges` | Duplicate merge audit |
| `override_requests` | Status override requests |

**Workflow (4 tables):**

| Table | Purpose |
|---|---|
| `workflow_templates` | Versioned workflow definitions |
| `workflow_instances` | Per-contract workflow execution |
| `workflow_stage_actions` | Action records per stage |
| `escalation_rules` | SLA triggers per template |
| `escalation_events` | Triggered escalation events |

**Signing (5 tables):**

| Table | Purpose |
|---|---|
| `signing_sessions` | Per-contract signing round |
| `signing_session_signers` | Individual signers within session |
| `signing_fields` | Form fields placed on PDF pages |
| `signing_audit_log` | Immutable signing event log |
| `boldsign_envelopes` | Historical BoldSign data (read-only, deprecated) |

**KYC (4 tables):**

| Table | Purpose |
|---|---|
| `kyc_templates` | Admin-defined KYC checklists |
| `kyc_template_items` | Individual items within templates |
| `kyc_packs` | Instantiated checklist per contract |
| `kyc_pack_items` | Individual items per pack |

**AI (3 tables):**

| Table | Purpose |
|---|---|
| `ai_analysis_results` | Analysis job tracking |
| `ai_extracted_fields` | Structured field extraction |
| `obligations_register` | Ongoing obligation tracking |

**Notifications (3 tables):**

| Table | Purpose |
|---|---|
| `reminders` | Configurable date-based reminders |
| `notifications` | Internal user notifications |
| `vendor_notifications` | Vendor portal notifications |

**Vendor Portal (2 tables):**

| Table | Purpose |
|---|---|
| `vendor_users` | Vendor portal users (magic-link auth) |
| `vendor_documents` | Documents uploaded by vendors |

**System (4 tables):**

| Table | Purpose |
|---|---|
| `users` | Internal users (Azure AD provisioned) |
| `audit_log` | Immutable action log |
| `bulk_upload_jobs` | Background import jobs |
| `notification_preferences` | User notification channel preferences |

**Phase 2 (4 tables):**

| Table | Purpose |
|---|---|
| `redline_sessions` | Clause comparison sessions |
| `redline_clauses` | Individual clause redlines |
| `regulatory_frameworks` | Framework definitions per jurisdiction |
| `compliance_findings` | Per-contract compliance findings |

**Spatie (via package, 5 tables):**
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

**Total: ~51 tables** (including Spatie managed tables)

---

## Integrations

| Integration | Purpose | Method |
|---|---|---|
| **Microsoft Entra ID** | SSO + role group mapping | OIDC via Socialite |
| **Microsoft Teams** | Notification delivery | Graph API |
| **SharePoint** | Collaborative document drafting | URL linking (stored on contract) |
| **TiTo Platform** | Merchant agreement validation | REST API (`/api/tito/validate`) |
| **AWS S3** | Document storage | Laravel Storage (S3 driver) |
| **Anthropic Claude** | Contract analysis | Python microservice (ai-worker) |
| **SMTP (SendGrid)** | Email delivery | Laravel Mail |

---

## Non-Functional Requirements

| Category | Requirement |
|---|---|
| **Availability** | 99.9% uptime (excluding planned maintenance) |
| **Performance** | Search < 2s, TiTo API < 500ms p95, PDF render < 3s |
| **Scalability** | 50,000+ contracts, 200+ concurrent users |
| **Security** | TLS 1.2+, encryption at rest, least-privilege RBAC, comprehensive audit trail |
| **Compliance** | GDPR-aligned, data retention policies, export capabilities |
| **Accessibility** | WCAG 2.1 AA |
| **DR/Backup** | RPO near-zero (executed documents), RTO < 4 hours |
| **Observability** | Structured logging, health endpoints, alerting |
| **Mobile** | Responsive design, signing works on mobile browsers |

---

## Deployment Architecture

### Sandbox (Current)

Single Kubernetes pod with 3 containers:
1. **App container** — Laravel + Filament (port 8080)
2. **MySQL sidecar** — MySQL 8.0 (port 3306)
3. **phpMyAdmin sidecar** — DB management (port 8888)

- **CI/CD:** Jenkins (push to `main` triggers build + deploy)
- **SSL:** Cloudflare termination
- **Cache/Queue/Session:** Database driver (no Redis)
- **URLs:** https://ccrs-sandbox.digittal.mobi | https://ccrs-pma-sandbox.digittal.mobi

### Production (Target)

- Multi-pod deployment with HPA (2-10 replicas)
- Separate MySQL operator instance
- Redis for cache/queue/session
- AI worker sidecar or separate pod
- Queue worker pod for background jobs

---

## Feature Flags

| Flag | Default | Controls |
|---|---|---|
| `FEATURE_VENDOR_PORTAL` | true | Vendor self-service portal |
| `FEATURE_MEILISEARCH` | false | Advanced search via Meilisearch |
| `FEATURE_REDLINING` | false | Clause redlining & AI negotiation |
| `FEATURE_REGULATORY_COMPLIANCE` | false | Compliance framework checking |
| `FEATURE_ADVANCED_ANALYTICS` | false | Sophisticated dashboards & reports |
| `FEATURE_IN_HOUSE_SIGNING` | false | In-house e-signing engine (replaces BoldSign) |

---

## Delivery Phases

| Phase | Scope | Status |
|---|---|---|
| **Phase 1a** | Foundation, core repository, org structure, counterparties, security | Complete |
| **Phase 1b** | Workflows, visual builder, commercial/merchant automation, amendments | Complete |
| **Phase 1c** | AI intelligence, monitoring, reporting, multi-language, escalation | Complete |
| **Phase 1d** | Production hardening, K8s deployment, test suite | Complete |
| **Phase 2a** | Clause redlining & AI negotiation engine | Complete (feature-gated) |
| **Phase 2b** | Regulatory compliance + advanced analytics | Complete (feature-gated) |
| **Phase 2c** | Vendor portal | Complete (feature-gated) |
| **Phase 3a** | Jurisdictions & entity configuration | Planned |
| **Phase 3b** | KYC pack/checklist system | Planned |
| **Phase 3c** | In-house e-signing engine | Planned |
| **Phase 3d** | Visual agreement repository | Planned |
| **Phase 3e** | BoldSign deprecation & integration | Planned |
