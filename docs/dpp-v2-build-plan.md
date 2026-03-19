# DPP v2 — Build Plan & Scope Verification

> **Purpose:** This document defines the intended scope, module feature set, infrastructure requirements, and phased build plan for the Digittal Productivity Platform (DPP) v2.
>
> **Status:** Confirmed — sign-off complete 2026-03-19
> **Branch:** `platform/v2-dpp` (forked from `laravel-migration` at commit `1142e11`)
> **Date:** 2026-03-19

---

## 1. Current State — What Is Already Built

CCRS (the Agreements module) is complete and production-ready. The table below shows what has been delivered and is live on `laravel-migration` / `sandbox`.

| Area | Status | Details |
|---|---|---|
| Laravel 12 + Filament 3 monolith | ✅ Complete | PHP 8.4, Tailwind v4, 59 models, 25 services |
| Contract lifecycle | ✅ Complete | Full state machine: draft → executed → archived |
| AI analysis pipeline | ✅ Complete | 5 analysis types, FastAPI sidecar, Claude Sonnet |
| In-house e-signing | ✅ Complete | PDF.js, TCPDF/FPDI, audit certificate, token-based |
| Vendor portal | ✅ Complete | Magic-link auth, VendorUser guard, KYC packs |
| Workflow engine | ✅ Complete | Template-driven, version-controlled, visual builder |
| Compliance + Redlining | ✅ Complete | Feature-flagged Phase 2 modules, enabled |
| Advanced analytics | ✅ Complete | Portfolio dashboards, export, AI cost tracking |
| SharePoint integration | ✅ Complete | Graph API, link/list folder, tenant-scoped tokens |
| Multi-tenancy (F-6) | ✅ Complete | stancl/tenancy v3.9.1, database-per-tenant, platform panel |
| Platform superadmin panel | ✅ Complete | PlatformAdmin model, TenantResource, central DB |
| WCAG 2.1 AA (signing/vendor) | ✅ Complete | ARIA, keyboard nav, contrast — 22 regression tests |
| E2E test infrastructure | ✅ Complete | Playwright, 40 E2E tests, global setup/teardown |
| Test suite | ✅ 1038 tests | PestPHP, 0 failures |

### What Is NOT Yet Built (DPP v2 Scope)

The following are planned but do not exist:
- `app/Modules/` directory structure
- `database/migrations/platform/` directory
- DPP modules: Notes, Meetings, Discovery, Tasks, Email, Chat, Automation
- Platform services: EventBus (Redis Streams), VaultEngine, dpp-realtime (WebSockets)
- dpp-ai abstraction layer with Bedrock provider (currently direct Anthropic API only)
- dpp-trawler (semantic discovery agent)
- Temporal Cloud workflow orchestration
- PostgreSQL + pgvector (central, with RLS)
- SeaweedFS S3 storage (S3 adapter installed but pointing at MySQL BLOBs)
- Tenant onboarding automation (seed roles, provision S3 bucket, configure AI secret)

---

## 2. Target Architecture — DPP v2

### 2.1 Sidecar Architecture

The DPP monolith gains 4 new sidecars alongside the existing AI Worker:

```
┌─────────────────────────────────────────────────┐
│                  dpp-core (Laravel)              │
│  app/Modules/Agreements + Notes + Meetings + ... │
│  app/Platform/ (shared services)                 │
└────────┬───────────────────────────┬─────────────┘
         │                           │
    ┌────▼────┐              ┌───────▼──────┐
    │ dpp-ai  │              │ dpp-realtime  │
    │(FastAPI)│              │ (Soketi/Node) │
    │ Bedrock │              │  WebSockets   │
    │ Anthro. │              └───────────────┘
    └─────────┘
         │
    ┌────▼────────┐        ┌──────────────────┐
    │dpp-workflows│        │  dpp-trawler      │
    │(Temporal    │        │ (Python discovery)│
    │  Cloud)     │        │ pgvector search   │
    └─────────────┘        └──────────────────┘
```

### 2.2 Data Store Evolution

| Store | Current | DPP v2 |
|---|---|---|
| Application DB | MySQL 8.0 (per-tenant + central) | MySQL 8.0 (unchanged) |
| Document storage | MySQL BLOBs (`file_storage`) | SeaweedFS (S3-compatible) |
| Cache | Redis db 1 | Redis db 1 (unchanged) |
| Queue | Redis db 2 + Horizon | Redis db 2 + Horizon + Temporal Cloud |
| Sessions | Redis db 3 | Redis db 3 (unchanged) |
| Event bus | None | Redis Streams (new db 4) — external/cross-module events only |
| Vector search | None | Central PostgreSQL + pgvector (RLS-enforced tenant_id) |
| Full-text search | Meilisearch (feature-flagged) | Meilisearch (enable in K8s for Stage 2) |

### 2.3 Module Directory Layout (target)

```
app/
├── Modules/
│   ├── Agreements/     ← CCRS code reorganised here (Stage 1)
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Filament/
│   │   ├── Jobs/
│   │   └── Providers/AgreementsServiceProvider.php
│   ├── Notes/          ← Stage 2
│   ├── Meetings/       ← Stage 2
│   ├── Discovery/      ← Stage 3
│   ├── Tasks/          ← Stage 3
│   ├── Email/          ← Stage 4
│   ├── Chat/           ← Stage 4 (replaces ExchangeRoom — two-sprint migration)
│   └── Automation/     ← Stage 5
├── Platform/           ← Stage 1
│   ├── Auth/           (AuthService, guard abstraction)
│   ├── Events/         (EventBus — Redis Streams, external-only)
│   ├── Vault/          (VaultEngine — secret/key management)
│   ├── Notifications/  (unified notification dispatch)
│   └── Tenancy/        (tenant provisioning, lifecycle)
└── (existing app/ structure coexists during transition)

database/migrations/
├── (existing CCRS central migrations)
├── tenant/             (existing 84 CCRS tenant migrations)
└── platform/           ← new per-module migrations go here
```

