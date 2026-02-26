# CCRS User Manual — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a comprehensive user manual with ~20 Mermaid flowcharts covering all CCRS features for internal staff, executives, and external parties, plus an enhanced in-app Help page.

**Architecture:** 15 standalone markdown files in `docs/user-manual/` each with embedded Mermaid diagrams. A `README.md` acts as the table of contents. The existing Filament Help & Guide blade view is rewritten with collapsible sections mirroring the manual, including inline flowcharts rendered via a JS Mermaid CDN script.

**Tech Stack:** Markdown + Mermaid (GitHub-native rendering), Blade + Filament `<x-filament::section>` components, Mermaid JS CDN for in-app rendering.

---

## Task 1: Create README.md Table of Contents

**Files:**
- Create: `docs/user-manual/README.md`

**Step 1: Write the table of contents file**

Create `docs/user-manual/README.md` with:
- Title: "CCRS — User Manual"
- Brief intro paragraph: "Comprehensive guide to the Contract & Compliance Review System"
- Audience note: Internal staff (6 roles), executives, external counterparties/vendors
- Numbered links to all 15 section files (relative markdown links)
- "Quick Links" section: role matrix (section 14), external signing (section 13), vendor portal (section 12)

**Step 2: Commit**

```bash
git add docs/user-manual/README.md
git commit -m "docs: add user manual table of contents"
```

---

## Task 2: Section 1 — Platform Overview

**Files:**
- Create: `docs/user-manual/01-platform-overview.md`

**Flowcharts to include:**
1. **System Architecture Diagram** — `graph TD` showing: Users → Azure AD → Filament Admin Panel → Laravel Services → MySQL + Redis + S3. Also show: External Signers → Signing Controller → SigningService. And: AI Worker ← ProcessAiAnalysis Job.
2. **Navigation Map by Role** — `graph LR` showing nav groups (Contracts, Counterparties, Workflows, KYC, Org Structure, Compliance, Admin, Settings, Reports) with role annotations showing who sees each group.

**Content to write:**
- What CCRS is (1 paragraph)
- Key capabilities list: contract lifecycle, e-signing, AI analysis, compliance, vendor portal
- Platform architecture (reference the Mermaid diagram)
- Navigation overview with the role-based nav map diagram
- Six role descriptions (system_admin, legal, commercial, finance, operations, audit) — 2-3 sentences each
- URL reference: app URL, help contact

**Step: Commit**

```bash
git add docs/user-manual/01-platform-overview.md
git commit -m "docs: section 1 — platform overview with architecture + nav diagrams"
```

---

## Task 3: Section 2 — Getting Started

**Files:**
- Create: `docs/user-manual/02-getting-started.md`

**Content to write:**
- Azure AD login flow (step-by-step with what the user sees)
- First-time login: Azure group → role auto-assignment
- Dashboard overview: describe each widget (Contract Status, Expiry Horizon, Pending Workflows, Active Escalations, AI Cost, Obligation Tracker)
- Navigation: left sidebar, search (Cmd+K), breadcrumbs
- Profile / notification preferences

No Mermaid diagrams needed — this is procedural text.

**Step: Commit**

```bash
git add docs/user-manual/02-getting-started.md
git commit -m "docs: section 2 — getting started guide"
```

---

## Task 4: Section 3 — Contract Lifecycle

**Files:**
- Create: `docs/user-manual/03-contract-lifecycle.md`

**Flowcharts to include:**
3. **Contract Lifecycle State Machine** — `stateDiagram-v2` showing: Draft → Review → Approval → Signing → Countersign → Executed → Archived. Include notes on each state (who can transition, what actions available). Show Cancelled as a possible exit from any active state.
4. **Contract Creation Flow** — `flowchart TD` showing: User clicks New Contract → Select Region/Entity/Project → Select Counterparty → Choose Contract Type → Upload file → Save → Draft state → Workflow template auto-assigned.

