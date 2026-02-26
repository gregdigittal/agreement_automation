<x-filament-panels::page>
    <div class="space-y-6">

        <x-filament::section collapsible>
            <x-slot name="heading">Getting Started</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p><strong>CCRS</strong> (Contract & Compliance Review System) is Digittal Group's platform for managing the full contract lifecycle — from drafting through execution and archival.</p>
                <ul>
                    <li><strong>Log in</strong> using your Azure AD (Microsoft) credentials via the login page.</li>
                    <li>After login, you'll land on the <strong>Dashboard</strong> showing recent activity, pending tasks, and key metrics.</li>
                    <li>Use the left-hand navigation to access different sections. Menu items are role-based — you'll only see what your role permits.</li>
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Organisation Structure</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>CCRS organises contracts using a three-level hierarchy:</p>
                <ol>
                    <li><strong>Regions</strong> — Geographic groupings (e.g. MENA, EMEA, APAC). Each has a unique code used in reports and CSV uploads.</li>
                    <li><strong>Entities</strong> — Legal entities within a region (e.g. Digittal AE, Digittal UK). Each entity belongs to one region.</li>
                    <li><strong>Projects</strong> — Business projects within an entity. Each project belongs to one entity.</li>
                </ol>
                <p>The <strong>"Code"</strong> field on Regions, Entities, and Projects is a short internal identifier used for CSV uploads, reports, and filters. For example, a region code might be "MENA" and an entity code "DGT-AE".</p>
                <p>You must create Regions before Entities, and Entities before Projects — each level depends on the one above it.</p>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Contract Lifecycle</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>Every contract moves through a defined workflow:</p>
                <ol>
                    <li><strong>Draft</strong> — Initial creation. Upload or generate the contract document.</li>
                    <li><strong>Review</strong> — Legal and stakeholder review. AI analysis can be triggered at this stage.</li>
                    <li><strong>Approval</strong> — Internal approval by designated approvers.</li>
                    <li><strong>Signing</strong> — External counterparty signs the document.</li>
                    <li><strong>Countersign</strong> — Internal Digittal signers countersign the executed copy.</li>
                    <li><strong>Executed</strong> — Fully signed and in effect. Contract becomes read-only.</li>
                    <li><strong>Archived</strong> — Contract term has ended or replaced. Read-only.</li>
                </ol>
                <p>Workflow templates define which stages apply to each contract type and region. Only published templates are automatically assigned.</p>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Creating a Contract</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <ol>
                    <li>Navigate to <strong>Contracts</strong> in the left menu and click <strong>New Contract</strong>.</li>
                    <li>Select the <strong>Region</strong>, <strong>Entity</strong>, and <strong>Project</strong> this contract belongs to.</li>
                    <li>Choose the <strong>Counterparty</strong> — the external party. If they don't exist yet, create them first under Counterparties.</li>
                    <li>Select the <strong>Contract Type</strong> (Commercial or Merchant). This determines which workflow template is applied.</li>
                    <li>Enter a descriptive <strong>Title</strong> and upload the contract file (PDF or DOCX).</li>
                    <li>Save. The contract will be created in <strong>Draft</strong> state with the appropriate workflow applied.</li>
                </ol>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Workflow Templates</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>Workflow templates define the stages a contract moves through and who approves at each step.</p>
                <ul>
                    <li>Only <strong>System Admins</strong> can create and manage workflow templates.</li>
                    <li>Templates can be scoped to a specific Region, Entity, or Project — or left global.</li>
                    <li>A template must be <strong>Published</strong> to be automatically assigned to new contracts.</li>
                    <li>If no matching template exists, the contract stays in Draft until one is created.</li>
                </ul>
                <p><strong>Tip:</strong> Create your Regions, Entities, and Projects <em>before</em> creating workflow templates, so you can scope them correctly.</p>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Counterparty Management</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <ul>
                    <li><strong>Adding:</strong> Navigate to Counterparties and click New. Enter the legal name and registration number.</li>
                    <li><strong>Duplicate Checking:</strong> Use the "Check for Duplicates" button before saving. The system searches for similar names and registration numbers.</li>
                    <li><strong>Status:</strong> Active, Suspended, or Blacklisted. Suspended/Blacklisted counterparties require an override request to proceed with new contracts.</li>
                    <li><strong>Override Requests:</strong> Commercial users can submit override requests. Legal or System Admins approve or reject them.</li>
                    <li><strong>Merging:</strong> System Admins can merge duplicate counterparties — all contracts are moved to the target record.</li>
                </ul>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Bulk Uploads</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                <p>System Admins can bulk-import data via CSV under <strong>Administration > Bulk Data Upload</strong>.</p>
                <ul>
                    <li>Select the data type (Regions, Entities, Projects, Users, or Counterparties).</li>
                    <li>Click <strong>Download Template</strong> to get a CSV with the correct column headers.</li>
                    <li>Fill in the template and upload it.</li>
                    <li>The system validates each row and reports successes and errors.</li>
                </ul>
                <p><strong>Important:</strong> When importing Entities, use the Region's <em>code</em> (not name) in the <code>region_code</code> column. Similarly for Projects, use the Entity's <em>code</em> in <code>entity_code</code>.</p>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Role Permissions</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
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
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Edit Contracts</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Manage Counterparties</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Approve Overrides</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Org Structure (Regions, Entities, Projects)</td>
                            <td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Workflow Templates</td>
                            <td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Bulk Uploads</td>
                            <td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">Audit Logs</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">Yes</td>
                        </tr>
                        <tr class="border-t">
                            <td class="py-1 pr-4">KYC Templates</td>
                            <td class="text-center">Yes</td><td class="text-center">Yes</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

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
                Go to Contracts > Generate Merchant Agreement. Fill in the counterparty, region, entity, and project, then click "Generate Agreement" to create the DOCX from the master template.</p>

                <p><strong>Q: What file formats are accepted for contract uploads?</strong><br>
                PDF and DOCX files only.</p>

                <p><strong>Q: How do restricted/locked contracts work?</strong><br>
                System Admins and Legal users can mark a contract as "Restricted". When restricted, only explicitly authorized users (and System Admins) can see or edit it. Other users won't see it in their contract list at all.</p>

                <p><strong>Q: I need help not covered here.</strong><br>
                Contact the CCRS support team at <strong>support@digittal.io</strong> or reach out to your System Administrator.</p>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
