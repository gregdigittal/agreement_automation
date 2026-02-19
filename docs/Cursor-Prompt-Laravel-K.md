# Cursor Prompt — Laravel Migration Phase K: Production K8s Hardening + Advanced Phase 2

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through J were executed.

This is the final phase before production deployment. It covers:

1. **K8s Production Hardening**: Horizontal Pod Autoscaler, PodDisruptionBudget, resource requests/limits, liveness/readiness probes, secrets management, and production configuration for the `deploy/k8s/` manifests created in Phase A.
2. **Advanced Phase 2 Foundations**: Scaffold two Phase 2 capabilities so architecture decisions are locked in without blocking current deployment — AI-assisted clause redlining (outline only) and regulatory compliance checking (outline only).
3. **Performance Hardening**: Laravel Octane configuration, queue worker tuning, database connection pooling, Redis session driver.
4. **Security Hardening**: Content Security Policy, HTTP security headers, rate limiting on public endpoints, S3 pre-signed URL expiry standardisation.

---

## Task 1: Kubernetes Production Manifests

All manifests are in `deploy/k8s/`. The template (`sandbox-template-laravel-filament`) provides a baseline `deployment.yaml`. Modify and extend it for production.

### 1.1 Update `deploy/k8s/deployment.yaml`

Replace the single deployment with a multi-container setup or separate deployments. At minimum, update the existing deployment with:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ccrs-app
  labels:
    app: ccrs
    component: app
spec:
  replicas: 2
  selector:
    matchLabels:
      app: ccrs
      component: app
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 0
      maxSurge: 1
  template:
    metadata:
      labels:
        app: ccrs
        component: app
    spec:
      terminationGracePeriodSeconds: 60
      containers:
        - name: app
          image: ${REGISTRY}/ccrs-app:${IMAGE_TAG}
          ports:
            - containerPort: 8080
          resources:
            requests:
              cpu: "250m"
              memory: "256Mi"
            limits:
              cpu: "1000m"
              memory: "512Mi"
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
            initialDelaySeconds: 30
            periodSeconds: 10
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 5
            failureThreshold: 3
          envFrom:
            - secretRef:
                name: ccrs-secrets
          env:
            - name: APP_ENV
              value: production
            - name: LOG_CHANNEL
              value: json
            - name: CACHE_DRIVER
              value: redis
            - name: SESSION_DRIVER
              value: redis
            - name: QUEUE_CONNECTION
              value: redis
```

### 1.2 Create `deploy/k8s/hpa.yaml`

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: ccrs-app-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: ccrs-app
  minReplicas: 2
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
```

### 1.3 Create `deploy/k8s/pdb.yaml`

```yaml
apiVersion: policy/v1
kind: PodDisruptionBudget
metadata:
  name: ccrs-app-pdb
spec:
  minAvailable: 1
  selector:
    matchLabels:
      app: ccrs
      component: app
```

### 1.4 Create `deploy/k8s/queue-worker.yaml`

A separate deployment for queue workers (does not serve HTTP — dedicated to processing jobs):

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ccrs-queue-worker
  labels:
    app: ccrs
    component: queue-worker
spec:
  replicas: 2
  selector:
    matchLabels:
      app: ccrs
      component: queue-worker
  template:
    metadata:
      labels:
        app: ccrs
        component: queue-worker
    spec:
      terminationGracePeriodSeconds: 120
      containers:
        - name: queue-worker
          image: ${REGISTRY}/ccrs-app:${IMAGE_TAG}
          command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600", "--memory=256"]
          resources:
            requests:
              cpu: "100m"
              memory: "128Mi"
            limits:
              cpu: "500m"
              memory: "256Mi"
          livenessProbe:
            exec:
              command: ["php", "artisan", "queue:health"]
            initialDelaySeconds: 30
            periodSeconds: 30
            failureThreshold: 3
          envFrom:
            - secretRef:
                name: ccrs-secrets
          env:
            - name: APP_ENV
              value: production