**Content to write:**
- Overview of the 7 workflow states with descriptions
- Creating a contract (step-by-step matching the flowchart)
- Contract types: Commercial vs Merchant
- File upload (PDF/DOCX)
- SharePoint URL/version tracking
- Contract actions by state: Download, AI Analysis, Amendments, Renewals, Side Letters, Send for Signing
- Access control: restricted contracts, authorized users
- Immutability: Executed/Archived contracts are read-only
- Amendments, renewals, and side letters (linked contracts)

**Step: Commit**

```bash
git add docs/user-manual/03-contract-lifecycle.md
git commit -m "docs: section 3 — contract lifecycle with state machine + creation flow"
```

---

## Task 5: Section 4 — Counterparty Management

**Files:**
- Create: `docs/user-manual/04-counterparty-management.md`

**Flowcharts to include:**
10. **Counterparty Onboarding + KYC** — `flowchart TD` showing: Create Counterparty → Check Duplicates → (duplicates found? → Review/Acknowledge) → Save → Status Active → KYC template assigned → KYC checklist completed → Ready for contracts.
11. **Duplicate Detection + Merge** — `flowchart TD` showing: System Admin selects source → Select target → Confirm merge → All contracts moved to target → Source marked "Merged".
12. **Override Request Approval** — `flowchart LR` showing: Commercial user → Submit Override (reason) → Pending → Legal/Admin reviews → Approved OR Rejected (with comment).

**Content to write:**
- Creating a counterparty (fields: legal name, registration, jurisdiction, status)
- Duplicate detection (how the "Check for Duplicates" button works)
- Status management: Active, Suspended, Blacklisted
- Override requests: when needed, how to submit, approval flow
- Merging counterparties (admin-only)
- Contacts relation manager (managing contact persons)
- Stored signatures for counterparties (relation manager)

**Step: Commit**

```bash
git add docs/user-manual/04-counterparty-management.md
git commit -m "docs: section 4 — counterparty management with onboarding, merge, override flows"
```

---

## Task 6: Section 5 — Signing & E-Signatures

**Files:**
- Create: `docs/user-manual/05-signing.md`

**Flowcharts to include:**
5. **Sequential Signing Flow** — `flowchart TD` showing: Session Created → Email to Signer 1 → Signer 1 opens link → Views PDF (page enforcement) → Captures signature (draw/type/upload/webcam) → Submits → advanceSession() → Email to Signer 2 → ... → All signed → completeSession() → PDF overlay + audit certificate → Contract marked Signed → Completion emails.
6. **Parallel Signing Flow** — `flowchart TD` showing: Session Created → Emails to ALL signers simultaneously → Each signer signs independently → advanceSession() checks all signed → completeSession().
7. **Stored Signature Management** — `flowchart TD` showing: User goes to My Signatures → Choose capture method (Draw/Type/Upload/Webcam) → Save to S3 → Set as default → Available in future signing sessions.
8. **Webcam Signature Capture** — `flowchart LR` showing: Start Camera → Hold paper to camera → Capture → Grayscale conversion → Threshold → Background removal → Preview → Accept/Retake → Save.
9. **Page Enforcement (Viewing + Initials)** — `flowchart TD` showing: PDF renders → Intersection Observer tracks pages → Progress bar updates → (require_all_pages_viewed? → all pages must be scrolled through) → (require_page_initials? → each page gets "Initial" button → mini-canvas capture → mark page initialed) → Submit enabled when all requirements met.

**Content to write:**
- Overview of in-house signing system
- Creating a signing session (from contract actions)
- Sequential vs parallel signing explained
- The signer's experience (external signer perspective):
  - Receiving the email
  - Opening the link
  - Viewing the PDF with page enforcement
  - Choosing a signature method (draw, type, upload, webcam)
  - Using a stored signature
  - Submitting
  - Declining (with reason)
- Stored signatures: My Signatures page, capture methods, defaults
- Template signing blocks (WikiContract field placement)
- Page enforcement: viewing requirement, per-page initials
- Save for future use (post-signing checkbox)
- Completion: PDF overlay, audit certificate, document hash
- Security: SHA-256 token hashing, expiry, audit trail

**Step: Commit**

