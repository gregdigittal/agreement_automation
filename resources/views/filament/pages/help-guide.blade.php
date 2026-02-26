<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Section 1: Getting Started (expanded by default) --}}
        <x-filament::section collapsible>
            <x-slot name="heading">Getting Started</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p><strong>CCRS</strong> (Contract & Compliance Review System) is Digittal Group's platform for managing the full contract lifecycle — from drafting through execution and archival.</p>
                <h4>Logging In</h4>
                <ul>
                    <li><strong>Log in</strong> using your Azure AD (Microsoft) credentials via the login page.</li>
                    <li>First-time users: your Azure AD group determines your CCRS role automatically.</li>
                    <li>After login, you'll land on the <strong>Dashboard</strong> showing contract status, pending workflows, expiry alerts, and key metrics.</li>
                </ul>
                <h4>Navigation</h4>
                <ul>
                    <li><strong>Left sidebar</strong> — primary navigation grouped by feature. Menu items are role-based — you'll only see what your role permits.</li>
                    <li><strong>Global search</strong> — press <code>Cmd+K</code> (macOS) or <code>Ctrl+K</code> (Windows) to search contracts, counterparties, and other records.</li>
                    <li><strong>Notifications bell</strong> — top-right corner shows unread notifications.</li>
                </ul>
                <h4>Dashboard Widgets</h4>
                <p>The Dashboard displays: Contract Status, Expiry Horizon (30/60/90 days), Pending Workflows, Active Escalations, AI Cost summary, Compliance Overview, Contract Pipeline Funnel, Obligation Tracker, Risk Distribution, and Workflow Performance.</p>
                <h4>Getting Help</h4>
                <p>For issues not covered here, contact <strong>support@digittal.io</strong> or reach out to your System Administrator.</p>
            </div>
        </x-filament::section>

        {{-- Section 2: Contract Lifecycle --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Contract Lifecycle</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>Every contract moves through a defined workflow with seven states:</p>
                <ol>
                    <li><strong>Draft</strong> — Initial creation. Upload or generate the contract document.</li>
                    <li><strong>Review</strong> — Legal and stakeholder review. AI analysis can be triggered.</li>
                    <li><strong>Approval</strong> — Internal approval by designated approvers.</li>
                    <li><strong>Signing</strong> — External counterparty signs the document.</li>
                    <li><strong>Countersign</strong> — Internal signers countersign the executed copy.</li>
                    <li><strong>Executed</strong> — Fully signed and in effect. Contract becomes read-only.</li>
                    <li><strong>Archived</strong> — Contract term ended or replaced. Read-only.</li>
                </ol>
                <div class="mermaid my-4">
                    stateDiagram-v2
                        [*] --> Draft
                        Draft --> Review : Submit for Review
                        Review --> Approval : Approve
                        Review --> Draft : Request Changes
                        Approval --> Signing : Approved
                        Approval --> Review : Reject
                        Signing --> Countersign : External Signed
                        Countersign --> Executed : Countersigned
                        Executed --> Archived : Term Ended
                        Draft --> Cancelled : Cancel
                        Review --> Cancelled : Cancel
                        Approval --> Cancelled : Cancel
                        Signing --> Cancelled : Cancel
                </div>
                <p><strong>Workflow templates</strong> define which stages apply to each contract type and region. Only published templates are automatically assigned to new contracts.</p>
                <p><strong>Immutability:</strong> Executed and Archived contracts are locked for compliance. To make changes, create an Amendment, Renewal, or Side Letter from the contract's action menu.</p>
                <p><em>See the full <a href="/docs/user-manual/03-contract-lifecycle.md">Contract Lifecycle</a> guide for details.</em></p>
            </div>
        </x-filament::section>

        {{-- Section 3: Creating a Contract --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Creating a Contract</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <ol>
                    <li>Navigate to <strong>Contracts</strong> in the left menu and click <strong>New Contract</strong>.</li>
                    <li>Select the <strong>Region</strong>, <strong>Entity</strong>, and <strong>Project</strong> this contract belongs to.</li>
                    <li>Choose the <strong>Counterparty</strong> — the external party. If they don't exist yet, create them first under Counterparties.</li>
                    <li>Select the <strong>Contract Type</strong>:
                        <ul>
                            <li><strong>Commercial</strong> — upload a PDF or DOCX contract file.</li>
                            <li><strong>Merchant</strong> — generate from a master template via Contracts &gt; Generate Merchant Agreement.</li>
                        </ul>
                    </li>
                    <li>Enter a descriptive <strong>Title</strong>, start/end dates, contract value, and upload the file.</li>
                    <li>Save. The contract is created in <strong>Draft</strong> state with the appropriate workflow template applied.</li>
                </ol>
                <p><strong>Access Control:</strong> System Admins and Legal can mark a contract as <strong>Restricted</strong>. When restricted, only explicitly authorized users (and System Admins) can view or edit it.</p>
            </div>
        </x-filament::section>

        {{-- Section 4: Counterparty Management --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Counterparty Management</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <ul>
                    <li><strong>Adding:</strong> Navigate to Counterparties → New. Enter the legal name and registration number.</li>
                    <li><strong>Duplicate Checking:</strong> Click "Check for Duplicates" before saving. The system searches by name similarity and registration number.</li>
                    <li><strong>Status:</strong> Active, Suspended, or Blacklisted. Suspended/Blacklisted counterparties require an override request to proceed with new contracts.</li>
                    <li><strong>Override Requests:</strong> Commercial users submit an override with a business justification. Legal or System Admins approve or reject.</li>
                    <li><strong>Merging:</strong> System Admins can merge duplicate counterparties — all contracts are moved to the target record.</li>
                    <li><strong>Contacts:</strong> Manage contact persons (name, email, phone, position) in the Contacts tab.</li>
                    <li><strong>KYC:</strong> KYC templates assign compliance checklists to counterparties, completed by Legal during onboarding.</li>
                </ul>
            </div>
        </x-filament::section>

        {{-- Section 5: Signing & E-Signatures --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Signing & E-Signatures</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>CCRS includes a full in-house electronic signing system with four capture methods, stored signatures, template-based signing blocks, and a complete audit trail.</p>
                <h4>Creating a Signing Session</h4>
                <ol>
                    <li>From a contract's action menu, select <strong>Send for Signing</strong>.</li>
                    <li>Choose signing order: <strong>Sequential</strong> (one after another) or <strong>Parallel</strong> (all at once).</li>
                    <li>Add signers: name, email, type (internal/external), order.</li>
                    <li>Optionally enable: <em>Require all pages viewed</em>, <em>Require page initials</em>.</li>
                </ol>
                <div class="mermaid my-4">
                    flowchart TD
                        A[Session Created] --> B{Signing Order?}
                        B -->|Sequential| C[Email Signer 1]
                        B -->|Parallel| D[Email ALL Signers]
                        C --> E[Signer Opens Link]
                        D --> E
                        E --> F[Views PDF + Page Enforcement]
                        F --> G[Chooses Signature Method]
                        G --> H[Submits Signature]
                        H --> I{More Signers?}
                        I -->|Yes, Sequential| C
                        I -->|Waiting, Parallel| J[Check All Signed]
                        I -->|All Done| K[Overlay Signatures on PDF]
                        J --> K
                        K --> L[Generate Audit Certificate]
                        L --> M[Completion Emails Sent]
                </div>
                <h4>Signature Methods</h4>
                <ul>
                    <li><strong>Draw</strong> — draw on a canvas using mouse or touch.</li>
                    <li><strong>Type</strong> — type your name, rendered as a signature-style image.</li>
                    <li><strong>Upload</strong> — upload a PNG or JPEG image file.</li>
                    <li><strong>Camera</strong> — webcam capture: hold paper with signature to camera, system removes background.</li>
                </ul>
                <h4>Stored Signatures</h4>
                <p>Manage saved signatures and initials on the <strong>My Signatures</strong> page. Set a default signature and initials for one-click signing. After signing, you can save your signature for future use.</p>
                <h4>Page Enforcement</h4>
                <ul>
                    <li><strong>Viewing requirement:</strong> Signers must scroll through every page before the submit button is enabled.</li>
                    <li><strong>Page initials:</strong> Each page shows an "Initial" button — signers must initial every page.</li>
                </ul>
                <h4>Security</h4>
                <p>Signing tokens are cryptographically generated (SHA-256 hashed in database). Every action is audit-logged with IP address and user agent. Document integrity verified via SHA-256 hashing at session creation and completion.</p>
                <p><em>See the full <a href="/docs/user-manual/05-signing.md">Signing & E-Signatures</a> guide for details.</em></p>
            </div>
        </x-filament::section>

        {{-- Section 6: Workflow Templates --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Workflow Templates</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>Workflow templates define the stages a contract moves through and who approves at each step.</p>
                <ul>
                    <li>Only <strong>System Admins</strong> can create and manage workflow templates.</li>
                    <li>Templates can be scoped to a specific Region, Entity, or Project — or left global.</li>
                    <li>Build stages using the <strong>visual workflow builder</strong> or <strong>AI generation</strong> (describe in natural language).</li>
                    <li>A template must be <strong>Published</strong> to be automatically assigned to new contracts.</li>
                    <li>Publishing increments the version number; previous versions are preserved.</li>
                </ul>
                <h4>Escalation Rules</h4>
                <p>Each workflow stage can have up to 3 escalation tiers. When SLA thresholds are breached, additional stakeholders are notified — from Tier 1 (colleagues) through Tier 3 (executive escalation).</p>
                <p><strong>Tip:</strong> Create your Regions, Entities, and Projects <em>before</em> creating workflow templates, so you can scope them correctly.</p>
            </div>
        </x-filament::section>

        {{-- Section 7: AI Analysis & Redlining --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">AI Analysis & Redlining</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>CCRS integrates AI-powered contract analysis with five analysis types:</p>
                <ul>
                    <li><strong>Summary</strong> — executive summary of key points, parties, and terms.</li>
                    <li><strong>Extraction</strong> — structured field extraction (names, dates, values) with confidence scores.</li>
                    <li><strong>Risk Assessment</strong> — identifies risk factors with scores and mitigation recommendations.</li>
                    <li><strong>Deviation</strong> — compares against a WikiContract template, highlighting non-standard terms.</li>
                    <li><strong>Obligations</strong> — extracts contractual obligations with due dates and responsible parties.</li>
                </ul>
                <p>Trigger analysis from a contract's action menu. Results appear in the <strong>AI Analysis</strong> tab. All analyses track token usage and USD cost.</p>
                <h4>Redline Review</h4>
                <p>AI-powered clause-by-clause comparison against reference templates. Each clause gets a recommendation: Accept, Modify, or Reject. Legal reviews and overrides as needed.</p>
            </div>
        </x-filament::section>

        {{-- Section 8: Reports & Analytics --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Reports & Analytics</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <h4>Reports Page</h4>
                <p>Filterable contract list with export to Excel (.xlsx) and PDF. Filter by: state, type, region, entity, project, date range. Accessible to Finance, Legal, Audit, and System Admin.</p>
                <h4>Analytics Dashboard</h4>
                <p>Visual widgets: Contract Pipeline Funnel, Risk Distribution, Compliance Overview, Obligation Tracker, AI Usage & Cost, Workflow Performance. Accessible to Finance and System Admin.</p>
                <h4>AI Cost Report</h4>
                <p>Breakdown of AI analysis costs by type, contract, and time period. Tracks analyses, tokens, and USD cost.</p>
            </div>
        </x-filament::section>

        {{-- Section 9: Bulk Operations --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Bulk Operations</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>System Admins can bulk-import data and contracts via CSV.</p>
                <h4>Bulk Data Upload</h4>
                <ol>
                    <li>Navigate to <strong>Administration &gt; Bulk Data Upload</strong>.</li>
                    <li>Select data type: Regions, Entities, Projects, Users, or Counterparties.</li>
                    <li>Click <strong>Download Template</strong> to get a CSV with the correct column headers.</li>
                    <li>Fill in the template and upload it.</li>
                    <li>Review results: success and failure counts with error details.</li>
                </ol>
                <p><strong>Important:</strong> Use <em>codes</em> (not names) for references — Region's code in <code>region_code</code>, Entity's code in <code>entity_code</code>. Create dependencies in order: Regions → Entities → Projects.</p>
                <h4>Bulk Contract Upload</h4>
                <p>Upload a CSV manifest plus a ZIP file containing contract documents (PDF/DOCX). Limits: 500 files, 50MB per file. Progress tracked in real-time.</p>
            </div>
        </x-filament::section>

        {{-- Section 10: Notifications & Reminders --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Notifications & Reminders</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <h4>Notification Channels</h4>
                <ul>
                    <li><strong>Email</strong> — standard email notifications.</li>
                    <li><strong>Microsoft Teams</strong> — posts to configured Teams channel webhooks.</li>
                    <li><strong>In-App</strong> — notifications bell in the top-right corner.</li>
                    <li><strong>Calendar (ICS)</strong> — downloadable ICS files for key dates, importable into Outlook/Google Calendar.</li>
                </ul>
                <h4>Preferences</h4>
                <p>Configure per-category channel toggles on the <strong>Notification Preferences</strong> page. Categories: workflow actions, contract updates, signing events, escalations, reminders.</p>
                <h4>Reminders</h4>
                <p>Reminders are linked to Contract Key Dates (expiry, renewal, payment). Configure lead days and notification channel. View upcoming milestones on the <strong>Key Dates</strong> page.</p>
            </div>
        </x-filament::section>

        {{-- Section 11: Organization Setup --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Organization Setup</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>CCRS organises contracts using a three-level hierarchy (System Admin only):</p>
                <ol>
                    <li><strong>Regions</strong> — Geographic groupings (e.g. MENA, EMEA, APAC). Each has a unique code.</li>
                    <li><strong>Entities</strong> — Legal entities within a region (e.g. Digittal AE, Digittal UK). Supports parent/child hierarchy.</li>
                    <li><strong>Projects</strong> — Business projects within an entity.</li>
                </ol>
                <p>Create in order: Regions → Entities → Projects (each level depends on the one above).</p>
                <h4>Additional Configuration</h4>
                <ul>
                    <li><strong>Jurisdictions:</strong> Country codes and regulatory bodies, assigned to Entities.</li>
                    <li><strong>Signing Authorities:</strong> Define who can sign for each Entity/Project, with maximum contract value limits.</li>
                    <li><strong>Organization Visualization:</strong> Visual tree display of your Region → Entity → Project hierarchy.</li>
                </ul>
                <p>The <strong>"Code"</strong> field is a short identifier (e.g. "MENA", "DGT-AE") used for CSV uploads, reports, and filters.</p>
            </div>
        </x-filament::section>

        {{-- Section 12: Vendor Portal --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Vendor Portal</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>The Vendor Portal is an external-facing interface for counterparty contacts. It provides a simplified, secure way to view contracts and upload documents without a full CCRS account.</p>
                <ul>
                    <li><strong>Access:</strong> Vendors visit the portal URL, enter their email, and receive a magic link (valid 24 hours).</li>
                    <li><strong>View Contracts:</strong> Browse and download contracts assigned to their counterparty.</li>
                    <li><strong>Upload Documents:</strong> Submit documents for review by CCRS users.</li>
                    <li><strong>Notifications:</strong> Receive messages from CCRS users about their contracts.</li>
                </ul>
                <p><strong>For Admins:</strong> Manage vendor users under <strong>Administration &gt; Vendor Users</strong>. Create, edit, activate/deactivate vendor access.</p>
            </div>
        </x-filament::section>

        {{-- Section 13: External Signing --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">External Signing Guide</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>When an external party needs to sign a contract, they receive an email with a secure signing link. No account is required.</p>
                <ol>
                    <li>Click the <strong>Sign Document</strong> button in the email.</li>
                    <li>Review the contract PDF in the built-in viewer.</li>
                    <li>If page enforcement is enabled, scroll through all pages (and initial each page if required).</li>
                    <li>Choose a signature method: Draw, Type, Upload, or Camera.</li>
                    <li>Submit your signature. Optionally save it for future use.</li>
                </ol>
                <p><strong>Troubleshooting:</strong> Links expire after 7 days — ask the sender to resend. Camera requires HTTPS and browser permission. Use a modern browser (Chrome, Firefox, Safari, Edge).</p>
                <p><em>See the full <a href="/docs/user-manual/13-external-signing-guide.md">External Signing Guide</a> for step-by-step instructions.</em></p>
            </div>
        </x-filament::section>

        {{-- Section 14: Role Permissions --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Role Permissions</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>CCRS uses six roles. Each user has one role that determines their permissions:</p>
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="text-left py-1 pr-4">Feature</th>
                            <th class="py-1 px-2 text-center">System Admin</th>
                            <th class="py-1 px-2 text-center">Legal</th>
                            <th class="py-1 px-2 text-center">Commercial</th>
                            <th class="py-1 px-2 text-center">Finance</th>
                            <th class="py-1 px-2 text-center">Operations</th>
                            <th class="py-1 px-2 text-center">Audit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t">
                            <td class="py-1 pr-4">View Contracts</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Create Contracts</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Edit Contracts</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Manage Counterparties</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Approve Overrides</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Manage Org Structure</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Workflow Templates</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Bulk Uploads</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">AI Analysis & Redlining</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Send for Signing</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Reports & Export</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">Yes</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Analytics Dashboard</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Audit Logs</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">Yes</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">KYC Templates</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Key Dates & Reminders</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">Yes</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Manage Vendor Users</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Signing Authorities</td>
                            <td class="text-center">Yes</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td><td class="text-center">—</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">My Signatures</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3"><strong>Restricted Contracts:</strong> Even with role-based access, restricted contracts add a secondary check — only explicitly authorized users can see them.</p>
            </div>
        </x-filament::section>

        {{-- Section 15: FAQ --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Frequently Asked Questions</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p><strong>Q: I can't see a menu item I expect to have access to.</strong><br>
                Menu items are shown based on your role. Contact your System Admin if you believe your role needs updating.</p>

                <p><strong>Q: What does the "Code" field mean on Regions / Entities / Projects?</strong><br>
                It's a short internal identifier (e.g. "MENA", "DGT-AE", "PRJ-001") used for CSV uploads, reports, and filters. It doesn't appear on contracts themselves.</p>

                <p><strong>Q: Why can't I edit an executed contract?</strong><br>
                Executed and archived contracts are locked for compliance. To make changes, create an Amendment or Renewal via the contract's action menu.</p>

                <p><strong>Q: How do I create a Merchant Agreement?</strong><br>
                Go to Contracts &gt; Generate Merchant Agreement. Fill in the counterparty, region, entity, and project, then click "Generate Agreement" to create the DOCX from the master template.</p>

                <p><strong>Q: What file formats are accepted for contract uploads?</strong><br>
                PDF and DOCX files only.</p>

                <p><strong>Q: How do restricted/locked contracts work?</strong><br>
                System Admins and Legal users can mark a contract as "Restricted". When restricted, only explicitly authorized users (and System Admins) can see or edit it.</p>

                <p><strong>Q: How do I save my signature for future use?</strong><br>
                After signing a document, check the "Save this signature for future use" checkbox. You can also manage stored signatures on the <strong>My Signatures</strong> page.</p>

                <p><strong>Q: What are the four signature methods?</strong><br>
                Draw (canvas), Type (text rendered as image), Upload (PNG/JPEG file), and Camera (webcam capture with background removal).</p>

                <p><strong>Q: How does page enforcement work during signing?</strong><br>
                When enabled, signers must scroll through every page before submitting. If page initials are required, each page must be individually initialed.</p>

                <p><strong>Q: How do I access the Vendor Portal?</strong><br>
                Vendors receive a magic link via email — no password needed. Contact your CCRS account manager to be added as a vendor user.</p>

                <p><strong>Q: I need help not covered here.</strong><br>
                Contact the CCRS support team at <strong>support@digittal.io</strong> or reach out to your System Administrator.</p>
            </div>
        </x-filament::section>

    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            mermaid.initialize({
                startOnLoad: false,
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'neutral',
                securityLevel: 'loose',
            });

            // Render mermaid diagrams, including those inside collapsed sections
            function renderMermaid() {
                document.querySelectorAll('.mermaid:not([data-processed])').forEach(function (el) {
                    el.setAttribute('data-processed', 'true');
                    mermaid.run({ nodes: [el] });
                });
            }

            renderMermaid();

            // Re-render when collapsed sections are expanded (Filament uses Alpine.js)
            document.addEventListener('click', function () {
                setTimeout(renderMermaid, 300);
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