```

### 1.5 Create `deploy/k8s/ai-worker.yaml`

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ccrs-ai-worker
  labels:
    app: ccrs
    component: ai-worker
spec:
  replicas: 1
  selector:
    matchLabels:
      app: ccrs
      component: ai-worker
  template:
    metadata:
      labels:
        app: ccrs
        component: ai-worker
    spec:
      containers:
        - name: ai-worker
          image: ${REGISTRY}/ccrs-ai-worker:${IMAGE_TAG}
          ports:
            - containerPort: 8001
          resources:
            requests:
              cpu: "100m"
              memory: "256Mi"
            limits:
              cpu: "500m"
              memory: "512Mi"
          livenessProbe:
            httpGet:
              path: /health
              port: 8001
            initialDelaySeconds: 20
            periodSeconds: 15
          envFrom:
            - secretRef:
                name: ccrs-secrets
---
apiVersion: v1
kind: Service
metadata:
  name: ccrs-ai-worker
spec:
  selector:
    app: ccrs
    component: ai-worker
  ports:
    - port: 8001
      targetPort: 8001
  clusterIP: None   # Headless — internal only, not exposed outside cluster
```

### 1.6 Create `deploy/k8s/secrets.yaml.template`

Create a **template** (not the actual secret — never commit real values):

```yaml
# deploy/k8s/secrets.yaml.template
# Copy to secrets.yaml, fill values, apply with: kubectl apply -f secrets.yaml
# DO NOT commit secrets.yaml — it is in .gitignore
apiVersion: v1
kind: Secret
metadata:
  name: ccrs-secrets
type: Opaque
stringData:
  APP_KEY: "base64:CHANGE_ME"
  DB_HOST: "mysql-host"
  DB_PORT: "3306"
  DB_DATABASE: "ccrs"
  DB_USERNAME: "ccrs_user"
  DB_PASSWORD: "CHANGE_ME"
  REDIS_HOST: "redis-host"
  REDIS_PASSWORD: "CHANGE_ME"
  AWS_ACCESS_KEY_ID: "CHANGE_ME"
  AWS_SECRET_ACCESS_KEY: "CHANGE_ME"
  AWS_DEFAULT_REGION: "ap-southeast-1"
  AWS_BUCKET: "ccrs-documents"
  AZURE_AD_CLIENT_ID: "CHANGE_ME"
  AZURE_AD_CLIENT_SECRET: "CHANGE_ME"
  AZURE_AD_TENANT_ID: "CHANGE_ME"
  BOLDSIGN_API_KEY: "CHANGE_ME"
  BOLDSIGN_WEBHOOK_SECRET: "CHANGE_ME"
  ANTHROPIC_API_KEY: "CHANGE_ME"
  AI_WORKER_SECRET: "CHANGE_ME"
  TITO_API_KEY: "CHANGE_ME"
  MEILISEARCH_KEY: "CHANGE_ME"
  TEAMS_TEAM_ID: ""
  TEAMS_CHANNEL_ID: ""
```

Add `deploy/k8s/secrets.yaml` to `.gitignore`.

### 1.7 Add Health Check Endpoints

In `routes/web.php`:

```php
// Kubernetes liveness probe — returns 200 if app is running
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
})->name('health');

// Kubernetes readiness probe — returns 200 only if DB and Redis are reachable
Route::get('/health/ready', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        \Illuminate\Support\Facades\Redis::connection()->ping();
        return response()->json(['status' => 'ready']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'not_ready', 'error' => $e->getMessage()], 503);
    }
})->name('health.ready');
```

---

## Task 2: Performance Hardening

### 2.1 Configure Redis for Sessions, Cache, and Queues

In `.env.example` (and confirm in production secrets):

```dotenv
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

In `config/database.php`, ensure the Redis connection is configured. Use separate Redis databases for cache, sessions, and queues to avoid key collisions:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'cache' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port'     => env('REDIS_PORT', 6379),
        'database' => 1, // DB 1 for cache
    ],
    'queue' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port'     => env('REDIS_PORT', 6379),
        'database' => 2, // DB 2 for queues
    ],
    'sessions' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port'     => env('REDIS_PORT', 6379),
        'database' => 3, // DB 3 for sessions
    ],
],
```

