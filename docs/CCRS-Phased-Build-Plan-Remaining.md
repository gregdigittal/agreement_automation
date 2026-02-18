# CCRS — Phased Build Plan (Remaining Work)

**Date:** 2026-02-17
**Status:** Phase 1a API migrated to FastAPI. Phase 1a frontend has 11 outstanding bugs.
**Source:** CCRS Requirements v3 Board Edition 4, code audit, and current codebase inventory.

---

## Current State Summary

### What's Done (Phase 1a — API)
The FastAPI backend (`apps/api`) is functional with 41 endpoints across 9 modules:
- Auth (JWT + Azure AD), Regions, Entities, Projects, Counterparties, Counterparty Contacts, Contracts (upload/search/CRUD), Signing Authority, Audit, Health
- Structured logging, global exception handler, RBAC via `require_roles`
- Audit logging on all mutations with IP capture
- Counterparty status enforcement on contract creation
- Fuzzy duplicate detection with substring matching

### What's Done (Phase 1a — Database)
- 8 tables: regions, entities, projects, counterparties, counterparty_contacts, contracts, audit_log, signing_authority
- Full-text search (tsvector), pg_trgm for fuzzy matching, updated_at triggers
- Supabase Storage bucket `contracts`

### What's Broken (Phase 1a — Frontend)
11 items remain from the original audit — **all frontend fixes are unaddressed**:
1. Root page is Vercel boilerplate (shadows dashboard)
2. Entity edit page missing ([id]/page.tsx)
3. Project edit page missing ([id]/page.tsx)
4. Counterparty detail is view-only (no edit, no status management)
5. Contract list has no search/filter UI
6. Audit page is a placeholder
7. API proxy has multipart boundary bug
8. Nav highlighting broken on sub-routes
9. Auth uses deprecated `azure-ad` provider import
10. Dead code in `lib/api.ts`
11. Middleware doesn't redirect authenticated users from /login

### What's Broken (Phase 1a — API)
5 items remain:
1. Azure AD RS256 JWT verification uses client secret instead of JWKS
2. `request.state.user_id` never populated in logging middleware
3. `AuditExportFilters` schema defined but unused
4. `Role` enum defined but unused
5. Only 3 tests exist (all just check 401 on unauthed requests)

---

## Phase 1a-fix — Frontend Remediation + API Polish

**Goal:** Complete Phase 1a to a shippable state before starting new epics.
**Estimated effort:** 2–3 sprints

### Sprint 1a-fix.1: Critical Frontend Fixes

| # | Task | Epic | Priority |
|---|------|------|----------|
| F1 | Delete `app/page.tsx` (Vercel boilerplate) so dashboard route group handles `/` | 1 | Critical |
| F2 | Fix multipart proxy — don't set Content-Type header for FormData bodies | 2 | Critical |
| F3 | Create entity edit page (`entities/[id]/page.tsx` + `edit-entity-form.tsx`) | 7 | High |
| F4 | Create project edit page (`projects/[id]/page.tsx` + `edit-project-form.tsx`) | 7 | High |
| F5 | Add counterparty edit mode to detail page | 8 | High |
| F6 | Add counterparty status management UI (dropdown, reason, supporting doc ref) | 17 | High |
| F7 | Fix nav sub-route highlighting (`startsWith` instead of `===`) | UX | Medium |
| F8 | Replace `<a>` with `<Link>` on dashboard | UX | Low |
| F9 | Fix `auth.ts` — use `microsoft-entra-id` provider import | 1 | Medium |
| F10 | Delete dead `lib/api.ts` | Cleanup | Low |
| F11 | Add authenticated-user redirect from `/login` in middleware | 1 | Medium |
| F12 | Add `.catch()` error handling on all list fetches | UX | Medium |
| F13 | Fix double-encoded error responses in API proxy | UX | Medium |

### Sprint 1a-fix.2: Search/Filter UI + Audit UI + Shared Types

| # | Task | Epic | Priority |
|---|------|------|----------|
| F14 | Create `lib/types.ts` with shared TypeScript interfaces for all API responses | Arch | High |
| F15 | Build contract search/filter UI (search bar, dropdowns for region/entity/project/type/state, pagination) | 2 | High |
| F16 | Build audit trail UI (date pickers, filters, table view using shadcn table, export button) | 14 | High |
| F17 | Replace card grids with data tables (shadcn Table) for contracts and audit | UX | Medium |
| F18 | Add counterparty contacts management to counterparty detail page | 8 | Medium |