---

## 3. Module Scope — Confirmed Feature Set

All module sign-off questions resolved as part of decision sign-off on 2026-03-19.

---

### Module 0 — Agreements (CCRS reorganised)

**What it is:** The existing CCRS contract management system, moved into `app/Modules/Agreements/`.

**Features (already built — no new development required):**
- Contract lifecycle management (21 epics from CCRS spec)
- Workflow engine, signing, KYC, compliance, analytics, vendor portal
- All 84 tenant migrations, 59 models, 25 services

**Stage 1 task:** Reorganise `app/` into `app/Modules/Agreements/` using a layer-by-layer approach with test verification at each layer. See Stage 1 detailed tasks (Section 6) for the sequencing.

---

### Module 1 — Notes (Intelligent Note Capture)

**What it is:** AI-enhanced note-taking linked to contracts, counterparties, meetings, and tasks.

**Confirmed feature set:**
- Rich-text note editor (Filament, TipTap or similar)
- Link notes to: Contract, Counterparty, Meeting, Task, or stand-alone
- AI summarisation: on-demand only (not automatic on save)
- Tag system: user-defined tags, auto-suggested by AI based on content
- Full-text search via Meilisearch (note content + tags)
- Version history: immutable note versions, diff view — retained indefinitely
- Permissions: creator can edit; linked entity owners have read access; system_admin has full access
- Notifications: @mention a user in a note → in-app notification dispatched
- Export: note to PDF or copy to clipboard

> **Note:** File attachments deferred to a later stage (not in Module 1 scope).

**Tenant migrations needed:** `notes`, `note_tags`, `note_versions`, `note_links`

---

### Module 2 — Meetings (Meeting Intelligence)

**What it is:** Meeting management with AI transcription, action item extraction, and linkage to contracts and tasks.

**Confirmed feature set:**
- Meeting records: title, date, attendees (linked to Users + Counterparty contacts), agenda
- Integration: Teams meeting join link (via Graph API — permissions already granted)
- Transcription: upload-only (audio/video upload → dpp-ai transcribes and extracts action items)
- Action item extraction: AI identifies tasks from transcript → auto-creates Tasks (Module 4)
- Minutes generation: AI-generated minutes from transcript, editable, exportable to PDF/DOCX
- Linkage: meetings linked to Contracts, Counterparties, Projects
- Calendar: .ics invite generation (CalendarService already exists)
- Notifications: pre-meeting reminder (24h, 1h) via existing ReminderService

> **Note:** Microsoft Teams calendar direct integration (Calendars.ReadWrite) deferred — requires additional Graph permissions and CTO action. Real-time transcription (WebSocket) not in scope for Module 2. Meeting minutes supplement (do not replace) the existing Teams notification format.

**Tenant migrations needed:** `meetings`, `meeting_attendees`, `meeting_action_items`, `meeting_transcripts`

---

### Module 3 — Discovery (Knowledge Graph)

**What it is:** Semantic search and knowledge graph across all tenant content. Powered by central PostgreSQL + pgvector + dpp-trawler.

**Confirmed feature set:**
- Unified search bar across all modules (Agreements, Notes, Meetings, Tasks)
- Semantic search: embedding-based similarity via central PostgreSQL + pgvector (OD-2: central with tenant_id RLS)
- Knowledge graph: surface related entities ("contracts similar to this one", "counterparties mentioned in these notes")
- dpp-trawler agent: cron-scheduled Python agent (not real-time) that crawls tenant content, generates embeddings, writes to pgvector
- Filters: date range, entity type, confidence threshold
- Explainability: "Why is this result shown?" — shows matching concepts
- SharePoint surface: index documents in linked SharePoint folders (Stage 3 scope)

**Infrastructure requirement:** Central PostgreSQL + pgvector (D-INF-2) with mandatory `tenant_id` predicate and Row-Level Security. See Section 5.

---

### Module 4 — Tasks (Task Management)

**What it is:** Task management linked to contracts, counterparties, meetings, and notes.

**Confirmed feature set:**
- Task records: title, description, assignee (User), due date, priority, status
- Status machine: `open → in_progress → blocked → done → cancelled`
- Link tasks to: Contract, Counterparty, Meeting, Note, or stand-alone
- Recurring tasks: daily/weekly/monthly schedule
- Dependencies: task can be blocked by another task
- Subtasks: nested task hierarchy (1 level)
- AI suggestion: after a meeting transcript is processed, AI suggests tasks from action items
- Reminders: due-date notification (24h, 1h) via existing ReminderService
- Reporting: task completion rate per user, per contract, per period
- Board view: Kanban-style Filament custom page

> **Note:** Time-tracking deferred. Projects use the existing CCRS Project model (no separate concept). File attachments deferred.

**Tenant migrations needed:** `tasks`, `task_links`, `task_dependencies`

---

### Module 5 — Email (Email Integration)

**What it is:** Email integration allowing users to log inbound/outbound emails against contracts and counterparties.

