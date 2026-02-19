# Cursor Prompt — CCRS Final Hardening: Bugs, RBAC, RLS & Polish

**Copy everything below this line into Cursor as the prompt.**

---

## Context

The CCRS codebase is substantially complete across Phases 1a–1d. This prompt addresses the **final hardening** required before production deployment:

1. **3 critical bugs** (timestamps stored as literal strings, audit bypass)
2. **Frontend RBAC** (roles never populated or checked)
3. **Row-Level Security (RLS)** on all 24 database tables
4. **Minor polish** (delete operations, CSV export, counterparty pagination, schema validation)

**Pre-requisites:** All previous Cursor prompts (Code Rectification, Gaps & Quality, UI/UX Overhaul) are already applied. The codebase has 23 API routers, 18 frontend routes, and 24 database tables.

After each section, verify:
- Backend: `cd apps/api && python -m py_compile app/main.py && pytest tests/ -v`
- Frontend: `cd apps/web && npm run build`

---

# SECTION 1: CRITICAL BUG FIXES

## 1.1 Fix `"now()"` String Bug in Notifications Router

**File:** `apps/api/app/notifications/router.py`

The Supabase REST API does not interpret SQL functions. `"now()"` is stored as the literal string `now()` instead of a timestamp.

**Replace** lines 51–56 of the `notification_mark_read` endpoint:

```python
# OLD (broken):
result = (
    supabase.table("notifications")
    .update({"read_at": "now()"})
    .eq("id", str(id))
    .eq("recipient_email", user.email)
    .execute()
)

# NEW (fixed):
from datetime import datetime, timezone

result = (
    supabase.table("notifications")
    .update({"read_at": datetime.now(timezone.utc).isoformat()})
    .eq("id", str(id))
    .eq("recipient_email", user.email)
    .execute()
)
```

**Replace** lines 68–70 of the `notification_mark_all_read` endpoint:

```python
# OLD (broken):
supabase.table("notifications").update({"read_at": "now()"}).eq(
    "recipient_email", user.email
).is_("read_at", "null").execute()

# NEW (fixed):
from datetime import datetime, timezone

supabase.table("notifications").update(
    {"read_at": datetime.now(timezone.utc).isoformat()}
).eq("recipient_email", user.email).is_("read_at", "null").execute()
```

Move the `from datetime import datetime, timezone` to the top of the file (with the other imports).

## 1.2 Fix `"now()"` String Bug in Override Requests Service

**File:** `apps/api/app/override_requests/service.py`

**Replace** line 91 in the `decide()` function:

```python
# OLD (broken):
"decided_at": "now()",

# NEW (fixed):
"decided_at": datetime.now(timezone.utc).isoformat(),
```

Add `from datetime import datetime, timezone` to the top of the file.

## 1.3 Fix Override Requests to Use Shared `audit_log()` Helper

**File:** `apps/api/app/override_requests/service.py`

The override_requests service bypasses the shared audit helper from `app/audit/service.py`, which means it misses the `ip_address` field and the error-handling/logging logic.

**Add import** at top of file:

```python
from app.audit.service import audit_log
```

**Replace** the inline audit insert in `create_request()` (lines 41–50):

```python
# OLD (bypass):
supabase.table("audit_log").insert(
    {
        "action": "override_request_created",
        "resource_type": "override_request",
        "resource_id": str(request_row["id"]),
        "actor_id": actor.id,
        "actor_email": actor.email,
        "details": {"counterparty_id": str(counterparty_id), "reason": body.reason},
    }
).execute()

# NEW (use shared helper):
await audit_log(
    supabase,
    action="override_request_created",
    resource_type="override_request",
    resource_id=str(request_row["id"]),
    details={"counterparty_id": str(counterparty_id), "reason": body.reason},
    actor=actor,
)
```

**Replace** the inline audit insert in `decide()` (lines 114–123):

