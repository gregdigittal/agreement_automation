# AI Worker Deployment — CTO Instructions

## Overview

The AI Worker is a Python FastAPI microservice that handles all AI-powered features:
- Contract analysis (summary, risk, extraction, obligations)
- Clause redlining (compare contract against templates)
- Regulatory compliance checking

It communicates with the Laravel app via internal HTTP and authenticates using a shared secret header (`X-AI-Worker-Secret`).

---

## Architecture

```
Laravel App (port 8080)
    |
    |  POST /analyze, /generate-workflow, /analyze-redline, /check-compliance
    |  Header: X-AI-Worker-Secret: <shared_secret>
    v
AI Worker (port 8001, internal only)
    |
    |  Anthropic Messages API
    v
Claude API (api.anthropic.com)
    |
    |  SQLAlchemy queries (read-only org structure lookups)
    v
MySQL (127.0.0.1:3306 or mysql:3306)
```

---

## Option A: Sidecar Container (Recommended for Sandbox)

Add the AI Worker as a 5th container in the existing pod, alongside app, MySQL, Redis, and phpMyAdmin.

### Container Spec

```yaml
- name: ai-worker
  image: repo-de.digittal.mobi/ccrs-ai-worker:latest
  ports:
    - containerPort: 8001
  env:
    - name: ANTHROPIC_API_KEY
      value: "<YOUR_ANTHROPIC_API_KEY>"
    - name: AI_WORKER_SECRET
      value: "<GENERATE_A_RANDOM_SECRET>"
    - name: DB_URL
      value: "mysql+pymysql://ccrs:ccrs-sandbox-pass@127.0.0.1:3306/ccrs"
    - name: AI_MODEL
      value: "claude-sonnet-4-6"
    - name: AI_AGENT_MODEL
      value: "claude-sonnet-4-6"
    - name: AI_MAX_BUDGET_USD
      value: "5.0"
    - name: LOG_LEVEL
      value: "info"
  livenessProbe:
    httpGet:
      path: /health
      port: 8001
    initialDelaySeconds: 10
    periodSeconds: 30
  readinessProbe:
    httpGet:
      path: /health
      port: 8001
    initialDelaySeconds: 5
    periodSeconds: 10
  resources:
    requests:
      memory: "256Mi"
      cpu: "100m"
    limits:
      memory: "512Mi"
      cpu: "500m"
```

### Laravel App Env Vars (add to app container)

```yaml
- name: AI_WORKER_URL
  value: "http://127.0.0.1:8001"
- name: AI_WORKER_SECRET
  value: "<SAME_SECRET_AS_ABOVE>"
```

Since the AI worker runs in the same pod, use `127.0.0.1:8001` (localhost). No service or ingress needed.

### Docker Build (add to Jenkinsfile)

```groovy
// Build AI Worker image
sh "docker build -t repo-de.digittal.mobi/ccrs-ai-worker:${BUILD_NUMBER} ./ai-worker"
sh "docker push repo-de.digittal.mobi/ccrs-ai-worker:${BUILD_NUMBER}"
sh "docker tag repo-de.digittal.mobi/ccrs-ai-worker:${BUILD_NUMBER} repo-de.digittal.mobi/ccrs-ai-worker:latest"
sh "docker push repo-de.digittal.mobi/ccrs-ai-worker:latest"
```

---

## Option B: Separate Deployment

If you prefer the AI worker in its own pod/deployment:

### Service

```yaml
apiVersion: v1
kind: Service
metadata:
  name: ccrs-ai-worker
spec:
  selector:
    app: ccrs-ai-worker
  ports:
    - port: 8001
      targetPort: 8001
  type: ClusterIP   # internal only, no external access
```

### Laravel App Env Vars

```yaml
- name: AI_WORKER_URL
  value: "http://ccrs-ai-worker:8001"
- name: AI_WORKER_SECRET
  value: "<SAME_SECRET_AS_AI_WORKER>"
```

