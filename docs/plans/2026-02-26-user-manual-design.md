# CCRS User Manual — Design Document

**Date:** 2026-02-26
**Status:** Approved

## Audience

- Internal staff (all 6 roles: system_admin, legal, commercial, finance, operations, audit)
- Board / executive stakeholders
- External counterparties and vendors (signing flow + vendor portal)

## Deliverables

1. **`docs/user-manual/`** — Rich markdown with Mermaid flowcharts (GitHub-renderable, PDF-exportable)
2. **In-app Help page** — Condensed version rendered inside Filament's Help & Guide page

## Manual Structure (15 sections)

| # | Section | File | Audience | Flowcharts |
|---|---------|------|----------|------------|
| 1 | Platform Overview | `01-platform-overview.md` | All | System architecture, navigation map |
| 2 | Getting Started | `02-getting-started.md` | Internal | Azure AD login, dashboard, role navigation |
| 3 | Contract Lifecycle | `03-contract-lifecycle.md` | Internal + Exec | Full workflow state machine, creation, approval gates |
| 4 | Counterparty Management | `04-counterparty-management.md` | Commercial, Legal | CRUD, duplicate detection, merge, override, KYC |
| 5 | Signing & E-Signatures | `05-signing.md` | All | Sequential/parallel, stored sigs, webcam, page enforcement |
| 6 | Workflow Templates | `06-workflow-templates.md` | Admin | Create, publish, AI generation, escalation rules |
| 7 | AI Analysis & Redlining | `07-ai-analysis.md` | Legal, Finance | Trigger, cost tracking, clause redlining |
| 8 | Reports & Analytics | `08-reports-analytics.md` | Finance, Exec | Dashboard widgets, exports, AI cost reports |
| 9 | Bulk Operations | `09-bulk-operations.md` | Admin | CSV import, ZIP upload, progress monitoring |
| 10 | Notifications & Reminders | `10-notifications-reminders.md` | All internal | Channels, preferences, key dates |
| 11 | Organization Setup | `11-organization-setup.md` | Admin | Regions, entities, projects, jurisdictions, signing authorities |
| 12 | Vendor Portal | `12-vendor-portal.md` | External | Magic link login, contract viewing, document upload |
| 13 | External Signing Guide | `13-external-signing-guide.md` | External | Step-by-step for email link signers |
| 14 | Role Reference Matrix | `14-role-reference-matrix.md` | All | Permissions grid — who can do what |
| 15 | Compliance & Audit | `15-compliance-audit.md` | Legal, Audit | Regulatory frameworks, audit logs, immutability |

## Flowcharts (~20 Mermaid diagrams)

1. Platform navigation map (by role)
2. Contract lifecycle state machine (draft → archived)
3. Contract creation flow
4. Workflow approval flow with escalation paths
5. Sequential signing flow (multi-party)
6. Parallel signing flow
7. Stored signature management
8. Webcam signature capture process
9. Page enforcement (viewing + initials)
10. Counterparty onboarding + KYC
11. Duplicate detection + merge
12. Override request approval
13. AI analysis trigger + results
14. Redline review session
15. Bulk contract upload processing
16. Bulk data import
17. Reminder/notification dispatch
18. Vendor portal login + access
19. Report export flow
20. Role-based access decision tree

## In-App Help Page

Condensed version of the manual rendered on the existing `/admin/help` Filament page:
- Collapsible sections matching the manual chapters
- Inline Mermaid flowcharts for key workflows
- Quick-reference role permissions table
- Links to the full docs/ manual for detail

## Implementation Notes

- Use Mermaid `graph TD`, `stateDiagram-v2`, and `flowchart` syntax for GitHub rendering
- Each section file is standalone with its own flowcharts
- `docs/user-manual/README.md` serves as the table of contents
- In-app Help page reads from a simplified blade view, not directly from markdown
