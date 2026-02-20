# CCRS — Backlog and Build Plan

**Contract & Merchant Agreement Repository System**  
**Version:** 1.1 (Board Edition — Render/Vercel)  
**Date:** 2026-02-17  
**Source:** CCRS Requirements v3 Board Edition 4

This document provides a **backlog** and **build plan** for CCRS, adapted for **backend on Render**, **frontend on Vercel**, **Supabase** for application data, and recommended **object storage** options. It incorporates the **hybrid AI architecture** from Board Edition 4: **Claude Opus 4.6 Agent SDK** for complex agentic tasks and **Anthropic Messages API** for high-throughput calls. Suitable for Jira/ADO import and sprint planning.

---

## 1. Hosting and Data Architecture

### 1.1 Hosting Summary

| Layer        | Platform | Purpose |
|-------------|----------|---------|
| **Frontend** | **Vercel** | Next.js (React) app; SSO, dashboards, workflow builder, repo UI |
| **Backend**  | **Render** | API (Node/NestJS or .NET), background workers, webhooks, TiTo API |
| **Database** | **Supabase** | PostgreSQL for metadata, workflow state, RBAC, audit, counterparty |
| **Object storage** | See §1.3 | Contract documents (PDF/DOCX), executed copies, immutable blobs |
| **Secrets** | Render + Vercel env / Supabase Vault | API keys, Boldsign, Entra ID, DB URL — no secrets in code |

### 1.2 Why This Split

- **Vercel:** Optimized for Next.js, edge caching, preview deployments, and fast global frontend.
- **Render:** Good fit for long-running API and background jobs; native PostgreSQL (or use Supabase), cron jobs, and private services; simpler than managing Azure VMs/Container Apps for this scope.
- **Supabase:** Managed PostgreSQL + Auth (can proxy Entra ID), optional Realtime; single vendor for app DB and (if chosen) first-tier object storage.

### 1.3 Database and Object Storage Recommendations

#### Application database: **Supabase (PostgreSQL)** — recommended

- **Use for:** Contract metadata, classification, workflow state, signing authority matrix, counterparty master, audit logs, key dates, obligations register, user/role mappings, workflow templates.
- **Rationale:** Managed Postgres, connection pooling, backups, point-in-time restore; fits NFRs (RPO/RTO, scalability). Integrates with Render (env-based connection string) and Vercel (serverless/API routes if needed).
- **Compliance:** Encryption at rest and in transit; use Supabase Vault or Render/Vercel env for secrets.

#### Object storage: **two recommended patterns**

You may need **two** logical stores: (1) general contract/document blobs, (2) immutable executed copies. Both can be implemented with one storage product and bucket policies, or split if you prefer.

**Option A — Supabase Storage (recommended default)**  
- **Use for:** All contract files (drafts, executed PDFs/DOCX), versioned and immutable executed copies.  
- **Pros:** One vendor with Supabase DB; S3-compatible API; built-in CDN; Row Level Security (RLS) for access control; simple for backend on Render to use (Supabase client or S3 client).  
- **Cons:** Storage and egress limits on lower plans; for 50k+ contracts and 200+ users, review Pro/Team limits and egress.  
- **Fit:** Best when you want to minimize vendors and already use Supabase for DB.

**Option B — Cloudflare R2**  
- **Use for:** Same as above; optionally use for high-volume or large-file scenarios (e.g. bulk historical ingestion).  
- **Pros:** S3-compatible; no egress fees; strong for large scale and cost predictability; works from Render (backend) via AWS SDK.  
- **Cons:** Separate vendor; no built-in RLS (access control must be in your API).  
- **Fit:** Best when you expect large document volume, want to decouple storage cost from egress, or prefer not to put all blobs in Supabase.

**Option C — AWS S3**  
- **Use for:** Same as above; standard choice if you already use AWS.  
- **Pros:** Mature, feature-rich; strict compliance and retention controls; Render and Vercel integrate well.  
- **Cons:** Egress costs; extra AWS account/ops if you are not already on AWS.  
- **Fit:** Best when you have existing AWS governance or compliance requirements tied to S3.

**Recommendation:**  
- Start with **Supabase for PostgreSQL + Supabase Storage** for documents and executed copies.  
- Add **Cloudflare R2** (or S3) later if you hit scale/cost limits or want a dedicated object store with no egress fees.

### 1.4 High-Level Architecture Sketch

