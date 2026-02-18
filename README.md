# CCRS — Contract & Merchant Agreement Repository System

Centralized platform for storing, managing, and monitoring Commercial Contracts and Merchant Agreements (Digittal Group). Backend on **Render**, frontend on **Vercel**, data on **Supabase** (+ optional object storage).

## Repo structure

- **`docs/`** — Backlog, build plan, Phase 1a setup, and dev/testing guide
- **`.github/workflows/`** — CI (lint & test) on push and PRs
- **`apps/api`** — Python FastAPI backend (Supabase, JWT auth, regions/entities/projects/counterparties/contracts/audit)
- **`apps/web`** — Next.js frontend (Shadcn, next-auth, Phase 1a UI)
- **`supabase/migrations/`** — Phase 1a schema (run in Supabase SQL Editor)

## Docs

| Document | Purpose |
|----------|---------|
| [CCRS-Backlog-and-Build-Plan.md](docs/CCRS-Backlog-and-Build-Plan.md) | Full backlog, phases, hosting and DB recommendations |
| [Dev-and-Testing-with-GitHub.md](docs/Dev-and-Testing-with-GitHub.md) | Run dev and testing online with GitHub, Vercel, and Render |
| [Phase-1a-Setup.md](docs/Phase-1a-Setup.md) | How to run Phase 1a (API, web, Supabase, auth) |

## CI

Lint and test run on every push and pull request to `main`, `master`, and `develop`. See the [Actions](https://github.com/gregdigittal/agreement_automation/actions) tab.

[![CI](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml/badge.svg)](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml)

## Local setup (Phase 1a)

1. **Supabase:** Create a project, run `supabase/migrations/20260216000000_phase1a_schema.sql`, create Storage bucket `contracts`.
2. **API:** `cd apps/api && python -m venv .venv && source .venv/bin/activate && pip install -r requirements.txt && cp .env.example .env` (set SUPABASE_*, JWT_SECRET), `uvicorn app.main:app --reload --port 4000` → http://localhost:4000
3. **Web:** `cd apps/web && cp .env.example .env.local` (set AUTH_SECRET, NEXT_PUBLIC_API_URL), `npm install && npm run dev` → http://localhost:3000
4. Sign in with Dev (any email) when Azure AD is not configured. See [Phase-1a-Setup.md](docs/Phase-1a-Setup.md).

## License

Proprietary — Digittal Group.