**Confirmed feature set:**
- Email ingestion: Exchange OAuth via `EmailProvider` interface (first implementation: `ExchangeEmailProvider`)
- Auto-link: AI analyses email subject/body → suggests contract or counterparty to link it to
- Email log: immutable record of all emails linked to a contract
- Compose: send emails from within DPP (using existing SMTP config)
- Templates: re-usable email templates (e.g. "Request signed contract", "KYC reminder")
- Notifications: when a counterparty replies, notify the contract owner in-app
- Attachment handling: email attachments stored via SeaweedFS, auto-linked to contract

> **EmailProvider interface pattern:** `app/Platform/Email/EmailProvider` abstract contract. `ExchangeEmailProvider` is the first implementation. Gmail adapter can be added later without refactoring any existing code. This is per-user email integration (each user connects their own inbox). Open/click tracking not in scope.

**Infrastructure requirement:** Per-user Exchange OAuth credentials; existing Graph API permissions cover mail read.

---

### Module 6 — Chat (Communication Bridge)

**What it is:** In-app chat threads linked to contracts and counterparties. Replaces ExchangeRoom. Real-time via dpp-realtime (Soketi).

**Confirmed feature set:**
- Chat threads: per-contract or per-counterparty threaded conversations
- Real-time delivery: dpp-realtime (Soketi) WebSocket push from Stage 4 — full real-time, no polling fallback
- Participants: invite internal users and counterparty contacts (read-only view for vendors via vendor portal)
- File sharing: share documents from the contract file library within a chat
- @mention: tag a user → in-app notification
- AI summary: "Summarise this thread" → dpp-ai generates a paragraph summary
- Audit: all chat messages are immutable and included in contract audit log
- Teams bridge: optionally post chat messages to a Teams channel (TeamsNotificationService reuse)

> **ExchangeRoom deprecation plan (two-sprint migration):**
> - Sprint A: Build Chat module. Run both Chat and ExchangeRoom simultaneously.
> - Sprint B: Data migration copies ExchangeRoom posts to Chat threads. Disable `exchange_room` feature flag. Remove ExchangeRoom code in Stage 6.
> - Counterparty contacts access chat via vendor portal (read-only).

**Infrastructure requirement:** dpp-realtime sidecar (Soketi/Node.js) — D-INF-3.

---

### Module 7 — Automation (Automation Engine)

**What it is:** No-code automation builder backed by Temporal Cloud for durable workflow execution.

**Confirmed feature set:**
- Trigger types: Contract state change, date-based (key date approaching), form submission, webhook, manual
- Action types: Send notification (email/Teams/in-app), Create task, Update field, Call external webhook, Generate document (PDF), Start workflow
- Condition filters: if [field] = [value], if [user role] = [role]
- Visual builder: drag-and-drop rule builder (Livewire — extends existing visual workflow builder pattern)
- Temporal Cloud execution: each automation run is a Temporal workflow — durable, retryable, auditable
- Run history: log of every automation execution with status, duration, and outcome
- Rate limits: max N automation runs per tenant per hour (configurable)
- Templates: pre-built automation templates ("Send KYC reminder 30 days before contract expiry")

> **Temporal Cloud (managed):** Zero-ops. `TemporalWorkflowProvider` interface built from day one to enable self-host migration if required. CTO must provision Temporal Cloud namespace, egress rules, and K8s secrets before Stage 5 begins. Automation runs via Temporal do not replace Horizon — Horizon continues managing all existing CCRS background jobs.

**Infrastructure requirement:** Temporal Cloud account + D-INF-4 — see Section 11.

---

## 4. Platform Services — Confirmed Scope

These are shared services used by all modules, built in `app/Platform/`.

### 4.1 EventBus (Redis Streams — external-only)

**What it is:** An internal event bus using Redis Streams (db 4) for cross-module and external broadcast events.

**Architecture decision (OD-8 — Option B):** Redis Streams is the external/cross-module bus only. Laravel's built-in Event/Listener system is RETAINED for synchronous, transactional, and audit-critical event handling. The two systems complement each other — do not replace Laravel events.

**When to use Redis Streams vs Laravel Events:**

| Use Redis Streams | Use Laravel Events |
|---|---|
| Cross-module notifications (Notes listening for contract events) | State machine transitions (contract.state_changed writes audit log) |
| Durable event delivery (retry on consumer failure) | Synchronous side-effects (clear cache on save) |
| External subscribers (dpp-trawler consuming content events) | Filament resource observers |
| Fan-out to multiple unrelated modules | Simple notification chains within Agreements |

**Confirmed initial stream events:**
- `contract.created`, `contract.state_changed`, `contract.signed`, `contract.expiring`
- `task.created`, `task.completed`, `task.overdue`
- `meeting.created`, `meeting.transcribed`
- `note.created`, `note.linked`
- `tenant.provisioned`, `tenant.suspended`

### 4.2 VaultEngine (Secret & Key Management)

**What it is:** Per-tenant secret storage. Manages AI worker API keys, SharePoint credentials, and per-user Exchange OAuth tokens.

**Current state:** Per-tenant AI secret is stored as a tenant data attribute (P0-4, already implemented). VaultEngine formalises this pattern.

**Confirmed scope:**
- Store and retrieve per-tenant secrets (AI key, SharePoint credentials, SMTP credentials, Exchange OAuth tokens)
- Backed by K8s Secrets (read from K8s Secret store, not DB) — no HashiCorp Vault or AWS Secrets Manager in scope
- Rotation: log secret rotation events to `platform_audit_log`

### 4.3 dpp-ai (AI Abstraction Layer)