### Sprint 1a-fix.3: API Polish + Test Coverage

| # | Task | Epic | Priority |
|---|------|------|----------|
| A1 | Fix Azure AD JWKS verification (fetch public keys from Microsoft endpoint) | 1 | High |
| A2 | Set `request.state.user_id` in auth dependency for logging middleware | Obs | Medium |
| A3 | Wire `AuditExportFilters` schema to the export endpoint | 14 | Low |
| A4 | Use `Role` enum in `require_roles` calls | Arch | Low |
| A5 | Add `X-Total-Count` to contracts and signing_authority list endpoints | 2 | Medium |
| A6 | Add pagination to counterparty contacts list | 8 | Low |
| A7 | Add `CreateContractInput` Pydantic schema (currently inline Form params) | 2 | Low |
| A8 | Add Pydantic response models to all endpoints for OpenAPI docs | Arch | Medium |
| A9 | Write comprehensive test suite (target: 80%+ coverage) — mock Supabase via dependency override | All | High |
| A10 | Fix CI pipeline — Python backend, remove `continue-on-error` | CI | High |

---

## Phase 1b — Workflows, Signing & Amendments

**Goal:** Workflow engine, visual builder, Boldsign e-signature, SharePoint collaboration, Merchant Agreement automation, amendments/renewals.
**Epics:** 4, 5, 9, 10, 15
**Dependencies:** Phase 1a complete (org structure, contracts, counterparties, signing authority all working).
**Estimated effort:** 10–12 sprints

### Phase 1b Database Schema

New tables required:

```
workflow_templates          — Versioned workflow definitions per contract type
  id, version, name, contract_type, region_id, entity_id, project_id,
  stages_json (JSONB), status (draft/published/deprecated),
  created_by, published_at, created_at, updated_at

workflow_instances          — Active workflow bound to a contract
  id, contract_id (FK), template_id (FK), template_version,
  current_stage, state (active/completed/cancelled),
  created_at, updated_at

workflow_stage_actions      — Approval/rejection actions on stages
  id, instance_id (FK), stage_name, action (approve/reject/rework),
  actor_id, actor_email, comment, artifacts_json,
  created_at

wiki_contracts              — Template/precedent library
  id, name, category, region_id, version, status (draft/review/published/deprecated),
  storage_path, file_name, created_by, published_at,
  created_at, updated_at

boldsign_envelopes          — Signing envelope tracking
  id, contract_id (FK), boldsign_document_id, status,
  signing_order (parallel/sequential),
  signers_json (JSONB), webhook_payload_json,
  sent_at, completed_at, created_at, updated_at

contract_links              — Parent/child relationships (amendments, renewals, side letters)
  id, parent_contract_id (FK), child_contract_id (FK),
  link_type (amendment/renewal/side_letter/addendum),
  created_at

contract_key_dates          — Extracted or manual key dates
  id, contract_id (FK), date_type, date_value, reminder_days,
  is_verified, verified_by, created_at, updated_at

merchant_agreement_inputs   — Structured inputs for document generation
  id, contract_id (FK), template_id (FK → wiki_contracts),
  vendor_name, merchant_fee, region_terms_json,
  generated_at, created_at
```

### Phase 1b Sprints

#### Sprint 1b.1–1b.2: Workflow Engine (Epic 4)

**Backend:**
- `app/workflows/` module — templates CRUD + instances CRUD + stage action endpoints
- Workflow template schema: stages as JSONB with `name`, `type` (approval/signing/review), `owners` (role or user), `required_artifacts`, `allowed_transitions`, `sla_hours`
- Template versioning: publish creates a new version, contracts bind to a specific version
- Workflow instance management: `POST /contracts/{id}/workflow` starts an instance, `POST /workflow-instances/{id}/stages/{stage}/action` records approve/reject/rework
- State machine validation: enforce allowed transitions, prevent skipping stages
- Signing authority check at signing stage: query `signing_authority` table, block if no matching authority

**Frontend:**
- Admin page to list/create/edit workflow templates
- Contract detail shows current workflow stage and history of actions

#### Sprint 1b.3–1b.4: Visual Workflow Builder (Epic 5)

**Frontend:**
- Install `reactflow` (React Flow) in `apps/web`
- Workflow builder page: drag-and-drop stages, connect transitions, side panel for stage config
- Save/load workflow definition as JSON via API
- Validation before publish: must have at least one approval stage, at least one signing stage, no orphan nodes
- Visual diff between template versions

#### Sprint 1b.5–1b.6: WikiContracts + Document Generation (Epics 4, 10)

