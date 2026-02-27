# CCRS Agent Operating Guide

Last updated: 2026-02-27

## Purpose
This file is the operational guide for AI/code agents working in this repository. It is derived from `CLAUDE.md`, then corrected to match the actual files currently in git.

## Team Roles
- CTO (you): owns deployment architecture, infrastructure decisions, and technical stack guardrails.
- CCO: drives product delivery and frequent application updates.
- Agents: implement requested changes safely, preserve deployment behavior, and escalate infra-impacting decisions.

## Decision Rules
- Do not change infrastructure files unless explicitly requested by the CTO in the current task.
- If the CTO requests infra changes, implement them directly and document risk/tradeoffs.
- Never introduce alternate deployment patterns that conflict with this sandbox (external DB, separate queue deployment, HPA, etc.) unless explicitly requested.

## Infrastructure Files (CTO-owned by default)
- `.github/workflows/deploy.yml`
- `Dockerfile`
- `docker/**`
- `deploy/k8s/**`
- `docker-compose.yml`
- `Jenkinsfile` (legacy reference; do not remove unless requested)
- `CLAUDE.md`

## Current Runtime Topology (source of truth)
Sandbox is deployed from `.github/workflows/deploy.yml` on pushes to `sandbox`.

Kubernetes deployment currently runs these containers in one pod:
1. Laravel app container (`${APP_NAME}`), port 8080
2. MySQL sidecar, port 3306
3. Redis sidecar, port 6379
4. AI worker sidecar, port 8001
5. phpMyAdmin sidecar, port 8888

Reference: `deploy/k8s/deployment.yaml`.

## Important Correction vs CLAUDE.md
- `CLAUDE.md` says no AI worker infrastructure exists. That is outdated.
- Current manifests and workflow do build and deploy `ccrs-ai-worker` and inject AI worker secrets.

## Deployment Flow
- `main`: development branch (no deploy trigger)
- `sandbox`: deploy branch (push triggers build/push/apply/rollout)
- Registry: `repo-de.digittal.mobi`
- Namespace: `cco-sandbox`

## Build Performance Guardrails
- Keep BuildKit enabled. Do not set `DOCKER_BUILDKIT=0` in active pipelines.
- Keep build context small via `.dockerignore` (exclude CI/docs/deploy/meta files from app build context).
- Avoid broad `COPY . .` when narrower `COPY` can preserve cache for dependency layers.
- Avoid expensive recursive ownership fixes (`chown -R`) when `COPY --chown` can be used.
- Prefer warm-cache strategies:
  - pull/cache-from `:latest` before build
  - buildx cache export/import (local or registry) when runner lifecycle is not stable

## Operational Triage (Slow Builds)
When build time regresses, check in this order:
1. Host contention (CPU, IO wait, memory pressure) on build host
2. Build cache disabled or invalidated (BuildKit off, aggressive cleanup, changed context)
3. Dockerfile cache busting (broad copy/chown patterns)
4. Registry push/pull latency

## Security & Secrets
- Never commit plaintext credentials or kubeconfigs.
- Continue using GitHub secrets for:
  - registry password
  - `KUBE_CONFIG`
  - app/azure/AI worker secrets

## Change Validation Minimum
- For app changes: run project tests relevant to touched code.
- For Dockerfile/workflow changes: run at least one full sandbox build + deploy and record build stage timings.
- If changing probes/startup behavior, verify `/health` and rollout status.
