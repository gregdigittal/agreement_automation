# Cursor Prompt — CCRS UI/UX Overhaul (Phase 1d)

**Copy everything below this line into Cursor as the prompt.**

---

## Context

The CCRS frontend (`apps/web`) is a Next.js 16 / React 19 app using shadcn/ui + Tailwind CSS. All 17 epics have been scaffolded, but the UI is a developer prototype. This prompt transforms it into a production-quality business application.

**Pre-requisites:** Apply the Code Rectification and Gaps & Quality prompts first. This prompt assumes bugs are fixed and all modules exist.

**Existing shadcn/ui components (already installed):**
Button, Card, Input, Label, Table, Badge, Tabs, Dialog, DropdownMenu

**Missing components that must be generated (Section 1 below):**
Select, Textarea, Checkbox, Skeleton, AlertDialog, Sidebar, Sheet, Separator, Tooltip, Sonner/Toast

After each section, verify: `cd apps/web && npm run build`

---

# SECTION 1: INSTALL DEPENDENCIES AND GENERATE SHADCN COMPONENTS

## 1.1 Install New Dependencies

```bash
cd apps/web
npm install sonner
```

## 1.2 Generate Missing shadcn Components

Run each of these:

```bash
npx shadcn@latest add select
npx shadcn@latest add textarea
npx shadcn@latest add checkbox
npx shadcn@latest add skeleton
npx shadcn@latest add alert-dialog
npx shadcn@latest add sidebar
npx shadcn@latest add sheet
npx shadcn@latest add separator
npx shadcn@latest add tooltip
```

These will create files in `apps/web/src/components/ui/`. Do NOT modify the generated files.

---

# SECTION 2: SIDEBAR NAVIGATION (Replaces Horizontal Nav)

Replace the entire horizontal nav with a collapsible sidebar layout.

## 2.1 Create Sidebar Navigation Component

**Replace** `apps/web/src/components/app-nav.tsx` with a new sidebar-based navigation. Delete the old content entirely. The new component should:

1. Use the shadcn Sidebar component (`SidebarProvider`, `Sidebar`, `SidebarContent`, `SidebarGroup`, `SidebarGroupLabel`, `SidebarGroupContent`, `SidebarMenu`, `SidebarMenuItem`, `SidebarMenuButton`)

2. Import icons from `lucide-react` (already installed). Use these icons for each nav item:
   - Dashboard: `LayoutDashboard`
   - Regions: `Globe`
   - Entities: `Building2`
   - Projects: `FolderKanban`
   - All Contracts: `FileText`
   - Templates: `BookTemplate`
   - Obligations: `ClipboardCheck`
   - Merchant Agreements: `Handshake`
   - Counterparties: `Users`
   - Overrides: `ShieldAlert`
   - Workflow Templates: `GitBranch`
   - Escalations: `AlertTriangle`
   - Audit: `ScrollText`
   - Reports: `BarChart3`
   - Settings: `Settings`

3. Group items logically:

```
[CCRS Logo/Text]

Overview
  - Dashboard (/)

Org Structure
  - Regions (/regions)
  - Entities (/entities)
  - Projects (/projects)

Contracts
  - All Contracts (/contracts)
  - Templates (/wiki-contracts)
  - Obligations (/obligations)
  - Merchant Agreements (/merchant-agreements)

Counterparties
  - All Counterparties (/counterparties)
  - Overrides (/override-requests)

Workflows
  - Templates (/workflows)
  - Escalations (/escalations)

Admin
  - Audit (/audit)
  - Reports (/reports)

[separator]
Settings (/settings)
Sign out
```

4. Highlight active item using `pathname` comparison (same logic as current nav: exact match for `/`, `startsWith` for others). Add `aria-current="page"` on the active item.

5. Add `aria-label="Main navigation"` to the `<nav>` element.

6. The sidebar should be collapsible:
   - On `lg` screens and up: sidebar visible by default, can collapse to icon-only mode
   - On screens below `lg`: sidebar hidden, triggered by a hamburger button (use Sheet component for the mobile drawer)

7. Include a `SidebarTrigger` button in the header area for toggling.

## 2.2 Update Dashboard Layout

**Replace** `apps/web/src/app/(dashboard)/layout.tsx`:

```tsx
import { SidebarProvider, SidebarTrigger, SidebarInset } from '@/components/ui/sidebar';
import { AppSidebar } from '@/components/app-sidebar';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-12 items-center gap-2 border-b px-4">
          <SidebarTrigger />
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
      </SidebarInset>
    </SidebarProvider>
  );
}
```

Note: Rename `app-nav.tsx` to `app-sidebar.tsx` (or create a new file and delete the old one). Export the component as `AppSidebar`.

## 2.3 Update Root Layout for Sidebar CSS

The shadcn Sidebar requires specific CSS. Ensure that `globals.css` includes the sidebar CSS variables (they likely already exist — check for `--sidebar-*` variables). If not, the `npx shadcn@latest add sidebar` command should have added them.

---

# SECTION 3: GLOBAL TOAST SYSTEM

## 3.1 Add Toaster to Root Layout

**File:** `apps/web/src/app/layout.tsx`

Add the Sonner Toaster component:

```tsx
import { Toaster } from 'sonner';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={`${geistSans.variable} ${geistMono.variable} antialiased`}>
        <Providers>{children}</Providers>
        <Toaster richColors position="top-right" />
      </body>
    </html>
  );
}
```

## 3.2 Add Success Toasts to All Mutations

In every file that performs a create/update/delete operation, add `import { toast } from 'sonner'` and call `toast.success(...)` after the operation succeeds.

Here is the complete list of mutations to add toasts to:

### Region CRUD
- `create-region-form.tsx`: After successful create → `toast.success('Region created')`
- `edit-region-form.tsx`: After successful update → `toast.success('Region updated')`
- Region list delete (if exists) → `toast.success('Region deleted')`

### Entity CRUD
- `create-entity-form.tsx`: After successful create → `toast.success('Entity created')`
- `edit-entity-form.tsx`: After successful update → `toast.success('Entity updated')`

### Project CRUD
- `create-project-form.tsx`: After successful create → `toast.success('Project created')`
- `edit-project-form.tsx`: After successful update → `toast.success('Project updated')`

### Counterparty
- `create-counterparty-form.tsx`: After create → `toast.success('Counterparty created')`
- `counterparty-detail-page.tsx`:
  - After edit save → `toast.success('Counterparty updated')`
  - After status change → `toast.success('Status changed to ' + newStatus)`
  - After add contact → `toast.success('Contact added')`
  - After delete contact → `toast.success('Contact removed')`
  - After override request → `toast.success('Override request submitted')`

### Contracts
- `upload-contract-form.tsx`: After upload → `toast.success('Contract uploaded')`
- `contract-detail.tsx`:
  - After workflow action (approve/reject/rework) → `toast.success('Stage ' + action + 'd')`
  - After create linked doc → `toast.success('Amendment created')`
  - After create renewal → `toast.success('Renewal created')`
  - After create side letter → `toast.success('Side letter created')`
  - After create key date → `toast.success('Key date added')`
  - After delete key date → `toast.success('Key date removed')`
  - After verify key date → `toast.success('Key date verified')`
  - After toggle reminder → `toast.success('Reminder updated')`
  - After trigger AI analysis → `toast.success('Analysis started')`
  - After verify field → `toast.success('Field verified')`
  - After correct field → `toast.success('Field updated')`
  - After upload language version → `toast.success('Language version uploaded')`
  - After delete language → `toast.success('Language version removed')`

### Wiki Contracts
- `wiki-contract-detail.tsx`:
  - After save → `toast.success('Template saved')`
  - After publish → `toast.success('Template published')`
  - After file upload → `toast.success('File uploaded')`

### Workflows
- `workflow-builder.tsx`:
  - After save → `toast.success('Workflow template saved')`
  - After publish → `toast.success('Workflow template published')`
  - After AI generate → `toast.success('Workflow generated')`

### Escalations
- `escalations/page.tsx`: After resolve → `toast.success('Escalation resolved')`

### Obligations
- `obligations/page.tsx`: After status change → `toast.success('Obligation status updated')`

### Override Requests
- `override-requests/page.tsx`: After decide → `toast.success('Override request ' + decision)`

## 3.3 Replace Error Display with Toast Errors

For every `setError(await res.text())` pattern across the app, replace with:

```tsx
if (!res.ok) {
  const errorText = await res.text();
  let message = 'An error occurred';
  try {
    const parsed = JSON.parse(errorText);
    message = parsed.detail || parsed.message || errorText;
  } catch {
    message = errorText;
  }
  toast.error(message);
  return;
}
```

