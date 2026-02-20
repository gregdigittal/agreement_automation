# Cursor Prompt — Diagnose and Fix Remote Sync to `digittaldotio/digittal-ccrs`

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch.**

The target repo `digittaldotio/digittal-ccrs` has been provisioned by the CTO. The task is to:

1. Diagnose why git cannot reach it from this environment
2. Fix the authentication/remote configuration
3. Push `laravel-migration` to `digittal-ccrs:main`
4. Create the GitHub Actions auto-sync workflow so future pushes to `laravel-migration` automatically mirror to `digittal-ccrs:main`

---

## Step 1 — Diagnose Connectivity

Run these two commands in sequence to identify the exact failure mode:

```bash
# Test SSH access to the digittaldotio org
ssh -T git@github.com 2>&1

# Test if the specific repo is reachable via SSH
git ls-remote git@github.com:digittaldotio/digittal-ccrs.git HEAD 2>&1

# Test the same repo via HTTPS (no SSH key required)
git ls-remote https://github.com/digittaldotio/digittal-ccrs.git HEAD 2>&1
```

**Interpret the results:**

| Result | Cause | Fix |
|---|---|---|
| `Hi <your-username>! You've successfully authenticated` from SSH but `ls-remote` fails | Your personal SSH key is not authorised for the `digittaldotio` org | Use HTTPS with a PAT (Step 2A) |
| `Hi <your-username>!` and `ls-remote` succeeds | SSH works — proceed to Step 3 (direct push) |  |
| `Permission denied (publickey)` | No SSH key in this environment | Use HTTPS with a PAT (Step 2A) |
| HTTPS `ls-remote` returns refs | HTTPS works without auth (public repo) | Use HTTPS (Step 2B) |
| HTTPS returns `Authentication failed` | Private repo, need PAT | Use HTTPS with PAT (Step 2A) |
| Both fail with `Repository not found` | Repo name or org is wrong — verify with CTO | — |

---

## Step 2A — HTTPS with Personal Access Token (most likely fix)

If SSH fails or the SSH key is not authorised for `digittaldotio`, use HTTPS with a PAT:

1. Go to `https://github.com/settings/tokens/new` (your GitHub account, NOT the org)
2. Generate a **Classic** token with scopes: `repo` (full)
3. Name it `digittal-ccrs-sync`
4. Copy the token value (you will not see it again)

Then run:

```bash
# Add the remote using HTTPS + PAT embedded in the URL
# Replace YOUR_PAT with the token you just copied
# Replace YOUR_GITHUB_USERNAME with your GitHub username

git remote remove ccrs 2>/dev/null || true

git remote add ccrs https://YOUR_GITHUB_USERNAME:YOUR_PAT@github.com/digittaldotio/digittal-ccrs.git

# Verify the remote is reachable
git ls-remote ccrs HEAD
```

If that returns a commit hash (or "warning: You appear to have cloned an empty repository"), proceed to Step 3.

---

## Step 2B — SSH Key Not Authorised for `digittaldotio` Org

If SSH authenticates fine for your personal account but the `digittaldotio` org hasn't authorised your key, you have two options:

**Option 1 (preferred):** Ask the CTO to authorise your SSH key for the `digittaldotio` org at:
`https://github.com/orgs/digittaldotio/settings/ssh-certificate-authorities` — or by adding you as a member/collaborator on the `digittal-ccrs` repo.

**Option 2:** Use HTTPS with a PAT as in Step 2A.

---

## Step 3 — One-Time Push (First-Time Only)

Once the remote `ccrs` is reachable:

```bash
# Ensure you are on the laravel-migration branch
git checkout laravel-migration

# Push to digittal-ccrs main
# --force is safe here ONLY for the initial push to an empty repo
# If the repo is not empty (CTO has added files), use --no-force and resolve conflicts first
git push ccrs laravel-migration:main

# Verify
git ls-remote ccrs HEAD
```

If the target repo already has commits and `--force` is inappropriate:

```bash
# Fetch what exists on the target
git fetch ccrs main:refs/remotes/ccrs/main

# Check what's there
git log --oneline ccrs/main | head -10

# If target has only scaffold/template files and you want to overwrite:
git push ccrs laravel-migration:main --force

# If target has meaningful commits to merge:
git merge ccrs/main --allow-unrelated-histories -m "Merge CTO scaffold into laravel-migration"
git push ccrs laravel-migration:main
```

---

## Step 4 — Create the GitHub Actions Auto-Sync Workflow

Create this file in the `agreement_automation` repo so that every future push to `laravel-migration` automatically mirrors to `digittal-ccrs:main`.

**Create file: `.github/workflows/sync-to-digittal-ccrs.yml`**

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

Commit this file:

```bash
git add .github/workflows/sync-to-digittal-ccrs.yml
git commit -m "ci: add GitHub Actions workflow to sync laravel-migration to digittal-ccrs"
git push origin laravel-migration
```

---

## Step 5 — Add the PAT Secret to the `agreement_automation` Repo

1. Go to: `https://github.com/gregdigittal/agreement_automation/settings/secrets/actions`
2. Click **New repository secret**
3. Name: `DIGITTAL_CCRS_PAT`
4. Value: the PAT you generated in Step 2A (must have `repo` scope on `digittaldotio/digittal-ccrs`)
5. Click **Add secret**

From this point on, every `git push origin laravel-migration` in Cursor will automatically trigger the workflow and mirror the changes to `digittaldotio/digittal-ccrs:main` within ~30 seconds.

---

## Verification

After completing the above:

```bash
# Confirm the workflow file is committed
git log --oneline -3

# Confirm the ccrs remote is set
git remote -v

# Confirm the target repo has your code
git ls-remote ccrs HEAD
```

Then go to `https://github.com/gregdigittal/agreement_automation/actions` and confirm the **Sync laravel-migration → digittal-ccrs** workflow ran successfully after your last push.

---

## Important Notes

- **`--force-with-lease`** (used in the workflow) is safer than `--force` — it only overwrites if no one else has pushed in the interim.
- If the `digittaldotio` org has branch protection on `main`, ask the CTO to add the PAT's user as a collaborator with **write** access, or to add an exception for the CI token.
- The PAT must be from a GitHub account that has **write access** to `digittaldotio/digittal-ccrs`. If your personal PAT doesn't have org access, ask the CTO to create a machine-user PAT from within the `digittaldotio` org instead.
- Do **not** commit the raw PAT value anywhere in the repo. Always use GitHub Secrets.
