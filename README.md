# CCRS — Contract & Merchant Agreement Repository System

Centralized platform for storing, managing, and monitoring Commercial Contracts and Merchant Agreements (Digittal Group). Backend on **Render**, frontend on **Vercel**, data on **Supabase** (+ optional object storage).

## Repo structure

- **`docs/`** — Backlog, build plan, and dev/testing guide
- **`.github/workflows/`** — CI (lint & test) on push and PRs
- **`apps/`** — (Add later) `web` (Next.js), `api` (backend)

## Docs

| Document | Purpose |
|----------|---------|
| [CCRS-Backlog-and-Build-Plan.md](docs/CCRS-Backlog-and-Build-Plan.md) | Full backlog, phases, hosting and DB recommendations |
| [Dev-and-Testing-with-GitHub.md](docs/Dev-and-Testing-with-GitHub.md) | Run dev and testing online with GitHub, Vercel, and Render |

## CI

Lint and test run on every push and pull request to `main`, `master`, and `develop`. See the [Actions](https://github.com/gregdigittal/agreement_automation/actions) tab.

[![CI](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml/badge.svg)](https://github.com/gregdigittal/agreement_automation/actions/workflows/ci.yml)

## Local setup

- Node.js 20+ (see `.nvmrc`)
- `npm ci` then `npm run lint` and `npm run test` at root
- When present: run frontend from `apps/web`, backend from `apps/api`

## License

Proprietary — Digittal Group.