Create a shared helper for this at `apps/web/src/lib/api-error.ts`:

```tsx
import { toast } from 'sonner';

export async function handleApiError(res: Response): Promise<boolean> {
  if (res.ok) return false;
  const text = await res.text();
  let message = 'An error occurred';
  try {
    const parsed = JSON.parse(text);
    message = parsed.detail || parsed.message || text;
  } catch {
    message = text;
  }
  toast.error(message);
  return true;
}
```

Usage in forms:
```tsx
import { handleApiError } from '@/lib/api-error';

const res = await fetch('/api/ccrs/regions', { ... });
if (await handleApiError(res)) return;
const data = await res.json();
toast.success('Region created');
```

Remove all local `error` state variables and their `<p className="text-destructive">` displays that are replaced by toast errors. Keep `error` state ONLY for inline validation that must appear near a specific field.

---

# SECTION 4: CONFIRMATION DIALOGS

## 4.1 Create Reusable Confirmation Dialog

**Create** `apps/web/src/components/confirm-dialog.tsx`:

```tsx
'use client';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

interface ConfirmDialogProps {
  trigger: React.ReactNode;
  title: string;
  description: string;
  confirmLabel?: string;
  variant?: 'default' | 'destructive';
  onConfirm: () => void | Promise<void>;
}

export function ConfirmDialog({
  trigger,
  title,
  description,
  confirmLabel = 'Confirm',
  variant = 'default',
  onConfirm,
}: ConfirmDialogProps) {
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction
            onClick={onConfirm}
            className={variant === 'destructive' ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : ''}
          >
            {confirmLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
```

## 4.2 Replace All `window.confirm()` Calls

Find every `window.confirm(` in the codebase and replace with `<ConfirmDialog>`.

**`counterparty-detail-page.tsx`** — contact delete:
```tsx
// Before: if (!window.confirm('Remove this contact?')) return;
// After:
<ConfirmDialog
  trigger={<Button variant="ghost" size="sm">Remove</Button>}
  title="Remove contact"
  description="This will permanently remove this contact from the counterparty."
  confirmLabel="Remove"
  variant="destructive"
  onConfirm={() => removeContact(contact.id)}
/>
```

**`escalations/page.tsx`** — resolve escalation:
```tsx
<ConfirmDialog
  trigger={<Button size="sm">Resolve</Button>}
  title="Resolve escalation"
  description="This will mark the escalation as resolved."
  confirmLabel="Resolve"
  onConfirm={() => resolveEscalation(esc.id)}
/>
```

## 4.3 Add Confirmations to Workflow Actions

**`contract-detail.tsx`** — workflow stage actions:

For Reject and Rework actions specifically, the confirmation dialog should include a mandatory comment textarea:

```tsx
// For Approve: simple confirm
<ConfirmDialog
  trigger={<Button size="sm">Approve</Button>}
  title="Approve this stage"
  description={`Approve the "${currentStage}" stage and advance the workflow.`}
  confirmLabel="Approve"
  onConfirm={() => submitAction('approve')}
/>

// For Reject/Rework: use a custom Dialog with comment field
// (since AlertDialog doesn't support form inputs)
```

For Reject and Rework, use a `Dialog` (not AlertDialog) with:
- Title: "Reject stage" or "Request rework"
- Description explaining what will happen
- Mandatory comment `<Textarea>` field
- Cancel and Confirm buttons

---

# SECTION 5: REPLACE ALL RAW HTML FORM ELEMENTS

## 5.1 Replace All Raw `<select>` with shadcn Select

Search the entire `apps/web/src` directory for `<select` elements. There are approximately 20 instances. Replace each with the shadcn Select component.

**Pattern to replace:**
```tsx
// OLD:
<select
  className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
  value={value}
  onChange={(e) => setValue(e.target.value)}
>
  <option value="">Select...</option>
  {items.map(i => <option key={i.id} value={i.id}>{i.name}</option>)}
</select>

// NEW:
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

<Select value={value} onValueChange={setValue}>
  <SelectTrigger>
    <SelectValue placeholder="Select..." />
  </SelectTrigger>
  <SelectContent>
    {items.map(i => (
      <SelectItem key={i.id} value={i.id}>{i.name}</SelectItem>
    ))}
  </SelectContent>
</Select>
```