```bash
git add docs/user-manual/05-signing.md
git commit -m "docs: section 5 — signing with sequential/parallel/webcam/enforcement flows"
```

---

## Task 7: Section 6 — Workflow Templates

**Files:**
- Create: `docs/user-manual/06-workflow-templates.md`

**Flowcharts to include:**
4. **Workflow Approval Flow with Escalation** — `flowchart TD` showing: Contract enters stage → Assigned approver notified → (Approve → next stage) OR (Reject → previous stage) → SLA timer running → (SLA breached? → Escalation Tier 1 → still no action? → Tier 2 → Tier 3).

**Content to write:**
- What workflow templates are
- Creating a template (admin-only): name, contract type, region/entity/project scoping
- Visual workflow builder: adding stages, roles, duration, approval flags
- Publishing a template (increments version)
- AI workflow generation (describe → AI builds stages)
- Escalation rules: SLA thresholds, tier notifications
- How templates auto-assign to new contracts

**Step: Commit**

```bash
git add docs/user-manual/06-workflow-templates.md
git commit -m "docs: section 6 — workflow templates with approval + escalation flow"
```

---

## Task 8: Section 7 — AI Analysis & Redlining

**Files:**
- Create: `docs/user-manual/07-ai-analysis.md`

**Flowcharts to include:**
13. **AI Analysis Trigger + Results** — `flowchart TD` showing: User clicks "AI Analysis" on contract → Select type (Summary/Extraction/Risk/Deviation/Obligations) → ProcessAiAnalysis job queued → AI Worker processes → Results stored → AiAnalysisResult visible in relation manager → Cost tracked.
14. **Redline Review Session** — `flowchart TD` showing: User clicks "Start Redline Review" → Select WikiContract template → RedlineService creates session → AI performs clause-by-clause comparison → Results shown on RedlineSessionPage → User reviews accepted/rejected clauses.

**Content to write:**
- Five analysis types explained (what each produces)
- Triggering an analysis (step-by-step)
- Viewing results in the AI Analysis tab
- Cost tracking (tokens, USD)
- Redline review: what it is, when to use it, how to start one
- WikiContract templates (the reference documents for deviation/redlining)

**Step: Commit**

```bash
git add docs/user-manual/07-ai-analysis.md
git commit -m "docs: section 7 — AI analysis + redlining with trigger and session flows"
```

---

## Task 9: Section 8 — Reports & Analytics

**Files:**
- Create: `docs/user-manual/08-reports-analytics.md`

**Flowcharts to include:**
19. **Report Export Flow** — `flowchart LR` showing: Reports page → Apply filters (state, type, region, entity) → Click Export Excel OR Export PDF → Server generates file → Browser downloads.

**Content to write:**
- Reports page: what it shows, available filters
- Exporting to Excel and PDF
- Analytics Dashboard: describe each widget (pipeline funnel, risk distribution, compliance, obligations, AI usage, workflow performance)
- AI Cost Report page: tracking API spend by analysis type
- Who can access what (finance, exec, admin, audit)

**Step: Commit**

```bash
git add docs/user-manual/08-reports-analytics.md
git commit -m "docs: section 8 — reports + analytics with export flow"
```

---

## Task 10: Section 9 — Bulk Operations

**Files:**
- Create: `docs/user-manual/09-bulk-operations.md`

**Flowcharts to include:**
15. **Bulk Contract Upload Processing** — `flowchart TD` showing: Admin uploads CSV manifest + ZIP → Parse CSV rows → Extract ZIP to S3 → Create BulkUpload record → Queue ProcessContractBatch per row → Each row: validate → create Contract → link file → Progress display updates (pending → processing → completed/failed).
16. **Bulk Data Import** — `flowchart LR` showing: Select data type → Download CSV template → Fill in data → Upload CSV → BulkDataImportService validates rows → Success/failure counts → Error report for failed rows.

**Content to write:**
- Bulk Data Upload: supported types (Regions, Entities, Projects, Users, Counterparties)
- Step-by-step: download template, fill in, upload, review results
- Code field requirements (region_code, entity_code references)
- Bulk Contract Upload: CSV manifest format, ZIP file structure
- Limits: 500 files, 50MB per file
- Monitoring progress in real-time

