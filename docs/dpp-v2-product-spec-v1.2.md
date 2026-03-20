# Digittal Productivity Platform — Product Specification

**Version:** 1.2
**Date:** March 2026
**Status:** Specification — source of truth for DPP v2 requirements

---

## 1. Introduction

The Digittal Productivity Platform (DPP) is a unified, modular, Markdown-first productivity operating system designed to replace Digittal Group's fragmented toolchain. DPP is the parent application; the existing CCRS (Contract and Compliance Review System) becomes a first-class module within it.

DPP addresses three strategic objectives: eliminating per-seat SaaS costs across Notion, Fireflies, Relay.app, Unito, Morgen, and other tools; building on open formats (Markdown with YAML frontmatter) to avoid vendor lock-in; and creating a platform that can be launched as a SaaS product targeting African fintechs, PSPs, and SMEs underserved by USD/EUR-priced competitors.

### 1.1 Design Principles

**Platform-first:** DPP is the parent application. CCRS is a sub-module. Agreements originate from emails, meetings, and tasks — the productivity platform captures upstream triggers and routes to CCRS.

**Markdown-first:** All notes, meeting transcripts, and knowledge base entries are stored as .md files with YAML frontmatter. CRDT-based sync (Automerge/Yjs) for real-time collaboration. This ensures future-proof, portable data.

**Modular adoption:** Each module can be enabled independently. A tenant can run CCRS standalone, or Notes + Tasks without CCRS. Feature flags control module availability per tenant.

**Event-driven:** Modules communicate through a shared event bus (Redis Streams). A contract signed in CCRS emits an event; the Task module creates follow-up tasks; the Email module sends notifications.

**Self-hosted:** SeaweedFS over S3 (data sovereignty), pgvector over dedicated vector DB (no additional infrastructure), Redis Streams over Kafka (already deployed, sufficient scale).

**Cost reduction:** Estimated ~78% reduction vs current SaaS spend. Modular pricing: Starter $5, Professional $12, Enterprise $25 per user/month — undercutting Notion/competitors for African market.

### 1.2 Architecture Overview

DPP uses a polyglot services architecture around a Laravel monolith core:

- **dpp-core:** PHP 8.4 / Laravel 12 / Filament 3 / MySQL 8. Owns auth, CRUD, CCRS lifecycle, billing, admin UI, API gateway. The existing CCRS codebase, extended.
- **dpp-ai:** Python 3.12 / FastAPI / Celery. AI orchestration: transcription (Whisper), OCR (PaddleOCR), classification, embeddings (sentence-transformers), Claude/Bedrock API. Replaces the existing Python AI worker sidecar.
- **dpp-realtime:** Node.js 22 / Soketi. WebSocket layer for live meeting collaboration, real-time redlining, presence indicators.
- **dpp-workflows:** PHP 8.4 / Temporal PHP SDK / RoadRunner. Durable workflow orchestration for the Automation Engine, multi-step chains, saga compensation.
- **dpp-trawler:** Python 3.12 / FastAPI / APScheduler. Discovery agent: file trawling across Google Drive, OneDrive, SharePoint. Semantic linking via pgvector.
- **Storage:** SeaweedFS (S3-compatible object store, Apache 2.0, K8s-native). Replaces DatabaseAdapter MySQL BLOB storage.
- **Search:** PostgreSQL 16 + pgvector for vector embeddings and semantic search. Additive to MySQL (not replacing relational data).
- **Event Bus:** Redis 7 with Streams for internal module communication. Continues serving cache (Horizon), queues, and sessions.

### 1.3 AI Provider Strategy

Current: Direct Claude API calls. Future: AWS Bedrock (configuration switch only — AI_PROVIDER=claude|bedrock). The architecture abstracts the AI provider so switching requires no code rewrite. Bedrock adds: DPA via AWS contractual controls (R-SEC-024), Guardrails for prompt injection (R-SEC-025), content filtering (R-SEC-026), invocation logging via CloudTrail (R-SEC-028), and geographic region routing (R-SEC-030).

---

## 2. Module 1 — Intelligent Note Capture