```
[Users] → [Vercel: Next.js Frontend]
                ↓ (HTTPS, Entra ID / Supabase Auth)
[Render: API + Workers]
    ↓                    ↓
[Supabase: PostgreSQL]  [Supabase Storage or R2/S3]
    ↑                    ↑
    └── metadata, workflow, audit  └── PDF/DOCX, executed copies

External: Entra ID, Boldsign (webhooks → Render), SharePoint, TiTo (API → Render), Teams/Email
```

---

## 2. Hybrid AI Architecture (Board Edition 4)

CCRS uses a **hybrid AI architecture** as specified in Board Edition 4:

| Use case | Approach | Technology |
|----------|----------|------------|
| **Complex, multi-step tasks** | Autonomous AI agents that read documents, query internal data, compare against templates, and iterate until complete | **Claude Opus 4.6 Agent SDK** (or equivalent Claude Agent SDK). Agents have access to org structure, authority matrix, and WikiContracts via **MCP (Model Context Protocol) tool integrations**. |
| **Simple, high-throughput tasks** | Direct LLM API calls for speed and cost efficiency | **Anthropic Messages API** (summaries, language detection, classification, quick extractions). |

**Agent use cases:** Full contract review, risk scoring, template deviation detection, obligation extraction, bulk ingestion of complex contracts (multi-step extraction and self-correction), and LLM-assisted workflow generation (agent queries org/authority/templates via MCP and validates workflow JSON).

**Cost and safety:** Per-agent budget limits, token/cost logging per contract, and fallback to direct API if agent processing exceeds cost or time thresholds (see Epic 3).

*Note: Board Edition 4 also refactors the backend to Python (FastAPI); this build plan retains the current NestJS/Render setup. A future migration to FastAPI can be scheduled separately if the board adopts the full stack change.*

---

## 3. Adapted Technology Stack

| Area | Choice | Notes |
|------|--------|--------|
| **Frontend** | Next.js 14+ (App Router), React, TypeScript | Vercel-native; Shadcn UI + Tailwind |
| **Backend** | Node.js (NestJS) or .NET 8 (ASP.NET Core) | REST API on Render; webhooks for Boldsign. (Spec: Python/FastAPI optional future migration.) |
| **Auth** | Microsoft Entra ID (OIDC) | Roles via groups/claims; optional Supabase Auth as proxy |
| **Database** | Supabase (PostgreSQL) | Metadata, workflow, authority, audit |
| **Object storage** | Supabase Storage (default) or Cloudflare R2 / S3 | Documents and executed copies |
| **Search** | Supabase full-text (pg) or Meilisearch/Typesense on Render | &lt; 2 s search NFR |
| **Cache** | Render Redis or Upstash (serverless) | Counterparty lookups, TiTo validation |
| **AI (complex / agentic)** | **Claude Opus 4.6 Agent SDK** | Full contract review, risk scoring, template deviation, obligation extraction, workflow draft generation. Agents use **MCP tools** to query org structure, authority matrix, WikiContracts. |
| **AI (high-throughput)** | **Anthropic Messages API** | Quick summaries, language detection, classification, low-latency extraction. Fallback when agent budget/time exceeded. |
| **Document generation** | docx-templates / Carbone | Merchant Agreement and amendments |
| **Workflow/async** | Render background workers + queue (e.g. BullMQ + Redis) or Render cron | Agent jobs and async pipelines |
| **Observability** | Render metrics + structured logs; Sentry; optional Axiom/Datadog | APM, health checks, alerting; **agent token/cost logging** |

---

## 4. Non-Functional Requirements (Summary)

| Category | Requirement |
|----------|-------------|
| **Availability** | 99.9% uptime (excluding planned maintenance) |
| **Security** | TLS 1.2+; encryption at rest; least-privilege; audit trail for key actions |
| **Performance** | Search &lt; 2 s; TiTo validation API p95 &lt; 500 ms; async AI jobs |
| **Compliance** | Data retention/export; GDPR-aligned handling of personal data |
| **Scalability** | 50,000+ contracts; background workers; indexing |
| **Disaster recovery** | RPO near-zero for executed docs; RTO &lt; 4 h; point-in-time restore (DB + storage) |
| **Capacity** | 200+ concurrent users; TiTo API capacity plan for POS peaks |
| **Accessibility** | WCAG 2.1 AA |
| **Mobile** | Responsive; approval actions on mobile |
| **Observability** | Structured logging, APM, health checks, alerting |

