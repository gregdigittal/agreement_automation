# DPP v2 — Requirements Specification

**Digittal Productivity Platform — Version 2**
**Digittal Group**

**Version:** 1.0
**Date:** 2026-03-19
**Status:** Draft — awaiting sign-off
**Branch:** `platform/v2-dpp`
**Supersedes:** CCRS Requirements Specification v4 (DPP v2 scope only)

> **Scope:** This document specifies requirements for the DPP v2 platform layer and seven new modules (Notes, Meetings, Discovery, Tasks, Email, Chat, Automation). CCRS Agreements module requirements are already captured in `CCRS-Requirements-Specification-v4.md` and are not repeated here. All architectural decisions have been signed off — see `docs/dpp-v2-build-plan.md`.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Platform Vision & Scope](#2-platform-vision--scope)
3. [User Roles & Access Model](#3-user-roles--access-model)
4. [Platform Architecture Requirements](#4-platform-architecture-requirements)
5. [Platform Services](#5-platform-services)
   - [P-1: EventBus (Redis Streams)](#p-1-eventbus-redis-streams)
   - [P-2: VaultEngine (Secret Management)](#p-2-vaultengine-secret-management)
   - [P-3: dpp-ai (AI Abstraction Layer)](#p-3-dpp-ai-ai-abstraction-layer)
   - [P-4: dpp-realtime (WebSocket Service)](#p-4-dpp-realtime-websocket-service)
   - [P-5: dpp-trawler (Discovery Agent)](#p-5-dpp-trawler-discovery-agent)
   - [P-6: dpp-workflows (Temporal Orchestration)](#p-6-dpp-workflows-temporal-orchestration)
6. [Module 1 — Notes](#6-module-1--notes)
7. [Module 2 — Meetings](#7-module-2--meetings)
8. [Module 3 — Discovery](#8-module-3--discovery)
9. [Module 4 — Tasks](#9-module-4--tasks)
10. [Module 5 — Email](#10-module-5--email)
11. [Module 6 — Chat](#11-module-6--chat)
12. [Module 7 — Automation](#12-module-7--automation)
13. [Non-Functional Requirements](#13-non-functional-requirements)
14. [Integration Requirements](#14-integration-requirements)
15. [Data & Storage Requirements](#15-data--storage-requirements)
16. [Security Requirements](#16-security-requirements)
17. [Migration Requirements](#17-migration-requirements)
18. [Feature Flag Registry](#18-feature-flag-registry)

---

## 1. Executive Summary

DPP v2 extends the Digittal Productivity Platform from a single-purpose contract management system (CCRS) into a modular, multi-tenant SaaS productivity platform. The Agreements module (CCRS) becomes one of eight modules sharing a common platform layer.

**New modules:** Notes, Meetings, Discovery, Tasks, Email, Chat, Automation.

**New platform services:** EventBus (Redis Streams), VaultEngine, dpp-ai (Bedrock abstraction), dpp-realtime (WebSockets), dpp-trawler (semantic discovery), dpp-workflows (Temporal Cloud).

**Target scale:** 200+ concurrent users per tenant, 50,000+ contracts, multi-tenant SaaS deployment.

**Technology foundation:** All existing CCRS infrastructure is retained. DPP v2 adds sidecars and platform services — it does not replace the Laravel monolith.

---

## 2. Platform Vision & Scope

### 2.1 What DPP v2 Is

DPP is a platform where contract, compliance, and productivity work happen in one place. Every note, meeting, task, and email can be linked to a contract or counterparty — creating a complete record of context around every agreement.

### 2.2 What DPP v2 Is Not

- **Not a general-purpose project management tool** — all modules are anchored to contract/counterparty context
- **Not a standalone email client** — email integration surfaces contract-relevant emails within DPP, not a full inbox replacement
- **Not a CRM** — counterparty management remains within the Agreements module

### 2.3 Module Delivery Scope

| Module | Type | Stage |
|---|---|---|
| Agreements | Existing (reorganised) | Stage 1 |
| Notes | New | Stage 2 |
| Meetings | New | Stage 2 |
| Discovery | New | Stage 3 |
| Tasks | New | Stage 3 |
| Email | New | Stage 4 |
| Chat | New — replaces ExchangeRoom | Stage 4 |
| Automation | New | Stage 5 |

---

## 3. User Roles & Access Model

### 3.1 Existing Roles (unchanged from CCRS)

| Role | Key Additional DPP Capabilities |
|---|---|
| `system_admin` | Full access to all modules; configure automation rules; manage feature flags |
| `legal` | Create notes/meetings/tasks linked to contracts; use Discovery; access Chat |
| `commercial` | Create notes/meetings/tasks linked to counterparties; use Discovery; access Chat |
| `finance` | Read access to notes/meetings linked to financial contracts; Tasks assigned to them |
| `operations` | Notes/tasks linked to operational contracts; read access to meetings |
| `audit` | Read-only access across all modules (notes, meetings, tasks, chat history, automation logs) |

### 3.2 New Role Interactions

- **DPP-R-1** All module access is gated by the user's existing Spatie role — no new top-level roles are introduced in DPP v2.
- **DPP-R-2** Module-level access can be restricted at the feature flag level — disabling a module feature flag removes access for all roles.
- **DPP-R-3** The `vendor` guard (VendorUser model, magic-link auth) gains read-only access to Chat threads on contracts they are a party to.
- **DPP-R-4** The `platform` panel (PlatformAdmin model) gains visibility into module-level usage metrics and automation run history across all tenants.

---

## 4. Platform Architecture Requirements

### 4.1 Module Structure

- **DPP-ARCH-1** All new module code resides in `app/Modules/{ModuleName}/` with subdirectories: `Models/`, `Services/`, `Jobs/`, `Filament/`, `Providers/`.
- **DPP-ARCH-2** Each module registers itself via a `{Module}ServiceProvider` — no module is bootstrapped via hardcoded references in `config/app.php` (provider auto-discovery or explicit provider registration only).
- **DPP-ARCH-3** Modules must not call each other's services directly. Cross-module communication goes through the EventBus (Redis Streams) or through the shared `app/Platform/` services.
- **DPP-ARCH-4** The existing `app/` directory structure coexists with `app/Modules/` during the Stage 1 Agreements reorganisation. Post-Stage-1, all new code is written in `app/Modules/` or `app/Platform/`.
- **DPP-ARCH-5** New DPP migrations go in `database/migrations/platform/`. Existing CCRS migrations in `database/migrations/tenant/` are not moved or modified.

### 4.2 Agreements Reorganisation (Stage 1)

- **DPP-ARCH-6** The Agreements reorganisation is a namespace/directory move only — no logic changes, no model changes, no migration changes.
- **DPP-ARCH-7** The full test suite (1038+ tests, 0 failures) must pass after each layer is moved before the next layer begins. Layers: Models → Services → Jobs → Filament → Providers.
- **DPP-ARCH-8** At Stage 1 completion, no file in `app/Models/`, `app/Services/`, `app/Jobs/`, or `app/Filament/` should belong to the Agreements module (they live in `app/Modules/Agreements/`).

---

## 5. Platform Services

### P-1: EventBus (Redis Streams)

#### Purpose
Decouple modules by providing a durable, async event bus for cross-module and external events. Internal synchronous events continue to use Laravel's built-in Event/Listener system.

#### Requirements

- **DPP-P1-1** EventBus publishes to a single Redis Stream named `platform.events` on Redis database 4.
- **DPP-P1-2** Each event on the stream contains: `event_type` (dot-notation string), `tenant_id`, `payload` (JSON), `published_at` (ISO 8601), `source_module` (string).
- **DPP-P1-3** Consumers are registered as named consumer groups on the stream — each module that subscribes has its own consumer group, ensuring it receives every event independently of other modules.
- **DPP-P1-4** Failed event delivery is retried up to 3 times before the event is written to a dead-letter log table (`platform_event_dead_letters`).
- **DPP-P1-5** EventBus is used for cross-module broadcast events only. Laravel Events/Listeners are RETAINED for synchronous, audit-critical, and transactional paths within a module.
- **DPP-P1-6** The following events are published to the bus at minimum:
  - `contract.created`, `contract.state_changed`, `contract.signed`, `contract.expiring`
  - `task.created`, `task.completed`, `task.overdue`
  - `meeting.created`, `meeting.transcribed`
  - `note.created`, `note.linked`
  - `tenant.provisioned`, `tenant.suspended`
- **DPP-P1-7** EventBus publishing is non-blocking — it must not delay the primary HTTP response.
- **DPP-P1-8** The EventBus is tenant-aware — consumers must only process events for the tenant they are initialised for.

---

### P-2: VaultEngine (Secret Management)

#### Purpose
Centralise per-tenant secret storage, extending the existing per-tenant AI secret pattern (P0-4) to cover all per-tenant and per-user credentials.

#### Requirements

- **DPP-P2-1** VaultEngine exposes three operations: `get(string $key): string`, `put(string $key, string $value): void`, `rotate(string $key): void`.
- **DPP-P2-2** Secrets are stored in K8s Secrets — not in the application database or environment files.
- **DPP-P2-3** Every `rotate()` call logs an entry to `platform_audit_log` with: tenant_id, key name (not value), rotation timestamp, user_id (if user-initiated).
- **DPP-P2-4** Secret values are never written to application logs.
- **DPP-P2-5** VaultEngine manages the following secret types at minimum: per-tenant AI worker key, per-tenant SharePoint OAuth token, per-user Exchange OAuth token (Email module), per-tenant SMTP credentials.
- **DPP-P2-6** VaultEngine is the single retrieval point for all secrets — no module may read credentials directly from `config()` or `env()` at runtime after Stage 1 is complete.

---

### P-3: dpp-ai (AI Abstraction Layer)

#### Purpose
Evolve the existing FastAPI AI sidecar to support multiple AI providers, with AWS Bedrock as the production path and Anthropic as dev/fallback.

#### Requirements

- **DPP-P3-1** An `AiProvider` abstract base class defines the provider interface. Concrete implementations: `AnthropicProvider` and `BedrockProvider`.
- **DPP-P3-2** Active provider is selected via `AI_PROVIDER` environment variable (`anthropic` | `bedrock`). Defaults to `anthropic` in local development.
- **DPP-P3-3** `BedrockProvider` targets AWS Bedrock in the `af-south-1` region using Claude Sonnet (model ID configurable via `BEDROCK_MODEL_ID`).
- **DPP-P3-4** `AnthropicProvider` calls the Anthropic API directly using the existing `AI_WORKER_SECRET` pattern. It serves as dev environment and production fallback.
- **DPP-P3-5** Provider switching does not require code changes — changing `AI_PROVIDER` and redeploying is sufficient.
- **DPP-P3-6** Per-tenant AI budget is enforced: `AI_BUDGET_TENANT_USD` caps spend per tenant per day. Requests that would exceed the cap are rejected with a 402 response before the provider is called.
- **DPP-P3-7** Per-module AI budget allocation is configurable: each module (Agreements, Notes, Meetings, etc.) can be assigned a budget share.
- **DPP-P3-8** Every AI call is logged to `ai_usage_log` with: `tenant_id`, `module`, `model`, `provider`, `input_tokens`, `output_tokens`, `cost_usd`, `duration_ms`.
- **DPP-P3-9** A prompt registry table (`ai_prompts`) stores versioned prompt templates. Modules reference prompts by name + version rather than embedding prompt strings in code.
- **DPP-P3-10** All existing `AiWorkerClient` callers in the Agreements module continue to work without modification — the abstraction is added at the sidecar level, not the PHP client level.
- **DPP-P3-11** If `BedrockProvider` is unavailable (service error, quota exceeded), the sidecar automatically falls back to `AnthropicProvider` and logs the fallback event.

---

### P-4: dpp-realtime (WebSocket Service)

#### Purpose
Provide real-time message delivery for the Chat module via Soketi (Pusher-compatible WebSocket server).

#### Requirements

- **DPP-P4-1** dpp-realtime is a Soketi pod deployed as a K8s sidecar alongside dpp-core.
- **DPP-P4-2** The K8s ingress routes WebSocket upgrade requests (`ws://`, `wss://`) to the Soketi pod.
- **DPP-P4-3** Laravel Broadcasting is configured to use the Pusher driver pointing at Soketi.
- **DPP-P4-4** All WebSocket channels are private and tenant-scoped — channel names include `tenant_{id}` as a prefix.
- **DPP-P4-5** WebSocket connections are authenticated via the standard Laravel Broadcasting authentication endpoint.
- **DPP-P4-6** Message delivery failures (Soketi unreachable) are handled gracefully — the message is saved to the database and a retry flag is set; the UI shows "sent" status when the message is persisted, not when the WebSocket ACK is received.
- **DPP-P4-7** dpp-realtime is used exclusively by the Chat module in DPP v2. It is not used for live notifications or presence detection in other modules.

---

### P-5: dpp-trawler (Discovery Agent)

#### Purpose
Background agent that crawls tenant content, generates vector embeddings, and writes them to the central pgvector store to power semantic search.

#### Requirements

- **DPP-P5-1** dpp-trawler is a Python agent that runs on a configurable cron schedule (default: every 6 hours).
- **DPP-P5-2** dpp-trawler reads from: Contracts (extracted text from `ai_analysis_results`), Notes (content), Meetings (transcript + minutes), Tasks (title + description).
- **DPP-P5-3** dpp-trawler generates embeddings via `dpp-ai` (using the configured provider) and writes them to the central PostgreSQL + pgvector store.
- **DPP-P5-4** Every embedding record includes `tenant_id` — Row-Level Security enforces that queries only return embeddings for the authenticated tenant.
- **DPP-P5-5** dpp-trawler is incremental — it only processes content created or modified since its last run (tracked via a `last_crawled_at` timestamp per content type).
- **DPP-P5-6** dpp-trawler handles missing or empty content gracefully — records with no extractable text are skipped without error.
- **DPP-P5-7** dpp-trawler indexes SharePoint documents linked to tenants via SharePointService (Stage 3 scope).
- **DPP-P5-8** A crawl run log is maintained: start time, end time, records processed, errors encountered. Visible in the platform admin panel.

---

### P-6: dpp-workflows (Temporal Orchestration)

#### Purpose
Durable, retryable workflow execution for the Automation module, backed by Temporal Cloud.

#### Requirements

- **DPP-P6-1** A `TemporalWorkflowProvider` interface abstracts the Temporal client — all workflow invocations go through it. This enables future self-host migration without code changes.
- **DPP-P6-2** The dpp-workflows sidecar is a Temporal worker built with the Temporal PHP SDK. It connects to the Temporal Cloud namespace via `TEMPORAL_CLOUD_HOST`, `TEMPORAL_CLOUD_NAMESPACE`, `TEMPORAL_CLOUD_API_KEY`.
- **DPP-P6-3** Each Automation rule execution is a Temporal workflow — it is durable (survives pod restarts), retryable (configurable retry policy per action type), and auditable (full run history in Temporal Cloud UI).
- **DPP-P6-4** Temporal workflows do not replace Laravel Horizon — Horizon continues to manage all existing CCRS background jobs. Temporal is additive, not a migration of existing jobs.
- **DPP-P6-5** Temporal workflow execution is tenant-isolated — each workflow includes `tenant_id` in its metadata and is tagged for filtering in the Temporal Cloud UI.
- **DPP-P6-6** If Temporal Cloud is unreachable at workflow dispatch time, the dispatch fails gracefully and the Automation run is marked as `failed` with reason `temporal_unavailable`. It does not crash the primary request.

---

## 6. Module 1 — Notes

### 6.1 Overview

AI-enhanced note-taking linked to contracts, counterparties, meetings, and tasks. Notes are searchable, taggable, and versioned.

### 6.2 Functional Requirements

#### Note Creation and Editing

- **DPP-M1-1** A user can create a note with a rich-text editor (minimum support: headings, bold, italic, bullet lists, numbered lists, inline code).
- **DPP-M1-2** A note must have at least a title to be saved.
- **DPP-M1-3** A note can be linked to zero or more of: Contract, Counterparty, Meeting, Task. A note with no links is a stand-alone note.
- **DPP-M1-4** A note can be tagged with user-defined text tags. Up to 20 tags per note.
- **DPP-M1-5** When a note is saved, the system optionally suggests up to 5 tags based on the note content via dpp-ai (on-demand, not automatic).
- **DPP-M1-6** A user can @mention another user within a note. The mentioned user receives an in-app notification.

#### AI Summarisation

- **DPP-M1-7** A "Summarise" action is available on any note. When triggered, dpp-ai generates a 1–3 paragraph summary of the note content.
- **DPP-M1-8** AI summarisation is on-demand only — it is not triggered automatically on save.
- **DPP-M1-9** The generated summary is displayed alongside the note (not replacing the original) and can be dismissed.

#### Version History

- **DPP-M1-10** Every save to a note creates an immutable version record with: content snapshot, editor user_id, saved_at timestamp.
- **DPP-M1-11** Version history is retained indefinitely.
- **DPP-M1-12** A user can view a diff between any two versions of a note.
- **DPP-M1-13** A user can restore a previous version (which creates a new version, not an overwrite).

#### Permissions

- **DPP-M1-14** The note creator and users with `system_admin` role can edit the note.
- **DPP-M1-15** Users who have access to any entity the note is linked to (via their role and Filament Shield permissions) have read access to that note.
- **DPP-M1-16** Deleting a note is a soft-delete (logical only). System_admin can hard-delete.

#### Search and Export

- **DPP-M1-17** Notes are indexed in Meilisearch. Search covers: title, content, tags.
- **DPP-M1-18** A note can be exported to PDF. The export includes: title, content, tags, creation date, and author.
- **DPP-M1-19** EventBus publishes `note.created` and `note.linked` events when a note is saved and when a link is added.

### 6.3 Data Model

Tables: `notes`, `note_versions`, `note_tags`, `note_tag_pivot`, `note_links`

| Table | Key Columns |
|---|---|
| `notes` | `id` (UUID), `tenant_id`, `title`, `content` (longtext), `created_by`, `deleted_at` |
| `note_versions` | `id` (UUID), `note_id`, `content`, `editor_id`, `created_at` |
| `note_tags` | `id` (UUID), `tenant_id`, `name` |
| `note_tag_pivot` | `note_id`, `note_tag_id` |
| `note_links` | `id` (UUID), `note_id`, `linkable_type`, `linkable_id` |

---

## 7. Module 2 — Meetings

### 7.1 Overview

Meeting management with AI transcription, action item extraction, and linkage to contracts and tasks.

### 7.2 Functional Requirements

#### Meeting Records

- **DPP-M2-1** A meeting record contains: title, description, date/time (start + end), location (optional), Teams meeting join link (optional), organiser (User), attendees.
- **DPP-M2-2** Attendees can be: internal Users, and/or Counterparty contacts (linked via Counterparty model).
- **DPP-M2-3** A meeting can be linked to: Contract(s), Counterparty, Project — or stand-alone.
- **DPP-M2-4** A Teams meeting join link can be entered manually. Automatic Teams calendar integration is out of scope for DPP v2.
- **DPP-M2-5** Calendar invite (.ics) can be generated and downloaded for any meeting (via existing CalendarService).

#### Notifications and Reminders

- **DPP-M2-6** The meeting organiser and all attendees receive an in-app notification when the meeting is created.
- **DPP-M2-7** Reminder notifications are dispatched 24 hours and 1 hour before the meeting start time (via existing ReminderService).
- **DPP-M2-8** If a meeting is rescheduled, all attendees are notified of the new time.

#### Transcription

- **DPP-M2-9** A user can upload an audio or video file to a meeting record. Supported formats: MP3, MP4, M4A, WAV (up to 500 MB).
- **DPP-M2-10** On upload, the file is stored via SeaweedFS and a transcription job is queued.
- **DPP-M2-11** dpp-ai processes the audio/video and returns a plain-text transcript. The transcript is stored against the meeting record.
- **DPP-M2-12** Real-time (live) transcription is out of scope for DPP v2.
- **DPP-M2-13** Transcription completion triggers an in-app notification to the meeting organiser.
- **DPP-M2-14** EventBus publishes `meeting.transcribed` after transcription completes.

#### Action Item Extraction and Minutes

- **DPP-M2-15** Once a transcript is available, a user can trigger "Extract Action Items" — dpp-ai analyses the transcript and suggests a list of action items.
- **DPP-M2-16** Each suggested action item displays: description, suggested assignee (matched against meeting attendees), suggested due date (if inferable from transcript).
- **DPP-M2-17** The user reviews and confirms each action item before it is created as a Task record (Module 4). Unconfirmed items are discarded.
- **DPP-M2-18** A user can trigger "Generate Minutes" — dpp-ai produces structured meeting minutes from the transcript (agenda items, decisions made, action items).
- **DPP-M2-19** Generated minutes are editable. The final minutes can be exported to PDF or DOCX (via existing PdfService and PHPWord).
- **DPP-M2-20** Minutes do not replace Teams notification format — they are an export artefact only.

#### Permissions

- **DPP-M2-21** Meeting records are visible to: organiser, attendees, `system_admin`, and users with access to any linked entity.
- **DPP-M2-22** Only the organiser or `system_admin` can edit or delete a meeting record.

### 7.3 Data Model

| Table | Key Columns |
|---|---|
| `meetings` | `id` (UUID), `tenant_id`, `title`, `description`, `starts_at`, `ends_at`, `location`, `teams_url`, `organiser_id`, `transcript` (longtext, nullable), `minutes` (longtext, nullable) |
| `meeting_attendees` | `meeting_id`, `attendee_type` (User/CounterpartyContact), `attendee_id` |
| `meeting_links` | `meeting_id`, `linkable_type`, `linkable_id` |
| `meeting_transcription_jobs` | `id` (UUID), `meeting_id`, `file_path`, `status`, `queued_at`, `completed_at`, `error` |

---

## 8. Module 3 — Discovery

### 8.1 Overview

Semantic search and knowledge graph across all tenant content. Powered by central PostgreSQL + pgvector and the dpp-trawler background agent.

### 8.2 Functional Requirements

#### Unified Search

- **DPP-M3-1** A global search bar is available in the Filament navigation. It searches across: Contracts, Counterparties, Notes, Meetings, Tasks.
- **DPP-M3-2** Search supports two modes: keyword (Meilisearch) and semantic (pgvector). The UI allows the user to toggle between them.
- **DPP-M3-3** Search results are grouped by entity type and show: entity name, a content excerpt, relevance score, and a link to the entity.
- **DPP-M3-4** Search results are always scoped to the current tenant — cross-tenant results are never returned.

#### Semantic Search (pgvector)

- **DPP-M3-5** Semantic search uses vector similarity (cosine distance) against embeddings stored in the central PostgreSQL + pgvector instance.
- **DPP-M3-6** Every query includes a mandatory `tenant_id` predicate. PostgreSQL Row-Level Security enforces this at the database level as a second layer.
- **DPP-M3-7** Search results include an explainability note: "Matched because: [list of 1–3 matching concepts extracted from the query and the result]."
- **DPP-M3-8** Results can be filtered by: entity type, date range (created_at), confidence threshold (minimum similarity score — slider control).

#### Knowledge Graph

- **DPP-M3-9** From any entity's detail page, a "Related" panel shows semantically similar entities across all modules (e.g. "Contracts similar to this one", "Notes that mention this counterparty").
- **DPP-M3-10** Related entities are retrieved from pgvector similarity search, not rule-based linking.
- **DPP-M3-11** The user can navigate directly from a related entity suggestion to that entity's page.

#### SharePoint Indexing

- **DPP-M3-12** dpp-trawler indexes documents in SharePoint folders linked to tenants (via existing SharePointService) in Stage 3.
- **DPP-M3-13** SharePoint documents appear in semantic search results with: document name, SharePoint folder path, tenant-scoped access link.
- **DPP-M3-14** SharePoint indexing respects the existing SharePoint feature flag (`FEATURE_SHAREPOINT`).

### 8.3 Data Model (pgvector — central PostgreSQL)

| Table | Key Columns |
|---|---|
| `content_embeddings` | `id` (UUID), `tenant_id`, `entity_type`, `entity_id`, `content_hash` (for deduplication), `embedding` (vector), `crawled_at` |

RLS policy: `tenant_id = current_setting('app.tenant_id')::uuid`

---

## 9. Module 4 — Tasks

### 9.1 Overview

Task management linked to contracts, counterparties, meetings, and notes. Replaces ad-hoc reminder and action-item tracking across CCRS.

### 9.2 Functional Requirements

#### Task Records

- **DPP-M4-1** A task contains: title (required), description (optional), assignee (User), due date, priority (`low`, `medium`, `high`, `urgent`), status.
- **DPP-M4-2** Tasks are linked to: Contract, Counterparty, Meeting, Note, or stand-alone.
- **DPP-M4-3** Task status machine: `open → in_progress → blocked → done → cancelled`. Any status can transition to `cancelled`. `done` and `cancelled` are terminal states.
- **DPP-M4-4** The existing CCRS Project model is used for grouping tasks under a project — no separate Projects entity is introduced.

#### Subtasks and Dependencies

- **DPP-M4-5** A task can have subtasks (1 level only — subtasks cannot have subtasks).
- **DPP-M4-6** A task can declare that it is blocked by another task. A task in `blocked` status shows which task(s) are blocking it.
- **DPP-M4-7** When all blocking tasks reach `done` or `cancelled`, the blocked task is automatically transitioned from `blocked` to `open` and the assignee is notified.

#### Recurring Tasks

- **DPP-M4-8** A task can be set to recur on a schedule: daily, weekly, monthly, or custom interval.
- **DPP-M4-9** When a recurring task is completed, a new instance is created for the next scheduled occurrence.
- **DPP-M4-10** Recurring tasks show a "recurrence" indicator in the list view.

#### Reminders

- **DPP-M4-11** The assignee receives in-app reminder notifications at: 24 hours before due date, 1 hour before due date (via existing ReminderService).
- **DPP-M4-12** If a task reaches its due date without being completed, it transitions to `overdue` status (sub-status of `in_progress`) and EventBus publishes `task.overdue`.

#### Board View

- **DPP-M4-13** A Kanban board view is available as a Filament custom page, showing tasks grouped by status column (`open`, `in_progress`, `blocked`, `done`).
- **DPP-M4-14** Tasks can be dragged between columns on the board (drag-and-drop updates status).
- **DPP-M4-15** The board can be filtered by: assignee, linked contract, priority, due date range.

#### Reporting

- **DPP-M4-16** A task completion report is available showing: completion rate per user, per contract, per period (weekly/monthly).
- **DPP-M4-17** Overdue tasks are surfaced in a dedicated "Overdue" filter on the task list.

#### EventBus

- **DPP-M4-18** EventBus publishes: `task.created` on creation, `task.completed` on done, `task.overdue` on due date breach.

### 9.3 Data Model

| Table | Key Columns |
|---|---|
| `tasks` | `id` (UUID), `tenant_id`, `title`, `description`, `assignee_id`, `due_at`, `priority`, `status`, `parent_task_id` (nullable, for subtasks), `recurrence_rule` (JSON, nullable), `project_id` (nullable) |
| `task_links` | `task_id`, `linkable_type`, `linkable_id` |
| `task_dependencies` | `task_id`, `blocked_by_task_id` |

---

## 10. Module 5 — Email

### 10.1 Overview

Email integration that surfaces contract-relevant emails within DPP. Per-user Exchange OAuth inbox connection via an `EmailProvider` interface.

### 10.2 Functional Requirements

#### EmailProvider Interface

- **DPP-M5-1** An `EmailProvider` abstract class defines the interface for all email provider implementations. Methods: `connect(User $user)`, `pollInbox(User $user): array`, `sendEmail(array $payload): bool`, `disconnect(User $user)`.
- **DPP-M5-2** `ExchangeEmailProvider` is the first concrete implementation (Microsoft Exchange OAuth via Graph API).
- **DPP-M5-3** The interface is designed so that a `GmailEmailProvider` or other adapters can be added in a future release without modifying any existing module code.

#### Inbox Connection

- **DPP-M5-4** Each user can independently connect their Exchange mailbox via an OAuth flow within their DPP profile settings.
- **DPP-M5-5** OAuth credentials are stored in VaultEngine (per-user, per-tenant), not in the application database.
- **DPP-M5-6** Users can disconnect their mailbox at any time. Disconnection revokes the OAuth token and removes it from VaultEngine.
- **DPP-M5-7** Open/click tracking is out of scope for DPP v2.

#### Email Ingestion

- **DPP-M5-8** A background job polls each connected user's inbox on a configurable interval (default: every 15 minutes).
- **DPP-M5-9** Only emails that match a linking heuristic are ingested: subject or body contains a contract reference number, counterparty name, or a tracked domain.
- **DPP-M5-10** dpp-ai analyses the subject and body of each candidate email and suggests: the contract or counterparty to link it to, a confidence score.
- **DPP-M5-11** Emails with confidence score above a configurable threshold are auto-linked. Emails below the threshold are surfaced for manual review.
- **DPP-M5-12** Ingested emails are stored as an immutable log — they cannot be edited or deleted from within DPP.

#### Email Log

- **DPP-M5-13** All emails linked to a contract are visible in an "Email" tab on the contract detail page, showing: subject, sender, recipient, date, body preview, attachment count.
- **DPP-M5-14** Attachments are extracted and stored via SeaweedFS, auto-linked to the contract's file library.
- **DPP-M5-15** A notification is dispatched to the contract owner when a counterparty email is linked to their contract.

#### Compose and Send

- **DPP-M5-16** A user can compose an email from within DPP (linked to a contract). The email is sent via the existing SMTP config.
- **DPP-M5-17** Sent emails are automatically added to the email log for the linked contract.
- **DPP-M5-18** Re-usable email templates can be created by `system_admin` and used by all roles. Templates support placeholder variables: `{{contract_title}}`, `{{counterparty_name}}`, `{{signing_deadline}}`.

### 10.3 Data Model

| Table | Key Columns |
|---|---|
| `emails` | `id` (UUID), `tenant_id`, `user_id` (connected inbox owner), `message_id` (Exchange message ID), `subject`, `sender_address`, `sender_name`, `recipient_addresses` (JSON), `body_preview`, `received_at`, `direction` (`inbound`/`outbound`) |
| `email_links` | `email_id`, `linkable_type`, `linkable_id` |
| `email_attachments` | `id` (UUID), `email_id`, `file_name`, `file_path` (SeaweedFS), `file_size` |
| `email_templates` | `id` (UUID), `tenant_id`, `name`, `subject_template`, `body_template`, `created_by` |

---

## 11. Module 6 — Chat

### 11.1 Overview

Real-time in-app chat threads linked to contracts and counterparties. Replaces ExchangeRoom via a two-sprint migration.

### 11.2 Functional Requirements

#### Chat Threads

- **DPP-M6-1** A chat thread is created in the context of a Contract or Counterparty. A thread has: title (optional), linked entity, list of participants.
- **DPP-M6-2** Each message in a thread contains: author (User or system), content (plain text + optional rich text), timestamp, attachments (optional).
- **DPP-M6-3** Messages are immutable once sent — no edit or delete by users. Audit-critical immutability.
- **DPP-M6-4** Multiple threads can exist per contract or counterparty (e.g. "Legal discussion thread", "Commercial terms thread").

#### Real-Time Delivery

- **DPP-M6-5** New messages are delivered in real-time via dpp-realtime (Soketi). Messages appear instantly in all open browser sessions for thread participants without page refresh.
- **DPP-M6-6** If WebSocket delivery fails, the message is persisted to the database. The recipient sees the message on next page load or WebSocket reconnect.
- **DPP-M6-7** No polling fallback is implemented — dpp-realtime (D-INF-3) is a hard prerequisite for Stage 4.

#### Participants

- **DPP-M6-8** Internal users can be added to a thread by any existing participant or by `system_admin`.
- **DPP-M6-9** Counterparty contacts (VendorUser) can be added to a thread and access it via the vendor portal (read and post, not thread management).
- **DPP-M6-10** Participants receive an in-app notification when added to a thread.

#### Features

- **DPP-M6-11** A user can @mention another participant — the mentioned user receives an in-app notification.
- **DPP-M6-12** A user can share a document from the contract file library into a chat thread. The document appears as a linked file attachment, not an inline copy.
- **DPP-M6-13** A "Summarise thread" action triggers dpp-ai to generate a paragraph-length summary of the conversation.
- **DPP-M6-14** An optional Teams bridge posts chat messages to a configured Teams channel (per-thread setting, uses TeamsNotificationService).
- **DPP-M6-15** All chat messages are included in the contract audit log (immutable `AuditLog` entries).

#### ExchangeRoom Migration

- **DPP-M6-16** During Stage 4, Chat and ExchangeRoom coexist. Both feature flags (`chat=true`, `exchange_room=true`) are enabled.
- **DPP-M6-17** During Stage 5, a data migration script copies all ExchangeRoom posts to Chat threads. The migration must be verified by: count of ExchangeRoom posts before = count of Chat messages after.
- **DPP-M6-18** After migration is verified, `exchange_room=false` feature flag is set. ExchangeRoom UI is hidden but code is not removed until Stage 6.
- **DPP-M6-19** In Stage 6, all ExchangeRoom model, service, Filament resource, migration, and route code is deleted. This is a clean removal — no backwards-compatibility shims.

### 11.3 Data Model

| Table | Key Columns |
|---|---|
| `chat_threads` | `id` (UUID), `tenant_id`, `title` (nullable), `linkable_type`, `linkable_id`, `created_by` |
| `chat_messages` | `id` (UUID), `thread_id`, `author_type`, `author_id`, `content`, `sent_at` |
| `chat_participants` | `thread_id`, `participant_type` (User/VendorUser), `participant_id`, `added_at` |
| `chat_attachments` | `id` (UUID), `message_id`, `file_path`, `file_name` |

---

## 12. Module 7 — Automation

### 12.1 Overview

No-code automation builder enabling users to define rules: "when X happens, do Y". Backed by Temporal Cloud for durable, auditable workflow execution.

### 12.2 Functional Requirements

#### Rule Structure

- **DPP-M7-1** An automation rule contains: name, trigger, zero or more conditions, one or more actions.
- **DPP-M7-2** A rule can be enabled or disabled independently of its definition.
- **DPP-M7-3** Rules are tenant-scoped — a rule created in Tenant A cannot affect Tenant B.
- **DPP-M7-4** Only `system_admin` and `legal` roles can create or modify automation rules.

#### Triggers

- **DPP-M7-5** Available trigger types:
  - `contract.state_changed` — fires when a contract transitions workflow state
  - `key_date` — fires N days before a contract date field (due date, expiry date, etc.)
  - `form_submitted` — fires when a specified Filament form is submitted
  - `webhook` — fires when an inbound webhook POST is received at the tenant's webhook URL
  - `manual` — fires only when explicitly triggered by a user from the contract detail page

#### Conditions

- **DPP-M7-6** Available condition operators: `equals`, `not_equals`, `contains`, `greater_than`, `less_than`, `is_empty`, `is_not_empty`.
- **DPP-M7-7** Conditions can reference any field on the triggering entity (e.g. `contract.type = "Merchant Agreement"`, `contract.value > 100000`).
- **DPP-M7-8** Conditions can reference the current user's role (`user.role = "legal"`).
- **DPP-M7-9** Multiple conditions are combined with AND logic (all must be true for actions to execute).

#### Actions

- **DPP-M7-10** Available action types:
  - `send_notification` — in-app, email, or Teams notification to a specified user or role
  - `create_task` — create a task record (with configurable title template, assignee, due date offset)
  - `update_field` — update a field on the triggering entity (e.g. set `contract.status = "under_review"`)
  - `call_webhook` — POST a JSON payload to an external URL
  - `generate_document` — generate a PDF from a specified template
  - `start_workflow` — trigger an existing Workflow Template on the contract
- **DPP-M7-11** Action fields support template variables: `{{contract.title}}`, `{{counterparty.name}}`, `{{user.name}}`, `{{date}}`.

#### Visual Builder

- **DPP-M7-12** The automation rule builder is a visual Livewire interface — not a code editor. Users select trigger, add condition blocks, and add action blocks via dropdowns and form fields.
- **DPP-M7-13** The builder extends the existing visual workflow builder pattern (Livewire drag-and-drop components).
- **DPP-M7-14** Pre-built templates are available: "KYC reminder 30 days before expiry", "Teams alert on contract state change", "Create task when contract enters review".

#### Execution and History

- **DPP-M7-15** Each automation rule execution is a Temporal workflow — durable and retryable.
- **DPP-M7-16** Each action within a run is a separate Temporal activity, retried independently up to 3 times on failure.
- **DPP-M7-17** A run log is stored per execution: rule name, trigger event, trigger entity, conditions evaluated, actions executed (with individual success/failure), total duration, Temporal workflow ID.
- **DPP-M7-18** Run history is visible in the Automation resource in Filament, showing the last 100 runs with status (success, partial, failed).

#### Rate Limiting

- **DPP-M7-19** A maximum of N automation rule executions per tenant per hour is enforced (N is configurable, default: 100). Executions beyond the limit are queued and processed in the next window.
- **DPP-M7-20** Webhook action calls include a 30-second timeout. If the external endpoint does not respond, the action is marked failed and retried.

### 12.3 Data Model

| Table | Key Columns |
|---|---|
| `automation_rules` | `id` (UUID), `tenant_id`, `name`, `trigger_type`, `trigger_config` (JSON), `conditions` (JSON), `actions` (JSON), `is_enabled`, `created_by` |
| `automation_runs` | `id` (UUID), `rule_id`, `tenant_id`, `trigger_entity_type`, `trigger_entity_id`, `status`, `temporal_workflow_id`, `started_at`, `completed_at` |
| `automation_run_actions` | `id` (UUID), `run_id`, `action_type`, `action_config` (JSON), `status`, `attempts`, `result` (JSON), `executed_at` |

---

## 13. Non-Functional Requirements

### 13.1 Performance

- **DPP-NFR-1** All Filament page loads (including module pages) must render in under 2 seconds for a tenant with 50,000 contracts and 1,000 active tasks at p95.
- **DPP-NFR-2** Semantic search queries (pgvector) must return results in under 500ms at p95 for a tenant with 100,000 indexed content items.
- **DPP-NFR-3** Chat message delivery via WebSocket must complete in under 200ms from send to receipt in the same region.
- **DPP-NFR-4** The Notes and Meetings list views must not produce N+1 query patterns. All list queries must use eager loading.
- **DPP-NFR-5** dpp-trawler incremental crawl for a tenant with 10,000 content items must complete in under 30 minutes.
- **DPP-NFR-6** AI summarisation (Notes, Meetings) must return a result within 30 seconds. Requests exceeding this timeout return an error — they are not silently dropped.

### 13.2 Scalability

- **DPP-NFR-7** The platform supports a minimum of 200 concurrent users per tenant.
- **DPP-NFR-8** The platform supports a minimum of 50,000 contracts per tenant.
- **DPP-NFR-9** The central pgvector store must scale to 10 million embedding vectors across all tenants without performance degradation on tenant-scoped queries.
- **DPP-NFR-10** Automation rule execution must support bursts of up to 500 workflow dispatches per minute across all tenants.

### 13.3 Reliability

- **DPP-NFR-11** dpp-realtime (Soketi) outage must not prevent message creation — messages are persisted and delivered on reconnect.
- **DPP-NFR-12** dpp-ai provider unavailability must not block primary application operations — AI features degrade gracefully (features become unavailable, not errors).
- **DPP-NFR-13** Temporal Cloud unavailability must not crash primary HTTP requests — automation dispatch fails gracefully with a logged error.
- **DPP-NFR-14** dpp-trawler failure (any run) must not affect search functionality — the last indexed state remains available.

### 13.4 Accessibility

- **DPP-NFR-15** All new module UIs (Notes, Meetings, Tasks, Chat, Email, Discovery, Automation) must meet WCAG 2.1 Level AA baseline.
- **DPP-NFR-16** Keyboard navigation must be supported across all module list and detail views.
- **DPP-NFR-17** Screen reader compatibility for all new forms (ARIA labels, roles, and live regions where applicable).

### 13.5 Maintainability

- **DPP-NFR-18** Every new module must have PestPHP test coverage achieving minimum 80% line coverage.
- **DPP-NFR-19** Module code must pass Laravel Pint (`--test`) with no violations.
- **DPP-NFR-20** No module may reference another module's internals directly — inter-module communication is through the EventBus or `app/Platform/` services only (enforced at code review).

---

## 14. Integration Requirements

### 14.1 Microsoft 365 (existing — unchanged)

- **DPP-INT-1** Azure AD SSO continues to serve as the authentication provider for all internal users — no changes to the existing Socialite integration.
- **DPP-INT-2** SharePoint Graph API integration (Sites.Read.All, Files.Read.All) continues to function as in CCRS. Discovery (Module 3) extends it with indexing.
- **DPP-INT-3** Teams notifications via TeamsNotificationService continue to function — Chat module adds an optional bridge but does not replace or modify the existing service.

### 14.2 Microsoft Exchange (new — Module 5)

- **DPP-INT-4** Exchange email integration uses Microsoft Graph API Mail endpoints (`Mail.Read`, `Mail.Send` permissions per user via delegated OAuth).
- **DPP-INT-5** Per-user OAuth tokens are stored in VaultEngine.
- **DPP-INT-6** Exchange integration is gated by `FEATURE_EMAIL=true` feature flag.

### 14.3 AWS Bedrock (new — dpp-ai)

- **DPP-INT-7** Bedrock integration targets the `af-south-1` region.
- **DPP-INT-8** Model selection is configurable via `BEDROCK_MODEL_ID` environment variable.
- **DPP-INT-9** Bedrock is only invoked when `AI_PROVIDER=bedrock`. When `AI_PROVIDER=anthropic`, no AWS credentials are required or used.

### 14.4 Temporal Cloud (new — dpp-workflows)

- **DPP-INT-10** The dpp-workflows sidecar connects to Temporal Cloud via the Temporal PHP SDK.
- **DPP-INT-11** All workflow execution remains in Temporal Cloud — no self-hosted Temporal server is provisioned.
- **DPP-INT-12** Temporal Cloud dashboard is used directly by `system_admin` for run inspection and manual replay. There is no in-app Temporal UI embedding.

### 14.5 Meilisearch (existing — activation)

- **DPP-INT-13** Meilisearch is enabled for Stage 2 (Notes full-text search). The Laravel Scout + Meilisearch integration is already installed (`FEATURE_MEILISEARCH` flag).
- **DPP-INT-14** Notes, Meetings titles, and Task titles are indexed in Meilisearch. Contract indexing (already designed) is also activated at this point.

---

## 15. Data & Storage Requirements

### 15.1 SeaweedFS (document storage)

- **DPP-DS-1** SeaweedFS replaces MySQL BLOB storage (`file_storage` table) as the document store. The `database` Flysystem disk is swapped for the `s3` disk.
- **DPP-DS-2** Existing documents in `file_storage` must be migrated to SeaweedFS before the disk swap. The migration must run with zero downtime (write to both, cutover, verify, clean up).
- **DPP-DS-3** All new document writes in DPP v2 modules (meeting transcripts, chat attachments, email attachments) go directly to SeaweedFS — never to MySQL.
- **DPP-DS-4** SeaweedFS paths are served via the existing signed URL pattern (`StorageServeController`) — no direct SeaweedFS URLs are exposed to the browser.

### 15.2 Central pgvector Store

- **DPP-DS-5** The central PostgreSQL instance hosts the `content_embeddings` table (and only that table). It is not used as a general-purpose application database.
- **DPP-DS-6** Row-Level Security is enabled at the PostgreSQL level: `tenant_id = current_setting('app.tenant_id')::uuid`. This is non-negotiable — it is the primary isolation boundary for vector data.
- **DPP-DS-7** The application database user for pgvector has SELECT and INSERT privileges only — no UPDATE, DELETE, DROP.
- **DPP-DS-8** Embeddings for deleted content are removed during dpp-trawler's next run (soft-delete aware).

### 15.3 Tenant Data Isolation

- **DPP-DS-9** All new module tables are created in the per-tenant database (in `database/migrations/platform/`). These run on the tenant connection, not the central connection.
- **DPP-DS-10** No cross-tenant query is permitted. All Eloquent models in DPP modules must either live in a tenant database or enforce `tenant_id` scoping via a global scope.

---

## 16. Security Requirements

The following are DPP v2-specific security requirements. They supplement (and do not replace) the 50 requirements in `docs/reference/DPP_Security_Amendment_v1_1.md` (R-SEC-001 through R-SEC-050).

### 16.1 Multi-Tenancy

- **DPP-SEC-1** pgvector RLS must be tested with an explicit attempt to query cross-tenant data — this must return zero results (automated test in Stage 3).
- **DPP-SEC-2** EventBus consumers must validate `tenant_id` on every consumed event — an event published by Tenant A must never trigger an action on Tenant B's data.
- **DPP-SEC-3** Chat WebSocket channels must include `tenant_id` as a channel name prefix. A user who authenticates for one tenant must not be able to subscribe to another tenant's channel.

### 16.2 Credential Handling

- **DPP-SEC-4** Exchange OAuth tokens (Module 5) are stored in VaultEngine only. They are never written to the application database, log files, or event payloads.
- **DPP-SEC-5** AWS credentials (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`) are stored as K8s Secrets and injected as environment variables — never in application config files or source code.
- **DPP-SEC-6** Temporal Cloud API keys are stored as K8s Secrets — never in source code.

### 16.3 AI Safety

- **DPP-SEC-7** AI-generated content (task suggestions, meeting minutes, email auto-links) is always presented for user review before it creates, modifies, or links any record. No AI action is irreversible without a human confirmation step.
- **DPP-SEC-8** AI prompt templates in the `ai_prompts` table are immutable once published (versioned, not updated). New prompts create new versions.
- **DPP-SEC-9** AI usage logs (`ai_usage_log`) do not include the full prompt or completion text — only metadata (tenant_id, module, tokens, cost).

### 16.4 Automation Safety

- **DPP-SEC-10** Automation webhook actions (outbound HTTP calls) may only POST to URLs on an allowlist configured by `system_admin`. Arbitrary URLs cannot be entered by non-admin users.
- **DPP-SEC-11** Inbound webhook triggers are authenticated via HMAC signature verification. Unsigned inbound webhooks are rejected.
- **DPP-SEC-12** Automation rules cannot directly access or export tenant data in bulk — action types are limited to the defined set (DPP-M7-10). New action types require explicit code changes and a security review.

### 16.5 Chat and Email

- **DPP-SEC-13** Chat message content is not included in EventBus event payloads — only metadata (thread_id, message_id, author_id) is broadcast.
- **DPP-SEC-14** Email body content is not written to application logs.
- **DPP-SEC-15** Email attachments downloaded from Exchange are scanned for MIME type before being stored in SeaweedFS. Executables and archives (`.exe`, `.zip`, `.tar`) are rejected.

---

## 17. Migration Requirements

### 17.1 Agreements Reorganisation (Stage 1)

- **DPP-MIG-1** The Stage 1 reorganisation is a namespace/directory move only. No migration files are moved or modified.
- **DPP-MIG-2** All 1038+ existing tests must pass after each layer move (Models, Services, Jobs, Filament, Providers). No batch moves.
- **DPP-MIG-3** Pint `--test` must pass after Stage 1 completion.
- **DPP-MIG-4** Playwright smoke suite must pass after Stage 1 completion.

### 17.2 ExchangeRoom → Chat Migration (Stage 5)

- **DPP-MIG-5** A data migration script copies all ExchangeRoom posts to Chat threads. The mapping is: one Chat thread per contract that had ExchangeRoom activity, messages transferred in chronological order.
- **DPP-MIG-6** Migration verification: `count(exchange_room_posts WHERE tenant_id = ?) = count(chat_messages WHERE thread_id IN (migrated threads))`. Migration may not proceed if counts do not match.
- **DPP-MIG-7** The migration is reversible — a rollback script restores the ExchangeRoom data before `exchange_room=false` is committed.
- **DPP-MIG-8** ExchangeRoom code removal (Stage 6) may only proceed after: the migration is verified, `exchange_room=false` flag has been stable in production for at least 2 weeks, and the CTO sign-off is given.

### 17.3 MySQL BLOB → SeaweedFS Migration (Stage 1)

- **DPP-MIG-9** All documents currently in `file_storage` (MySQL BLOBs) must be migrated to SeaweedFS before the disk is swapped.
- **DPP-MIG-10** Migration runs in parallel mode: new writes go to SeaweedFS, old reads fall back to MySQL until all records are migrated. No document is lost.
- **DPP-MIG-11** Migration completeness is verified by: total byte count in SeaweedFS ≥ total byte count in `file_storage`, and random sample of 100 documents returns identical content from both stores.

---

## 18. Feature Flag Registry

New feature flags introduced by DPP v2. All use the `Feature::enabled()` pattern via `config/features.php`.

| Flag | Default | Module | Stage |
|---|---|---|---|
| `notes` | `false` | Module 1 — Notes | Stage 2 |
| `meetings` | `false` | Module 2 — Meetings | Stage 2 |
| `discovery` | `false` | Module 3 — Discovery | Stage 3 |
| `tasks` | `false` | Module 4 — Tasks | Stage 3 |
| `email_integration` | `false` | Module 5 — Email | Stage 4 |
| `chat` | `false` | Module 6 — Chat | Stage 4 |
| `automation` | `false` | Module 7 — Automation | Stage 5 |
| `meilisearch` | `false` → `true` | Platform | Stage 2 (activate) |
| `event_bus` | `false` | Platform | Stage 1 |
| `seaweedfs` | `false` | Platform | Stage 1 |
| `tenant_self_serve` | `false` | Platform | Stage 6 |

> `exchange_room` is an existing flag. It transitions from `true` → `false` in Stage 5 (after migration) and the underlying code is removed in Stage 6.

---

> This document lives at `docs/dpp-v2-requirements.md` on the `platform/v2-dpp` branch.
> It supersedes the CCRS Requirements Specification v4 for all DPP v2 scope items.
> CCRS Agreements module requirements remain in `CCRS-Requirements-Specification-v4.md`.
> Last updated: 2026-03-19
