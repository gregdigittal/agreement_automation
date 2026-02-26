# 15. Compliance & Audit

CCRS provides a comprehensive compliance and audit framework designed to give your organisation full visibility into every action taken on the platform. From automatic audit logging of every record change, through a dedicated signing audit trail with legal-grade evidence, to regulatory framework tracking and document integrity verification -- every layer is built to support internal governance, external audits, and legal defensibility.

---

## Audit Logging

Every create, update, and delete operation in CCRS is automatically recorded in the audit log. This happens transparently -- users do not need to take any action to generate audit entries, and no user can disable or bypass the logging system.

### What Gets Logged

Every time a record is created, updated, or deleted anywhere in CCRS, the `AuditService` captures a complete snapshot of the change.

| Field | Description |
|---|---|
| **User** | The user who performed the action, identified by name and user ID. |
| **Event** | The type of operation: `created`, `updated`, or `deleted`. |
| **Record Type** | The type of record affected (e.g. Contract, Counterparty, Workflow Stage, Signing Session). |
| **Record ID** | The unique identifier of the specific record. |
| **Old Values** | For updates and deletes, the field values before the change. Stored as structured JSON. |
| **New Values** | For creates and updates, the field values after the change. Stored as structured JSON. |
| **IP Address** | The IP address from which the action was performed. |
| **User Agent** | The browser and operating system used to perform the action. |
| **Timestamp** | The exact date and time of the action (UTC). |

### Immutability

Audit log entries are **immutable**. Once written, they cannot be edited, overwritten, or deleted -- not even by System Admins. This guarantees the integrity of the audit trail and ensures it can serve as reliable evidence during internal reviews, external audits, or legal proceedings.

### Viewing Audit Logs

Audit logs are accessible to three roles:

| Role | Access Level |
|---|---|
| **System Admin** | Full access to all audit log entries across the entire platform. |
| **Legal** | View access to all audit log entries. |
| **Audit** | View access to all audit log entries. |

To view audit logs:

1. Navigate to **Audit Logs** in the left sidebar.
2. The page displays a chronological list of all logged events, newest first.
3. Each entry shows the user, event type, record type, and timestamp in the list view.
4. Click any entry to expand it and view the full detail -- including old values, new values, IP address, and user agent.

### Filtering Audit Logs

Use the filters at the top of the Audit Logs page to narrow results:

- **User** -- show only actions performed by a specific user.
- **Event Type** -- filter by created, updated, or deleted.
- **Record Type** -- focus on a specific model (e.g. only Contract changes, only Counterparty changes).
- **Date Range** -- restrict to a specific time window.
- **Record ID** -- search for all changes to a specific record.

### Contract-Level Audit History

In addition to the centralised Audit Logs page, each contract's detail page includes an **Activity** tab that shows the audit history for that specific contract. This provides a focused view of every change made to a single contract over its lifetime without the need to filter the global log.

---

## Signing Audit Trail

The signing process has its own dedicated audit trail, separate from the general audit log. The `SigningAuditLog` captures every event in the lifecycle of a signing session with the detail required for legal defensibility.

### Events Tracked

| Event | Description |
|---|---|
| **Session Created** | A new signing session was initiated for a contract. Records who created the session and when. |
| **Invitation Sent** | A signing invitation (magic link) was dispatched to a signer. Records the recipient and delivery channel. |
| **Document Viewed** | A signer opened the document for review. Proves the signer had access to the full document before signing. |
| **Signature Submitted** | A signer applied their signature (typed, drawn, uploaded, or webcam). Records the signature method used. |
| **Signing Declined** | A signer explicitly declined to sign. Records the reason if provided. |
| **Session Completed** | All required signatures have been collected and the session is marked as complete. |
| **Session Cancelled** | The session was cancelled before completion. Records who cancelled and the reason. |
| **Reminder Sent** | A follow-up reminder was sent to a signer who has not yet signed. |

### Data Captured Per Event

Each signing audit log entry includes:

| Field | Description |
|---|---|
| **Signing Session** | The session this event belongs to. |
| **Signer** | The name and identifier of the signer involved (if applicable). |
| **Event Type** | One of the events listed above. |
| **IP Address** | The IP address from which the event occurred. |
| **User Agent** | The browser and operating system used. |
| **Timestamp** | The exact date and time of the event (UTC). |
| **Additional Data** | Event-specific metadata -- e.g. signature method for "Signature Submitted", decline reason for "Signing Declined". |

### Viewing the Signing Audit Trail

The signing audit trail is accessible from two locations:

1. **Signing Session Detail Page** -- navigate to a signing session record and open the **Audit Trail** tab to see every event for that session in chronological order.
2. **Contract Detail Page** -- the contract's **Signing** tab shows all signing sessions for that contract, each with a link to its full audit trail.

---

## Contract Immutability

CCRS enforces strict immutability rules on contracts that have reached certain lifecycle stages. This ensures that the terms of an executed agreement cannot be altered after the fact.

### Immutability Rules

| Contract Status | Editable? | Explanation |
|---|---|---|
| Draft | Yes | Contracts in draft status can be freely edited by users with the appropriate role permissions. |
| In Review | Yes | Contracts under review can still be modified as part of the negotiation and approval process. |
| Approved | Limited | Approved contracts can be modified only through specific override workflows. |
| Executed | No | Once a contract is executed (all signatures collected and the signing session is complete), all fields become **read-only**. No user, including System Admins, can edit the contract record directly. |
| Archived | No | Archived contracts are permanently read-only. They are retained for reference and audit purposes. |

### Making Changes to Executed Contracts

When business circumstances require changes to an executed contract, CCRS supports three mechanisms -- each of which creates a new, linked contract record rather than modifying the original:

- **Amendment** -- a formal modification to specific terms of the executed contract. The amendment is linked to the parent contract and goes through its own workflow and signing process.
- **Renewal** -- an extension or replacement of the contract for a new term. The renewal references the original contract and captures updated terms.
- **Side Letter** -- a supplementary agreement that modifies or clarifies specific provisions without rewriting the full contract.

In all three cases, the original executed contract remains unchanged. The linked record provides a clear audit trail showing what was modified, when, and by whom.

---

## Document Integrity

CCRS uses SHA-256 cryptographic hashing to guarantee that contract documents are not tampered with during the signing process.

### How It Works

1. **At session creation** -- when a signing session is initiated, CCRS computes a SHA-256 hash of the original contract PDF and stores it in the signing session record. This hash acts as a digital fingerprint of the document as it existed at the moment signing began.

2. **At session completion** -- when all signatures have been collected and the session is marked as complete, CCRS computes a SHA-256 hash of the final signed PDF and stores it alongside the original hash.

3. **Verification** -- at any point after signing, the stored hashes can be compared against the actual document files. If the hash of the file on disk matches the stored hash, the document is confirmed to be unaltered. If the hashes do not match, the document has been modified since the hash was recorded.

### What SHA-256 Guarantees

- **Tamper detection** -- any change to the document, no matter how small (even a single byte), produces a completely different hash value. This makes undetected tampering computationally infeasible.
- **Non-repudiation** -- the stored hash proves that the document the signers reviewed and signed is identical to the document on file.
- **Chain of custody** -- the pair of hashes (pre-signing and post-signing) establishes that the document was consistent throughout the signing process.

### Where Hashes Are Stored

Document hashes are stored in the signing session record and included in the audit certificate (see below). They are also recorded in the signing audit trail, providing multiple independent references.

---

## Audit Certificate

When a signing session completes, CCRS automatically generates an **audit certificate** -- a standalone PDF that serves as a comprehensive record of the entire signing process.

### What the Audit Certificate Contains

| Section | Contents |
|---|---|
| **Contract Details** | Contract title, reference number, parties, effective date, total value. |
| **Signing Session** | Session ID, creation date, completion date, total duration. |
| **Signer Information** | For each signer: full name, email address, role, signature method (typed/drawn/uploaded/webcam), signing timestamp. |
| **IP Addresses** | The IP address from which each signer accessed and signed the document. |
| **User Agents** | The browser and operating system each signer used. |
| **Document Hashes** | The SHA-256 hash of the original document (at session creation) and the SHA-256 hash of the signed document (at session completion). |
| **Event Timeline** | A chronological list of every signing event (created, sent, viewed, signed, completed) with timestamps. |