**Files to update (all raw `<select>` instances):**
1. `contracts/contracts-list.tsx` — 5 filter selects (region, entity, project, counterparty, type)
2. `contracts/upload-contract-form.tsx` — 5 selects (region, entity, project, counterparty, type)
3. `contracts/contract-detail.tsx` — workflow template select, reminder type, reminder channel, linked doc type, renewal type
4. `counterparties/counterparty-detail-page.tsx` — status change select
5. `entities/create-entity-form.tsx` — region select
6. `projects/create-project-form.tsx` — entity select
7. `merchant-agreements/page.tsx` — template, region, entity, project, counterparty selects
8. `obligations/page.tsx` — status filter, type filter, inline status change per row
9. `wiki-contracts/new/page.tsx` — region select
10. `workflows/workflow-builder.tsx` — stage type select

**Important:** The shadcn Select uses `onValueChange` (not `onChange`) and does NOT use `event.target.value`. The value is passed directly to the callback.

## 5.2 Replace All Raw `<textarea>` with shadcn Textarea

**Files:**
- `counterparty-detail-page.tsx` — status reason textarea
- `merchant-agreements/page.tsx` — region terms textarea (if it should be textarea instead of input)

```tsx
import { Textarea } from '@/components/ui/textarea';

// Replace: <textarea className="..." value={x} onChange={...} />
// With:    <Textarea value={x} onChange={(e) => setX(e.target.value)} />
```

## 5.3 Replace All Raw `<input type="checkbox">` with shadcn Checkbox

**Files:**
- `counterparty-detail-page.tsx` — contact "is signer" checkbox
- `contract-detail.tsx` — language "is primary" checkbox

```tsx
import { Checkbox } from '@/components/ui/checkbox';

// Replace: <input type="checkbox" checked={x} onChange={(e) => setX(e.target.checked)} />
// With:    <Checkbox checked={x} onCheckedChange={setX} />
```

Ensure the Checkbox has a proper `<Label>` associated via `htmlFor`/`id`.

---

# SECTION 6: LOADING SKELETONS

## 6.1 Replace All "Loading..." Text

Search for `Loading...` across the frontend. Replace each instance with a Skeleton layout that matches the final page shape.

**For list/table pages** (contracts, counterparties, workflows, wiki-contracts, obligations, escalations, audit):

```tsx
import { Skeleton } from '@/components/ui/skeleton';

// Replace: if (loading) return <p>Loading...</p>;
// With:
if (loading) return (
  <div className="space-y-4">
    <Skeleton className="h-10 w-full" />
    <Skeleton className="h-10 w-full" />
    <Skeleton className="h-10 w-full" />
    <Skeleton className="h-10 w-full" />
    <Skeleton className="h-10 w-full" />
  </div>
);
```

**For detail pages** (contract detail, counterparty detail, wiki-contract detail):

```tsx
if (loading) return (
  <div className="space-y-6">
    <Skeleton className="h-8 w-1/3" />
    <div className="grid gap-4 md:grid-cols-2">
      <Skeleton className="h-32 w-full" />
      <Skeleton className="h-32 w-full" />
    </div>
    <Skeleton className="h-64 w-full" />
  </div>
);
```

**For form pages** (create region, create entity, etc.): Keep the card visible and show skeletons inside it for pre-filled form data.

**Important:** On list pages, keep the header/title and filter controls visible during loading. Only replace the table/grid data area with skeletons.

---

# SECTION 7: FORM CONSISTENCY

## 7.1 Add Required Field Indicators

In every form across the app, add `*` after labels for required fields. Use this pattern:

```tsx
<Label htmlFor="name">
  Name <span className="text-destructive">*</span>
</Label>
```

**Forms to update:**
- `create-region-form.tsx` — Name (required)
- `create-entity-form.tsx` — Name (required), Region (required)
- `create-project-form.tsx` — Name (required), Entity (required)
- `create-counterparty-form.tsx` — Legal Name (required)
- `upload-contract-form.tsx` — Region, Entity, Project, Counterparty, Contract Type (all required)
- `counterparty-detail-page.tsx` — Status (required), Reason (required)
- `wiki-contracts/new/page.tsx` — Name (required), Category (required)
- `workflow-builder.tsx` — Template Name (required)

## 7.2 Add Cancel/Back Buttons to All Forms

Every create and edit form should have a Cancel button that navigates back to the list page:

```tsx
import { useRouter } from 'next/navigation';

const router = useRouter();

// In the form footer, next to the Submit button:
<div className="flex gap-2 justify-end">
  <Button type="button" variant="outline" onClick={() => router.back()}>
    Cancel
  </Button>
  <Button type="submit" disabled={submitting}>
    {submitting ? 'Saving...' : 'Save'}
  </Button>
</div>
```

**Forms to update:** create-region, edit-region, create-entity, edit-entity, create-project, edit-project, create-counterparty, upload-contract, wiki-contract new, workflow builder.

## 7.3 Wrap Non-Form Pages in `<form>`

Several pages use `onClick` handlers on buttons instead of `<form onSubmit>`. This breaks HTML validation (`required` attributes) and Enter-to-submit.

**Files to wrap in `<form onSubmit={handleSubmit}>`:**
- `counterparty-detail-page.tsx` — the status change section should be its own `<form>`
- `wiki-contracts/[id]/wiki-contract-detail.tsx` — the edit fields should be in a `<form>`
- `workflows/workflow-builder.tsx` — the template name and save section

---

# SECTION 8: REAL DASHBOARD

**Replace** `apps/web/src/app/(dashboard)/page.tsx` entirely.

The new dashboard should be a server component that fetches summary data and passes it to client sub-components.

### 8.1 Dashboard Layout

```
┌─────────────────────────────────────────────────┐
│  Welcome back, {user.name}              {date}  │
├───────────┬───────────┬───────────┬─────────────┤
│  47       │  12       │  3        │  5          │
│  Total    │  Pending  │  Expiring │  Active     │
│  Contracts│  Approval │  <30 days │  Escalations│
├───────────┴───────────┴───────────┴─────────────┤
│                                                  │
│  [Expiry Timeline Chart]   [Contract Status Pie] │
│                                                  │
├─────────────────────┬────────────────────────────┤
│  My Pending Actions │  Recent Activity           │
│  (workflow stages   │  (latest audit log entries) │
│   requiring my      │                            │
│   action)           │                            │
└─────────────────────┴────────────────────────────┘
```

### 8.2 Implementation

Create `apps/web/src/app/(dashboard)/dashboard-content.tsx` as a `'use client'` component:

```tsx
'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import Link from 'next/link';
import { FileText, Clock, AlertTriangle, CheckCircle } from 'lucide-react';

interface DashboardData {
  contractStatus: { state: string; count: number }[];
  expiryHorizon: { window: string; count: number }[];
  escalationCount: number;
  recentAudit: { action: string; resource_type: string; actor_email: string; created_at: string }[];
  notificationCount: number;
}

export function DashboardContent({ userName }: { userName: string }) {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch('/api/ccrs/reports/contract-status').then(r => r.ok ? r.json() : []),
      fetch('/api/ccrs/reports/expiry-horizon').then(r => r.ok ? r.json() : []),
      fetch('/api/ccrs/escalations/active').then(r => r.ok ? r.json() : []),
      fetch('/api/ccrs/audit?limit=10').then(r => r.ok ? r.json() : []),
      fetch('/api/ccrs/notifications/unread-count').then(r => r.ok ? r.json() : { count: 0 }),
    ]).then(([status, expiry, escalations, audit, notifs]) => {
      const totalContracts = Array.isArray(status)
        ? status.reduce((sum: number, s: { count: number }) => sum + s.count, 0)
        : 0;
      const pendingApproval = Array.isArray(status)
        ? status.filter((s: { state: string }) => ['review', 'approval', 'legal_review'].includes(s.state)).reduce((sum: number, s: { count: number }) => sum + s.count, 0)
        : 0;
      const expiringSoon = Array.isArray(expiry)
        ? expiry.filter((e: { window: string }) => e.window === '30_days').reduce((sum: number, e: { count: number }) => sum + e.count, 0)
        : 0;

      setData({
        contractStatus: status,
        expiryHorizon: expiry,
        escalationCount: Array.isArray(escalations) ? escalations.length : 0,
        recentAudit: audit,
        notificationCount: notifs.count ?? 0,
      });
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-64" />
          <Skeleton className="h-64" />
        </div>
      </div>
    );
  }

  // Calculate KPI values from data
  const totalContracts = data?.contractStatus?.reduce((sum, s) => sum + s.count, 0) ?? 0;
  const pendingApproval = data?.contractStatus?.filter(s => ['review', 'approval', 'legal_review'].includes(s.state)).reduce((sum, s) => sum + s.count, 0) ?? 0;
  const expiringSoon = data?.expiryHorizon?.filter(e => e.window === '30_days').reduce((sum, e) => sum + e.count, 0) ?? 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Welcome back, {userName}</h1>
          <p className="text-muted-foreground">{today}</p>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Contracts</CardTitle>
            <FileText className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{totalContracts}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Pending Approval</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{pendingApproval}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Expiring &lt;30 Days</CardTitle>
            <AlertTriangle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{expiringSoon}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Active Escalations</CardTitle>
            <AlertTriangle className="h-4 w-4 text-destructive" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{data?.escalationCount ?? 0}</div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {(data?.recentAudit ?? []).slice(0, 8).map((entry, i) => (
                <div key={i} className="flex items-center justify-between text-sm">
                  <div>
                    <span className="font-medium">{entry.action.replace(/_/g, ' ')}</span>
                    <span className="text-muted-foreground"> on {entry.resource_type}</span>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {new Date(entry.created_at).toLocaleString()}
                  </span>
                </div>
              ))}
              {(!data?.recentAudit || data.recentAudit.length === 0) && (
                <p className="text-sm text-muted-foreground">No recent activity</p>
              )}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Quick Links</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-2">
              <Link href="/contracts/upload" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <FileText className="h-4 w-4" /> Upload a contract
              </Link>
              <Link href="/counterparties/new" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> Add a counterparty
              </Link>
              <Link href="/workflows" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> Manage workflows
              </Link>
              <Link href="/reports" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> View reports
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
```