---

## 5. Backlog (Epics and User Stories)

The backlog is organized by epic with user stories and acceptance criteria. Refine during discovery and import into Jira/ADO as needed.

---

### Epic 1 — Foundation and Infrastructure

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 1.1 | As an Admin, I can sign in using Microsoft Entra ID so that access is controlled by corporate SSO. | Users authenticate via Entra ID (OIDC). RBAC roles can be assigned via groups/claims. Unauthorized users are denied access and actions are audited. |
| 1.2 | As a System Admin, I can configure environments and secrets securely. | Secrets stored in Render/Vercel env or Supabase Vault (or equivalent). No secrets in code or client apps. Audit trail for config changes. |

**Implementation notes:** Entra ID OIDC with NextAuth.js or similar on Vercel; backend on Render validates JWT. Use Render env vars and Vercel env for secrets.

---

### Epic 2 — Core Repository

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 2.1 | As a user, I can upload a contract and store it in the correct region/entity/project and counterparty hierarchy. | Upload supports PDF/DOCX. Classification fields are mandatory. Contract is stored with an immutable ID and audit entry. |
| 2.2 | As a user, I can search and filter contracts by metadata and full-text. | Search supports keyword and filters (region/entity/project/type/status). Typical search returns in under 2 seconds. Results show key metadata and status. |

**Implementation notes:** Store files in Supabase Storage (or R2/S3); metadata and versioning in Supabase PostgreSQL. Full-text via Postgres or dedicated search service on Render. **Bulk upload (Board Edition 4):** Complex contracts are processed by autonomous AI agents (Claude Agent SDK) capable of multi-step extraction, classification, and self-correction; pipeline remains async with retries and review queue.

---

### Epic 3 — AI Contract Intelligence

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 3.1 | As a user, I can view an AI-generated summary and extracted key fields for a contract. | Summary includes purpose, parties, duration. Extracted fields include dates/renewal/breach/jurisdiction. Each extracted field includes evidence and confidence. **Complex contracts:** Processed by an autonomous AI agent (Claude Agent SDK) that reads the document, extracts fields, compares against templates, and iterates until extraction is complete. **Simple summary requests:** Use direct Anthropic Messages API calls for low-latency responses. |
| 3.2 | As a Legal user, I can verify and correct extracted key dates and terms. | Critical fields can be marked "verified". Corrections are audited. Downstream reminders use the verified values. |
| 3.3 | As a Legal user, I can view risk scores and template deviations for a contract. | Each contract displays an overall risk classification (high/medium/low). Deviations from standard WikiContracts templates are flagged with clause reference and deviation summary. Risk scores recalculated when AI extraction is re-run or verified data changes. **The AI agent accesses the WikiContracts template library via MCP tools to perform clause-level comparison.** |
| 3.4 | As a user, I can view extracted obligations in a structured obligations register. | Ongoing obligations (reporting, SLA, insurance, deliverables) are extracted and listed separately from key dates. Each obligation includes evidence, confidence, and a responsible party field for manual assignment. Obligations can be exported and filtered by type, status, and due date. |
| 3.5 | As the system, AI agent processing costs are tracked and controlled per contract. | Per-agent budget limits are enforced to prevent runaway costs on complex documents. Token usage and cost per contract are logged and available in reporting dashboards. Fallback to direct Anthropic Messages API calls if agent processing exceeds cost or time thresholds. |

**Implementation notes:** **Hybrid AI (Board Edition 4):** Complex tasks (full review, risk scoring, template deviation, obligation extraction) use Claude Opus 4.6 Agent SDK with MCP tools (WikiContracts, org data). Simple tasks use Anthropic Messages API. Async jobs on Render (queue + worker); store results and evidence in Supabase; run after upload or on-demand. Implement MCP server/tools for org structure, authority matrix, and WikiContracts so agents can query them.

---

### Epic 4 — Workflow Engine

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 4.1 | As an Admin, I can define workflow stages and transitions for a contract type. | Stages support owners, approvals, and required artifacts. Transitions support rework loops. Workflow definitions are versioned. |

**Implementation notes:** Workflow templates and instances in Supabase; versioned JSON or normalized tables; enforce transitions in API.

---