### 2.2 Horizon Queue Configuration

In `config/horizon.php`, configure environments for production:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'maxProcesses'  => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'maxProcesses' => 3,
        ],
    ],
],
```

### 2.3 Eager Load Critical Relationships

Review and add `with()` eager loading on the most-accessed Filament Resources to eliminate N+1 queries:

In `ContractResource::getEloquentQuery()`:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['counterparty', 'region', 'entity', 'project']);
}
```

Apply the same pattern to `CounterpartyResource` (eager load `contacts`) and `WorkflowTemplateResource`.

### 2.4 Database Indexes

Create `database/migrations/XXXX_add_performance_indexes.php`:

```php
public function up(): void
{
    // Most common contract list query patterns
    Schema::table('contracts', function (Blueprint $table) {
        $table->index(['workflow_state', 'created_at']);
        $table->index(['counterparty_id', 'workflow_state']);
        $table->index(['region_id', 'entity_id', 'project_id']);
        $table->fullText(['title']); // For Meilisearch fallback
    });

    Schema::table('audit_log', function (Blueprint $table) {
        $table->index(['resource_type', 'resource_id']);
        $table->index(['actor_id', 'at']);
    });

    Schema::table('notifications', function (Blueprint $table) {
        $table->index(['recipient_user_id', 'status', 'created_at']);
    });

    Schema::table('reminders', function (Blueprint $table) {
        $table->index(['next_due_at', 'is_active']);
    });
}
```

---

## Task 3: Security Hardening

### 3.1 HTTP Security Headers Middleware

Create `app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private array $headers = [
        'X-Content-Type-Options'    => 'nosniff',
        'X-Frame-Options'           => 'SAMEORIGIN',
        'X-XSS-Protection'          => '1; mode=block',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Content Security Policy — adjust nonce if using inline scripts
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .  // Filament needs unsafe-inline
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' data:; " .
            "connect-src 'self' https://graph.microsoft.com https://login.microsoftonline.com;"
        );

        return $response;
    }
}
```

Register globally in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

### 3.2 Rate Limiting on Public Endpoints

In `bootstrap/app.php`, configure rate limits:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleApi();

    // TiTo endpoint: 200 req/min per IP (POS terminals may burst)
    \Illuminate\Support\Facades\RateLimiter::for('tito', function ($request) {
        return \Illuminate\Cache\RateLimiting\Limit::perMinute(200)->by($request->ip());
    });

    // Magic link request: 5 req/min per IP (prevent enumeration)
    \Illuminate\Support\Facades\RateLimiter::for('magic-link', function ($request) {
        return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
    });
})
```

Apply `throttle:tito` to the TiTo route in `routes/api.php`:
```php
Route::middleware(['tito.auth', 'throttle:tito'])->group(function () {
    Route::get('/tito/validate', [TitoController::class, 'validate']);
});
```

Apply `throttle:magic-link` to the magic link POST route in `routes/web.php`.

### 3.3 Standardise S3 Pre-Signed URL Expiry

Create a helper in `app/Helpers/StorageHelper.php`:

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Generate a pre-signed S3 URL with a consistent expiry window.
     * Adjusts expiry based on sensitivity:
     *   - 'download' → 10 minutes (user-triggered file download)
     *   - 'preview'  → 2 minutes  (inline browser preview)
     *   - 'api'      → 30 seconds (API response to frontend)
     */
    public static function temporaryUrl(string $path, string $context = 'download'): string
    {
        $minutes = match ($context) {
            'preview' => 2,
            'api'     => 0.5,
            default   => 10,
        };

        return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
```

Find all `Storage::disk('s3')->temporaryUrl(...)` calls in the codebase and replace with `StorageHelper::temporaryUrl($path, 'download')` or the appropriate context.

---

## Task 4: Phase 2 Feature Scaffolding

### 4.1 Clause Redlining — Architecture Scaffold

