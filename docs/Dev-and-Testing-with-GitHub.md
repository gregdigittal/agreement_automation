# Running Dev and Testing Online with GitHub

This guide explains how to run **development** and **testing** for CCRS using your GitHub repo, with CI on GitHub Actions and optional **online preview environments** on Vercel (frontend) and Render (backend).

---

## 1. Push Your Repo to GitHub

If the project is not yet on GitHub:

1. **Create a new repository** on GitHub (e.g. `Agreement_automation` or `ccrs`).
2. **Initialize git** (if needed) and add the remote:

   ```bash
   cd "/path/to/Agreement_automation"
   git init
   git add .
   git commit -m "Initial commit: CCRS backlog, CI, and docs"
   git branch -M main
   git remote add origin https://github.com/gregdigittal/agreement_automation.git
   git push -u origin main
   ```

3. **Update the repository URL** in the root `package.json` if you use a different org/repo name.

---

## 2. CI: Lint and Test on Every Push / PR

The repo includes a **GitHub Actions** workflow that runs on every push and pull request to `main`, `master`, and `develop`.

### What runs

| Job | When | What it does |
|-----|------|----------------|
| **Lint & Test** | Always | From repo root: `npm ci`, `npm run lint`, `npm run test`. |
| **Frontend (Next.js)** | When `apps/web/package.json` exists | In `apps/web`: install, lint, test, build (no deploy). |
| **Backend (API)** | When `apps/api/package.json` exists | In `apps/api`: install, lint, test, build (no deploy). |

### Where to see results

- **Actions** tab in your GitHub repo: [https://github.com/gregdigittal/agreement_automation/actions](https://github.com/gregdigittal/agreement_automation/actions)
- On each **pull request**: status checks appear; you can require them to pass before merge (Branch protection → Require status checks).

### Adding real lint/test later

- **Root:** Edit `package.json` scripts and add real tools (e.g. ESLint, Jest) or leave as placeholders.
- **Frontend:** Add `apps/web` (Next.js) with `lint` and `test` scripts; CI will run them automatically.
- **Backend:** Add `apps/api` (NestJS or Node API) with `lint` and `test`; CI will run them automatically.

---

## 3. Online Dev and Preview Environments (Vercel + Render)

To run **dev and testing online** (preview URLs for every branch/PR):

### Frontend (Vercel)

1. Go to [vercel.com](https://vercel.com) and sign in with **GitHub**.
2. **Import** your repo: **Add New… → Project** and select `Agreement_automation`.
3. **Configure:**
   - **Framework Preset:** Next.js (once you have `apps/web` or a root Next.js app).
   - **Root Directory:** `apps/web` if you use a monorepo; leave blank if the Next.js app is at repo root.
   - **Build Command:** `npm run build` (default).
   - **Output Directory:** `.next` (default for Next.js).
4. Add **Environment variables** (e.g. `NEXT_PUBLIC_API_URL`, auth client IDs) in the Vercel project settings. Use the same names for Production, Preview, and Development if you want one set for all.
5. **Deploy.** Every push to `main` deploys to production; every push to other branches and every PR get a **Preview** URL (e.g. `ccrs-xxx-org.vercel.app`). Use these for dev and testing.

### Backend (Render)

1. Go to [render.com](https://render.com) and sign in with **GitHub**.
2. **New → Web Service** (or **Background Worker** for workers/cron later).
3. **Connect** the same repo: `Agreement_automation`.
4. **Configure:**
   - **Root Directory:** `apps/api` if your API lives there; leave blank if at repo root.
   - **Build Command:** `npm install && npm run build` (or your backend build command).
   - **Start Command:** `npm run start` or `node dist/main.js` (or your start command).
   - **Plan:** Free or paid; Free tier sleeps after inactivity.
5. Add **Environment variables** in the Render dashboard (e.g. `DATABASE_URL`, `BOLDSIGN_*`, `SUPABASE_*`). No secrets in code.
6. **Deploy.** Render can deploy on every push to `main`, or only for the branch you select. For **dev/testing**, you can create a second Render service that deploys from `develop` or a `staging` branch so you have a stable “dev” API URL.

### Branch strategy for online dev/testing

- **`main`** → Production (Vercel production + Render production).
- **`develop`** → Dev/staging (optional second Vercel “Preview” env + second Render service from `develop`).
- **Feature branches / PRs** → Vercel Preview URLs per branch/PR; backend can share the staging Render service or use a single “preview” backend with branch in env.

---

## 4. Quick Reference

| Goal | Where |
|------|--------|
| Run tests on every push/PR | GitHub Actions (see **Actions** tab) |
| Lint on every push/PR | Same CI workflow (root + `apps/web` + `apps/api`) |
| Frontend preview URL per branch/PR | Vercel (connect repo, deploy) |
| Backend dev/staging URL | Render (one or two services for prod vs dev branch) |
| Require CI to pass before merge | GitHub → Settings → Branches → Branch protection → Require status checks |

---

## 5. Optional: Status Badge in README

CI status badge (already in the root README):

```markdown
[![CI](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml/badge.svg)](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml)
```

---

*See [CCRS-Backlog-and-Build-Plan.md](./CCRS-Backlog-and-Build-Plan.md) for full architecture and backlog.*