### Epic 5 — Visual Workflow Builder

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 5.1 | As an Admin, I can visually create and edit workflows using drag-and-drop. | Stages can be added/reordered/connected. Stage configuration is editable in a side panel. Publish is blocked until validation passes. |

**Implementation notes:** React Flow (or equivalent) on Next.js; save/load workflow definition to backend; validation before publish.

---

### Epic 6 — LLM Workflow Generator

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 6.1 | As an Admin, I can describe a workflow in natural language and receive a draft workflow template. | **An AI agent** receives the natural language description and **autonomously queries the org structure, authority matrix, and existing workflow templates via MCP tools**. System produces structured draft workflow linked to region/entity/project. Draft includes at least one approval stage and one signing stage where applicable. Admin can edit and must explicitly publish. **The agent validates the generated workflow JSON against the system schema and self-corrects before presenting the draft.** |

**Implementation notes:** Use **Claude Agent SDK** with MCP tool integrations that expose org structure, authority matrix, and workflow templates. Agent returns JSON draft; visual builder loads draft; publish only after explicit Admin action. Agent validates workflow against schema and iterates if validation fails.

---

### Epic 7 — Org Structure and Authority Matrix

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 7.1 | As an Admin, I can create regions, entities, and projects from the Admin UI. | CRUD for regions/entities/projects. New structures require assigning default workflow and signers before activation. Changes are audited. |
| 7.2 | As an Admin, I can configure signing authority rules and thresholds per entity/project. | Authority rules support role-based and named signers. Threshold checks (value/term/risk) can be configured. Signing initiation is blocked if authority does not match. |

**Implementation notes:** Supabase tables for org hierarchy and authority matrix; API enforces at Boldsign send.

---

### Epic 8 — Counterparty Management

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 8.1 | As a user, I can manage counterparties and their signing contacts. | Counterparty master supports legal and contact details. Signatories can be added and required fields enforced. Counterparty data must be complete before signature. |
| 8.2 | As a user, I am warned when creating a counterparty that may already exist. | System performs fuzzy matching on legal name and registration number during creation. Potential duplicates are presented for review before a new record is created. Merge/link functionality available for confirmed duplicates. |

**Implementation notes:** Counterparty and signatory tables in Supabase; fuzzy match (e.g. pg_trgm or external service) on create/edit.

---

### Epic 9 — Commercial Contract Workflow Integration

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 9.1 | As a user, I can collaborate on drafts through SharePoint and progress through approvals. | SharePoint link/version is stored in CCRS. Approvals are captured with timestamps and approver identity. Workflow state updates are audited. |
| 9.2 | As a user, I can send a contract to Boldsign and track signature status. | Boldsign send action is available only at the signing stage. Webhook updates update CCRS status. Executed copy is stored and locked on completion. |
| 9.3 | As a user, I can configure parallel or sequential signing order for a contract. | Signing order (parallel/sequential) is configurable per workflow template. Multiple internal signers are supported before counterparty signing. Countersigning workflow is available for third-party paper where the counterparty signs first. |

**Implementation notes:** Webhook endpoint on Render for Boldsign; store executed document in object storage; mark record immutable.

---

### Epic 10 — Merchant Agreement Automation

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 10.1 | As a user, I can generate a Merchant Agreement using a regional template and input parameters. | Inputs include vendor name, merchant fee, region, and required terms. System generates a document from the template. Document can be sent to Boldsign and stored after signing. |
| 10.2 | As TiTo, I can validate that a signed Merchant Agreement exists before POS deployment. | An API endpoint returns agreement status for vendor/entity/region/project. POS deployment is blocked if status is not "Fully Signed". All validation calls are logged. |
| 10.3 | As a user, I can create an amendment to an existing Merchant Agreement. | Amendment record is linked to the parent Merchant Agreement. Amendment inherits classification, counterparty, and entity from the parent. Amendment follows its own signing workflow and the executed copy is stored alongside the parent. |

**Implementation notes:** Document generation (docx-templates/Carbone) on Render; TiTo validation API on Render with &lt; 500 ms target; amendments as linked records in Supabase.

---

### Epic 11 — Monitoring and Alerts

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 11.1 | As a user, I receive reminders ahead of renewal notice windows and expiries. | Reminders are configurable by lead time. Notifications can be sent by email and Teams. Reminder sends are logged and deduplicated. |

**Implementation notes:** Cron or scheduled worker on Render; use verified key dates; integrate with Graph API (Teams/Email) or SendGrid.

