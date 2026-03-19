# DPP v2 — Build Plan & Scope Verification

> **Purpose:** This document defines the intended scope, module feature set, infrastructure requirements, and phased build plan for the Digittal Productivity Platform (DPP) v2. It is written for review and sign-off by Greg Morris and the CTO before any implementation begins on `platform/v2-dpp`.
>
> **Status:** Draft — awaiting sign-off
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
- dpp-ai abstraction layer (currently direct FastAPI sidecar)
- dpp-trawler (semantic discovery agent)
- Temporal.io workflow orchestration
- PostgreSQL + pgvector (for semantic/vector search)
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
    │ Claude  │              │  WebSockets   │
    │ Bedrock │              └───────────────┘
    └─────────┘
         │
    ┌────▼────────┐        ┌──────────────────┐
    │dpp-workflows│        │  dpp-trawler      │
    │(Temporal.io)│        │ (Python discovery)│
    │  async jobs │        │ pgvector search   │
    └─────────────┘        └──────────────────┘
```

### 2.2 Data Store Evolution

| Store | Current | DPP v2 |
|---|---|---|
| Application DB | MySQL 8.0 (per-tenant + central) | MySQL 8.0 (unchanged) |
| Document storage | MySQL BLOBs (`file_storage`) | SeaweedFS (S3-compatible) |
| Cache | Redis db 1 | Redis db 1 (unchanged) |
| Queue | Redis db 2 + Horizon | Redis db 2 + Horizon + Temporal |
| Sessions | Redis db 3 | Redis db 3 (unchanged) |
| Event bus | None | Redis Streams (new db 4) |
| Vector search | None | PostgreSQL + pgvector |
| Full-text search | Meilisearch (feature-flagged) | Meilisearch (enable in K8s) |

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
│   ├── Chat/           ← Stage 4
│   └── Automation/     ← Stage 5
├── Platform/           ← Stage 1
│   ├── Auth/           (AuthService, guard abstraction)
│   ├── Events/         (EventBus — Redis Streams)
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

## 3. Module Scope — Feature Set for Sign-Off

> **Action required:** Review each module below and confirm the feature set is as intended. Add notes or corrections before build begins.

---

### Module 0 — Agreements (CCRS reorganised)

**What it is:** The existing CCRS contract management system, moved into `app/Modules/Agreements/`.

**Features (already built — no new development required):**
- Contract lifecycle management (21 epics from CCRS spec)
- Workflow engine, signing, KYC, compliance, analytics, vendor portal
- All 84 tenant migrations, 59 models, 25 services

**Stage 1 task:** Reorganise `app/` into `app/Modules/Agreements/` without breaking any functionality. This is a rename/move operation — no logic changes.

**⚠ Sign-off question M0-1:** Should the Agreements module reorganisation happen before new modules are built, or should new modules be scaffolded first and Agreements moved in Stage 2?

---

### Module 1 — Notes (Intelligent Note Capture)

**What it is:** AI-enhanced note-taking linked to contracts, counterparties, meetings, and tasks. Notes are searchable, taggable, and automatically summarised.

**Proposed feature set:**
- Rich-text note editor (Filament, TipTap or similar)
- Link notes to: Contract, Counterparty, Meeting, Task, or stand-alone
- AI summarisation: on save, optionally generate a 1-paragraph summary via dpp-ai
- Tag system: user-defined tags, auto-suggested by AI based on content
- Full-text search via Meilisearch (note content + tags)
- Version history: immutable note versions, diff view
- Permissions: creator can edit; linked entity owners have read access; system_admin has full access
- Notifications: @mention a user in a note → in-app notification dispatched
- Export: note to PDF or copy to clipboard

**Tenant migrations needed:** `notes`, `note_tags`, `note_versions`, `note_links`

**⚠ Sign-off questions:**
- M1-1: Should notes support attachments (files)?
- M1-2: Should AI summarisation be on-demand only or automatic on save?
- M1-3: Should note version history be retained indefinitely or capped?

---

### Module 2 — Meetings (Meeting Intelligence)

**What it is:** Meeting management with AI transcription, action item extraction, and linkage to contracts and tasks.

**Proposed feature set:**
- Meeting records: title, date, attendees (linked to Users + Counterparty contacts), agenda
- Integration: Teams meeting join link (via Graph API — Graph permissions are already granted)
- Transcription: upload audio/video → dpp-ai transcribes and extracts action items
- Action item extraction: AI identifies tasks from transcript → auto-creates Tasks (Module 4)
- Minutes generation: AI-generated minutes from transcript, editable, exportable to PDF/DOCX
- Linkage: meetings linked to Contracts, Counterparties, Projects
- Calendar: .ics invite generation (CalendarService already exists)
- Notifications: pre-meeting reminder (24h, 1h) via existing ReminderService

**Tenant migrations needed:** `meetings`, `meeting_attendees`, `meeting_action_items`, `meeting_transcripts`

**⚠ Sign-off questions:**
- M2-1: Should we integrate with Microsoft Teams calendar directly (requires Calendars.ReadWrite Graph permission — CTO action)?
- M2-2: Should transcription support live/real-time (WebSocket, dpp-realtime) or upload-only?
- M2-3: Should meeting minutes replace or supplement the existing Teams notification format?

---

### Module 3 — Discovery (Knowledge Graph)

**What it is:** Semantic search and knowledge graph across all tenant content (contracts, notes, meetings, counterparties). Powered by pgvector + dpp-trawler.

**Proposed feature set:**
- Unified search bar across all modules (Agreements, Notes, Meetings, Tasks)
- Semantic search: embedding-based similarity search via PostgreSQL + pgvector
- Knowledge graph: surface related entities ("contracts similar to this one", "counterparties mentioned in these notes")
- dpp-trawler agent: background Python agent that crawls tenant content, generates embeddings, writes to pgvector
- Filters: date range, entity type, confidence threshold
- Explainability: "Why is this result shown?" — shows matching concepts
- SharePoint surface: index documents in linked SharePoint folders (uses SharePointService)

**Infrastructure requirement:** PostgreSQL + pgvector (new — CTO must provision a Postgres sidecar per tenant or as a central vector store)

**⚠ Sign-off questions:**
- M3-1: Should pgvector be a per-tenant database (matching the existing database-per-tenant model) or a single central vector store with tenant_id scoping?
- M3-2: Should SharePoint document indexing be included in Stage 3 or deferred?
- M3-3: Should dpp-trawler run on a cron schedule or as a real-time event-driven crawler?

---

### Module 4 — Tasks (Task Management)

**What it is:** Task management linked to contracts, counterparties, meetings, and notes. Replaces the ad-hoc reminder/action-item pattern in CCRS.

**Proposed feature set:**
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

**Tenant migrations needed:** `tasks`, `task_links`, `task_dependencies`

**⚠ Sign-off questions:**
- M4-1: Should tasks have a time-tracking component (log hours against a task)?
- M4-2: Should there be a separate "Projects" concept within Tasks, or use the existing CCRS Project model?
- M4-3: Should tasks support file attachments?

---

### Module 5 — Email (Email Integration)

**What it is:** Email integration that allows users to log inbound/outbound emails against contracts and counterparties, with AI triage and auto-linking.

**Proposed feature set:**
- Email ingestion: IMAP polling (Microsoft Exchange / Gmail via OAuth) or inbound webhook (SendGrid/Mailgun)
- Auto-link: AI analyses email subject/body → suggests contract or counterparty to link it to
- Email log: immutable record of all emails linked to a contract
- Compose: send emails from within DPP (using existing SMTP config)
- Templates: re-usable email templates (e.g. "Request signed contract", "KYC reminder")
- Notifications: when a counterparty replies, notify the contract owner in-app
- Attachment handling: email attachments stored via SeaweedFS, auto-linked to contract

**Infrastructure requirement:** OAuth mail credentials per tenant (user must connect their Exchange/Gmail account)

**⚠ Sign-off questions:**
- M5-1: Should email integration be per-user (each user connects their own inbox) or per-tenant (shared mailbox)?
- M5-2: Is Microsoft Exchange the primary target, or do we need Gmail too?
- M5-3: Should sent emails be tracked for opens/clicks (requires SendGrid or equivalent)?

---

### Module 6 — Chat (Communication Bridge)

**What it is:** In-app chat threads linked to contracts and counterparties. Real-time via dpp-realtime (Soketi/WebSockets).

**Proposed feature set:**
- Chat threads: per-contract or per-counterparty threaded conversations
- Real-time delivery: dpp-realtime (Soketi) WebSocket push — messages appear instantly
- Participants: invite internal users and counterparty contacts (read-only view for vendors via vendor portal)
- File sharing: share documents from the contract file library within a chat
- @mention: tag a user → in-app notification
- AI summary: "Summarise this thread" → dpp-ai generates a paragraph summary of the conversation
- Audit: all chat messages are immutable and included in contract audit log
- Teams bridge: optionally post chat messages to a Teams channel (existing TeamsNotificationService)

**Infrastructure requirement:** dpp-realtime sidecar (Soketi/Node.js). CTO must provision WebSocket pod and update K8s ingress.

**⚠ Sign-off questions:**
- M6-1: Should the chat module replace the existing ExchangeRoom feature, or coexist?
- M6-2: Should counterparty contacts (external) access chat via the vendor portal?
- M6-3: Should we start with polling (simpler, no WebSocket infra) and upgrade to Soketi in a later stage?

---

### Module 7 — Automation (Automation Engine)

**What it is:** No-code automation builder — "when X happens, do Y". Backed by Temporal.io for durable workflow execution.

**Proposed feature set:**
- Trigger types: Contract state change, date-based (key date approaching), form submission, webhook, manual
- Action types: Send notification (email/Teams/in-app), Create task, Update field, Call external webhook, Generate document (PDF), Start workflow
- Condition filters: if [field] = [value], if [user role] = [role]
- Visual builder: drag-and-drop rule builder (Livewire — extends existing visual workflow builder pattern)
- Temporal.io execution: each automation run is a Temporal workflow — durable, retryable, auditable
- Run history: log of every automation execution with status, duration, and outcome
- Rate limits: max N automation runs per tenant per hour (configurable)
- Templates: pre-built automation templates ("Send KYC reminder 30 days before contract expiry")

**Infrastructure requirement:** Temporal.io server + dpp-workflows sidecar. CTO must provision Temporal cluster (can use Temporal Cloud or self-hosted).

**⚠ Sign-off questions:**
- M7-1: Is Temporal.io the confirmed choice for workflow orchestration, or should we evaluate alternatives (Inngest, AWS Step Functions)?
- M7-2: Should we use Temporal Cloud (managed) or self-host Temporal in K8s?
- M7-3: Should the Automation module replace Horizon-managed job chains, or coexist?

---

## 4. Platform Services — Scope for Sign-Off

These are shared services used by all modules, built in `app/Platform/`.

### 4.1 EventBus (Redis Streams)

**What it is:** An internal event bus using Redis Streams (db 4) that decouples modules. When a Contract is signed, the EventBus publishes a `contract.signed` event — any module can subscribe (Notes, Tasks, Automation) without the Agreements module knowing they exist.

**Proposed events (initial set):**
- `contract.created`, `contract.state_changed`, `contract.signed`, `contract.expiring`
- `task.created`, `task.completed`, `task.overdue`
- `meeting.created`, `meeting.transcribed`
- `note.created`, `note.linked`
- `tenant.provisioned`, `tenant.suspended`

**⚠ Sign-off question P-1:** Should the EventBus replace the current Laravel Event/Listener system, or sit alongside it as an external bus only?

### 4.2 VaultEngine (Secret & Key Management)

**What it is:** Per-tenant secret storage. Manages AI worker API keys, SharePoint credentials, and future per-tenant encryption keys.

**Current state:** Per-tenant AI secret is stored as a tenant data attribute (P0-4, already implemented). VaultEngine formalises and extends this pattern.

**Proposed scope:**
- Store and retrieve per-tenant secrets (AI key, SharePoint credentials, SMTP credentials)
- Integration with K8s Secrets (read from K8s Secret store, not DB)
- Rotation: log secret rotation events to `platform_audit_log`

**⚠ Sign-off question P-2:** Should VaultEngine integrate with HashiCorp Vault or AWS Secrets Manager, or remain K8s-Secret-backed?

### 4.3 dpp-ai (AI Abstraction Layer)

**What it is:** An evolution of the existing FastAPI AI sidecar. Adds provider abstraction (Claude → Bedrock → OpenAI fallback), budget enforcement per module, and a unified prompt registry.

**Current state:** The sidecar (`ai-worker/`) directly calls Claude Sonnet via Anthropic API. Budget cap is `AI_MAX_BUDGET_USD=5.0` per instance.

**Proposed additions:**
- Provider switching: environment-level config to use Claude, Bedrock, or OpenAI
- Per-tenant budget: `AI_BUDGET_TENANT_USD` — cap AI spend per tenant per day
- Per-module budget: Agreements/Notes/Meetings each have a configurable budget allocation
- Prompt registry: centralised versioned prompt storage (DB table) rather than hardcoded strings
- Usage logging: every AI call logged with tenant_id, module, model, tokens, cost

**⚠ Sign-off question P-3:** Should dpp-ai support AWS Bedrock as an alternative AI provider? The `docs/aws-bedrock-future.md` reference in CLAUDE.md suggests this was planned.

---

## 5. Infrastructure Requirements (CTO Action Required Before Build)

The following infrastructure changes are needed before DPP v2 modules can be built. These all affect CTO-protected files (Dockerfile, K8s manifests).

| ID | Requirement | Priority | Stage |
|---|---|---|---|
| **D-INF-1** | Provision SeaweedFS pod in K8s — S3-compatible, replaces MySQL BLOBs | HIGH | Stage 1 |
| **D-INF-2** | Provision PostgreSQL + pgvector sidecar (or external managed Postgres) | HIGH | Stage 3 |
| **D-INF-3** | Provision Soketi/Node.js WebSocket pod + K8s ingress for WebSocket path | MEDIUM | Stage 4 |
| **D-INF-4** | Provision Temporal.io (Temporal Cloud or self-hosted K8s deployment) | MEDIUM | Stage 5 |
| **D-INF-5** | Enable Meilisearch in K8s (pod exists in plan, feature-flagged — just needs activation) | LOW | Stage 2 |
| **D-INF-6** | Add Redis Streams config to K8s deployment (new DB index 4, likely zero-config) | LOW | Stage 1 |
| **D-INF-7** | Per-tenant MySQL `CREATE DATABASE` privilege — already granted (confirmed 2026-03-19) | ✅ Done | Done |
| **D-INF-8** | Wildcard DNS `*.digittal.mobi` in Cloudflare — needed for tenant subdomains | ✅ Done | Done |

---

## 6. Build Stages — Phased Plan

> Stages are sequential. Each stage has a clear output that can be reviewed before the next begins.

### Stage 1 — Platform Foundation (prerequisite for all modules)

**Goal:** Scaffold the platform layer without breaking existing CCRS functionality.

| Task | Description | Effort |
|---|---|---|
| S1-T1 | Create `app/Platform/` with empty service stubs (EventBus, VaultEngine, PlatformAuthService) | S |
| S1-T2 | Create `database/migrations/platform/` directory + first platform migration (platform config table) | S |
| S1-T3 | Implement EventBus: Redis Streams publisher/subscriber, `platform.events` stream | M |
| S1-T4 | Wire EventBus to existing contract lifecycle events (fire `contract.state_changed` on workflow transitions) | M |
| S1-T5 | Implement VaultEngine: per-tenant secret read/write backed by tenant data attribute (extend P0-4 pattern) | M |
| S1-T6 | SeaweedFS: swap `database` disk to `s3` disk in config (D-INF-1 prerequisite) | S |
| S1-T7 | Automate tenant provisioning: add `SeedDatabase` job (seed default roles), make `CreateDatabase` queued | M |
| S1-T8 | Move Agreements code into `app/Modules/Agreements/` (rename/move, no logic changes) | L |

**Exit criteria:** All 1038 existing tests still pass. EventBus publishes events on contract state changes (verified by new integration test). SeaweedFS stores and retrieves a test document.

### Stage 2 — Notes + Meetings Modules

**Goal:** First two DPP-native modules live in `platform/module-notes` and `platform/module-meetings` branches, merged into `platform/v2-dpp`.

| Task | Description | Effort |
|---|---|---|
| S2-T1 | Notes module scaffold: model, migrations, Filament resource, CRUD | M |
| S2-T2 | Notes: AI summarisation via dpp-ai on save | S |
| S2-T3 | Notes: full-text search via Meilisearch (D-INF-5) | M |
| S2-T4 | Meetings module scaffold: model, migrations, Filament resource, CRUD | M |
| S2-T5 | Meetings: .ics calendar generation (CalendarService reuse) | S |
| S2-T6 | Meetings: audio upload + dpp-ai transcription | M |
| S2-T7 | Meetings: AI action item extraction → Task creation (stub Tasks model) | M |
| S2-T8 | EventBus subscriptions: Notes/Meetings listen for relevant contract events | S |

**Exit criteria:** A note can be created, linked to a contract, AI-summarised, and found via search. A meeting can be created, transcribed, and action items extracted.

### Stage 3 — Discovery + Tasks Modules

**Goal:** Semantic search live, Task module complete.

| Task | Description | Effort |
|---|---|---|
| S3-T1 | Tasks module scaffold: model, migrations, Filament resource + Kanban page | M |
| S3-T2 | Tasks: dependency graph, subtasks, recurring | M |
| S3-T3 | Tasks: reminders via existing ReminderService | S |
| S3-T4 | Discovery: dpp-trawler scaffold (Python agent, PostgreSQL pgvector) — D-INF-2 prerequisite | L |
| S3-T5 | Discovery: unified search UI in Filament (cross-module search bar) | M |
| S3-T6 | Discovery: SharePoint indexing via SharePointService | M |
| S3-T7 | EventBus: Discovery subscribes to all content-created events | S |

**Exit criteria:** Tasks are created, assigned, completed. Semantic search returns results across Contracts, Notes, and Meetings with relevance ranking.

### Stage 4 — Email + Chat Modules

**Goal:** Email integration and real-time chat live.

| Task | Description | Effort |
|---|---|---|
| S4-T1 | Email: IMAP polling (Exchange OAuth) + email log model | L |
| S4-T2 | Email: AI auto-link (suggest contract/counterparty from email subject/body) | M |
| S4-T3 | Email: compose + SMTP send from within DPP | M |
| S4-T4 | Chat: model + migrations (threads, messages, participants) | M |
| S4-T5 | Chat: Filament real-time UI + Soketi WebSocket integration — D-INF-3 prerequisite | L |
| S4-T6 | Chat: Teams bridge (post to channel via TeamsNotificationService reuse) | S |
| S4-T7 | Chat: audit log integration | S |

**Exit criteria:** Email from a counterparty can be ingested and linked to a contract. Chat messages appear in real-time between two users on the same contract.

### Stage 5 — Automation Module + Temporal

**Goal:** No-code automation builder backed by Temporal.io durable workflows.

| Task | Description | Effort |
|---|---|---|
| S5-T1 | dpp-workflows sidecar scaffold (Temporal worker, PHP SDK) — D-INF-4 prerequisite | L |
| S5-T2 | Automation: trigger/action/condition model + migrations | M |
| S5-T3 | Automation: visual builder (Livewire, extend existing workflow builder pattern) | L |
| S5-T4 | Automation: Temporal workflow execution + run history | M |
| S5-T5 | Automation: pre-built templates (KYC reminder, expiry alert, state-change webhook) | M |
| S5-T6 | EventBus: Automation subscribes to all platform events | S |

**Exit criteria:** A no-code automation rule fires a Teams notification when a contract changes state. Temporal dashboard shows the run history.

### Stage 6 — Integration, Hardening, and Launch Prep

| Task | Description | Effort |
|---|---|---|
| S6-T1 | v2/integration branch: merge platform/v2-dpp + laravel-migration | M |
| S6-T2 | Full E2E test coverage for all DPP modules | XL |
| S6-T3 | Security audit (DPP_Security_Amendment_v1_1 R-SEC-001 through R-SEC-050) | L |
| S6-T4 | Performance testing: 200+ concurrent users, 50,000+ contracts | M |
| S6-T5 | Multi-tenant smoke test: provision 3 test tenants, verify isolation | M |
| S6-T6 | WCAG 2.1 AA audit of new module UIs | M |
| S6-T7 | Tenant onboarding flow: self-service signup (feature-flagged: `tenant_self_serve`) | L |
| S6-T8 | Documentation: update CLAUDE.md, user manual, API reference | M |

---

## 7. Open Decisions — Required Before Build Starts

The following must be answered before implementation begins on `platform/v2-dpp`.

| ID | Question | Options | Decision |
|---|---|---|---|
| **OD-1** | Agreements module reorganisation timing | A) Move Agreements in Stage 1 before new modules | B) Build new modules alongside existing structure, move in Stage 6 | — |
| **OD-2** | PostgreSQL/pgvector strategy | A) One central Postgres with `tenant_id` scoping | B) One Postgres per tenant (matches MySQL model, complex) | — |
| **OD-3** | Temporal deployment | A) Temporal Cloud (managed, per-workflow pricing) | B) Self-hosted in K8s (CTO effort, lower cost at scale) | — |
| **OD-4** | Chat module WebSockets | A) Soketi in Stage 4 (full real-time) | B) Start with polling, upgrade to Soketi in Stage 5 | — |
| **OD-5** | Email integration target | A) Microsoft Exchange only (Graph API) | B) Exchange + Gmail (broader market) | — |
| **OD-6** | ExchangeRoom vs Chat | A) ExchangeRoom is deprecated, replaced by Chat | B) Both coexist (ExchangeRoom = contract-specific, Chat = general) | — |
| **OD-7** | dpp-ai / Bedrock | A) Stay on Anthropic API (current) | B) Add Bedrock as production path, keep Anthropic as fallback | — |
| **OD-8** | EventBus coexistence | A) Redis Streams replaces Laravel events (migration) | B) Redis Streams is external-only, Laravel events continue internally | — |

---

## 8. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Stage 1 Agreements reorganisation breaks tests | Medium | High | Run full suite before and after every file move; no logic changes |
| SeaweedFS migration loses existing documents | Low | Critical | Run parallel disks during migration; verify every document before cutover |
| Temporal provisioning delays | Medium | Medium | Use Temporal Cloud for staging; self-host only if cost is approved |
| Module isolation leaks (modules calling each other directly) | Medium | Medium | Enforce module boundaries via EventBus — no direct cross-module service calls |
| pgvector per-tenant scaling (one DB per tenant) | High | High | Use central pgvector with `tenant_id` scoping (recommend OD-2 Option A) |
| Chat Soketi infra not ready when Chat module is coded | Medium | Low | Use polling fallback (OD-4 Option B) until infrastructure is ready |

---

## 9. Scope Summary — Module Feature Count

| Module | Features | Stage | Effort |
|---|---|---|---|
| 0 — Agreements (reorganise) | Existing 21 epics | 1 | L (move only) |
| Platform Foundation | EventBus, VaultEngine, dpp-ai, SeaweedFS, tenant provisioning | 1 | L |
| 1 — Notes | 9 features | 2 | M |
| 2 — Meetings | 7 features | 2 | M/L |
| 3 — Discovery | 7 features + pgvector | 3 | L |
| 4 — Tasks | 9 features | 3 | M |
| 5 — Email | 6 features | 4 | L |
| 6 — Chat | 7 features + WebSockets | 4 | L |
| 7 — Automation | 8 features + Temporal | 5 | XL |
| Stage 6 — Integration & hardening | E2E, security, perf, launch | 6 | XL |

---

## 10. How to Proceed

1. **Review this document** — verify each module's feature set (Section 3) and answer the open decisions (Section 7).
2. **CTO reviews infrastructure requirements** (Section 5) — confirm which can be provisioned before each stage.
3. **Reply with answers to open decisions** and any scope adjustments to module features.
4. **On approval:** implementation begins on `platform/v2-dpp` using per-module branches (`platform/module-*`) merged into `platform/v2-dpp`.

> This document lives at `docs/dpp-v2-build-plan.md` on the `platform/v2-dpp` branch.
> It will be updated as decisions are made and stages complete.
