# Render and Vercel — Environment setup (including Supabase)

CCRS **API** runs on **Render**; the **web** app runs on **Vercel**. This doc lists the environment variables for both and how to set them (including Supabase).

---

## Render (CCRS API)

**Service:** [ccrs-api](https://dashboard.render.com/web/srv-d69v4t6r433s73d9f5k0)  
**URL:** https://ccrs-api.onrender.com

The service was created via MCP with the env var **keys** below. Replace the placeholder **values** in the Render dashboard.

1. Open [Render Dashboard → ccrs-api → Environment](https://dashboard.render.com/web/srv-d69v4t6r433s73d9f5k0).
2. Set these variables (use **Secret** for sensitive values):

| Key | Value | Notes |
|-----|--------|--------|
| `SUPABASE_URL` | `https://YOUR_PROJECT.supabase.co` | From Supabase: Project Settings → API → Project URL |
| `SUPABASE_SERVICE_ROLE_KEY` | *(secret)* | From Supabase: Project Settings → API → `service_role` (secret) |
| `JWT_SECRET` | *(you generate this)* | **Not from Supabase.** A shared secret (32+ chars) you create. Use the **same** value as `AUTH_SECRET` on Vercel so the API can validate next-auth session tokens. Generate with: `openssl rand -base64 32` |
| `CORS_ORIGIN` | `https://YOUR_VERCEL_APP.vercel.app` | Your Vercel app URL(s), comma-separated if multiple |
| `NODE_VERSION` | `20` | Already set |

Optional (for production Entra ID):

- `AZURE_AD_CLIENT_ID`
- `AZURE_AD_CLIENT_SECRET`
- `AZURE_AD_ISSUER` (e.g. `https://login.microsoftonline.com/{tenant-id}/v2.0`)

After saving, Render will redeploy. Ensure Supabase has the Phase 1a schema and a `contracts` storage bucket (see [Phase-1a-Setup.md](./Phase-1a-Setup.md)).

---

## Vercel (CCRS Web)

The Vercel MCP does not expose an “set environment variables” API, so configure them in the Vercel dashboard.

### 1. Create / link the project

1. Go to [Vercel Dashboard](https://vercel.com) and sign in (team **Digittal**).
2. **Add New… → Project** and import **gregdigittal/agreement_automation**.
3. Set **Root Directory** to `apps/web`.
4. **Framework Preset:** Next.js. Build command: `npm run build`. Output: default.
5. Deploy. Note the project URL (e.g. `agreement-automation-xxx.vercel.app`).

### 2. Environment variables

In the project: **Settings → Environment Variables**. Add:

| Name | Value | Environments |
|------|--------|----------------|
| `AUTH_SECRET` | *(secret, 32+ chars)* | Production, Preview, Development |
| `NEXT_PUBLIC_API_URL` | `https://ccrs-api.onrender.com` | Production, Preview, Development |
| `NEXT_PUBLIC_APP_URL` | `https://YOUR_VERCEL_APP.vercel.app` | Production (use your real Vercel URL) |

Optional (Azure AD / Entra ID):

| Name | Value |
|------|--------|
| `AZURE_AD_CLIENT_ID` | Your Azure AD app (client) ID |
| `AZURE_AD_CLIENT_SECRET` | Your Azure AD client secret |
| `AZURE_AD_TENANT_ID` | `common` or your tenant ID |
| `NEXT_PUBLIC_AZURE_AD_ENABLED` | `true` |

**Important:** `AUTH_SECRET` and `JWT_SECRET` are **not** Supabase keys. Generate one secret (e.g. `openssl rand -base64 32`) and set it as `AUTH_SECRET` on Vercel and `JWT_SECRET` on Render so the API can validate next-auth session tokens.

Redeploy the project after changing env vars so they apply to the next build.

---

## Supabase (shared)

Used by the **API** only (Vercel does not need Supabase keys in env for Phase 1a).

1. [Supabase](https://supabase.com) → create or open your project.
2. **SQL Editor** → run the full migration (creates tables and the `contracts` bucket):
   - Open **SQL Editor** → **New query**.
   - Paste the contents of `supabase/migrations/20260216000000_phase1a_schema.sql` from this repo and run it.
3. **Settings → API** → copy **Project URL** and **service_role** key into Render as `SUPABASE_URL` and `SUPABASE_SERVICE_ROLE_KEY`.

**If you already ran the migration without the bucket**, run this in SQL Editor to create the `contracts` bucket only:

```sql
INSERT INTO storage.buckets (id, name, public)
VALUES ('contracts', 'contracts', false)
ON CONFLICT (id) DO NOTHING;
```

---

## Quick checklist

- [ ] Supabase: migration run, `contracts` bucket created.
- [ ] Render: `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, `JWT_SECRET`, `CORS_ORIGIN` set; redeploy.
- [ ] Vercel: project from `agreement_automation` with root `apps/web`; `AUTH_SECRET`, `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_APP_URL` set; redeploy.
- [ ] `JWT_SECRET` (Render) = `AUTH_SECRET` (Vercel).
