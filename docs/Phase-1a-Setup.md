# Phase 1a — Setup and run

Phase 1a delivers: **Foundation and infrastructure**, **core repository**, **org structure**, **counterparty management**, **security/compliance (audit)**, and **counterparty due diligence**.

## Prerequisites

- Node.js 20+
- A **Supabase** project (for PostgreSQL and Storage)

## 1. Supabase

1. Create a project at [supabase.com](https://supabase.com).
2. In the SQL Editor, run the migration:  
   `supabase/migrations/20260216000000_phase1a_schema.sql`
3. In **Storage**, create a bucket named `contracts` (private).
4. Copy **Project URL** and **service_role** key (Settings → API).

## 2. API (`apps/api`)

```bash
cd apps/api
cp .env.example .env
# Edit .env: set SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, JWT_SECRET
npm install
npm run start:dev
```

- API runs at **http://localhost:4000**
- Use the same `JWT_SECRET` as the web app (e.g. same as `AUTH_SECRET`) so the API can validate next-auth session tokens.

## 3. Web (`apps/web`)

```bash
cd apps/web
cp .env.example .env.local
# Edit .env.local: set AUTH_SECRET, NEXT_PUBLIC_API_URL=http://localhost:4000
npm install
npm run dev
```

- App runs at **http://localhost:3000**
- Sign in with **Dev**: any email (e.g. `dev@example.com`) and any password when Azure AD is not configured.

## 4. Optional: Microsoft Entra ID (Azure AD)

- In Azure Portal, register an app and create a client secret.
- Set in **apps/web** `.env.local`:  
  `AZURE_AD_CLIENT_ID`, `AZURE_AD_CLIENT_SECRET`, and optionally `NEXT_PUBLIC_AZURE_AD_ENABLED=true`
- Set in **apps/api** `.env`:  
  `AZURE_AD_CLIENT_ID`, `AZURE_AD_CLIENT_SECRET`, `AZURE_AD_ISSUER` (e.g. `https://login.microsoftonline.com/{tenant-id}/v2.0`)  
  so the API validates Entra ID tokens when the frontend uses "Sign in with Microsoft".

## 5. What you can do (Phase 1a)

- **Regions** — Create, list, edit regions.
- **Entities** — Create, list, edit entities (per region).
- **Projects** — Create, list, edit projects (per entity).
- **Counterparties** — Create, list, edit; duplicate warning on legal name/registration number; set status (Active / Suspended / Blacklisted).
- **Contracts** — Upload PDF/DOCX with classification (region, entity, project, counterparty); search and list; get signed download URL.
- **Audit** — All mutations are logged; export via API `GET /audit/export` (restricted to roles when using Entra ID).

## 6. CI

From repo root:

- `npm run test` and `npm run lint` in `apps/api` and `apps/web` run in GitHub Actions on push/PR (see `.github/workflows/ci.yml`).