**Backend:**
- `app/wiki_contracts/` module — template/precedent library CRUD
- Admin publishing controls: draft → review → published → deprecated
- Region-specific filtering
- `app/doc_generation/` module — `python-docx-template` for Merchant Agreement generation
- Template placeholder system: `{{vendor_name}}`, `{{merchant_fee}}`, `{{region_terms}}`
- `POST /merchant-agreements/generate` — accepts structured inputs, generates DOCX, stores in Supabase Storage, creates contract record

**Frontend:**
- WikiContracts library browser (list, search, upload templates)
- Merchant Agreement generation form (vendor name, fee, region, terms → generate → preview → send to signing)

#### Sprint 1b.7–1b.8: Boldsign Integration + Commercial Workflow (Epics 9, 10)

**Backend:**
- `app/boldsign/` module — Boldsign API client
- `POST /contracts/{id}/send-to-sign` — validates signing stage, checks authority, creates Boldsign envelope
- `POST /webhooks/boldsign` — public webhook endpoint (verify Boldsign signature), updates `boldsign_envelopes` and contract `signing_status`
- On completion: download executed PDF from Boldsign, store in Supabase Storage, lock contract as `executed`
- Parallel/sequential signing order support
- Countersigning workflow for third-party paper
- SharePoint link storage: `sharepoint_url` and `sharepoint_version` fields on contracts

**Frontend:**
- "Send to Sign" button on contract detail (only visible at signing stage)
- Signing status tracker (sent → viewed → partially signed → completed/declined)
- SharePoint link field in contract edit form

#### Sprint 1b.9–1b.10: TiTo Validation + Amendments & Renewals (Epics 10, 15)

**Backend:**
- `GET /tito/validate` — public API (API key auth, not JWT) returning `{valid: bool, status, contract_id, signed_at}` for vendor/entity/region/project
- Performance target: p95 < 500ms (cache with Redis/in-memory if needed)
- All validation calls logged to audit
- `app/contract_links/` module — CRUD for parent/child links
- `POST /contracts/{id}/amendments` — creates amendment linked to parent, inherits classification
- `POST /contracts/{id}/renewals` — extension (update dates) or new version (new record linked to predecessor)
- `POST /contracts/{id}/side-letters` — linked side letter
- Parent contract detail API includes all linked amendments/renewals/side letters
- Contract key dates module: `app/key_dates/` — CRUD + reminder configuration

**Frontend:**
- Contract detail: "Amendments", "Renewals", "Side Letters" tabs showing linked documents
- "Create Amendment" / "Create Renewal" / "Add Side Letter" actions
- Renewal type selector (extend vs. new version)
- TiTo status indicator on Merchant Agreement detail

---

## Phase 1c — AI Intelligence, Monitoring & Escalation

**Goal:** Hybrid AI (Claude Agent SDK + Messages API), LLM workflow generation, reminders, dashboards, multi-language, escalation.
**Epics:** 3, 6, 11, 12, 13, 16
**Dependencies:** Phase 1b complete (workflow engine, WikiContracts, contract key dates).
**Estimated effort:** 10–12 sprints

### Phase 1c Database Schema

New tables required:

```
ai_analysis_results         — AI extraction results per contract
  id, contract_id (FK), analysis_type (summary/extraction/risk/deviation/obligations),
  status (pending/processing/completed/failed),
  result_json (JSONB), evidence_json (JSONB),
  confidence_score FLOAT, model_used,
  token_usage_input INT, token_usage_output INT, cost_usd DECIMAL,
  processing_time_ms INT, agent_budget_usd DECIMAL,
  created_at, updated_at

ai_extracted_fields         — Structured extracted fields per contract
  id, contract_id (FK), analysis_id (FK → ai_analysis_results),
  field_name, field_value, evidence_clause, evidence_page,
  confidence FLOAT, is_verified BOOLEAN, verified_by,
  verified_at, created_at, updated_at

obligations_register        — Extracted ongoing obligations
  id, contract_id (FK), analysis_id (FK),
  obligation_type (reporting/sla/insurance/deliverable/other),
  description, due_date, recurrence,
  responsible_party, status (active/completed/waived),
  evidence_clause, confidence FLOAT,
  created_at, updated_at

reminders                   — Configurable reminders for key dates
  id, contract_id (FK), key_date_id (FK → contract_key_dates),
  reminder_type (expiry/renewal_notice/payment/sla/custom),
  lead_days INT, channel (email/teams/calendar),
  recipient_email, recipient_user_id,
  last_sent_at, next_due_at, is_active BOOLEAN,
  created_at, updated_at

escalation_rules            — Per-workflow-template escalation config
  id, workflow_template_id (FK), stage_name,
  sla_breach_hours INT, tier INT,
  escalate_to_role TEXT, escalate_to_user_id TEXT,
  created_at, updated_at

escalation_events           — Active escalation instances
  id, workflow_instance_id (FK), rule_id (FK),
  contract_id (FK), stage_name, tier INT,
  escalated_at, resolved_at, resolved_by,
  created_at

notifications               — Notification log
  id, recipient_email, recipient_user_id,
  channel (email/teams), subject, body,
  related_resource_type, related_resource_id,
  status (pending/sent/failed), sent_at,
  created_at

contract_languages          — Language tags for multi-language support
  id, contract_id (FK), language_code, is_primary BOOLEAN,
  storage_path, file_name,
  created_at
```