Create `app/Services/RedlineService.php` as a documented scaffold (not implemented):

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\WikiContract;

/**
 * RedlineService — Phase 2: AI-assisted clause negotiation and redlining.
 *
 * ARCHITECTURE:
 * This service will use the AI Worker microservice (ai-worker/) to:
 * 1. Parse a DOCX contract into clauses using python-docx
 * 2. Compare each clause against the matching WikiContract template
 * 3. Generate AI-suggested redline changes using Claude (structured diff output)
 * 4. Store redline suggestions in a new `redline_suggestions` table
 * 5. Allow legal users to accept/reject/modify suggestions in Filament
 *
 * PHASE 2 TABLES NEEDED:
 *   redline_sessions:  id, contract_id, wiki_contract_id, status, created_by, created_at
 *   redline_clauses:   id, session_id, clause_number, original_text, suggested_text,
 *                      change_type (addition/deletion/modification), ai_rationale,
 *                      status (pending/accepted/rejected/modified), reviewed_by
 *
 * PHASE 2 FILAMENT ADDITIONS NEEDED:
 *   - ContractResource: "Redline" action → opens RedlineSessionPage
 *   - RedlineSessionPage: side-by-side diff view with accept/reject per clause
 *
 * NOT IMPLEMENTED IN THIS PHASE — stub only.
 */
class RedlineService
{
    /**
     * @throws \RuntimeException Phase 2 feature not yet implemented.
     */
    public function startRedlineSession(Contract $contract, WikiContract $template): never
    {
        throw new \RuntimeException(
            'Clause redlining is a Phase 2 feature. ' .
            'See docs/Cursor-Prompt-Laravel-K.md Task 4.1 for the implementation architecture.'
        );
    }
}
```

Create the Phase 2 migrations as **commented-out stubs** in `database/migrations/XXXX_phase2_redline_schema.php`:

```php
// Phase 2 migration — commented out until Phase 2 begins
// public function up(): void
// {
//     Schema::create('redline_sessions', function (Blueprint $table) {
//         $table->char('id', 36)->primary();
//         $table->char('contract_id', 36)->index();
//         $table->char('wiki_contract_id', 36)->nullable();
//         $table->string('status', 20)->default('pending'); // pending/processing/completed
//         $table->char('created_by', 36)->nullable();
//         $table->timestamps();
//     });
//
//     Schema::create('redline_clauses', function (Blueprint $table) {
//         $table->char('id', 36)->primary();
//         $table->char('session_id', 36)->index();
//         $table->unsignedSmallInteger('clause_number');
//         $table->longText('original_text');
//         $table->longText('suggested_text')->nullable();
//         $table->string('change_type', 20); // addition/deletion/modification/unchanged
//         $table->text('ai_rationale')->nullable();
//         $table->string('status', 20)->default('pending'); // pending/accepted/rejected/modified
//         $table->char('reviewed_by', 36)->nullable();
//         $table->timestamps();
//     });
// }
```

### 4.2 Regulatory Compliance — Architecture Scaffold

Create `app/Services/RegulatoryComplianceService.php` as a scaffold:

```php
<?php

namespace App\Services;

use App\Models\Contract;

/**
 * RegulatoryComplianceService — Phase 2: Regulatory compliance checking.
 *
 * ARCHITECTURE:
 * This service will use the AI Worker to:
 * 1. Identify the contract's jurisdiction from extracted fields (entity.region → country)
 * 2. Fetch applicable regulatory frameworks from a `regulatory_frameworks` table
 *    (maintained by System Admin: jurisdiction, framework_name, requirements_json)
 * 3. Run an AI check of the contract text against each applicable requirement
 * 4. Store findings in `compliance_findings` (contract_id, framework_id, requirement, status, evidence)
 * 5. Display findings in a "Compliance" tab on ContractResource
 *
 * PHASE 2 TABLES NEEDED:
 *   regulatory_frameworks:  id, jurisdiction_code, framework_name, requirements_json, is_active
 *   compliance_findings:    id, contract_id, framework_id, requirement_text,
 *                           status (compliant/non_compliant/unclear), evidence_clause,
 *                           ai_rationale, reviewed_by, reviewed_at
 *
 * NOT IMPLEMENTED IN THIS PHASE — stub only.
 */