---

### Epic 12 — Reporting and Analytics

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 12.1 | As an executive user, I can view a dashboard of contract status and expiring items. | Dashboard shows split Commercial vs Merchant. Drill-down by region/entity/project. Export to CSV/PDF available. |

**Implementation notes:** Next.js dashboard with API from Render; export as server-side CSV/PDF.

---

### Epic 13 — Multi-Language Enhancements

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 13.1 | As a user, I can attach multiple language versions to a single contract record. | Each file can be tagged with language. Bilingual inline documents are supported. AI extraction avoids double-counting bilingual clauses. |

**Implementation notes:** Language tag on document records; AI pipeline instructed to deduplicate by clause/date across languages.

---

### Epic 14 — Security and Compliance

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 14.1 | As an auditor, I can export an audit trail for a contract lifecycle. | Audit includes uploads, edits, approvals, signature events. Exports include timestamps and user identity. Access to exports is restricted to authorized roles. |

**Implementation notes:** Audit table in Supabase; export API with role check; optional retention/archive to object storage.

---

### Epic 15 — Contract Amendments and Renewals

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 15.1 | As a user, I can create an amendment linked to an existing contract that inherits its classification. | Amendment record references parent contract. Parent contract shows linked amendments in its history. Amendment goes through its own workflow instance. |
| 15.2 | As a user, I can execute a renewal that either extends the existing contract or creates a new version. | Renewal type (extension vs. new version) is selectable. New version inherits classification and counterparty. Expiry dates and reminders update accordingly. |
| 15.3 | As a user, I can link side letters and supplementary agreements to a master agreement. | Side letter is linked to the master with full traceability. Master agreement view shows all linked documents. Side letters can be independently versioned and signed. |

**Implementation notes:** Parent/child and link types in Supabase; workflow instance per amendment/renewal/side letter.

---

### Epic 16 — Escalation Engine

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 16.1 | As an Admin, I can configure escalation rules for overdue workflow stages. | Escalation triggers after configurable SLA breach period. Escalation targets are defined per stage (e.g., manager, then Legal, then executive). All escalations are logged in the contract audit trail. |
| 16.2 | As an executive, I can view all currently escalated items in a single dashboard view. | Dashboard shows escalated items with contract reference, stage, SLA breach duration, and current escalation level. Drill-down to the contract and workflow is available. Escalated items are cleared automatically when the underlying action is completed. |

**Implementation notes:** Escalation rules in workflow template; scheduled job on Render to evaluate SLA and create escalation events; dashboard reads from audit/events.

---

### Epic 17 — Counterparty Due Diligence

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| 17.1 | As a Legal user, I can flag a counterparty status and block contract initiation for non-active counterparties. | Status options: Active, Suspended, Blacklisted. Status changes require a mandatory reason and supporting documentation. Non-active counterparty blocks new contract initiation with a clear reason displayed to the user. |
| 17.2 | As a user with an active contract, I receive notification when a counterparty status changes. | Status changes trigger notifications to all users with active contracts involving the counterparty. Override requests are routed to an authorized role with full audit trail. All status changes and override decisions are audited. |

**Implementation notes:** Status and reason on counterparty in Supabase; API checks status on contract initiation; override flow with audit log.

---

## 6. Build Plan — Delivery Phases

The build plan is organized by **phase** and **epic**. Each phase can start once its predecessor is substantially complete. Dependencies are respected (e.g. Epic 2 before Epic 3; Epic 4/5 before Epic 9/10).

### Phase 1a — Foundation and Core Data

**Goal:** Secure app, core repository, org structure, counterparties, and compliance baseline.

| Epic | Name |
|------|------|
| 1 | Foundation and Infrastructure |
| 2 | Core Repository |
| 7 | Org Structure and Authority Matrix |
| 8 | Counterparty Management |
| 14 | Security and Compliance |
| 17 | Counterparty Due Diligence |

**Key outcomes:**  
- Entra ID sign-in; RBAC; secrets in env/Vault.  
- Upload/store/search contracts (metadata in Supabase, files in object storage).  
- Regions/entities/projects and signing authority matrix.  
- Counterparty CRUD, duplicate detection, status (Active/Suspended/Blacklisted).  
- Audit trail and restricted audit export.

**Suggested duration:** 8–10 sprints (refine in discovery).

---

### Phase 1b — Workflows and Signing