Update `apps/web/src/app/(dashboard)/page.tsx`:

```tsx
import { auth } from '@/auth';
import { DashboardContent } from './dashboard-content';

export default async function DashboardPage() {
  const session = await auth();
  const userName = session?.user?.name || session?.user?.email || 'User';
  return <DashboardContent userName={userName} />;
}
```

---

# SECTION 9: ACCESSIBILITY FIXES

## 9.1 Skip-to-Content Link

**File:** `apps/web/src/app/layout.tsx`

Add as the first element inside `<body>`:

```tsx
<a
  href="#main-content"
  className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:p-4 focus:bg-background focus:text-foreground"
>
  Skip to content
</a>
```

Then in the dashboard layout, add `id="main-content"` to the `<main>` element.

## 9.2 Chart Accessibility

**File:** `apps/web/src/app/(dashboard)/reports/page.tsx`

Wrap each chart in a container with `role="img"` and an `aria-label`:

```tsx
<div role="img" aria-label="Contract status breakdown: 20 draft, 15 in review, 12 executed">
  <PieChart ...>
</div>
```

Generate the `aria-label` dynamically from the data.

## 9.3 Confidence Bar Labels

**File:** `apps/web/src/app/(dashboard)/contracts/contract-detail.tsx`

For confidence progress bars, add text and aria attributes:

```tsx
<div
  className="h-2 rounded bg-muted overflow-hidden"
  role="progressbar"
  aria-valuenow={Math.round(field.confidence * 100)}
  aria-valuemin={0}
  aria-valuemax={100}
  aria-label={`Confidence: ${Math.round(field.confidence * 100)}%`}
>
  <div className="h-full bg-primary" style={{ width: `${field.confidence * 100}%` }} />
</div>
<span className="text-xs text-muted-foreground">{Math.round(field.confidence * 100)}%</span>
```

---

# Completion Checklist

After all changes:
1. [ ] `cd apps/web && npm run build` — no errors
2. [ ] Sidebar navigation renders with grouped icons on desktop
3. [ ] Sidebar collapses to hamburger on mobile-width viewport
4. [ ] Success toasts appear for all create/update/delete operations
5. [ ] Error toasts show user-friendly messages (not raw JSON/HTML)
6. [ ] All `window.confirm()` replaced with styled AlertDialog
7. [ ] No raw `<select>`, `<textarea>`, or `<input type="checkbox">` remain in the codebase
8. [ ] All loading states show Skeleton components instead of text
9. [ ] All required fields marked with `*`
10. [ ] All forms have Cancel/Back button
11. [ ] Dashboard shows KPIs, recent activity, and quick links
12. [ ] Skip-to-content link is first focusable element
13. [ ] Charts have `aria-label` descriptions
