# Cursor Prompt — Pre-Flight: Repo Verification & Sync Setup

## Purpose

**Run this prompt BEFORE starting any build phase (A through O).** It verifies you are working on the correct repository, correct branch, and that both repos stay in sync automatically.

There are **two repositories** for this project:

| Repo | Purpose | Branch |
|---|---|---|
| `gregdigittal/agreement_automation` | Development repo (your working copy) | `laravel-migration` |
| `digittaldotio/digittal-ccrs` | Production deployment repo (CTO's K8s pipeline) | `main` |

Every commit to `laravel-migration` on the development repo must be mirrored to `main` on the production repo.

---

## Step 1 — Verify Branch

```bash
git branch --show-current
```

**Expected:** `laravel-migration`

If you are on any other branch, switch immediately:
```bash
git checkout laravel-migration
```

If the branch does not exist:
```bash
git fetch origin
git checkout -b laravel-migration origin/laravel-migration
```

---

## Step 2 — Verify Remotes

```bash
git remote -v
```

**Expected remotes:**

| Name | URL | Purpose |
|---|---|---|
| `origin` | `https://github.com/gregdigittal/agreement_automation.git` (or SSH equivalent) | Development repo |
| `ccrs` | `git@github.com:digittaldotio/digittal-ccrs.git` (or HTTPS equivalent) | Production repo |

### If `origin` is wrong or missing:
```bash
git remote set-url origin https://github.com/gregdigittal/agreement_automation.git
```

### If `ccrs` remote is missing:
```bash
git remote add ccrs git@github.com:digittaldotio/digittal-ccrs.git
```

If SSH does not work for `ccrs`, use HTTPS with a PAT:
```bash
git remote add ccrs https://YOUR_GITHUB_USERNAME:YOUR_PAT@github.com/digittaldotio/digittal-ccrs.git
```

---

## Step 3 — Verify Access to Both Repos

```bash
# Test development repo
git ls-remote origin HEAD

# Test production repo
git ls-remote ccrs HEAD
```

Both commands must return a commit hash. If `ccrs` fails:
1. Check if `gregdigittal` has been added as a collaborator on `digittaldotio/digittal-ccrs`
2. If using SSH, verify your key is authorised for the `digittaldotio` org
3. If using HTTPS, verify the PAT has `repo` scope
4. See `docs/Cursor-Prompt-Laravel-Sync.md` for detailed diagnostics

**Do not proceed with any build phase until both remotes are accessible.**

---

## Step 4 — Verify Auto-Sync Workflow Exists

```bash
cat .github/workflows/sync-to-digittal-ccrs.yml
```

If the file does not exist, create it:

```bash
mkdir -p .github/workflows
```

Write `.github/workflows/sync-to-digittal-ccrs.yml`:

```yaml
name: Sync laravel-migration → digittal-ccrs

on:
  push:
    branches:
      - laravel-migration

jobs:
  sync:
    name: Mirror to digittal-ccrs
    runs-on: ubuntu-latest
    steps:
      - name: Checkout laravel-migration (full history)
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
          ref: laravel-migration

      - name: Push to digittaldotio/digittal-ccrs main
        env:
          DIGITTAL_CCRS_PAT: ${{ secrets.DIGITTAL_CCRS_PAT }}
        run: |
          git config user.email "ci@digittalgroup.com"
          git config user.name "CCRS CI"
          git remote add ccrs https://x-access-token:${DIGITTAL_CCRS_PAT}@github.com/digittaldotio/digittal-ccrs.git
          git push ccrs laravel-migration:main --force-with-lease
```

If you created or modified the file:
```bash
git add .github/workflows/sync-to-digittal-ccrs.yml
git commit -m "ci: add GitHub Actions auto-sync to digittal-ccrs"
git push origin laravel-migration
```

**Note:** The workflow requires a `DIGITTAL_CCRS_PAT` secret on the `agreement_automation` repo. If it has not been added yet:
1. Go to `https://github.com/gregdigittal/agreement_automation/settings/secrets/actions`
2. Add secret: Name = `DIGITTAL_CCRS_PAT`, Value = a GitHub PAT with `repo` scope that has write access to `digittaldotio/digittal-ccrs`

---

## Step 5 — Sync Repos Now

Push the current state to both repos to ensure they are aligned before starting any build work:

```bash
# Push to development repo
git push origin laravel-migration

# Push to production repo (mirrors laravel-migration → main)
git push ccrs laravel-migration:main --force-with-lease
```

Verify both repos have the same HEAD:
```bash
echo "origin:  $(git ls-remote origin refs/heads/laravel-migration | cut -f1)"
echo "ccrs:    $(git ls-remote ccrs refs/heads/main | cut -f1)"
echo "local:   $(git rev-parse HEAD)"
```

All three should show the same commit hash.

---

## Step 6 — Confirm Repo Structure

The `laravel-migration` branch should contain **only** Laravel application code. Verify the legacy code has been cleaned:

```bash
# These directories must NOT exist (they were removed):
ls apps/ 2>/dev/null && echo "ERROR: apps/ still exists — legacy code not cleaned" || echo "OK: apps/ removed"
ls supabase/ 2>/dev/null && echo "ERROR: supabase/ still exists — should be in docs/archive/" || echo "OK: supabase/ archived"

# These must exist:
ls docs/Cursor-Prompt-Laravel-A.md && echo "OK: Laravel prompts present"
ls .github/workflows/sync-to-digittal-ccrs.yml && echo "OK: Sync workflow present"
```

---

## Step 7 — Set Push Behaviour for All Future Work

To ensure every push goes to both repos automatically, configure a push alias. Run this once:

```bash
git config alias.pushall '!git push origin laravel-migration && git push ccrs laravel-migration:main --force-with-lease'
```

From this point on, after every commit, use:
```bash
git pushall
```

This pushes to both `origin/laravel-migration` AND `ccrs/main` in one command. The GitHub Actions workflow also provides a safety net — even if you forget `pushall` and only push to `origin`, the workflow will sync to `ccrs` automatically.

---

## Verification Summary

Before proceeding with any build phase, confirm ALL of the following:

| Check | Command | Expected |
|---|---|---|
| Correct branch | `git branch --show-current` | `laravel-migration` |
| Origin remote | `git remote get-url origin` | `gregdigittal/agreement_automation` |
| CCRS remote | `git remote get-url ccrs` | `digittaldotio/digittal-ccrs` |
| Origin accessible | `git ls-remote origin HEAD` | Returns commit hash |
| CCRS accessible | `git ls-remote ccrs HEAD` | Returns commit hash |
| Sync workflow exists | `ls .github/workflows/sync-to-digittal-ccrs.yml` | File exists |
| Legacy code removed | `ls apps/ 2>/dev/null` | No such directory |
| Repos aligned | Compare HEAD hashes | All three match |

**Once all checks pass, proceed with the next build phase prompt (A, B, C, etc.).**

---

## Ongoing Sync Protocol

During each build phase session:

1. **Commit frequently** — small, focused commits after each task in the prompt
2. **Push after each commit** — use `git pushall` or at minimum `git push origin laravel-migration`
3. **Never work on `main`** — all work happens on `laravel-migration`
4. **Never push directly to `ccrs/main`** without also pushing to `origin/laravel-migration` — keep them in sync
5. **If repos diverge** (someone pushes directly to `digittal-ccrs`), resync:
   ```bash
   git fetch ccrs main
   git merge ccrs/main --allow-unrelated-histories -m "chore: merge ccrs/main into laravel-migration"
   git pushall
   ```