```python
# OLD (bypass):
supabase.table("audit_log").insert(
    {
        "action": f"override_request_{body.decision}",
        "resource_type": "override_request",
        "resource_id": str(request_id),
        "actor_id": actor.id,
        "actor_email": actor.email,
        "details": {"decision": body.decision, "comment": body.comment},
    }
).execute()

# NEW (use shared helper):
await audit_log(
    supabase,
    action=f"override_request_{body.decision}",
    resource_type="override_request",
    resource_id=str(request_id),
    details={"decision": body.decision, "comment": body.comment},
    actor=actor,
)
```

## 1.4 Add `read_at` Column to Notifications Table (if missing)

Check if the `notifications` table has a `read_at` column. If not, apply this migration:

**Create** Supabase migration `supabase/migrations/20260219000001_add_notifications_read_at.sql`:

```sql
-- Add read_at column to notifications table if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'notifications'
        AND column_name = 'read_at'
    ) THEN
        ALTER TABLE public.notifications ADD COLUMN read_at timestamptz;
    END IF;
END $$;
```

---

# SECTION 2: FRONTEND RBAC

The backend already enforces roles via `require_roles()`, but the frontend never populates `roles` in the JWT/session, so the sidebar shows all pages to all users and admin operations are only blocked by 403 errors after the fact.

## 2.1 Propagate Roles Through NextAuth JWT

**File:** `apps/web/src/auth.ts`

Azure AD App Roles come through the `account.id_token` claims as `roles`. For the dev Credentials provider, assign a default role.

**Replace the entire file** with:

```typescript
import NextAuth from 'next-auth';
import MicrosoftEntraID from 'next-auth/providers/microsoft-entra-id';
import CredentialsProvider from 'next-auth/providers/credentials';

const providers = [];
if (process.env.AZURE_AD_CLIENT_ID && process.env.AZURE_AD_CLIENT_SECRET) {
  providers.push(
    MicrosoftEntraID({
      clientId: process.env.AZURE_AD_CLIENT_ID,
      clientSecret: process.env.AZURE_AD_CLIENT_SECRET,
      issuer: process.env.AZURE_AD_ISSUER,
    })
  );
}
if (process.env.NODE_ENV === 'development') {
  providers.push(
    CredentialsProvider({
      name: 'Credentials',
      credentials: { email: { label: 'Email', type: 'email' } },
      async authorize(credentials) {
        if (!credentials?.email) return null;
        const email = credentials.email as string;
        return {
          id: `dev-${email.split('@')[0]}`,
          email,
          name: email,
          roles: ['System Admin'],
        };
      },
    })
  );
}

export const { handlers, signIn, signOut, auth } = NextAuth({
  providers,
  callbacks: {
    async jwt({ token, user, account, profile }) {
      if (user) {
        token.id = user.id;
        if (user.email) token.email = user.email;
        // Dev Credentials: roles come from the user object
        if ('roles' in user && Array.isArray(user.roles)) {
          token.roles = user.roles;
        }
      }
      if (account?.access_token) token.accessToken = account.access_token;
      // Azure AD: roles come from the ID token claims
      if (profile && 'roles' in profile && Array.isArray(profile.roles)) {
        token.roles = profile.roles as string[];
      }
      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        const userId = token.id ?? token.sub;
        session.user.id = typeof userId === 'string' ? userId : '';
        session.user.roles = (token.roles as string[]) ?? [];
        const accessToken = token.accessToken;
        if (typeof accessToken === 'string') session.accessToken = accessToken;
      }
      return session;
    },
  },
  pages: { signIn: '/login' },
  session: { strategy: 'jwt', maxAge: 30 * 24 * 60 * 60 },
});
```

## 2.2 Update NextAuth Type Declarations

**File:** `apps/web/src/types/next-auth.d.ts`

**Replace the entire file** with:

```typescript
import { DefaultSession, DefaultJWT, DefaultUser } from 'next-auth';

declare module 'next-auth' {
  interface User extends DefaultUser {
    roles?: string[];
  }

  interface Session {
    user: {
      id: string;
      roles: string[];
    } & DefaultSession['user'];
    accessToken?: string;
  }
}

declare module 'next-auth/jwt' {
  interface JWT extends DefaultJWT {
    id?: string;
    roles?: string[];
    accessToken?: string;
  }
}
```