**What it is:** Evolution of the existing FastAPI AI sidecar. Adds Bedrock as the production AI provider with Anthropic as dev/fallback.

**Architecture decision (OD-7 — Option B):**
- `AiProvider` abstract base class in `dpp-ai`
- `AnthropicProvider` — development and fallback; direct Anthropic API calls
- `BedrockProvider` — production path; AWS Bedrock in `af-south-1` region

```
dpp-ai (FastAPI)
├── providers/
│   ├── base.py              ← AiProvider abstract class
│   ├── anthropic.py         ← AnthropicProvider (dev/fallback)
│   └── bedrock.py           ← BedrockProvider (production, af-south-1)
└── config.py                ← AI_PROVIDER env var selects active provider
```

**Provider selection:** `AI_PROVIDER=bedrock` (production K8s) / `AI_PROVIDER=anthropic` (local dev, fallback).

**Confirmed additions:**
- Provider abstraction layer as above
- Per-tenant budget: `AI_BUDGET_TENANT_USD` — cap AI spend per tenant per day
- Per-module budget: Agreements/Notes/Meetings each have a configurable budget allocation
- Prompt registry: centralised versioned prompt storage (DB table) rather than hardcoded strings
- Usage logging: every AI call logged with tenant_id, module, model, tokens, cost

**CTO action required:** AWS account in `af-south-1`, Bedrock model access, IAM role, K8s secrets — see Section 11.

---

## 5. Infrastructure Requirements

### 5.1 Platform Infrastructure Table

| ID | Requirement | Priority | Stage | Status |
|---|---|---|---|---|
| **D-INF-1** | Provision SeaweedFS pod in K8s — S3-compatible, replaces MySQL BLOBs | HIGH | Stage 1 | ⬜ Pending CTO |
| **D-INF-2** | Provision central PostgreSQL + pgvector. Configure RLS: `tenant_id` predicate enforced at DB level | HIGH | Stage 3 | ⬜ Pending CTO |
| **D-INF-3** | Provision Soketi/Node.js WebSocket pod + K8s ingress for `ws://`/`wss://` path | MEDIUM | Stage 4 | ⬜ Pending CTO |
| **D-INF-4** | Temporal Cloud: register namespace, configure egress rules, provision K8s secrets | MEDIUM | Stage 5 | ⬜ Pending CTO |
| **D-INF-5** | Enable Meilisearch in K8s (pod in plan, feature-flagged — activation only) | LOW | Stage 2 | ⬜ Pending CTO |
| **D-INF-6** | Add Redis Streams config to K8s deployment (db 4 — likely zero-config on existing Redis instance) | LOW | Stage 1 | ⬜ Pending CTO |
| **D-INF-7** | Per-tenant MySQL `CREATE DATABASE` privilege | ✅ Done | Done | Confirmed 2026-03-19 |
| **D-INF-8** | Wildcard DNS `*.digittal.mobi` in Cloudflare | ✅ Done | Done | Confirmed 2026-03-19 |

### 5.2 AWS / Bedrock Requirements (OD-7)