**Step: Commit**

```bash
git add docs/user-manual/09-bulk-operations.md
git commit -m "docs: section 9 — bulk operations with contract upload + data import flows"
```

---

## Task 11: Sections 10-11 — Notifications & Org Setup

**Files:**
- Create: `docs/user-manual/10-notifications-reminders.md`
- Create: `docs/user-manual/11-organization-setup.md`

**Flowcharts to include:**
17. **Reminder/Notification Dispatch** — `flowchart TD` showing: Key Date approaches → ReminderService checks lead_days → (email channel? → Send email) → (teams channel? → Post to Teams) → (calendar channel? → Generate ICS) → Mark reminder as sent → Update last_sent_at.

**Content for section 10:**
- Notification channels: email, Teams, in-app, calendar (ICS)
- Notification Preferences page: per-category channel toggles
- Reminders: how they link to key dates, lead days, active toggle
- Key Dates page: viewing upcoming milestones across all contracts
- Escalation notifications

**Content for section 11:**
- Setup order: Regions → Entities → Projects (dependency chain)
- Regions: name, code
- Entities: legal details, parent entity hierarchy, jurisdictions
- Projects: entity assignment, code
- Jurisdictions: country codes, regulatory bodies
- Signing Authorities: entity/project scoping, user assignment

**Step: Commit**

```bash
git add docs/user-manual/10-notifications-reminders.md docs/user-manual/11-organization-setup.md
git commit -m "docs: sections 10-11 — notifications/reminders + org setup"
```

---

## Task 12: Sections 12-13 — External User Guides

**Files:**
- Create: `docs/user-manual/12-vendor-portal.md`
- Create: `docs/user-manual/13-external-signing-guide.md`

**Flowcharts to include:**
18. **Vendor Portal Login + Access** — `flowchart TD` showing: Vendor visits /vendor/login → Enters email → Magic link sent → Click link → Token verified → Session created → Dashboard: view contracts, upload documents → Logout.

**Content for section 12 (Vendor Portal):**
- What the vendor portal is (external-facing for counterparties)
- Magic link authentication (no password needed)
- Viewing assigned contracts
- Downloading contract files
- Uploading documents
- Dashboard overview

**Content for section 13 (External Signing Guide) — written for non-technical external signers:**
- You received a signing email — what it means
- Clicking the signing link
- Viewing the contract document
- Page enforcement: scrolling through all pages, initialing
- Choosing a signature method (Draw, Type, Upload, Camera)
- Using a saved signature
- Submitting your signature
- Saving for future use
- Declining to sign
- What happens after you sign
- Troubleshooting: expired link, camera access, browser requirements

**Step: Commit**

```bash
git add docs/user-manual/12-vendor-portal.md docs/user-manual/13-external-signing-guide.md
git commit -m "docs: sections 12-13 — vendor portal + external signing guide"
```

---

## Task 13: Sections 14-15 — Role Matrix & Compliance

**Files:**
- Create: `docs/user-manual/14-role-reference-matrix.md`
- Create: `docs/user-manual/15-compliance-audit.md`

**Flowcharts to include:**
20. **Role-Based Access Decision Tree** — `flowchart TD` showing: User requests resource → Check role → (system_admin? → Full access) → (legal? → Check resource type → Contracts: view+edit, Counterparties: view+edit, Workflows: view-only, Audit: view) → ... for each role. Include restricted contract check: is_restricted? → Check authorized_users list.

**Content for section 14 (Role Matrix):**
- Full permissions grid table: rows = features/actions, columns = 6 roles
- Cover: Contracts (view/create/edit/delete), Counterparties, Workflow Templates, KYC, Org Structure, Audit Logs, Bulk Uploads, Reports, Analytics, Signing Authorities, Override Requests, Escalations
- Role-based access decision tree flowchart
- Notes on restricted contracts and access scope guards