**Goal:** Workflow engine, visual builder, commercial and merchant workflows, amendments/renewals.

| Epic | Name |
|------|------|
| 4 | Workflow Engine |
| 5 | Visual Workflow Builder |
| 9 | Commercial Contract Workflow Integration |
| 10 | Merchant Agreement Automation |
| 15 | Contract Amendments and Renewals |

**Key outcomes:**  
- Workflow templates (stages, transitions, versioning) and visual builder with validation.  
- SharePoint link storage and approval capture; Boldsign send and webhook; executed copy storage; parallel/sequential and countersigning.  
- Merchant Agreement generation from templates; TiTo validation API; amendments linked to parent.  
- Amendment/renewal/side letter records and workflows.

**Suggested duration:** 10–12 sprints.

---

### Phase 1c — Intelligence, Monitoring, and Escalation

**Goal:** AI extraction, LLM workflow draft, reminders, dashboards, multi-language, escalation.

| Epic | Name |
|------|------|
| 3 | AI Contract Intelligence |
| 6 | LLM Workflow Generator |
| 11 | Monitoring and Alerts |
| 12 | Reporting and Analytics |
| 13 | Multi-Language Enhancements |
| 16 | Escalation Engine |

**Key outcomes:**  
- **Hybrid AI:** Claude Opus 4.6 Agent SDK for complex contract review, risk scoring, template deviation, obligation extraction; Anthropic Messages API for simple/high-throughput tasks. **MCP tool integrations** for org structure, authority matrix, WikiContracts.  
- AI summary, key fields, key dates, risk scoring, template deviation, obligations register; verification and audit.  
- **Agent cost control:** Per-agent budget limits, token/cost logging per contract, fallback to direct API.  
- **LLM workflow generator:** Agent-driven draft via MCP (org, authority, templates); workflow JSON validation and self-correction; Admin edit and explicit publish.  
- Configurable reminders (email/Teams); dashboards (Commercial vs Merchant, drill-down, export).  
- Multiple language versions and bilingual handling in AI.  
- Escalation rules, SLA breach escalation, and executive escalation dashboard.

**Suggested duration:** 10–12 sprints.

---

### Phase 2 — Future Backlog

As per the board spec: vendor self-service portal, advanced clause negotiation, regulatory integration, and other items as prioritized.

---

## 7. Dependency Overview

- **Epics 1, 2, 7, 8** are foundational for all workflows and reporting.  
- **Epics 4, 5** (workflow engine and visual builder) are required before **Epics 9, 10, 15**.  
- **Epic 2** (repository and search) is required before **Epic 3** (AI runs on stored documents).  
- **Epic 6** (LLM workflow generator) can be developed in Phase 1c and consumes the same workflow model as Epics 4 and 5; it requires **MCP tool integrations** for org structure, authority matrix, and workflow templates (Claude Agent SDK).
- **Epic 3** (AI Contract Intelligence) requires **MCP tools** for WikiContracts (template comparison) and hybrid routing (agent vs. direct Messages API); agent cost controls and logging are part of Epic 3.

---

## 8. Next Steps

1. **Discovery:** Refine user stories and acceptance criteria; confirm Entra ID and Boldsign integration details; agree on Supabase vs R2/S3 for object storage at launch; define MCP tool surface for org, authority, WikiContracts.  
2. **Repos and CI/CD:** Create frontend (Vercel) and backend (Render) repos; configure Vercel and Render deployments and env vars.  
3. **Supabase:** Provision project; design schema (contracts, workflow, counterparty, org, audit); configure storage buckets and RLS.  
4. **Phase 1a kick-off:** Start with Epic 1 (Entra ID, secrets) and Epic 2 (upload, store, search) in parallel where possible.  
5. **Phase 1c (AI):** Implement MCP server/tools for CCRS (org structure, authority matrix, WikiContracts); integrate Claude Opus 4.6 Agent SDK for complex tasks and Anthropic Messages API for simple tasks; add agent budget and cost logging.  
6. **Import backlog:** Load this document (or an exported CSV) into Jira/ADO; map epics to initiatives and stories to tickets.

---

*Document generated from CCRS Requirements v3 Board Edition 4 and adapted for Render, Vercel, Supabase, and recommended object storage. Includes hybrid AI architecture (Claude Opus 4.6 Agent SDK + Anthropic Messages API) and MCP tool integrations.*