| ID | Requirement | Stage |
|---|---|---|
| **D-AWS-1** | AWS account provisioned in `af-south-1` region | Stage 2 |
| **D-AWS-2** | Bedrock model access requested and approved: Claude Sonnet in `af-south-1` | Stage 2 |
| **D-AWS-3** | IAM role created with `bedrock:InvokeModel` permission | Stage 2 |
| **D-AWS-4** | K8s secrets: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION=af-south-1`, `BEDROCK_MODEL_ID` | Stage 2 |

### 5.3 Temporal Cloud Requirements (OD-3)

| ID | Requirement | Stage |
|---|---|---|
| **D-TC-1** | Temporal Cloud account registered | Stage 4 (provision early) |
| **D-TC-2** | Temporal Cloud namespace provisioned for DPP | Stage 4 |
| **D-TC-3** | K8s network egress rules to Temporal Cloud endpoint | Stage 4 |
| **D-TC-4** | K8s secrets: `TEMPORAL_CLOUD_NAMESPACE`, `TEMPORAL_CLOUD_API_KEY`, `TEMPORAL_CLOUD_HOST` | Stage 5 |
| **D-TC-5** | mTLS certificates configured if required by Temporal Cloud namespace | Stage 5 |

---

## 6. Build Stages — Phased Plan

> Stages are sequential. Each stage has a clear output that can be reviewed before the next begins.
> **OD-1 constraint:** All Stage 1 file moves use a layer-by-layer approach with full test suite verification at each layer. Quality over speed — do not proceed to the next layer if any test fails.

---

### Stage 1 — Platform Foundation (prerequisite for all modules)

**Goal:** Scaffold the platform layer and reorganise Agreements code without breaking any existing CCRS functionality.

**Constraint:** Test suite must pass (currently 1038 tests, 0 failures) at the end of every sub-task group before the next begins. No batch moves.

#### Phase A — Agreements Module Reorganisation (layer-by-layer)

| Task | Description | Test Gate | Effort |
|---|---|---|---|
| **S1-A1** | Create `app/Modules/Agreements/` directory tree: `Models/`, `Services/`, `Jobs/`, `Filament/`, `Providers/` | n/a (structure only) | XS |
| **S1-A2** | Create `database/migrations/platform/` directory + `.gitkeep` | n/a | XS |
| **S1-A3** | **Layer 1 — Models:** Move all 59 models from `app/Models/` to `app/Modules/Agreements/Models/`. Update namespaces. Update all references (services, controllers, factories, migrations). | Run full suite — must pass | L |
| **S1-A4** | **Layer 2 — Services:** Move all 25 services from `app/Services/` to `app/Modules/Agreements/Services/`. Update namespaces. Update all references (jobs, controllers, Filament resources, providers). | Run full suite — must pass | L |
| **S1-A5** | **Layer 3 — Jobs:** Move all 9 jobs from `app/Jobs/` to `app/Modules/Agreements/Jobs/`. Update namespaces. Update queue config references. | Run full suite — must pass | M |
| **S1-A6** | **Layer 4 — Filament resources, pages, widgets:** Move from `app/Filament/` to `app/Modules/Agreements/Filament/`. Update namespaces. Update `AdminPanelProvider` registrations. | Run full suite — must pass | L |
| **S1-A7** | **Layer 5 — Providers:** Create `AgreementsServiceProvider.php` in `app/Modules/Agreements/Providers/`. Register models, migrations, routes, Filament resources. Register in `config/app.php`. | Run full suite — must pass | M |
| **S1-A8** | **Integration verification:** Run Pint (`--test`), full test suite, Playwright smoke tests, and manual smoke test against sandbox. | All green | S |

#### Phase B — Platform Foundation

| Task | Description | Effort |
|---|---|---|
| **S1-B1** | Create `app/Platform/Events/EventBus.php` — Redis Streams publisher/subscriber. Stream: `platform.events` on Redis db 4. | M |
| **S1-B2** | Wire EventBus to existing contract lifecycle events — fire `contract.state_changed` on WorkflowService transitions. Keep existing Laravel events in place (OD-8: Streams is external-only). | M |
| **S1-B3** | Create `app/Platform/Vault/VaultEngine.php` — per-tenant secret read/write extending P0-4 pattern. Methods: `get(string $key)`, `put(string $key, string $value)`, `rotate(string $key)`. | M |
| **S1-B4** | SeaweedFS: swap `database` disk to `s3` disk in `config/filesystems.php` (D-INF-1 prerequisite — CTO must provision SeaweedFS pod first). | S |
| **S1-B5** | Automate tenant provisioning: add `SeedDatabase` Temporal-aware job (seed default roles on tenant creation). Make `CreateDatabase` queued and audited. | M |
| **S1-B6** | Write integration tests for EventBus (event published + consumer reads), VaultEngine (store/retrieve/rotate), and SeaweedFS (store + retrieve a document). | M |

**Stage 1 exit criteria:**
- All 1038+ tests pass after each layer move
- EventBus publishes `contract.state_changed` on workflow transitions (verified by integration test)
- SeaweedFS stores and retrieves a test document
- VaultEngine stores and retrieves a tenant secret
- Pint (`--test`) passes
- Playwright smoke suite passes

---

### Stage 2 — Notes + Meetings Modules

**Goal:** First two DPP-native modules live and integrated with the EventBus.

| Task | Description | Effort |
|---|---|---|
| S2-T1 | Notes module scaffold: model, migrations, Filament resource, CRUD | M |
| S2-T2 | Notes: on-demand AI summarisation via dpp-ai | S |
| S2-T3 | Notes: full-text search via Meilisearch (D-INF-5 prerequisite) | M |
| S2-T4 | Notes: EventBus subscription — listen for `contract.signed`, `task.created` to auto-suggest note creation | S |
| S2-T5 | Meetings module scaffold: model, migrations, Filament resource, CRUD | M |
| S2-T6 | Meetings: .ics calendar generation (CalendarService reuse) | S |
| S2-T7 | Meetings: audio/video upload + dpp-ai transcription (upload-only, not real-time) | M |
| S2-T8 | Meetings: AI action item extraction → Task creation (stub Tasks model for handoff) | M |
| S2-T9 | dpp-ai: add Bedrock provider abstraction (`AiProvider` base class, `AnthropicProvider`, `BedrockProvider` — D-AWS-1 through D-AWS-4 prerequisite) | M |
| S2-T10 | Tests: PestPHP tests for Notes CRUD, AI summarisation (mocked dpp-ai), Meetings CRUD, transcription handoff | M |

**Exit criteria:** A note can be created, linked to a contract, AI-summarised on demand, and found via Meilisearch. A meeting can be created, transcribed (upload), and action items auto-created as task stubs.

---

### Stage 3 — Discovery + Tasks Modules

**Goal:** Semantic search live, Task module complete, pgvector operational.

| Task | Description | Effort |
|---|---|---|
| S3-T1 | Tasks module scaffold: model, migrations, Filament resource + Kanban page | M |
| S3-T2 | Tasks: dependency graph, subtasks (1 level), recurring tasks | M |
| S3-T3 | Tasks: due-date reminders via existing ReminderService | S |
| S3-T4 | Tasks: EventBus — publish `task.created`, `task.completed`, `task.overdue` | S |
| S3-T5 | Discovery: dpp-trawler Python agent scaffold — crawl all tenant content, generate embeddings, write to pgvector (D-INF-2 prerequisite) | L |
| S3-T6 | Discovery: central PostgreSQL RLS setup — `tenant_id` predicate enforced, row-level policies created | M |
| S3-T7 | Discovery: cron schedule for dpp-trawler (not real-time) | S |
| S3-T8 | Discovery: unified search UI in Filament — cross-module search bar (Agreements, Notes, Meetings, Tasks) | M |
| S3-T9 | Discovery: SharePoint indexing via SharePointService | M |
| S3-T10 | Tests: Tasks lifecycle, dependency graph, Discovery search accuracy (pgvector similarity scores) | M |

**Exit criteria:** Tasks are created, assigned, blocked, completed. Semantic search returns results across Contracts, Notes, and Meetings with relevance ranking. pgvector enforces tenant isolation via RLS.

---

### Stage 4 — Email + Chat Modules

**Goal:** Email integration and real-time chat live. ExchangeRoom migration Sprint A complete.

| Task | Description | Effort |
|---|---|---|
| S4-T1 | `EmailProvider` interface in `app/Platform/Email/` | S |
| S4-T2 | `ExchangeEmailProvider`: Exchange OAuth IMAP polling + email log model | L |
| S4-T3 | Email: AI auto-link (suggest contract/counterparty from email subject/body) | M |
| S4-T4 | Email: compose + SMTP send from within DPP | M |
| S4-T5 | Email: attachment handling via SeaweedFS | M |
| S4-T6 | Chat module scaffold: model, migrations (threads, messages, participants) | M |
| S4-T7 | Chat: Filament real-time UI + Soketi WebSocket integration (D-INF-3 prerequisite) | L |
| S4-T8 | Chat: dpp-realtime sidecar (Soketi/Node.js) wired to Filament broadcasting | M |
| S4-T9 | Chat: Teams bridge, @mention notifications, audit log integration | S |
| S4-T10 | ExchangeRoom migration Sprint A: Chat + ExchangeRoom coexist; feature flag `chat=true` enabled | S |
| S4-T11 | Tests: Email ingestion, auto-link accuracy, Chat real-time delivery, WebSocket connection tests | M |

**Exit criteria:** Email from a counterparty can be ingested and linked to a contract. Chat messages appear in real-time between two users. Both Chat and ExchangeRoom coexist with feature flags.

---

### Stage 5 — Automation Module + Temporal + ExchangeRoom Removal

**Goal:** No-code automation backed by Temporal Cloud. ExchangeRoom migration Sprint B complete.

| Task | Description | Effort |
|---|---|---|
| S5-T1 | `dpp-workflows` sidecar scaffold: Temporal PHP SDK worker (D-TC-4 prerequisite) | L |
| S5-T2 | `TemporalWorkflowProvider` interface built from day one (enables future self-host migration) | S |
| S5-T3 | Automation: trigger/action/condition model + migrations | M |
| S5-T4 | Automation: visual builder (Livewire, extend existing workflow builder pattern) | L |
| S5-T5 | Automation: Temporal workflow execution + run history | M |
| S5-T6 | Automation: pre-built templates (KYC reminder, expiry alert, state-change webhook) | M |
| S5-T7 | EventBus: Automation module subscribes to all platform events | S |
| S5-T8 | ExchangeRoom migration Sprint B: data migration copies posts → Chat threads; disable `exchange_room` flag | M |
| S5-T9 | Tests: Automation rule creation, Temporal workflow execution (mocked Temporal Cloud), ExchangeRoom data migration verification | M |

**Exit criteria:** A no-code automation fires a Teams notification on contract state change. Temporal Cloud dashboard shows run history. ExchangeRoom feature flag is disabled; all historical data migrated to Chat.

---

### Stage 6 — Integration, Hardening, and Launch Prep

| Task | Description | Effort |
|---|---|---|
| S6-T1 | Remove ExchangeRoom code (model, service, migrations, Filament resource, routes) — flag already disabled in Stage 5 | M |
| S6-T2 | `v2/integration` branch: merge `platform/v2-dpp` + `laravel-migration` | M |
| S6-T3 | Full E2E test coverage for all DPP modules | XL |
| S6-T4 | Security audit (DPP_Security_Amendment_v1_1 R-SEC-001 through R-SEC-050) | L |
| S6-T5 | Performance testing: 200+ concurrent users, 50,000+ contracts | M |
| S6-T6 | Multi-tenant smoke test: provision 3 test tenants, verify pgvector isolation and module isolation | M |
| S6-T7 | WCAG 2.1 AA audit of all new module UIs | M |
| S6-T8 | Tenant onboarding flow: self-service signup (feature-flagged: `tenant_self_serve`) | L |
| S6-T9 | Documentation: update CLAUDE.md, user manual, API reference | M |

---

## 7. Confirmed Decision Register

All 8 open decisions have been answered and locked. Do not re-open without explicit sign-off.

| ID | Decision | Chosen Option | Key Constraints |
|---|---|---|---|
| **OD-1** | Agreements module reorganisation timing | **Option A — Move Agreements in Stage 1 first** | Layer-by-layer: Models → Services → Jobs → Filament → Providers. Full test suite must pass after each layer. Slower and correct > fast and broken. |
| **OD-2** | PostgreSQL/pgvector strategy | **Option A — Central Postgres + RLS** | Single central PostgreSQL instance. Mandatory `tenant_id` predicate on every query. Row-Level Security enforced at DB level. NOT per-tenant instances. |
| **OD-3** | Temporal deployment | **Option A — Temporal Cloud (managed)** | Zero-ops. `TemporalWorkflowProvider` interface built from day one to enable self-host migration. CTO must provision namespace + egress + K8s secrets before Stage 5. |
| **OD-4** | Chat module WebSockets | **Option A — Soketi from Stage 4** | Full real-time via dpp-realtime sidecar. No polling fallback. D-INF-3 is a hard prerequisite for Stage 4. |
| **OD-5** | Email integration target | **Option A — Microsoft Exchange only (initial)** | `EmailProvider` interface built from day one. `ExchangeEmailProvider` is first implementation. Gmail adapter can be added later via the interface without refactoring. |
| **OD-6** | ExchangeRoom vs Chat | **Option A — Chat replaces ExchangeRoom** | Two-sprint migration: Stage 4 builds Chat alongside ExchangeRoom, Stage 5 migrates data and disables the flag, Stage 6 removes the code. |
| **OD-7** | dpp-ai / Bedrock | **Option B — Bedrock as production path** | `AiProvider` abstract base class. `BedrockProvider` = production (`af-south-1`). `AnthropicProvider` = dev + fallback. D-AWS-1 through D-AWS-4 are CTO prerequisites for Stage 2. |
| **OD-8** | EventBus coexistence | **Option B — Redis Streams external-only** | Laravel events RETAINED for synchronous/audit-critical paths. Redis Streams is the external/cross-module bus only. The two systems complement each other. |

---

## 8. Risk Register

| Risk | Likelihood | Impact | Mitigation | Status |
|---|---|---|---|---|
| Stage 1 Agreements reorganisation breaks tests | Medium | High | Layer-by-layer with test gate at each layer (OD-1 decision). No batch moves. | Mitigated by OD-1 decision |
| SeaweedFS migration loses existing documents | Low | Critical | Run parallel disks during migration; verify every document before cutover | Active |
| Temporal Cloud provisioning delays Stage 5 | Medium | Medium | CTO provisions Temporal Cloud by Stage 4 end. `TemporalWorkflowProvider` interface isolates the dependency. | Reduced — interface isolates |
| Module isolation leaks (modules calling each other directly) | Medium | Medium | Enforce module boundaries via EventBus — no direct cross-module service calls. Code review gate checks cross-module imports. | Active |
| ~~pgvector per-tenant scaling (one DB per tenant)~~ | ~~High~~ | ~~High~~ | Resolved by OD-2: central pgvector with RLS, not per-tenant instances. | ✅ Resolved |
| Bedrock availability in `af-south-1` | Medium | High | AnthropicProvider remains as fallback. `AI_PROVIDER` env var switches providers — fallback is zero-code change. | Mitigated by OD-7 design |
| Exchange OAuth credential management per-user | Medium | Medium | VaultEngine stores per-user Exchange tokens. Rotation logged to `platform_audit_log`. | Active |
| ExchangeRoom data migration data loss | Low | Medium | Data migration verified by test: count posts before, count chat messages after, assert equality. | Active |
| Soketi WebSocket infra not ready for Stage 4 | Medium | Low | Hard dependency (OD-4 Option A). CTO must confirm D-INF-3 before Stage 4 begins. | CTO dependency |

---

## 9. Scope Summary — Module Feature Count

| Module | Features | Stage | Effort |
|---|---|---|---|
| 0 — Agreements (reorganise) | Existing 21 epics | 1 | L (move only) |
| Platform Foundation | EventBus (Streams external), VaultEngine, dpp-ai (Bedrock), SeaweedFS, tenant provisioning | 1 | L |
| 1 — Notes | 8 features (no file attachments) | 2 | M |
| 2 — Meetings | 7 features (upload-only transcription) | 2 | M/L |
| dpp-ai Bedrock layer | AiProvider abstraction, BedrockProvider, AnthropicProvider | 2 | M |
| 3 — Discovery | 7 features + pgvector (central RLS) | 3 | L |
| 4 — Tasks | 8 features (no time-tracking) | 3 | M |
| 5 — Email | 6 features, EmailProvider interface, Exchange first | 4 | L |
| 6 — Chat | 7 features + WebSockets + ExchangeRoom Sprint A | 4 | L |
| 7 — Automation | 8 features + Temporal Cloud + ExchangeRoom Sprint B | 5 | XL |
| Stage 6 — Integration & hardening | E2E, security, perf, ExchangeRoom removal, launch | 6 | XL |

---

## 10. How to Proceed

Sign-off is complete. Implementation begins on `platform/v2-dpp` using per-module branches (`platform/module-*`) merged into `platform/v2-dpp`.

**Execution order:**
1. CTO provisions D-INF-1 (SeaweedFS) and D-INF-6 (Redis Streams db 4) before Stage 1 Phase B begins.
2. Stage 1 Phase A: Agreements layer-by-layer reorganisation.
3. Stage 1 Phase B: Platform foundation (EventBus, VaultEngine, SeaweedFS swap).
4. CTO provisions D-AWS-1 through D-AWS-4 (Bedrock) before Stage 2 S2-T9 begins.
5. CTO provisions D-INF-5 (Meilisearch) before Stage 2 S2-T3 begins.
6. Stages 2–5 proceed sequentially.
7. CTO provisions D-INF-3 (Soketi) before Stage 4 S4-T7 begins.
8. CTO provisions D-TC-1 through D-TC-5 (Temporal Cloud) before Stage 5 S5-T1 begins.
9. Stage 6: integration, hardening, launch prep.

> See Section 11 for the full CTO Infrastructure Checklist ordered by stage.

---

## 11. CTO Infrastructure Checklist

> This is the single reference document for all CTO infrastructure actions across the full DPP v2 build.
> Actions are ordered by the stage they must be complete before.
> No stage should begin if its prerequisite actions are not confirmed complete.

---

### Before Stage 1 — Phase B (Platform Foundation)

| # | Action | Requirement | Notes |
|---|---|---|---|
| CTO-1 | Provision SeaweedFS pod in K8s | D-INF-1 | S3-compatible object store. Must expose S3 API endpoint internally. Provide `SEAWEEDFS_S3_ENDPOINT`, `SEAWEEDFS_ACCESS_KEY`, `SEAWEEDFS_SECRET_KEY` as K8s secrets. |
| CTO-2 | Add Redis db 4 config to K8s deployment | D-INF-6 | Likely zero-config on existing Redis instance — confirm Redis `maxdatabases` is ≥ 5. Update K8s deployment env if `REDIS_STREAMS_DB` env var is needed. |

---

### Before Stage 2 (Notes + Meetings + Bedrock)

| # | Action | Requirement | Notes |
|---|---|---|---|
| CTO-3 | Enable Meilisearch in K8s | D-INF-5 | Pod is in the plan; activate and provide `MEILISEARCH_HOST` and `MEILISEARCH_KEY` as K8s secrets. Confirm `SCOUT_DRIVER=meilisearch` env var is set. |
| CTO-4 | Provision AWS account in `af-south-1` | D-AWS-1 | Primary region for Bedrock. Must be in `af-south-1` (South Africa) per data residency requirements. |
| CTO-5 | Request Bedrock model access in `af-south-1` | D-AWS-2 | Request access for: `anthropic.claude-sonnet-4-6` (or current Claude Sonnet model ID). Note: Bedrock model availability in `af-south-1` must be confirmed — fall back to `AnthropicProvider` if unavailable. |
| CTO-6 | Create IAM role with Bedrock permissions | D-AWS-3 | Policy: `bedrock:InvokeModel` on the approved model ARNs. Scope to `af-south-1` resource ARNs only. |
| CTO-7 | Add AWS credentials to K8s secrets | D-AWS-4 | Secrets required: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION=af-south-1`, `BEDROCK_MODEL_ID`. Add `AI_PROVIDER=bedrock` to K8s deployment env. |