**Content for section 15 (Compliance & Audit):**
- Audit log: what is logged, immutability, who can view
- Contract immutability (executed/archived read-only)
- Document integrity: SHA-256 hashing at signing creation and completion
- Signing audit trail: every view, sign, decline logged with IP + user agent
- Regulatory frameworks (feature-flagged)
- Compliance findings per contract

**Step: Commit**

```bash
git add docs/user-manual/14-role-reference-matrix.md docs/user-manual/15-compliance-audit.md
git commit -m "docs: sections 14-15 — role matrix + compliance/audit"
```

---

## Task 14: Update In-App Help Page

**Files:**
- Modify: `resources/views/filament/pages/help-guide.blade.php`

**Step 1: Rewrite the blade view**

Replace the existing 8 sections with 15 collapsible sections matching the manual chapters. Each section contains:
- Condensed version of the corresponding manual section (key points only, not full detail)
- 1-2 inline Mermaid diagrams for the most important flowcharts per section (rendered via JS)
- The role permissions table (section 14) included as an HTML table
- FAQ section kept and expanded

Add a Mermaid JS CDN script in `@push('scripts')`:
```html
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>mermaid.initialize({startOnLoad: true, theme: 'neutral'});</script>
```

Mermaid diagrams in blade use `<div class="mermaid">` blocks.

Sections to include (all collapsible, first expanded, rest collapsed):
1. Getting Started (expanded by default)
2. Contract Lifecycle (with state machine diagram)
3. Creating a Contract
4. Counterparty Management
5. Signing & E-Signatures (with sequential signing flow diagram)
6. Workflow Templates
7. AI Analysis & Redlining
8. Reports & Analytics
9. Bulk Operations
10. Notifications & Reminders
11. Organization Setup
12. Vendor Portal
13. External Signing (for reference — link to full guide)
14. Role Permissions (HTML table)
15. FAQ

**Step 2: Commit**

```bash
git add resources/views/filament/pages/help-guide.blade.php
git commit -m "feat: rewrite Help page with 15 sections + inline Mermaid flowcharts"
```

---

## Task 15: Run Tests + Final Commit

**Step 1: Run full test suite**

```bash
php -d memory_limit=256M vendor/bin/pest
```

Expected: All 418+ tests pass. The Help page test should still pass (it checks for page load and heading).

**Step 2: Verify Help page test specifically**

```bash
php -d memory_limit=256M vendor/bin/pest --filter=HelpGuide
```

Expected: PASS

**Step 3: Final commit with all manual files**

If any test adjustments are needed (e.g. the HelpGuidePage test checks for specific content), update the test to match the new content.

```bash
git add -A
git commit -m "docs: complete CCRS user manual — 15 sections, 20 flowcharts, enhanced Help page"
```

**Step 4: Push to all remotes**

```bash
git push origin laravel-migration
git push ccrs laravel-migration
```

---

## Summary

| Task | Files | Flowcharts | Description |
|------|-------|------------|-------------|
| 1 | README.md | 0 | Table of contents |
| 2 | 01-platform-overview.md | 2 | Architecture + nav map |
| 3 | 02-getting-started.md | 0 | Login, dashboard, navigation |
| 4 | 03-contract-lifecycle.md | 2 | State machine + creation flow |
| 5 | 04-counterparty-management.md | 3 | Onboarding, merge, override |
| 6 | 05-signing.md | 5 | Sequential, parallel, webcam, enforcement, stored sigs |
| 7 | 06-workflow-templates.md | 1 | Approval + escalation |
| 8 | 07-ai-analysis.md | 2 | Analysis trigger + redline session |
| 9 | 08-reports-analytics.md | 1 | Export flow |
| 10 | 09-bulk-operations.md | 2 | Contract upload + data import |
| 11 | 10 + 11 | 1 | Notification dispatch |
| 12 | 12 + 13 | 1 | Vendor portal login |
| 13 | 14 + 15 | 1 | Access decision tree |
| 14 | help-guide.blade.php | 2 inline | In-app Help rewrite |
| 15 | — | 0 | Tests + final push |
| **Total** | **17 files** | **20 diagrams** | |