### Phase 1c Sprints

#### Sprint 1c.1–1c.3: AI Contract Intelligence (Epic 3)

**Backend:**
- Add dependencies: `anthropic`, `anthropic[agent]` (Claude Agent SDK)
- `app/ai/` package with:
  - `config.py` — AI settings (model, max_budget_usd, timeout)
  - `messages_client.py` — Anthropic Messages API client for simple tasks
  - `agent_client.py` — Claude Agent SDK client for complex tasks
  - `mcp_tools.py` — MCP tool definitions: `query_org_structure`, `query_authority_matrix`, `query_wiki_contracts`, `query_counterparty`
  - `prompts/` — System prompts for each analysis type
  - `schemas.py` — Pydantic models for AI response validation
- `app/ai_analysis/` module:
  - `POST /contracts/{id}/analyze` — trigger AI analysis (async job)
  - `GET /contracts/{id}/analysis` — get analysis results
  - `POST /contracts/{id}/analysis/{field_id}/verify` — mark field as verified
  - `PATCH /contracts/{id}/analysis/{field_id}` — correct extracted value
- Task routing: simple summaries → Messages API; full extraction/risk/obligations → Agent SDK
- Per-contract cost tracking: log `token_usage_input`, `token_usage_output`, `cost_usd`
- Budget enforcement: agent `max_budget_usd` per analysis; fallback to Messages API on exceed
- `app/obligations/` module — CRUD for obligations register with filtering/export
- Bulk upload pipeline: async processing of multiple contracts via background worker

**Frontend:**
- Contract detail: "AI Analysis" tab showing summary, extracted fields, risk score, template deviations
- Each field shows evidence snippet and confidence badge
- "Verify" and "Correct" actions on extracted fields
- Obligations register view with filters (type, status, due date) and CSV export
- AI cost indicator on contract detail

#### Sprint 1c.4–1c.5: LLM Workflow Generator (Epic 6)

**Backend:**
- `app/ai/workflow_generator.py` — Claude Agent SDK agent that:
  1. Receives natural language workflow description
  2. Queries org structure, authority matrix, and existing templates via MCP tools
  3. Generates workflow template JSON matching the schema
  4. Validates against system schema
  5. Self-corrects if validation fails
  6. Returns draft with confidence/explanations per stage
- `POST /workflows/generate` — accepts `{description, region_id, entity_id, project_id}`, returns draft workflow JSON
- Draft is NOT auto-published — saved as `status: draft`

**Frontend:**
- "Generate with AI" button on workflow builder page
- Natural language input dialog
- Shows generated draft in the visual builder with confidence indicators
- Admin reviews, edits, and explicitly publishes

#### Sprint 1c.6–1c.7: Monitoring, Alerts & Escalation (Epics 11, 16)

**Backend:**
- `app/reminders/` module — CRUD for reminder rules, background scheduler
- `app/escalation/` module — CRUD for escalation rules, escalation event processing
- `app/notifications/` module — notification service (email via SendGrid/Graph API, Teams via Graph API)
- Background workers (Celery + Redis or APScheduler):
  - Reminder job: runs daily, evaluates key dates, sends reminders at configured lead times
  - Escalation job: runs hourly, evaluates workflow SLAs, creates escalation events, sends notifications
  - Deduplication: don't re-send same reminder within window
- `GET /escalations/active` — dashboard of all currently escalated items
- Escalation auto-clear when underlying action completes

**Frontend:**
- Reminder configuration in contract detail (add/edit/remove reminders per key date)
- Escalation rules config in workflow template builder
- Executive escalation dashboard: table of escalated items with contract ref, stage, SLA breach duration, escalation tier
- Notification preferences page (email/Teams toggles)