---

### Before Stage 3 (Discovery + pgvector)

| # | Action | Requirement | Notes |
|---|---|---|---|
| CTO-8 | Provision central PostgreSQL + pgvector sidecar | D-INF-2 | Options: managed PostgreSQL (e.g. Hetzner Managed DB, AWS RDS with pgvector) or sidecar pod. Must support pgvector extension. Minimum: PostgreSQL 15 + pgvector 0.5+. |
| CTO-9 | Configure Row-Level Security on pgvector instance | D-INF-2 | Create `tenant_id` RLS policies on all vector tables. Every query from dpp-trawler and dpp-core must include `tenant_id` predicate. Confirm RLS cannot be bypassed by the application DB user. |
| CTO-10 | Provide PostgreSQL connection secrets | D-INF-2 | K8s secrets: `PGVECTOR_HOST`, `PGVECTOR_PORT`, `PGVECTOR_DB`, `PGVECTOR_USER`, `PGVECTOR_PASSWORD`. |

---

### Before Stage 4 (Email + Chat + Soketi)

| # | Action | Requirement | Notes |
|---|---|---|---|
| CTO-11 | Provision Soketi/Node.js WebSocket pod | D-INF-3 | Soketi is an open-source Pusher-compatible WebSocket server. K8s pod + service required. |
| CTO-12 | Configure K8s ingress for WebSocket path | D-INF-3 | Ingress must pass `ws://` and `wss://` connections to the Soketi pod. Configure upgrade headers. Update Cloudflare to allow WebSocket connections (Cloudflare supports WebSockets — confirm no proxy-mode conflict). |
| CTO-13 | Provision Soketi app credentials | D-INF-3 | K8s secrets: `SOKETI_APP_ID`, `SOKETI_APP_KEY`, `SOKETI_APP_SECRET`, `SOKETI_HOST`, `SOKETI_PORT`. |
| CTO-14 | Register Temporal Cloud account | D-TC-1 | Register at cloud.temporal.io. Choose a plan appropriate for expected workflow volume. |
| CTO-15 | Provision Temporal Cloud namespace for DPP | D-TC-2 | Create namespace `dpp-production` (or equivalent). Note retention period and workflow execution limits. |