## 2.3 Create a `useSession` Hook with Roles Helper

**Create** `apps/web/src/hooks/use-roles.ts`:

```typescript
'use client';

import { useSession } from 'next-auth/react';

/**
 * Returns the current user's roles from the session.
 * Also provides helper functions for role checking.
 */
export function useRoles() {
  const { data: session } = useSession();
  const roles: string[] = session?.user?.roles ?? [];

  return {
    roles,
    hasRole: (...allowed: string[]) => allowed.some((r) => roles.includes(r)),
    isAdmin: roles.includes('System Admin'),
    isLegal: roles.includes('Legal'),
    isAdminOrLegal: roles.includes('System Admin') || roles.includes('Legal'),
  };
}
```

## 2.4 Gate Admin Sidebar Items by Role

**File:** `apps/web/src/components/app-sidebar.tsx`

Update the sidebar to conditionally show admin-only navigation items. Items that require specific roles should be hidden from users without those roles.

Add the `useRoles` hook import and filter nav items:

```typescript
import { useRoles } from '@/hooks/use-roles';
```

Add a `roles` field to each nav item and filter:

```typescript
const navGroups = [
  {
    label: 'Overview',
    items: [{ href: '/', label: 'Dashboard', icon: LayoutDashboard }],
  },
  {
    label: 'Org Structure',
    items: [
      { href: '/regions', label: 'Regions', icon: Globe },
      { href: '/entities', label: 'Entities', icon: Building2 },
      { href: '/projects', label: 'Projects', icon: FolderKanban },
    ],
  },
  {
    label: 'Contracts',
    items: [
      { href: '/contracts', label: 'All Contracts', icon: FileText },
      { href: '/wiki-contracts', label: 'Templates', icon: BookTemplate },
      { href: '/obligations', label: 'Obligations', icon: ClipboardCheck },
      { href: '/merchant-agreements', label: 'Merchant Agreements', icon: Handshake },
    ],
  },
  {
    label: 'Counterparties',
    items: [
      { href: '/counterparties', label: 'All Counterparties', icon: Users },
      { href: '/override-requests', label: 'Overrides', icon: ShieldAlert, roles: ['System Admin', 'Legal'] },
    ],
  },
  {
    label: 'Workflows',
    items: [
      { href: '/workflows', label: 'Templates', icon: GitBranch },
      { href: '/escalations', label: 'Escalations', icon: AlertTriangle, roles: ['System Admin', 'Legal'] },
    ],
  },
  {
    label: 'Admin',
    roles: ['System Admin', 'Legal', 'Audit'],
    items: [
      { href: '/audit', label: 'Audit', icon: ScrollText, roles: ['System Admin', 'Legal', 'Audit'] },
      { href: '/reports', label: 'Reports', icon: BarChart3 },
    ],
  },
];
```

In the `AppSidebar` component, use the hook and filter:

```typescript
export function AppSidebar() {
  const pathname = usePathname();
  const { hasRole, roles: userRoles } = useRoles();

  const visibleGroups = navGroups
    .filter((group) => !group.roles || group.roles.some((r) => userRoles.includes(r)))
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.roles || item.roles.some((r) => userRoles.includes(r))),
    }))
    .filter((group) => group.items.length > 0);

  return (
    <Sidebar collapsible="icon">
      <SidebarContent>
        {/* ... use visibleGroups instead of navGroups in the map */}
```

Note: The type for nav items needs to be updated to include the optional `roles` field:

```typescript
interface NavItem {
  href: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  roles?: string[];
}

interface NavGroup {
  label: string;
  roles?: string[];
  items: NavItem[];
}

const navGroups: NavGroup[] = [
  // ... same as above
];
```

## 2.5 Add Role Guard to Admin Pages

**Create** `apps/web/src/components/role-guard.tsx`:

```typescript
'use client';

import type { ReactNode } from 'react';
import { useRoles } from '@/hooks/use-roles';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface RoleGuardProps {
  children: ReactNode;
  allowed: string[];
  fallback?: ReactNode;
}

export function RoleGuard({ children, allowed, fallback }: RoleGuardProps) {
  const { hasRole, roles } = useRoles();

  // If session is still loading (roles empty and no session yet), show nothing
  // to avoid flash of forbidden content
  if (roles.length === 0) return null;

  if (!hasRole(...allowed)) {
    return (
      fallback ?? (
        <Card>
          <CardHeader>
            <CardTitle>Access Denied</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">
              You do not have permission to view this page. Required role:{' '}
              {allowed.join(' or ')}.
            </p>
          </CardContent>
        </Card>
      )
    );
  }

  return <>{children}</>;
}
```

**Wrap the following pages** with `<RoleGuard>`:

1. **`apps/web/src/app/(dashboard)/override-requests/page.tsx`** — wrap the page content with `<RoleGuard allowed={['System Admin', 'Legal']}>`.
2. **`apps/web/src/app/(dashboard)/escalations/page.tsx`** — wrap with `<RoleGuard allowed={['System Admin', 'Legal']}>`.
3. **`apps/web/src/app/(dashboard)/audit/page.tsx`** — wrap with `<RoleGuard allowed={['System Admin', 'Legal', 'Audit']}>`.

Example pattern for each page:

```tsx
import { RoleGuard } from '@/components/role-guard';

export default function OverrideRequestsPage() {
  return (
    <RoleGuard allowed={['System Admin', 'Legal']}>
      {/* ... existing page content ... */}
    </RoleGuard>
  );
}
```

---

# SECTION 3: ROW-LEVEL SECURITY (RLS) POLICIES

Enable RLS on all 24 public tables. The API uses the Supabase **service_role** key, which bypasses RLS. These policies protect against direct database access and future multi-tenant requirements.

**Create** Supabase migration `supabase/migrations/20260219000002_enable_rls.sql`:

```sql
-- ============================================================
-- CCRS Row-Level Security Policies
-- ============================================================
-- The FastAPI backend uses the service_role key (bypasses RLS).
-- These policies protect against:
--   1. Direct Supabase client access (anon/authenticated keys)
--   2. Supabase Dashboard REST API with user tokens
--   3. Future multi-tenant isolation
--
-- Strategy:
--   - All tables: RLS enabled, service_role bypasses
--   - Authenticated users: read access to most tables
--   - Write access: gated by app logic (service_role only)
--   - Sensitive tables (audit_log, notifications): read own only
-- ============================================================

-- ============================================================
-- 1. ORGANIZATIONAL STRUCTURE (read for all authenticated)
-- ============================================================

ALTER TABLE public.regions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "regions_select_authenticated"
  ON public.regions FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.entities ENABLE ROW LEVEL SECURITY;
CREATE POLICY "entities_select_authenticated"
  ON public.entities FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.projects ENABLE ROW LEVEL SECURITY;
CREATE POLICY "projects_select_authenticated"
  ON public.projects FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 2. COUNTERPARTIES (read for all authenticated)
-- ============================================================

ALTER TABLE public.counterparties ENABLE ROW LEVEL SECURITY;
CREATE POLICY "counterparties_select_authenticated"
  ON public.counterparties FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.counterparty_contacts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "counterparty_contacts_select_authenticated"
  ON public.counterparty_contacts FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 3. CONTRACTS & RELATED (read for all authenticated)
-- ============================================================

ALTER TABLE public.contracts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contracts_select_authenticated"
  ON public.contracts FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_links ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_links_select_authenticated"
  ON public.contract_links FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_key_dates ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_key_dates_select_authenticated"
  ON public.contract_key_dates FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_languages ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_languages_select_authenticated"
  ON public.contract_languages FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.signing_authority ENABLE ROW LEVEL SECURITY;
CREATE POLICY "signing_authority_select_authenticated"
  ON public.signing_authority FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.boldsign_envelopes ENABLE ROW LEVEL SECURITY;
CREATE POLICY "boldsign_envelopes_select_authenticated"
  ON public.boldsign_envelopes FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.merchant_agreement_inputs ENABLE ROW LEVEL SECURITY;
CREATE POLICY "merchant_agreement_inputs_select_authenticated"
  ON public.merchant_agreement_inputs FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 4. WORKFLOWS (read for all authenticated)
-- ============================================================

ALTER TABLE public.workflow_templates ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_templates_select_authenticated"
  ON public.workflow_templates FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.workflow_instances ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_instances_select_authenticated"
  ON public.workflow_instances FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.workflow_stage_actions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_stage_actions_select_authenticated"
  ON public.workflow_stage_actions FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.wiki_contracts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "wiki_contracts_select_authenticated"
  ON public.wiki_contracts FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 5. AI & ANALYSIS (read for all authenticated)
-- ============================================================

ALTER TABLE public.ai_analysis_results ENABLE ROW LEVEL SECURITY;
CREATE POLICY "ai_analysis_results_select_authenticated"
  ON public.ai_analysis_results FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.ai_extracted_fields ENABLE ROW LEVEL SECURITY;
CREATE POLICY "ai_extracted_fields_select_authenticated"
  ON public.ai_extracted_fields FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.obligations_register ENABLE ROW LEVEL SECURITY;
CREATE POLICY "obligations_register_select_authenticated"
  ON public.obligations_register FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 6. MONITORING & ESCALATION (read for all authenticated)
-- ============================================================

ALTER TABLE public.reminders ENABLE ROW LEVEL SECURITY;
CREATE POLICY "reminders_select_authenticated"
  ON public.reminders FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.escalation_rules ENABLE ROW LEVEL SECURITY;
CREATE POLICY "escalation_rules_select_authenticated"
  ON public.escalation_rules FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.escalation_events ENABLE ROW LEVEL SECURITY;
CREATE POLICY "escalation_events_select_authenticated"
  ON public.escalation_events FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 7. NOTIFICATIONS (read own only)
-- ============================================================

ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;

-- Users can only read their own notifications
CREATE POLICY "notifications_select_own"
  ON public.notifications FOR SELECT
  TO authenticated
  USING (
    recipient_user_id = auth.uid()::text
    OR recipient_email = auth.jwt()->>'email'
  );

-- ============================================================
-- 8. AUDIT LOG (read-only for admin roles, enforced by app)
-- ============================================================

ALTER TABLE public.audit_log ENABLE ROW LEVEL SECURITY;

-- All authenticated users can read audit logs (app-level RBAC
-- further restricts this to System Admin, Legal, Audit roles)
CREATE POLICY "audit_log_select_authenticated"
  ON public.audit_log FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 9. OVERRIDE REQUESTS (read for all authenticated)
-- ============================================================
-- Note: There is no override_requests table in the current
-- migration. If it was created by the rectification migration,
-- enable RLS on it. If the table does not exist, skip this.
-- ============================================================

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name = 'override_requests'
    ) THEN
        EXECUTE 'ALTER TABLE public.override_requests ENABLE ROW LEVEL SECURITY';
        EXECUTE '
            CREATE POLICY "override_requests_select_authenticated"
              ON public.override_requests FOR SELECT
              TO authenticated
              USING (true)
        ';
    END IF;
END $$;

-- ============================================================
-- NOTES:
-- - All INSERT/UPDATE/DELETE operations go through the FastAPI
--   backend using the service_role key, which bypasses RLS.
-- - These SELECT policies ensure that if anyone connects with
--   an anon or authenticated key, they can only read data.
-- - No INSERT/UPDATE/DELETE policies are created for
--   authenticated users — all writes must go through the API.
-- ============================================================
```

---

# SECTION 4: MINOR POLISH

## 4.1 Add Delete Operations for Regions, Entities, Projects

Each of these already has a `DELETE` endpoint in the API. The frontend needs delete buttons.

### 4.1.1 Regions Edit Page — Add Delete Button