#### Sprint 1c.8–1c.9: Reporting & Analytics Dashboard (Epic 12)

**Frontend:**
- Executive dashboard with:
  - Contract status summary (pie/bar): by workflow state, by type (Commercial/Merchant)
  - Expiry horizon timeline: contracts expiring in 30/60/90 days
  - Signing status breakdown: sent, viewed, partially signed, completed, declined
  - Drill-down filters: region, entity, project, counterparty, date range
- Export to CSV and PDF
- API aggregation endpoints:
  - `GET /reports/contract-status` — counts by state/type
  - `GET /reports/expiry-horizon` — contracts grouped by expiry window
  - `GET /reports/signing-status` — signing pipeline funnel
  - `GET /reports/ai-costs` — token usage and cost by contract/period

#### Sprint 1c.10: Multi-Language Support (Epic 13)

**Backend:**
- `app/contract_languages/` module — CRUD for language versions
- `POST /contracts/{id}/languages` — attach language version (file upload + language tag)
- `GET /contracts/{id}/languages` — list all language versions
- AI processing: instruction prompt to detect bilingual documents and deduplicate clauses/dates
- Summaries default to English with optional secondary language output

**Frontend:**
- Contract detail: "Languages" tab showing all attached versions
- Upload additional language version form (file + language selector)
- AI analysis shows language detection result

---

## Deferred Items (Track Before Phase 2)

These items were intentionally deferred from Phase 1c prompts and must be addressed before production:

| # | Item | Reason Deferred | When to Address |
|---|------|----------------|-----------------|
| D1 | **RLS policies for all 20+ tables** — Row-Level Security policies to enforce tenant/role isolation at the database level | Separate security hardening pass needed; too large for a single prompt | Dedicated security sprint before production launch |
| D2 | **SharePoint URL/version fields in Pydantic schemas** — `sharepoint_url` and `sharepoint_version` fields exist in DB but are missing from contract create/update Pydantic models | Intentional omission — SharePoint integration not yet active | When SharePoint integration is activated (Phase 1b Sprint 1b.7–8 or later) |
| D3 | **`preferred_language` field in counterparty create/update schemas** — DB column exists but not exposed in API input schemas | Multi-language support deferred to Phase 1c Sprint 1c.10 | Phase 1c Sprint 1c.10 (Multi-Language Support) |

---

## Phase 2 — Future Backlog

As prioritized by the board:
- Vendor self-service portal
- Advanced clause negotiation / redlining engine
- Regulatory integration
- Advanced search (Meilisearch/Elasticsearch)
- Redis caching layer
- OpenTelemetry APM integration

---

## Dependency Graph

```
Phase 1a (DONE) ──→ Phase 1a-fix (frontend + API polish)
                          │
                          ▼
                    Phase 1b
                    ├── Sprint 1b.1-2: Workflow Engine (Epic 4)
                    ├── Sprint 1b.3-4: Visual Builder (Epic 5)
                    ├── Sprint 1b.5-6: WikiContracts + DocGen (Epic 4, 10)
                    ├── Sprint 1b.7-8: Boldsign + Commercial (Epic 9, 10)
                    └── Sprint 1b.9-10: TiTo + Amendments (Epic 10, 15)
                          │
                          ▼
                    Phase 1c
                    ├── Sprint 1c.1-3: AI Intelligence (Epic 3) ←── needs WikiContracts for template comparison
                    ├── Sprint 1c.4-5: LLM Workflow Gen (Epic 6) ←── needs Workflow Engine
                    ├── Sprint 1c.6-7: Monitoring + Escalation (Epic 11, 16) ←── needs Workflow + Key Dates
                    ├── Sprint 1c.8-9: Reporting Dashboard (Epic 12)
                    └── Sprint 1c.10: Multi-Language (Epic 13)
                          │
                          ▼
                    Phase 2 (future)
```

---

## Technology Additions by Phase

| Phase | New Dependencies (Backend) | New Dependencies (Frontend) |
|-------|---------------------------|----------------------------|
| 1a-fix | — | — |
| 1b | `python-docx-template`, `boldsign` (or httpx), `redis` (optional), `celery` (optional) | `reactflow`, `@reactflow/core` |
| 1c | `anthropic`, `anthropic[agent]` (Claude Agent SDK), `celery`, `redis`, `apscheduler` | Chart library (e.g. `recharts`), `date-fns` |