Frictionless capture where AI classifies content automatically. Users never need to tell the system what type of content they're creating — AI infers from context, keywords, and patterns.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-NC-001 | AI classification: content is automatically categorised (idea, task, research, meeting note, etc.) without user intervention. Classification model improves from user corrections. | TODO | dpp-ai |
| R-NC-002 | Hashtag keyword system: #task, #research, #idea etc. trigger specific behaviours. Each hashtag maps to a .md instruction file defining what the system should do. | TODO | dpp-core |
| R-NC-003 | Auto-create hashtag definitions: typing a new hashtag (e.g., #client-onboarding) automatically creates a new .md instruction file that can be edited later. | TODO | dpp-core |
| R-NC-004 | Editable hashtag definitions: users can modify the .md instruction files for any hashtag to customise AI behaviour over time. | TODO | dpp-core |
| R-NC-005 | Multiple input methods: text, voice, image, handwriting (via OCR). All inputs are processed into structured .md notes. | TODO | dpp-ai |
| R-NC-006 | Quick-capture widget: minimal-friction capture from desktop/mobile without opening the full application. Supports text and voice. | TODO | Phase 1 |
| R-NC-007 | Voice note transcription and indexing: voice recordings are transcribed, indexed for search, and stored alongside the .md note. | TODO | dpp-ai (Whisper) |
| R-NC-008 | Markdown storage with YAML frontmatter: all notes stored as .md files. Frontmatter contains: type, tags[], created_at, modified_at, source, linked_files[], project, status. | TODO | dpp-core |
| R-NC-009 | File naming convention: consistent, human-readable naming (e.g., 2026-03-19_meeting-sprint-review.md). | TODO | dpp-core |
| R-NC-010 | Advanced voice mode via WhatsApp/Telegram: send voice messages to a bot that transcribes, classifies, and creates structured notes. | TODO | dpp-ai + WhatsApp API |
| R-NC-011 | Fire-and-forget voice workflow: user records voice note, system handles everything (transcribe, classify, extract tasks, route to modules). No further user action required. | TODO | Temporal workflow |
| R-NC-012 | Document/image intelligence: OCR on photos/documents, classify content type, route accordingly. Contracts detected → trigger CCRS workflow. | TODO | dpp-ai (PaddleOCR) |
| R-NC-013 | Long-term AI memory: AI maintains a .md profile per user built from all their notes, preferences, and patterns. Used to personalise classification, suggestions, and content generation. | TODO | dpp-ai + pgvector |

---

## 3. Module 2 — Meeting Intelligence

Hybrid human+AI note-taking for meetings. The user types what matters most (emphasis, decisions, private observations); AI fills context from the transcript. Two recording modes: normal (bot joins meeting) and incognito (system audio capture, no bot visible).

### 3.1 Recording Modes

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MI-001 | Normal mode: meeting bot joins the call (Zoom, Teams, Meet) with visible presence. Full transcript with speaker identification from meeting platform metadata. | TODO | dpp-ai |
| R-MI-002 | Incognito mode: system audio capture via local audio routing. No bot joins the meeting. Transcript generated from captured audio only. Speaker ID relies on voice fingerprinting. | TODO | dpp-ai |
| R-MI-003 | Mode selection per meeting: users choose normal or incognito before each meeting. Default can be set per calendar source or meeting type. | TODO | dpp-core |

### 3.2 Hybrid Note-Taking

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MI-004 | Hybrid scratchpad: user types emphasis/decisions/observations in a live editor while AI captures full transcript context. Post-meeting, AI merges human notes with transcript to produce comprehensive meeting notes (Granola-style). | TODO | dpp-realtime |
| R-MI-005 | @name task assignment: during meetings, typing @barry review the contract by Friday creates a task assigned to Barry with a due date. Parsed by AI from natural language. | TODO | dpp-ai + Task module |
| R-MI-006 | Hashtag system in meeting notes: same #tag system as Note Capture. #decision, #action, #risk etc. trigger specific post-meeting processing. | TODO | dpp-core |

### 3.3 Public/Private Sections

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MI-007 | Public and private note sections: meeting notes have a shared view (visible to all attendees) and a private section (personal observations, candid assessments). Clear visual separator (red divider line, lock icon, distinct background). | TODO | dpp-realtime |
| R-MI-008 | Safety mechanism: before sharing notes to the group, system displays a preview showing exactly what will be shared. Private content highlighted in red. Confirmation dialog required. | TODO | dpp-core |
| R-MI-009 | Private-by-default: private notes default to private. No accidental pathway where typing in the main area sends content to the group without explicit publish action. | TODO | dpp-core |

### 3.4 Post-Meeting Analysis

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MI-010 | AI post-meeting analysis: metadata suggestions (project classification, related prior meetings), sentiment analysis, key decisions, unresolved questions, suggested follow-up tasks. User approves/modifies before committing. | TODO | dpp-ai |
| R-MI-011 | Cross-meeting intelligence: query across all meeting history. Examples: What has Barry committed to in the last 30 days? What decisions were made about the Kenya expansion? | TODO | dpp-ai + pgvector |

### 3.5 Speaker Identification

Four-layer approach for speaker identification, especially in incognito mode:

- **Layer 1 — Calendar Matching:** Pre-populate expected speakers from calendar invite. AI matches voice patterns to expected attendees.
- **Layer 2 — Voice Fingerprinting:** Build voice profile library (with consent) for team members and frequent contacts. Improves over time.
- **Layer 3 — Post-Meeting Correction:** Users manually correct speaker labels. Corrections feed back into voice profile library.
- **Layer 4 — Contextual Inference:** AI uses conversational context (e.g., 'As the project manager, I think...') to infer identity when voice matching is uncertain.

### 3.6 Storage and Sync

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MI-012 | Meeting notes stored as .md files with frontmatter: meeting_id, date, attendees[], platform (Zoom/Teams/Meet), mode (normal/incognito), recording_available, duration. | TODO | dpp-core |
| R-MI-013 | CRDT-based versioned sync: local changes merge with remote using Automerge/Yjs conflict resolution. Version history maintained for all meeting notes. | TODO | dpp-realtime |
| R-MI-014 | MCP tool for sync operations: programmatic access to meeting note sync for external tool integration. | TODO | dpp-core |

---

## 4. Module 3 — Discovery & Knowledge Graph

An AI-powered trawler that builds organisational context by indexing local and shared file stores, creating semantic links between content, and maintaining a visualisable knowledge graph.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-DK-001 | File trawler: indexes Google Drive, OneDrive, SharePoint, and local filesystem. Extracts metadata, content summaries, and relationships. Runs on configurable schedule. | TODO | dpp-trawler |
| R-DK-002 | Context profile (context_profile.md): AI builds and maintains a comprehensive profile of each user and the company based on trawled content. Updated by AI agent on each trawl cycle. | TODO | dpp-trawler + dpp-ai |
| R-DK-003 | Privacy boundaries: users control exactly what folders/files are trawled. Explicit opt-in per source. Personal files never trawled without consent. | TODO | dpp-core |
| R-DK-004 | Semantic link suggestions: AI identifies relationships between files based on content, metadata, entities, and temporal proximity. Links appear in a review queue for user approval. | TODO | dpp-ai + pgvector |
| R-DK-005 | Metadata enrichment: on approving a suggested link, users can add/modify metadata (tags, descriptions, relationships) that feed back into the knowledge graph. | TODO | dpp-core |
| R-DK-006 | Knowledge graph visualisation: interactive visual map of entities (people, projects, contracts, topics) and their relationships. Filterable by type, date range, and module. | TODO | dpp-core (Filament) |
| R-DK-007 | Private vs company knowledge base toggle: users decide what knowledge is personal-only vs contributed to the shared company knowledge base. | TODO | dpp-core |
| R-DK-008 | Shared knowledge base with RBAC: company knowledge base respects role-based access control. Departments see relevant knowledge; sensitive information is restricted. | TODO | dpp-core |

---

## 5. Module 4 — Task Management Engine

Full-featured task management with hierarchical tasks, multiple views, AI-powered prioritisation, and bidirectional sync with external tools. Hybrid storage: MySQL primary (relational queries) + .md secondary (portability/offline).

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-TM-001 | Task properties: linked to people, projects, due dates, priorities, dependencies, recurring schedules, blocking/blocked-by relationships. Supports subtasks and task groups. | TODO | dpp-core |
| R-TM-002 | Multiple views: list, Kanban board, calendar, timeline/Gantt, and table. Users choose their preferred view per project. Views are customisable (columns, filters, sort). | TODO | dpp-core (Filament) |
| R-TM-003 | AI task scoring: urgency, importance, effort estimation, and context-aware scheduling. AI suggests optimal task ordering based on deadlines, dependencies, and user patterns. | TODO | dpp-ai |

### 5.1 External Tool Sync

Bidirectional sync with external task managers. Changes in either system propagate to the other. Sync frequency configurable per user.

| Tool | API | Notes |
|---|---|---|
| Todoist | REST API | Full bidirectional sync including projects, labels, priorities, and due dates |
| Microsoft To Do | Microsoft Graph API | Sync tasks, lists, and due dates |
| Apple Reminders | EventKit framework | Native app only (requires iOS/macOS). Limited to basic task properties |
| Google Tasks | Google Tasks API | Basic sync: title, due date, status, notes |
| TickTick | TickTick Open API | Bidirectional sync including habits and Pomodoro data |
| Asana | Asana REST API | Projects, sections, tasks, subtasks, custom fields |

---

## 6. Module 5 — Email Integration Hub

Deep integration with Office 365 and Gmail. AI triages incoming email, auto-creates tasks for messages requiring response, and detects contracts for CCRS routing.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-EI-001 | Office 365 integration via Microsoft Graph API. Read/send email, manage folders, handle calendar events. | TODO | dpp-core |
| R-EI-002 | Gmail integration via Gmail API. Read/send email, manage labels, handle calendar events. | TODO | dpp-core |
| R-EI-003 | Auto-task creation: emails requiring a response automatically generate tasks with due dates based on urgency classification. | TODO | dpp-ai |
| R-EI-004 | Snooze-to-task: snoozing an email creates a task linked to the email. Deferring the task updates the snooze in the mail client. Bidirectional sync. | TODO | dpp-core |
| R-EI-005 | AI email triage: classify incoming emails (action required, FYI, newsletter, spam). Priority scoring based on sender, content, and user history. | TODO | dpp-ai |
| R-EI-006 | Email threading: conversations are grouped and summarised. AI highlights key decisions and action items across email threads. | TODO | dpp-ai |
| R-EI-007 | Quick actions: reply, forward, snooze, archive, and create-task directly from the DPP interface without switching to the email client. | TODO | dpp-core |
| R-EI-008 | Email templates: AI-assisted response drafts based on email context and user's writing style. | TODO | dpp-ai |
| R-EI-009 | Contract detection: AI identifies emails containing contracts, agreements, or legal documents. Automatically routes to CCRS module for analysis. | TODO | dpp-ai → CCRS |

---

## 7. Module 6 — Chat & Communication Bridge

Bridge between chat platforms and the DPP productivity system. Messages in WhatsApp, Slack, or Teams can create notes, tasks, and trigger workflows.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-CB-001 | WhatsApp Business API integration (via Twilio). Voice messages transcribed and processed. Text messages parsed for tasks and notes. Primary input channel for field teams. | TODO | dpp-ai + Twilio |
| R-CB-002 | Slack integration: bot that creates tasks, notes, and triggers CCRS workflows from Slack commands and messages. Migrated from existing Notion Slack triager (R-MG-007). | TODO | dpp-core |
| R-CB-003 | Microsoft Teams integration: Bot Framework + Graph API. Create tasks and notes from Teams messages. Meeting integration for transcript capture. | TODO | dpp-core |

Additional chat capabilities: @mention system for routing messages to specific groups/people within DPP. Cross-platform message threading (a conversation started in WhatsApp can be continued in Slack with context preserved).

---

## 8. Module 7 — Agreement Automation (CCRS)

The existing CCRS becomes a first-class module within DPP. The full contract lifecycle management system is already built and in testing. Integration points connect CCRS to the broader productivity platform.

CCRS Status: 97 features documented in CCRS_Requirements_v5.docx (49 DONE, 8 PARTIAL, 22 TODO, 18 PLANNED). 826 tests passing. 81 migrations. 59 models. Single-DB row-level multi-tenancy via stancl/tenancy v3.

### 8.1 Integration Points

- **Email → CCRS:** R-EI-009 — contracts detected in email trigger CCRS analysis workflows automatically.
- **Meeting → CCRS:** @mentions in meeting notes create CCRS review workflows (e.g., @legal review the NDA discussed today).
- **Task → CCRS:** CCRS review tasks appear in the unified task view alongside all other tasks.
- **Knowledge Graph → CCRS:** Contracts and counterparties feed into the knowledge graph as entities with relationships.
- **Notifications:** CCRS events (contract signed, SLA breach, review deadline) use the unified DPP notification system.

---

## 9. Additional Capabilities

Beyond the seven core modules, DPP includes the following cross-cutting capabilities. These are implemented progressively across build phases.

### 9.1 Automation Engine

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-AU-001 | Webhook triggers: external systems fire webhooks that start DPP automations. Supports Stripe, GitHub, Jira, and custom webhooks. | TODO | Temporal |
| R-AU-002 | Recurring scheduled automations: user-defined schedules (daily, weekly, monthly, cron). Example: every Monday at 9am, generate weekly task summary and email to team. | TODO | Temporal |
| R-AU-003 | Event-driven automations: internal platform events trigger automations via Redis Streams event bus. Example: task marked overdue → escalate to manager via Slack. | TODO | Temporal |
| R-AU-004 | Natural language automation builder: users describe automations in plain English. AI parses into trigger/condition/action chains. User confirms before activation. | TODO | dpp-ai + Temporal |
| R-AU-005 | Automation history and debugging: log of all runs showing trigger, actions, success/failure. Replay capability. Deduplication to prevent webhook storms. | TODO | Temporal |
| R-AU-006 | Multi-step chains: output of one automation feeds into the next. Example: email received → AI classifies as contract → route to CCRS → create review task → notify via Slack → schedule follow-up. | TODO | Temporal |

### 9.2 Content Generation Pipeline (15.14)

Voice notes or rough text expanded into polished blog posts, newsletters, social media content, and internal communications. AI generates drafts in the user's writing style (learned from prior content). Integration with WordPress, Ghost, Substack, LinkedIn, and Mailchimp via API.

### 9.3 CRM-Lite Contact Intelligence (15.15)

Lightweight contact intelligence that automatically builds relationship profiles from meeting notes, emails, tasks, and agreements. Tracks: last interaction, interaction frequency, sentiment trends, open commitments, and shared projects. Not a replacement for Salesforce/HubSpot — a productivity-focused contact layer.

### 9.4 Expense/Receipt Tracking (15.16)

Photo → OCR → structured expense record. Multi-currency support (ZAR, USD, KES, MZN, ZWL). Temporal-based approval workflows. Export to CSV/accounting software.

### 9.5 Personal Vault (15.17)

Encrypted personal workspace separate from company data. User-only access with R-SEC-012 encryption. Cannot be accessed by company admins. For personal notes, financial records, and sensitive information.

### 9.6 Other Capabilities

- **Time Tracking (15.1):** Track time against tasks and projects. Reporting by person, project, and client.
- **Calendar Intelligence (15.2):** Smart calendar integration. Surface relevant notes before meetings. Contract key dates on calendar. Meeting prep suggestions.
- **Template Library (15.3):** Reusable templates for notes, meeting agendas, project plans, and reports.
- **Proactive AI Surfacing (15.4):** Before a meeting, AI surfaces relevant prior meetings, notes, tasks, and contracts for all attendees.
- **Collaborative Whiteboards (15.5):** Real-time collaborative drawing/diagramming. Integrates with notes and meeting intelligence.
- **Weekly Review System (15.6):** Structured weekly review workflow: accomplishments, open items, upcoming priorities. AI pre-populates from activity data.
- **Document Generation (15.7):** Generate formatted documents from notes and templates. Export to .docx, .pdf.
- **Analytics Dashboard (15.8):** Platform usage analytics, productivity metrics, team activity insights.
- **Guest/External Collaborator Access (15.9):** Invite external parties to specific notes, meetings, or projects without full platform access.
- **Clipboard/Screenshot Intelligence (15.10):** Capture clipboard content and screenshots. AI indexes and classifies for searchability.
- **Habit/Goal Tracking (15.11):** Personal and team goal tracking with progress visualisation.
- **Multi-Language (15.12):** English, Portuguese (Mozambique), Swahili (Kenya) for transcription and UI.

---

## 10. SaaS Architecture

Every design decision is multi-tenant from day one. stancl/tenancy v3 with single-database row-level isolation.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-SA-001 | Single-DB row-level multi-tenancy via stancl/tenancy v3. Tenant context applied via middleware. All queries scoped automatically. | TODO | stancl/tenancy v3 |
| R-SA-002 | Automated tenant provisioning: new tenants created via admin panel or self-service registration. Database seeding, default configuration, and welcome flow. | TODO | dpp-core |
| R-SA-003 | Tenant-level configuration: enabled modules, AI model preference, storage quota, user caps, custom branding, timezone, locale. | TODO | dpp-core |
| R-SA-004 | Modular pricing tiers: Starter ($5/user/month — Notes + Tasks), Professional ($12 — adds Email, Meetings, Chat), Enterprise ($25 — full platform + CCRS + Automation + API). | TODO | Stripe |
| R-SA-005 | AI usage billing: per-tenant token consumption tracking. Dashboard showing usage vs included allowance. Overage billing. | TODO | dpp-core + Stripe |
| R-SA-006 | White-label reselling via Stripe Connect: partners can resell DPP under their own brand with custom pricing. | PLANNED | Stripe Connect |
| R-SA-007 | Tenant-scoped APIs: all API endpoints respect tenant context. API keys are per-tenant. | TODO | dpp-core |
| R-SA-008 | Per-tenant feature flags: enable/disable specific features at tenant level without code deployment. | TODO | dpp-core |
| R-SA-009 | Tenant-facing webhook system: tenants configure outbound webhooks for platform events (contract signed, task completed, etc.). | PLANNED | dpp-core |
| R-SA-010 | Public API: OAuth2 authentication, rate limiting, OpenAPI documentation. Available on Enterprise tier. | PLANNED | dpp-core |
| R-SA-011 | Platform admin panel: Filament-based admin for tenant management, usage monitoring, billing overview, system health. | TODO | Filament |
| R-SA-012 | GDPR alignment: data export, right to erasure, consent management, data processing agreements. | TODO | dpp-core |
| R-SA-013 | POPIA compliance: South African data protection requirements. Data residency controls. Information officer designation per tenant. | TODO | dpp-core |
| R-SA-014 | SOC 2 readiness: audit logging, access controls, encryption, incident response procedures. | PLANNED | Phase 5 |

---

## 11. Visualisation & Dashboard

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-VZ-001 | Module status cards: interactive cards per module showing key metrics, health, last activity. | TODO | dpp-core |
| R-VZ-002 | Status card customisation: users configure which metrics appear per module card. | TODO | dpp-core |
| R-VZ-003 | Dashboard layout: drag-and-drop arrangement of module cards and widgets. | TODO | dpp-core |
| R-VZ-004 | Kanban board: drag-and-drop task boards with customisable columns per project. | TODO | dpp-core |
| R-VZ-005 | Board variants: sprint board, personal board, team board with different default configurations. | TODO | dpp-core |
| R-VZ-006 | Calendar view: tasks and meetings on a unified calendar with day/week/month views. | TODO | dpp-core |
| R-VZ-007 | Timeline/Gantt view: project timeline with dependencies, milestones, and resource allocation. | TODO | dpp-core |
| R-VZ-008 | Table view: spreadsheet-style task view with inline editing, sorting, filtering, and grouping. | TODO | dpp-core |
| R-VZ-009 | Unified activity feed: cross-module activity stream showing recent actions across all modules. | TODO | Redis Streams |
| R-VZ-010 | Knowledge graph visualisation: interactive entity-relationship map. Filterable and zoomable. | TODO | dpp-core + D3.js |
| R-VZ-011 | Module health dashboard (admin): system-level health indicators per module for platform administrators. | TODO | Filament |
| R-VZ-012 | Meeting analytics: frequency, duration, action item completion rate, sentiment trends. | TODO | dpp-core |
| R-VZ-013 | Meeting participant analytics: who attends most, who generates most action items, response rates. | TODO | dpp-core |
| R-VZ-014 | Contract pipeline Kanban: CCRS-specific pipeline view showing contracts by stage (draft, review, negotiation, signed). | TODO | dpp-core |
| R-VZ-015 | Obligation tracker: CCRS contract obligations with due dates, compliance status, and alerts. | TODO | dpp-core |

---

## 12. Offline & Mobile

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MO-001 | Native iOS and Android applications (React Native or Flutter). Feature parity with web for core modules (Notes, Tasks, Meetings). | PLANNED | Phase 5 |
| R-MO-002 | Full offline capability: create notes, tasks, record voice memos. Queue for sync when online. | PLANNED | Phase 5 |
| R-MO-003 | Encrypted local storage: SQLite + .md files on device. AES-256 encryption at rest. | PLANNED | Phase 5 |
| R-MO-004 | CRDT sync: Automerge/Yjs for conflict-free merging of offline changes with server state. | PLANNED | Phase 5 |
| R-MO-005 | Differential sync: only changed content syncs, not full documents. Bandwidth-efficient for African mobile networks. | PLANNED | Phase 5 |
| R-MO-006 | Version history: full change history for all notes and tasks. Rollback to any previous version. | PLANNED | Phase 5 |

### 12.1 On-Device AI

Quantised models for basic offline tasks: text classification, quick transcription, and tag suggestions. Candidates: Gemma 3n, Qwen 2.5 1.5B via ExecuTorch (Android), MLX (iOS), or llama.cpp (cross-platform). Full AI capabilities require connectivity to dpp-ai service.

---

## 13. Migration Paths

Seamless migration from existing tools. Parallel running period with bidirectional sync before hard cutover.

### 13.1 Notion Migration

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MG-001 | Automated Notion import via Notion MCP or API. Reads all databases (Projects, Tasks, Meeting Notes), pages, and nested content. | TODO | dpp-core |
| R-MG-002 | Notion pages → .md files: preserves headings, formatting, inline databases (YAML frontmatter + Markdown tables), linked pages ([[wikilinks]]), embedded media. | TODO | dpp-core |
| R-MG-003 | Notion databases → .md files + DB records: one .md per row with frontmatter matching schema. Property type mapping: Select → tags[], Date → due_date, Person → assignee, Relation → linked_files[], Status → status enum. | TODO | dpp-core |
| R-MG-004 | Unmappable features flagged: Notion formulas, Synced Blocks, Notion AI configs flagged in migration report with manual resolution suggestions. | TODO | dpp-core |
| R-MG-005 | Bidirectional sync bridge: during migration, changes in either Notion or DPP propagate to the other. Gradual workflow shift without hard cutover. | TODO | dpp-core |
| R-MG-006 | Migration dashboard: percentage migrated, items with mapping issues, feature gaps, per-user adoption metrics (who still uses Notion vs DPP). | TODO | dpp-core |

### 13.2 Other Migrations

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MG-007 | Slack bot migration: existing Notion Slack triager rebuilt as Slack-to-DPP bot. Same user-facing commands. Claude API replaces Notion AI for parsing. | TODO | dpp-core |
| R-MG-008 | Fireflies/Otter transcript import: via API export or CSV/JSON. Each transcript → .md meeting note with frontmatter (date, attendees, source_tool). | TODO | dpp-core |
| R-MG-009 | Retroactive AI analysis on imported transcripts: action items, decisions, sentiment extracted from historical meetings. | TODO | dpp-ai |
| R-MG-010 | Todoist/Asana import: tasks, projects, labels, due dates, priorities imported via respective APIs. | TODO | dpp-core |
| R-MG-011 | Trello import: boards, lists, cards mapped to DPP projects, task groups, and tasks. | TODO | dpp-core |
| R-MG-012 | Obsidian vault import: native .md compatibility. Direct import of vault structure with wikilinks, tags, and frontmatter preserved. | TODO | dpp-core |

### 13.3 Onboarding

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-MG-013 | Onboarding wizard: asks users what tools they currently use. Generates personalised migration plan. | TODO | dpp-core |
| R-MG-014 | Independent module adoption: users can migrate one module at a time (e.g., start with Tasks, add Notes later). | TODO | dpp-core |
| R-MG-015 | Tooltip walkthroughs: contextual tooltips guide users through new features during their first interactions. | TODO | dpp-core |

---

## 14. Development Branch Strategy

CCRS preserved on main/develop for production stability. All DPP work on platform/v2-dpp branch with module sub-branches.

| Req ID | Description | Status | Tech/Notes |
|---|---|---|---|
| R-BR-001 | Module code in isolated directories: app/Modules/Agreements/ (CCRS), app/Modules/Notes/, app/Modules/Tasks/, app/Modules/Meetings/, app/Modules/Email/, app/Modules/Chat/, app/Modules/Discovery/. | TODO | Convention |
| R-BR-002 | Shared infrastructure in app/Platform/: AuthService, EventBus, NotificationService, VaultEngine, SearchService, AIService, TenancyService. | TODO | Convention |
| R-BR-003 | Database migrations namespaced by module: platform migrations in database/migrations/platform/, module migrations in database/migrations/modules/{module}/. | TODO | Convention |
| R-BR-004 | Module registration via Laravel Service Provider. Platform boots only enabled modules. CCRS can run standalone; modules can run without CCRS. | TODO | Convention |

### 14.1 Three-Phase Merge Strategy

- **Phase A — Parallel Development (Months 1–6):** CCRS continues on develop for bug-fixing and testing. DPP modules built on platform/* branches. CI runs CCRS tests against platform/main continuously.
- **Phase B — Integration (Months 6–8):** v2/integration branch merges CCRS and platform for integration testing. Performance testing. Blue-green deployment prep on Kubernetes.
- **Phase C — V2 Release (Months 8–10):** main-v2 becomes the production branch. Blue-green deployment. Rollback plan if critical issues discovered.

---

## 15. Security Requirements

50 security requirements (R-SEC-001 to R-SEC-050) are defined in the DPP Security Amendment v1.1. These are integrated into each development stage. Key categories:

- **Tenant Isolation (R-SEC-001–003):** Row-level isolation, query scoping, cross-tenant access prevention.
- **Encryption (R-SEC-004–016):** TLS 1.3, AES-256 at rest, field-level encryption for PII, envelope encryption (DEK/KEK), mTLS for services, backup encryption.
- **Authentication (R-SEC-017–023):** MFA, session management, OAuth scoping, API key rotation, integration token management.
- **AI Security (R-SEC-024–030):** DPA, prompt injection prevention, content filtering, output validation, model security, data residency.
- **Privacy (R-SEC-031–033):** Secure deletion with certificates, retention policies, consent management.
- **Audit (R-SEC-034–036):** Comprehensive audit logging, append-only integrity, retention and export.
- **SDLC (R-SEC-037–044):** SAST/DAST, dependency scanning, security alerting, code review, incident response, disaster recovery, penetration testing.
- **Infrastructure (R-SEC-045–050):** K8s security, secrets management, network segmentation, automation sandboxing, SSRF prevention.

Full security requirements are detailed in DPP_Security_Amendment_v1_1.md. The Bedrock/AWS Coverage Matrix (DPP_RSEC_Bedrock_AWS_Coverage_Matrix_v1_0.md) maps each R-SEC to Bedrock-supported, AWS-supported, or Application-owned classification.

---

## 16. Implementation Roadmap

Six phases over 12 months. Phases 3 and 4 overlap intentionally — Phase 4 is the integration/testing phase.

**Phase 1: Foundation (Months 1–3)**
CCRS stabilisation + P0 remediation. Platform shared services (auth, event bus, tenancy). Note Capture + Task Management basics. Web shell and module dashboard. Notion import. CTO deploys: SeaweedFS, PostgreSQL+pgvector, Redis Streams upgrade.

**Phase 2: Communication (Months 3–5)**
Email integration (Office 365 + Gmail). Slack + WhatsApp bots. Voice-first capture (R-NC-010–012). AI long-term memory (R-NC-013). CCRS module wrapping. Fireflies/Todoist import. CTO deploys: dpp-ai service (Python/FastAPI).

**Phase 3: Intelligence (Months 5–8)**
Meeting Intelligence (R-MI-001–014). Discovery & Knowledge Graph (R-DK-001–008). Automation Engine (R-AU-001–006). Advanced task views. CTO deploys: dpp-realtime (Node.js/Soketi), Temporal.io, dpp-workflows.

**Phase 4: Integration (Months 6–8, overlaps Phase 3)**
v2/integration branch merge. Integration testing across all modules. Performance testing. Blue-green deployment preparation. First penetration test (R-SEC-040).

**Phase 5: Mobile & Completion (Months 8–12)**
Native mobile apps (React Native/Flutter). Offline AI. CRDT sync. External task sync. Analytics dashboard. Weekly review system. Content generation pipeline. CRM-Lite. Expenses. Personal vault. Second penetration test. SOC 2 Type I readiness. CTO deploys: dpp-trawler.

**Phase 6: SaaS Launch**
Public launch with modular pricing. Onboarding wizard. Migration tools for external customers. White-label reselling (Stripe Connect). Public API with OAuth2. Marketing website and documentation.

---

## 17. Document History

- **v1.0 (March 2026):** Initial specification. 7 core modules defined. Competitive analysis (Notion, Fireflies, Notis.ai, Granola, Todoist, Morgen, Relay.app). Requirements for Note Capture, Meeting Intelligence, Discovery, Tasks, Email, Chat, CCRS integration.
- **v1.1 (March 2026):** Added Section 16 (Migration Paths), Section 17 (Module State Visualisation), Section 18 (SaaS-First Architecture), Section 19 (Development Branch Strategy). Expanded roadmap to 6 phases.
- **v1.2 (March 2026):** Technical architecture refactored: polyglot services (Python/FastAPI, Node.js/Soketi, Temporal.io, SeaweedFS, pgvector, Redis Streams). Tech stack mapped to each module. All requirement IDs formalised (R-NC, R-MI, R-DK, R-TM, R-EI, R-CB, R-AU, R-SA, R-VZ, R-MO, R-MG, R-BR). Security Amendment v1.1 (50 R-SEC requirements) and Bedrock Coverage Matrix integrated. Corrected to PHP 8.4 / Laravel 12 from codebase audit.