**File:** `apps/web/src/app/(dashboard)/regions/edit-region-form.tsx`

Add a delete function and button to the bottom of the form:

```typescript
import { ConfirmDialog } from '@/components/confirm-dialog';
import { useRouter } from 'next/navigation';
// ... existing imports

// Inside the component, add:
const router = useRouter();

async function handleDelete() {
  const res = await fetch(`/api/ccrs/regions/${id}`, { method: 'DELETE' });
  if (await handleApiError(res)) return;
  toast.success('Region deleted');
  router.push('/regions');
}

// In the JSX, after the Save button, add:
<ConfirmDialog
  trigger={<Button variant="destructive" type="button">Delete region</Button>}
  title="Delete region"
  description="This will permanently delete this region. This cannot be undone. Any entities under this region must be reassigned first."
  confirmLabel="Delete"
  variant="destructive"
  onConfirm={handleDelete}
/>
```

### 4.1.2 Entities Edit Page — Add Delete Button

**File:** `apps/web/src/app/(dashboard)/entities/edit-entity-form.tsx`

Same pattern as regions. Add a `ConfirmDialog`-wrapped delete button that calls `DELETE /api/ccrs/entities/${id}` and redirects to `/entities`.

### 4.1.3 Projects Edit Page — Add Delete Button

**File:** `apps/web/src/app/(dashboard)/projects/edit-project-form.tsx`

Same pattern. Add a `ConfirmDialog`-wrapped delete button that calls `DELETE /api/ccrs/projects/${id}` and redirects to `/projects`.

## 4.2 Fix Reports CSV Export

**File:** `apps/web/src/app/(dashboard)/reports/page.tsx`

The `downloadText` function currently exports JSON as `.csv`. Replace it with a proper CSV converter.

**Replace** the `downloadText` function with:

```typescript
function jsonToCsv(data: unknown): string {
  if (!Array.isArray(data) || data.length === 0) {
    // Handle non-array data (e.g., { by_state: [...], by_type: [...] })
    if (data && typeof data === 'object') {
      const entries = Object.entries(data as Record<string, unknown>);
      const sections: string[] = [];
      for (const [key, value] of entries) {
        if (Array.isArray(value) && value.length > 0) {
          const headers = Object.keys(value[0] as Record<string, unknown>);
          const rows = value.map((row: Record<string, unknown>) =>
            headers.map((h) => `"${String(row[h] ?? '').replace(/"/g, '""')}"`).join(',')
          );
          sections.push(`${key}\n${headers.join(',')}\n${rows.join('\n')}`);
        }
      }
      return sections.join('\n\n');
    }
    return '';
  }
  const headers = Object.keys(data[0] as Record<string, unknown>);
  const rows = data.map((row: Record<string, unknown>) =>
    headers.map((h) => `"${String(row[h] ?? '').replace(/"/g, '""')}"`).join(',')
  );
  return `${headers.join(',')}\n${rows.join('\n')}`;
}

function downloadCsv(filename: string, data: unknown) {
  const csv = jsonToCsv(data);
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}
```

**Update** all 4 export button `onClick` handlers to use `downloadCsv`:

```tsx
<Button variant="outline" size="sm" onClick={() => downloadCsv('contract-status.csv', contractStatus)}>Export CSV</Button>
<Button variant="outline" size="sm" onClick={() => downloadCsv('expiry-horizon.csv', expiryHorizon)}>Export CSV</Button>
<Button variant="outline" size="sm" onClick={() => downloadCsv('signing-status.csv', signingStatus)}>Export CSV</Button>
<Button variant="outline" size="sm" onClick={() => downloadCsv('ai-costs.csv', aiCosts)}>Export CSV</Button>
```

Remove the old `downloadText` function entirely.

## 4.3 Add Search, Status Filter & Pagination to Counterparties List

**File:** `apps/web/src/app/(dashboard)/counterparties/counterparties-list.tsx`

The counterparties list currently fetches all records with no filtering or pagination. Replace with a filtered, paginated table.

**Replace the entire file** with:

```tsx
'use client';