---

## Environment Variables Reference

### AI Worker Container

| Variable | Required | Description |
|---|---|---|
| `ANTHROPIC_API_KEY` | Yes | Anthropic API key for Claude |
| `AI_WORKER_SECRET` | Yes | Shared secret for service-to-service auth |
| `DB_URL` | Yes | SQLAlchemy MySQL connection string |
| `AI_MODEL` | No | Claude model for simple analysis (default: `claude-sonnet-4-6`) |
| `AI_AGENT_MODEL` | No | Claude model for complex/agentic analysis (default: `claude-sonnet-4-6`) |
| `AI_MAX_BUDGET_USD` | No | Max spend per analysis request (default: `5.0`) |
| `AI_ANALYSIS_TIMEOUT` | No | Timeout in seconds (default: `120`) |
| `LOG_LEVEL` | No | Logging level: debug/info/warning/error (default: `info`) |

### Laravel App Container (add these)

| Variable | Required | Description |
|---|---|---|
| `AI_WORKER_URL` | Yes | URL to the AI worker (e.g., `http://127.0.0.1:8001` for sidecar) |
| `AI_WORKER_SECRET` | Yes | Must match the AI worker's `AI_WORKER_SECRET` |
| `ANTHROPIC_API_KEY` | No | Not used by Laravel directly — only the AI worker needs this |

---

## Generating the Shared Secret

The `AI_WORKER_SECRET` is a random string you generate yourself. Both the Laravel app and the AI worker must have the same value:

```bash
# Generate a 64-character random secret
openssl rand -hex 32
```

Example output: `a3f8b2c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1`

Set this same value in both containers:
- Laravel: `AI_WORKER_SECRET=a3f8b2c1...`
- AI Worker: `AI_WORKER_SECRET=a3f8b2c1...`

---

## Verification

After deployment, verify the AI worker is running:

```bash
# From within the pod (sidecar) or cluster
curl http://127.0.0.1:8001/health
# Expected: {"status":"ok"}

# Test analysis endpoint (from Laravel app container)
curl -X POST http://127.0.0.1:8001/analyze \
  -H "X-AI-Worker-Secret: <your_secret>" \
  -H "Content-Type: application/json" \
  -d '{"contract_id":"test","analysis_type":"summary","file_content_base64":"dGVzdA==","file_name":"test.pdf","context":{}}'
```

### Smoke Test via Filament

1. Log into https://ccrs-sandbox.digittal.mobi/admin
2. Navigate to Contracts, open any contract with an uploaded PDF
3. Click "Run AI Analysis" > select "Summary" > submit
4. The analysis queues via Redis/Horizon and calls the AI worker
5. Check the AI Analysis tab on the contract for results

---

## Feature Flags

AI-powered features can be individually toggled:

| Feature | Env Var | Default | Requires AI Worker |
|---|---|---|---|
| Contract Analysis | Always on | - | Yes |
| Clause Redlining | `FEATURE_REDLINING` | `false` | Yes |
| Regulatory Compliance | `FEATURE_REGULATORY_COMPLIANCE` | `false` | Yes |
| Advanced Analytics | `FEATURE_ADVANCED_ANALYTICS` | `false` | No (Eloquent queries) |

Enable as needed:
```
FEATURE_REDLINING=true
FEATURE_REGULATORY_COMPLIANCE=true
```

---

## Cost Control

The AI worker enforces a per-request budget via `AI_MAX_BUDGET_USD` (default: $5.00). The `ProcessAiAnalysis` job records token usage and cost in the `ai_analysis_results` table. The Dashboard "AI Cost" widget aggregates this data.

To use cheaper models for routine analysis:
```
AI_MODEL=claude-haiku-4-5-20251001          # Simple summary/extraction
AI_AGENT_MODEL=claude-sonnet-4-6    # Complex agentic analysis
```