### Storage

The audit certificate is stored as a separate PDF file alongside the signed contract document. Both files are retained in the same secure storage location (S3 with server-side encryption).

### Accessing the Audit Certificate

1. Navigate to the contract's detail page.
2. Open the **Signing** tab.
3. Click on the completed signing session.
4. The audit certificate is available as a downloadable PDF in the session detail view.

### Legal Significance

The audit certificate provides the evidence required to demonstrate in a legal proceeding that:

- The signers were properly identified and invited.
- Each signer reviewed the document before signing.
- The document was not altered between invitation and signing.
- Each signature was captured with a specific method, from a specific IP address, at a specific time.
- The complete chain of events is recorded and verifiable.

---

## Regulatory Frameworks

CCRS allows your organisation to define regulatory frameworks and check contracts against them for compliance. This feature is designed for organisations that operate across multiple jurisdictions or industries with varying regulatory requirements.

### Defining a Regulatory Framework

System Admins and Legal users can create and manage regulatory frameworks.

1. Navigate to **Regulatory Frameworks** in the left sidebar.
2. Click **"New Regulatory Framework"**.
3. Complete the following fields:

| Field | Description |
|---|---|
| **Name** | A descriptive name for the framework (e.g. "GDPR Data Processing Requirements", "South Africa Consumer Protection Act"). |
| **Description** | A detailed explanation of what the framework covers and when it applies. |
| **Jurisdiction** | The jurisdiction this framework applies to (selected from the Jurisdictions list). |
| **Requirements** | A structured list of specific requirements that contracts must meet. Stored as JSON and displayed as a checklist during compliance checks. |
| **Is Active** | Whether this framework is currently in use. Inactive frameworks are retained for reference but are not included in compliance checks. |

4. Click **Save**.

### Running a Compliance Check

The `RegulatoryComplianceService` evaluates contracts against applicable regulatory frameworks.

1. Open a contract's detail page.
2. Navigate to the **Compliance** tab.
3. Click **"Run Compliance Check"**.
4. CCRS evaluates the contract against all active regulatory frameworks that match the contract's jurisdiction and type.
5. The results appear as a list of **Compliance Findings**.

### Compliance Findings

Each finding represents a specific compliance observation or issue discovered during the check.

| Field | Description |
|---|---|
| **Regulatory Framework** | The framework that generated this finding. |
| **Finding Type** | The category of the finding (e.g. missing clause, non-compliant term, documentation gap). |
| **Severity** | The seriousness of the finding: **Critical**, **High**, **Medium**, or **Low**. |
| **Description** | A detailed explanation of the issue and what needs to be addressed. |
| **Status** | The current resolution status (see below). |
| **Remediation** | Notes on how the finding was or should be resolved. |

### Finding Statuses

Compliance findings progress through four statuses:

| Status | Meaning |
|---|---|
| **Open** | The finding has been identified but no action has been taken yet. |
| **In Progress** | Remediation is underway -- someone is actively working to resolve the issue. |
| **Resolved** | The issue has been addressed and the contract is now compliant with this requirement. |
| **Waived** | The finding has been reviewed and a decision has been made to accept the risk without remediation. This should be used sparingly and with documented justification. |

### Updating Finding Status

1. Open the contract's **Compliance** tab.
2. Click on the finding you want to update.
3. Change the **Status** field to the appropriate value.
4. If resolving or waiving, add a note in the **Remediation** field explaining the action taken and the rationale.
5. Click **Save**.

All status changes are recorded in the audit log, providing a full history of how each finding was handled.

---

## Compliance Monitoring

CCRS provides multiple views for monitoring compliance across your contract portfolio.

### Analytics Dashboard -- Compliance Widget

The **Analytics Dashboard** (accessible to System Admins and Finance users) includes a **Compliance Overview** widget that displays:

- **Total findings** across all contracts, broken down by severity.
- **Open vs. resolved** findings as a percentage and trend over time.
- **Frameworks with the most findings** -- helping identify which regulatory areas require the most attention.
- **Average time to resolution** -- how long it takes your organisation to address compliance findings.