class RegulatoryComplianceService
{
    /**
     * @throws \RuntimeException Phase 2 feature not yet implemented.
     */
    public function runComplianceCheck(Contract $contract): never
    {
        throw new \RuntimeException(
            'Regulatory compliance checking is a Phase 2 feature. ' .
            'See docs/Cursor-Prompt-Laravel-K.md Task 4.2 for the implementation architecture.'
        );
    }
}
```

### 4.3 Feature Flag System for Phase 2

Create `config/features.php` to gate Phase 2 features:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    | Set to true when the feature is ready for production use.
    | Phase 2 features are disabled by default.
    */

    'redlining'               => env('FEATURE_REDLINING', false),
    'regulatory_compliance'   => env('FEATURE_REGULATORY_COMPLIANCE', false),
    'advanced_analytics'      => env('FEATURE_ADVANCED_ANALYTICS', false),
    'vendor_portal'           => env('FEATURE_VENDOR_PORTAL', true),   // Phase J
    'meilisearch'             => env('FEATURE_MEILISEARCH', false),     // Enable when Meilisearch is deployed
];
```

Create a `Feature` facade helper in `app/Helpers/Feature.php`:

```php
<?php

namespace App\Helpers;

class Feature
{
    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }
}
```

Gate the Vendor Panel in `VendorPanelProvider::panel()`:
```php
if (Feature::disabled('vendor_portal')) {
    // Return minimal panel with maintenance page
}
```

Gate Meilisearch scout driver in `config/scout.php`:
```php
'driver' => Feature::enabled('meilisearch') ? env('SCOUT_DRIVER', 'meilisearch') : 'null',
```

---

## Verification Checklist

1. **K8s Manifests:**
   - `kubectl apply -f deploy/k8s/deployment.yaml` — applies without error.
   - `kubectl apply -f deploy/k8s/hpa.yaml` — HPA created; `kubectl get hpa` shows minReplicas=2.
   - `kubectl apply -f deploy/k8s/pdb.yaml` — PDB created; `kubectl get pdb` shows minAvailable=1.
   - `kubectl apply -f deploy/k8s/queue-worker.yaml` — queue worker deployment created.
   - `deploy/k8s/secrets.yaml` is in `.gitignore` and does NOT appear in `git status`.

2. **Health Checks:**
   - `curl http://localhost:8080/health` → `{"status":"ok",...}` with HTTP 200.
   - `curl http://localhost:8080/health/ready` → `{"status":"ready"}` when MySQL and Redis are running.
   - `curl http://localhost:8080/health/ready` → HTTP 503 when MySQL is stopped.

3. **Rate Limiting:**
   - Send 201 requests to `/api/tito/validate` within one minute from the same IP → 201st returns HTTP 429.
   - Send 6 magic link requests within one minute → 6th returns HTTP 429.

4. **Security Headers:**
   - `curl -I http://localhost:8080/admin` — response includes `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Strict-Transport-Security`.
   - `Content-Security-Policy` header present.

5. **Feature Flags:**
   - `config('features.redlining')` returns `false` by default.
   - Setting `FEATURE_REDLINING=true` in `.env` and running `php artisan config:cache` changes the value.

6. **Performance:**
   - `php artisan migrate` — performance indexes migration runs without error.
   - `php artisan horizon` starts and shows workers processing jobs.
   - `CACHE_DRIVER=redis` confirmed in production `.env`.

7. **Phase 2 Scaffolds:**
   - `app(RedlineService::class)->startRedlineSession(...)` throws `RuntimeException` with architecture instructions.
   - `app(RegulatoryComplianceService::class)->runComplianceCheck(...)` throws `RuntimeException` with architecture instructions.
   - No Phase 2 migration tables are created (they are commented out).