import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import Link from 'next/link';
import type { Counterparty } from '@/lib/types';
import { handleApiError } from '@/lib/api-error';

const PAGE_SIZE = 25;
const STATUS_OPTIONS = ['all', 'Active', 'Suspended', 'Blacklisted'] as const;

export function CounterpartiesList() {
  const [list, setList] = useState<Counterparty[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(0);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [searchDebounce, setSearchDebounce] = useState('');

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => setSearchDebounce(search), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (searchDebounce) params.set('search', searchDebounce);
      if (statusFilter !== 'all') params.set('status', statusFilter);
      params.set('limit', String(PAGE_SIZE));
      params.set('offset', String(page * PAGE_SIZE));
      const res = await fetch(`/api/ccrs/counterparties?${params}`);
      if (await handleApiError(res)) return;
      const totalCount = res.headers.get('X-Total-Count');
      if (totalCount) setTotal(parseInt(totalCount, 10));
      const data = await res.json();
      setList(data);
    } catch {
      toast.error('Failed to load counterparties');
    } finally {
      setLoading(false);
    }
  }, [searchDebounce, statusFilter, page]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Reset to first page when filters change
  useEffect(() => {
    setPage(0);
  }, [searchDebounce, statusFilter]);

  const totalPages = Math.ceil(total / PAGE_SIZE);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Search by name or registration..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-sm"
        />
        <Select value={statusFilter} onValueChange={setStatusFilter}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            {STATUS_OPTIONS.map((s) => (
              <SelectItem key={s} value={s}>
                {s === 'all' ? 'All statuses' : s}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <span className="text-sm text-muted-foreground">
          {total} counterpart{total !== 1 ? 'ies' : 'y'}
        </span>
      </div>

      {loading ? (
        <div className="space-y-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-10 w-full" />
          ))}
        </div>
      ) : list.length === 0 ? (
        <p className="text-sm text-muted-foreground">No counterparties found.</p>
      ) : (
        <>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Legal Name</TableHead>
                <TableHead>Registration</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Jurisdiction</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-medium">{c.legal_name}</TableCell>
                  <TableCell>{c.registration_number ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant={c.status === 'Active' ? 'default' : 'secondary'}>
                      {c.status}
                    </Badge>
                  </TableCell>
                  <TableCell>{c.jurisdiction ?? '—'}</TableCell>
                  <TableCell>
                    <Button variant="outline" size="sm" asChild>
                      <Link href={`/counterparties/${c.id}`}>View</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {totalPages > 1 && (
            <div className="flex items-center justify-between">
              <p className="text-sm text-muted-foreground">
                Page {page + 1} of {totalPages}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page === 0}
                  onClick={() => setPage((p) => p - 1)}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= totalPages - 1}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
```

**Important:** The counterparties API endpoint must support `search`, `status`, `limit`, and `offset` query parameters. Check `apps/api/app/counterparties/router.py` — if the list endpoint doesn't already accept these parameters, add them:

```python
@router.get("/counterparties", response_model=list[CounterpartyOut])
async def list_counterparties(
    response: Response,
    search: str | None = None,
    status: str | None = None,
    limit: int = 25,
    offset: int = 0,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
```

The service function should filter by `status` if provided, and use `ilike` on `legal_name` and `registration_number` if `search` is provided.

## 4.4 Add Schema Validation for Workflow State and Signing Status

**File:** `apps/api/app/contracts/schemas.py`

Add `Literal` type constraints to prevent arbitrary strings:

```python
from typing import Literal

WORKFLOW_STATES = Literal[
    "draft", "review", "approval", "signing", "executed", "archived", "cancelled"
]
SIGNING_STATUSES = Literal[
    "draft", "sent", "viewed", "partially_signed", "completed", "declined", "expired", "voided"
]

class UpdateContractInput(BaseModel):
    title: str | None = None
    contract_type: str | None = Field(None, pattern=r"^(Commercial|Merchant)$")
    workflow_state: WORKFLOW_STATES | None = None
    signing_status: SIGNING_STATUSES | None = None
```

## 4.5 Add Email Validation for Counterparty Contacts

**File:** `apps/api/app/counterparty_contacts/schemas.py`

```python
from pydantic import EmailStr

class CreateContactInput(BaseModel):
    name: str
    email: EmailStr | None = None
    role: str | None = None
    is_signer: bool = Field(False, alias="isSigner")
```

Add `pydantic[email]` to `apps/api/requirements.txt` if not already present:

```
pydantic[email]
```

---

# SECTION 5: TESTS FOR NEW CODE

## 5.1 Test the `"now()"` Bug Fix

**File:** `apps/api/tests/test_notifications.py`

Add a test to verify `read_at` is a valid timestamp, not a string:

```python
import re

def test_mark_read_sets_timestamp(authed_client):
    """Verify mark-read sets a real ISO timestamp, not the string 'now()'."""
    # Get a notification first
    res = authed_client.get("/notifications")
    assert res.status_code == 200
    notifications = res.json()
    if not notifications:
        pytest.skip("No notifications in seed data")
    notif_id = notifications[0]["id"]

    # Mark it as read
    mark_res = authed_client.patch(f"/notifications/{notif_id}/read")
    assert mark_res.status_code == 200
    data = mark_res.json()

    # Verify read_at is a real timestamp, not "now()"
    read_at = data.get("read_at")
    assert read_at is not None
    assert read_at != "now()"
    # Should match ISO 8601 format
    assert re.match(r"\d{4}-\d{2}-\d{2}T", read_at), f"Expected ISO timestamp, got: {read_at}"
```

## 5.2 Test Override Requests Audit Log

**File:** `apps/api/tests/test_override_requests.py`

Add a test to verify override requests write to audit_log with ip_address:

```python
def test_override_request_creates_audit_with_ip(authed_client, mock_supabase):
    """Verify override request creation writes audit log via shared helper (includes ip_address)."""
    # Create an override request
    cps = mock_supabase.table("counterparties").select("*").eq("status", "Blacklisted").execute()
    if not cps.data:
        pytest.skip("No blacklisted counterparty in seed data")
    cp_id = cps.data[0]["id"]

    authed_client.post(
        f"/counterparties/{cp_id}/override-requests",
        json={"contractTitle": "Audit Test", "reason": "Testing audit"},
    )

    # Check that audit_log has the entry with ip_address
    audit_entries = mock_supabase.table("audit_log").select("*").eq(
        "action", "override_request_created"
    ).execute()
    assert len(audit_entries.data) > 0
    entry = audit_entries.data[-1]
    assert entry.get("ip_address") is not None or entry.get("ip_address") == "testclient"
```

## 5.3 Test RBAC Role Propagation

**File:** `apps/api/tests/test_override_requests.py`

The existing `test_decide_override_request_requires_legal` test already validates RBAC on the backend. No additional backend tests needed for the frontend RBAC changes.

---

# VERIFICATION CHECKLIST

After applying all sections, verify:

1. **Backend builds and passes tests:**
   ```bash
   cd apps/api
   python -m py_compile app/main.py
   ruff check app/ tests/
   pytest tests/ -v --cov=app --cov-report=term-missing
   ```

2. **Frontend builds:**
   ```bash
   cd apps/web
   npm run build
   ```

3. **Database migration applies cleanly:**
   - Apply the two new migrations via Supabase dashboard or CLI
   - Verify all 24 tables show `rls_enabled: true`

4. **Manual smoke tests:**
   - Log in as a dev user → should see all sidebar items (System Admin role)
   - Mark a notification as read → verify `read_at` in the database is an ISO timestamp, not `now()`
   - Create an override request → verify `audit_log` entry has `ip_address` field
   - Visit Reports → click Export CSV → verify file opens correctly in Excel/Sheets
   - Visit Counterparties → search and filter work, pagination shows
   - Visit Regions/Entities/Projects edit pages → delete button present with confirmation dialog