### Contract Detail -- Compliance Tab

Each contract's detail page includes a **Compliance** tab that shows:

- All compliance findings for that specific contract.
- The status of each finding (open, in progress, resolved, waived).
- The regulatory framework each finding relates to.
- A summary of the contract's overall compliance posture.

### Reports

The **Reports** section (accessible to System Admins, Legal, Finance, and Audit roles) includes compliance-related reports that can be filtered by:

- **Jurisdiction** -- focus on a specific regulatory jurisdiction.
- **Framework** -- filter by a specific regulatory framework.
- **Severity** -- show only critical or high-severity findings.
- **Status** -- filter by open findings only, or show the full history.
- **Date range** -- restrict to findings created or resolved within a specific period.

Reports can be exported to Excel or PDF for distribution to stakeholders, regulatory bodies, or external auditors.

---

## Data Retention

CCRS retains audit and compliance data according to the following policies:

| Data Type | Retention Policy |
|---|---|
| **Audit Logs** | Retained indefinitely. Audit log entries are never deleted or purged. |
| **Signing Audit Logs** | Retained indefinitely alongside the associated signing sessions. |
| **Audit Certificates** | Retained indefinitely as PDF files in secure storage. |
| **Contract Documents** | Retained indefinitely in S3 with server-side encryption. |
| **Compliance Findings** | Retained indefinitely, including resolved and waived findings. |
| **Regulatory Frameworks** | Retained indefinitely. Inactive frameworks are preserved for historical reference. |

### Storage Security

- All files (contract documents, signed PDFs, audit certificates) are stored in S3 with server-side encryption enabled.
- Access to stored files is controlled through the same RBAC and restricted-contract mechanisms described in [Chapter 14 -- Role Reference Matrix](14-role-reference-matrix.md).
- Database records (audit logs, signing audit logs, compliance findings) are stored in the MySQL database with access restricted to authenticated users with appropriate role permissions.

---

## Preparing for an External Audit

When your organisation undergoes an external audit or regulatory review, CCRS provides the tools to respond efficiently.

### Step-by-Step Guide

1. **Identify the scope.** Determine which contracts, time periods, and regulatory frameworks the audit covers.

2. **Export audit logs.** Navigate to **Audit Logs**, apply the appropriate date range and record type filters, and export the results. The export includes all fields: user, event, record type, old/new values, IP address, and timestamps.

3. **Export compliance findings.** Navigate to **Reports**, select the compliance report, filter by the relevant jurisdiction and framework, and export to Excel or PDF.

4. **Gather signing evidence.** For each contract in scope, download the **audit certificate** from the signing session detail page. This single document contains the complete signing chain of custody.

5. **Verify document integrity.** If the auditor requires proof that contract documents have not been tampered with, provide the SHA-256 hashes from the signing session record and demonstrate that recomputing the hash of the stored file produces the same value.

6. **Provide role and access documentation.** Export the user list with role assignments to demonstrate that access controls are in place and appropriate.

---

## Best Practices

- **Do not treat compliance findings as optional.** Every open finding represents a risk. Establish a target resolution time and track against it.
- **Use the "Waived" status sparingly.** Waiving a finding means accepting the risk. Always document the rationale and ensure a senior stakeholder has approved the decision.
- **Review regulatory frameworks regularly.** Regulations change. Schedule a quarterly review of your active frameworks to ensure requirements are current.
- **Download audit certificates promptly.** While certificates are retained indefinitely in CCRS, keeping local copies in your organisation's document management system provides an additional layer of redundancy.
- **Leverage the Analytics Dashboard.** The Compliance Overview widget gives you a real-time snapshot of your organisation's compliance posture. Review it weekly to catch emerging issues early.
- **Restrict access to audit logs appropriately.** Audit log access is limited to System Admin, Legal, and Audit roles by design. Do not create workarounds that expose audit data to other roles -- the principle of least privilege protects the integrity of the audit trail.
- **Train your team on immutability.** Ensure all users understand that executed contracts cannot be edited and that changes must be made through amendments, renewals, or side letters. This prevents confusion and support requests.