---

### Before Stage 5 (Automation + Temporal execution)

| # | Action | Requirement | Notes |
|---|---|---|---|
| CTO-16 | Configure K8s network egress to Temporal Cloud | D-TC-3 | Temporal Cloud endpoint requires outbound TCP. Confirm K8s network policy allows egress to `*.tmprl.cloud` or the specific Temporal Cloud host. |
| CTO-17 | Add Temporal Cloud secrets to K8s | D-TC-4 | K8s secrets: `TEMPORAL_CLOUD_NAMESPACE`, `TEMPORAL_CLOUD_API_KEY`, `TEMPORAL_CLOUD_HOST`, `TEMPORAL_CLOUD_PORT`. |
| CTO-18 | Configure mTLS if required | D-TC-5 | Temporal Cloud namespaces may require mTLS client certificates. If so: generate cert, add `TEMPORAL_TLS_CERT` and `TEMPORAL_TLS_KEY` to K8s secrets. |

---

### Stage 6 / Launch

| # | Action | Notes |
|---|---|---|
| CTO-19 | Confirm all CTO-1 through CTO-18 items are complete and secrets are present in production K8s | Run `kubectl get secrets` and cross-reference against this checklist before launch sign-off. |
| CTO-20 | Scale review: confirm pod resource limits are appropriate for multi-module load (SeaweedFS, Soketi, Temporal worker, pgvector) | Adjust K8s resource requests/limits based on Stage 5 load test results. |

---

> This document lives at `docs/dpp-v2-build-plan.md` on the `platform/v2-dpp` branch.
> It will be updated as stages complete and decisions are refined.
> Last updated: 2026-03-19 — all 8 decisions signed off, Stage 1 ready to begin.
