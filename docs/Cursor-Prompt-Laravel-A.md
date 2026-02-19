# Cursor Prompt — Laravel Migration Phase A: Scaffold

## Context

The CCRS (Contract & Merchant Agreement Repository System) is being ported from Python FastAPI + Next.js + Supabase/PostgreSQL to **PHP 8.3 + Laravel 11 + Laravel Filament 3 + MySQL 8+**. This is Phase A (scaffold) — it lays the entire structural foundation but does not implement any business logic yet.

The source of truth for the database schema is `supabase/migrations/` in this repo. All 27 tables must be translated from PostgreSQL to MySQL following the type-mapping rules below.

---

## ⚠️ IMPORTANT: Where to Run This Prompt

**Run this prompt in the current `agreement_automation` repo on the `laravel-migration` branch.** Do not clone a separate repo.

```bash
# Confirm you are on the correct branch before starting
git checkout laravel-migration
```

The production deployment target is `digittaldotio/digittal-ccrs` (not yet provisioned). All work is done here on `laravel-migration` until the CTO provisions that repo.

---

## ⚠️ IMPORTANT: Pre-requisite — Fetch Template Infrastructure Files

The CTO's deployment template (`digittaldotio/sandbox-template-laravel-filament`) provides a pre-wired `Dockerfile`, nginx, supervisor, and Kubernetes manifests. Fetch these files into the repo root **before** running any other task:

```bash
# From the repo root on laravel-migration branch
git remote add template https://github.com/digittaldotio/sandbox-template-laravel-filament.git
git fetch template main
git checkout template/main -- Dockerfile docker/ deploy/ Jenkinsfile
git remote remove template
```

After this step the repo root will contain:

| Template file | Status |
|---|---|
| `Dockerfile` | ✅ **DO NOT recreate** — multi-stage PHP 8.3, port 8080 |
| `docker/nginx/nginx.conf` + `default.conf` | ✅ **DO NOT recreate** — already listens on port 8080 |
| `docker/php/` configs | ✅ **DO NOT recreate** |
| `docker/docker-entrypoint.sh` | ✅ **DO NOT recreate** — runs `php artisan migrate --force` + `filament:assets` on boot |
| `docker/supervisor/supervisord.conf` | ⚠️ **EXTEND** — add queue worker and scheduler (see Task 9) |
| `deploy/k8s/deployment.yaml` | ⚠️ **UPDATE** — add MySQL/Redis env vars (see Task 9) |
| `Jenkinsfile` | ✅ **DO NOT touch** — auto-builds and deploys to Kubernetes |

**The Laravel application code lives at the repository root** — not in a `laravel/` subdirectory. All file paths in this prompt are relative to the repo root.

**Port is 8080** everywhere (the template's nginx and Docker EXPOSE use 8080, not 8000).

For **local development**, create a `docker-compose.yml` at the repo root that uses the template's Dockerfile but adds MySQL and Redis sidecars.

---

## PostgreSQL → MySQL Type Mapping Rules

Apply these rules throughout every migration file:

| PostgreSQL | MySQL 8+ Laravel Schema Builder |
|---|---|
| `UUID PRIMARY KEY DEFAULT gen_random_uuid()` | `$table->uuid('id')->primary()->default(DB::raw('(UUID())'))` |
| `UUID` (FK) | `$table->uuid('region_id')` then `$table->foreign('region_id')->references('id')->on('regions')...` |
| `TIMESTAMPTZ NOT NULL DEFAULT now()` | `$table->timestamp('created_at')->useCurrent()` |
| `TIMESTAMPTZ NULL` | `$table->timestamp('field')->nullable()` |
| `JSONB` / `JSON` | `$table->json('field')` — cast to `array` in Eloquent |
| `TEXT[]` / `INTEGER[]` | `$table->json('field')->default(new Expression("('[]')"))`  |
| `BOOLEAN DEFAULT false` | `$table->boolean('field')->default(false)` |
| `TINYINT(1)` | same as boolean in Laravel |
| `TEXT` | `$table->text('field')` or `$table->string('field')` for ≤255 chars |
| `TSVECTOR` + GIN index | **Omit entirely** — add `$table->fullText(['title','contract_type'])` on contracts instead |
| Partial unique index `WHERE state='active'` | **Omit** — enforce at app layer in `WorkflowService` |
| PostgreSQL `updated_at` triggers | **Omit** — Eloquent `$timestamps = true` handles this |
| PostgreSQL-specific functions | **Omit** — not supported in MySQL |

**Critical Eloquent model requirements for all models:**
```php
protected $keyType = 'string';
public $incrementing = false;
```
Use a shared `HasUuidPrimaryKey` trait (create at `app/Traits/HasUuidPrimaryKey.php`) that all models use.

---

## Task 1: Create the Laravel Application

**Run from the repo root** (the cloned `digittal-ccrs` directory). Install Laravel into the current directory — the template files (Dockerfile, Jenkinsfile, docker/, deploy/) must be preserved.

```bash
# Install Laravel into the current directory, keeping existing files
composer create-project laravel/laravel . --prefer-dist --no-interaction

# Note: composer will warn about the non-empty directory — that is expected.
# It will create Laravel's standard file structure alongside the template files.
```

If composer refuses to install into a non-empty directory, use this alternative:
```bash
# Create in a temp dir then merge
composer create-project laravel/laravel /tmp/ccrs-laravel --prefer-dist
# Copy Laravel files to repo root, excluding Dockerfile/Jenkinsfile/docker/deploy
rsync -av --exclude='Dockerfile' --exclude='Jenkinsfile' --exclude='docker' --exclude='deploy' --exclude='.git' /tmp/ccrs-laravel/ .
```

---

## Task 2: Install Composer Dependencies

Update `composer.json` `require` section to include all packages, then run `composer install`:

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "filament/filament": "^3.2",
        "bezhansalleh/filament-shield": "^3.2",
        "socialiteproviders/microsoft-azure": "^5.1",
        "laravel/socialite": "^5.13",
        "spatie/laravel-permission": "^6.7",
        "spatie/laravel-medialibrary": "^11.0",
        "filament/spatie-laravel-media-library-plugin": "^3.2",
        "league/flysystem-aws-s3-v3": "^3.25",
        "laravel/horizon": "^5.26",
        "laravel/telescope": "^5.2",
        "guzzlehttp/guzzle": "^7.9",
        "maatwebsite/excel": "^3.1",
        "barryvdh/laravel-dompdf": "^2.2"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.17",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.4",
        "pestphp/pest": "^3.5",
        "pestphp/pest-plugin-laravel": "^3.1"
    }
}
```

After installing, run:
```bash
php artisan filament:install --panels
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="BezhanSalleh\FilamentShield\FilamentShieldServiceProvider"
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
php artisan vendor:publish --provider="Laravel\Telescope\TelescopeServiceProvider"
```

---

## Task 3: Configure the Application

### `.env.example` (update the template's existing file — do not create a new one)
```
APP_NAME="CCRS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ccrs
DB_USERNAME=ccrs
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@ccrs.digittal.com
MAIL_FROM_NAME="CCRS"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-2
AWS_BUCKET=ccrs-contracts
AWS_ENDPOINT=

AZURE_AD_CLIENT_ID=
AZURE_AD_CLIENT_SECRET=
AZURE_AD_TENANT_ID=
AZURE_AD_GROUP_SYSTEM_ADMIN=
AZURE_AD_GROUP_LEGAL=
AZURE_AD_GROUP_COMMERCIAL=
AZURE_AD_GROUP_FINANCE=
AZURE_AD_GROUP_OPERATIONS=
AZURE_AD_GROUP_AUDIT=

BOLDSIGN_API_KEY=
BOLDSIGN_API_URL=https://api.boldsign.com
BOLDSIGN_WEBHOOK_SECRET=

AI_WORKER_URL=http://ai-worker:8001
AI_WORKER_SECRET=

ANTHROPIC_API_KEY=
```

### `config/ccrs.php` (create this file)
```php
<?php

return [
    'ai_worker_url' => env('AI_WORKER_URL', 'http://ai-worker:8001'),
    'ai_worker_secret' => env('AI_WORKER_SECRET', ''),
    'ai_analysis_timeout' => env('AI_ANALYSIS_TIMEOUT', 120),
    'boldsign_api_key' => env('BOLDSIGN_API_KEY', ''),
    'boldsign_api_url' => env('BOLDSIGN_API_URL', 'https://api.boldsign.com'),
    'boldsign_webhook_secret' => env('BOLDSIGN_WEBHOOK_SECRET', ''),
    'azure_ad' => [
        'group_map' => [
            env('AZURE_AD_GROUP_SYSTEM_ADMIN') => 'system_admin',
            env('AZURE_AD_GROUP_LEGAL') => 'legal',
            env('AZURE_AD_GROUP_COMMERCIAL') => 'commercial',
            env('AZURE_AD_GROUP_FINANCE') => 'finance',
            env('AZURE_AD_GROUP_OPERATIONS') => 'operations',
            env('AZURE_AD_GROUP_AUDIT') => 'audit',
        ],
    ],
    'contracts_disk' => 's3',
    'wiki_contracts_disk' => 's3',
];
```

### `config/filesystems.php` — Add S3 disk config
In the `disks` array, add (or update the `s3` disk):
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-2'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => true,
],
```

### `config/services.php` — Add Azure AD and BoldSign
```php
'azure' => [
    'client_id' => env('AZURE_AD_CLIENT_ID'),
    'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
    'redirect' => env('APP_URL') . '/auth/azure/callback',
    'tenant' => env('AZURE_AD_TENANT_ID'),
],
```

---

## Task 4: Create All Laravel Database Migrations

Create the following migration files in `database/migrations/`. **Use exact timestamps shown so foreign key order is preserved.**

### 2026_02_20_000001_create_regions_table.php
```php
Schema::create('regions', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->string('name');
    $table->string('code')->unique()->nullable();
    $table->timestamps();
});
```

### 2026_02_20_000002_create_entities_table.php
```php
Schema::create('entities', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('region_id');
    $table->string('name');
    $table->string('code')->nullable();
    $table->timestamps();
    $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
    $table->unique(['region_id', 'code']);
});
```

### 2026_02_20_000003_create_projects_table.php
```php
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('entity_id');
    $table->string('name');
    $table->string('code')->nullable();
    $table->timestamps();
    $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
    $table->unique(['entity_id', 'code']);
});
```

### 2026_02_20_000004_create_counterparties_table.php
```php
Schema::create('counterparties', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->string('legal_name');
    $table->string('registration_number')->nullable();
    $table->text('address')->nullable();
    $table->string('jurisdiction')->nullable();
    $table->enum('status', ['Active', 'Suspended', 'Blacklisted'])->default('Active');
    $table->text('status_reason')->nullable();
    $table->timestamp('status_changed_at')->nullable();
    $table->string('status_changed_by')->nullable();
    $table->string('preferred_language', 10)->default('en');
    $table->timestamps();
});
```

### 2026_02_20_000005_create_counterparty_contacts_table.php
```php
Schema::create('counterparty_contacts', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('counterparty_id');
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('role')->nullable();
    $table->boolean('is_signer')->default(false);
    $table->timestamps();
    $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
});
```

### 2026_02_20_000006_create_contracts_table.php
```php
Schema::create('contracts', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('region_id');
    $table->uuid('entity_id');
    $table->uuid('project_id');
    $table->uuid('counterparty_id');
    $table->uuid('parent_contract_id')->nullable();
    $table->enum('contract_type', ['Commercial', 'Merchant']);
    $table->string('title')->nullable();
    $table->string('workflow_state', 50)->default('draft');
    $table->string('signing_status', 50)->nullable();
    $table->string('storage_path')->nullable();
    $table->string('file_name')->nullable();
    $table->integer('file_version')->default(1);
    $table->string('sharepoint_url')->nullable();
    $table->string('sharepoint_version')->nullable();
    $table->string('created_by')->nullable();
    $table->string('updated_by')->nullable();
    $table->timestamps();
    $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
    $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
    $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
    $table->foreign('counterparty_id')->references('id')->on('counterparties')->restrictOnDelete();
    $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();
    $table->index(['region_id', 'entity_id', 'project_id']);
    $table->index('counterparty_id');
    $table->index('workflow_state');
    $table->fullText(['title', 'contract_type']); // replaces TSVECTOR
});
```

### 2026_02_20_000007_create_audit_log_table.php
```php
Schema::create('audit_log', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->timestamp('at')->useCurrent();
    $table->string('actor_id')->nullable();
    $table->string('actor_email')->nullable();
    $table->string('action');
    $table->string('resource_type');
    $table->string('resource_id')->nullable();
    $table->json('details')->nullable();
    $table->string('ip_address')->nullable();
    // No updated_at — audit log is immutable
    $table->index('at');
    $table->index(['resource_type', 'resource_id']);
    $table->index('actor_id');
});
```

### 2026_02_20_000008_create_signing_authority_table.php
```php
Schema::create('signing_authority', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('entity_id');
    $table->uuid('project_id')->nullable();
    $table->string('user_id');
    $table->string('user_email')->nullable();
    $table->string('role_or_name');
    $table->string('contract_type_pattern')->nullable();
    $table->timestamps();
    $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
    $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
    $table->index('entity_id');
});
```

### 2026_02_20_000009_create_wiki_contracts_table.php
```php
Schema::create('wiki_contracts', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->string('name');
    $table->string('category')->nullable();
    $table->uuid('region_id')->nullable();
    $table->integer('version')->default(1);
    $table->enum('status', ['draft', 'review', 'published', 'deprecated'])->default('draft');
    $table->string('storage_path')->nullable();
    $table->string('file_name')->nullable();
    $table->text('description')->nullable();
    $table->string('created_by')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
    $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
    $table->index('status');
});
```

### 2026_02_20_000010_create_workflow_templates_table.php
```php
Schema::create('workflow_templates', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->string('name');
    $table->enum('contract_type', ['Commercial', 'Merchant']);
    $table->uuid('region_id')->nullable();
    $table->uuid('entity_id')->nullable();
    $table->uuid('project_id')->nullable();
    $table->integer('version')->default(1);
    $table->enum('status', ['draft', 'published', 'deprecated'])->default('draft');
    $table->json('stages')->default(new \Illuminate\Database\Query\Expression("('[]')"));
    $table->json('validation_errors')->nullable();
    $table->string('created_by')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
    $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
    $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
    $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
    $table->unique(['name', 'version']);
    $table->index('status');
    $table->index('contract_type');
});
```

### 2026_02_20_000011_create_workflow_instances_table.php
```php
Schema::create('workflow_instances', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->uuid('template_id');
    $table->integer('template_version');
    $table->string('current_stage', 100);
    $table->enum('state', ['active', 'completed', 'cancelled'])->default('active');
    $table->timestamp('started_at')->useCurrent();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('template_id')->references('id')->on('workflow_templates')->restrictOnDelete();
    // Note: partial unique index WHERE state='active' is NOT possible in MySQL.
    // Enforced at application layer in WorkflowService::startWorkflow() using DB::transaction + lockForUpdate()
    $table->index(['contract_id', 'state']);
    $table->index('state');
});
```

### 2026_02_20_000012_create_workflow_stage_actions_table.php
```php
Schema::create('workflow_stage_actions', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('instance_id');
    $table->string('stage_name', 100);
    $table->enum('action', ['approve', 'reject', 'rework', 'skip']);
    $table->string('actor_id')->nullable();
    $table->string('actor_email')->nullable();
    $table->text('comment')->nullable();
    $table->json('artifacts')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
    $table->index('instance_id');
});
```

### 2026_02_20_000013_create_boldsign_envelopes_table.php
```php
Schema::create('boldsign_envelopes', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->string('boldsign_document_id')->unique()->nullable();
    $table->enum('status', ['draft','sent','viewed','partially_signed','completed','declined','expired','voided'])->default('draft');
    $table->enum('signing_order', ['parallel', 'sequential'])->default('sequential');
    $table->json('signers')->default(new \Illuminate\Database\Query\Expression("('[]')"));
    $table->json('webhook_payload')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->index('contract_id');
    $table->index('boldsign_document_id');
});
```

### 2026_02_20_000014_create_contract_links_table.php
```php
Schema::create('contract_links', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('parent_contract_id');
    $table->uuid('child_contract_id');
    $table->enum('link_type', ['amendment', 'renewal', 'side_letter', 'addendum']);
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('parent_contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('child_contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->unique(['parent_contract_id', 'child_contract_id']);
    $table->index('parent_contract_id');
    $table->index('child_contract_id');
});
```

### 2026_02_20_000015_create_contract_key_dates_table.php
```php
Schema::create('contract_key_dates', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->string('date_type', 100);
    $table->date('date_value');
    $table->text('description')->nullable();
    $table->json('reminder_days')->nullable(); // was INTEGER[]
    $table->boolean('is_verified')->default(false);
    $table->string('verified_by')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->index('contract_id');
    $table->index('date_value');
});
```

### 2026_02_20_000016_create_merchant_agreement_inputs_table.php
```php
Schema::create('merchant_agreement_inputs', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->uuid('template_id')->nullable();
    $table->string('vendor_name');
    $table->string('merchant_fee')->nullable();
    $table->json('region_terms')->nullable();
    $table->timestamp('generated_at')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('template_id')->references('id')->on('wiki_contracts')->nullOnDelete();
});
```

### 2026_02_20_000017_create_ai_tables.php
```php
// ai_analysis_results
Schema::create('ai_analysis_results', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->enum('analysis_type', ['summary', 'extraction', 'risk', 'deviation', 'obligations']);
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
    $table->json('result')->nullable();
    $table->json('evidence')->nullable();
    $table->double('confidence_score')->nullable();
    $table->string('model_used')->nullable();
    $table->integer('token_usage_input')->nullable();
    $table->integer('token_usage_output')->nullable();
    $table->decimal('cost_usd', 10, 6)->nullable();
    $table->integer('processing_time_ms')->nullable();
    $table->decimal('agent_budget_usd', 10, 4)->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->index('contract_id');
    $table->index('analysis_type');
    $table->index('status');
});

// ai_extracted_fields
Schema::create('ai_extracted_fields', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->uuid('analysis_id');
    $table->string('field_name', 100);
    $table->text('field_value')->nullable();
    $table->text('evidence_clause')->nullable();
    $table->integer('evidence_page')->nullable();
    $table->double('confidence')->nullable();
    $table->boolean('is_verified')->default(false);
    $table->string('verified_by')->nullable();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('analysis_id')->references('id')->on('ai_analysis_results')->cascadeOnDelete();
    $table->index('contract_id');
    $table->index('analysis_id');
});
```

### 2026_02_20_000018_create_obligations_register_table.php
```php
Schema::create('obligations_register', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->uuid('analysis_id')->nullable();
    $table->enum('obligation_type', ['reporting', 'sla', 'insurance', 'deliverable', 'payment', 'other']);
    $table->text('description');
    $table->date('due_date')->nullable();
    $table->enum('recurrence', ['once', 'daily', 'weekly', 'monthly', 'quarterly', 'annually'])->nullable();
    $table->string('responsible_party')->nullable();
    $table->enum('status', ['active', 'completed', 'waived', 'overdue'])->default('active');
    $table->text('evidence_clause')->nullable();
    $table->double('confidence')->nullable();
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('analysis_id')->references('id')->on('ai_analysis_results')->nullOnDelete();
    $table->index('contract_id');
    $table->index('status');
    $table->index('due_date');
});
```

### 2026_02_20_000019_create_reminders_table.php
```php
Schema::create('reminders', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->uuid('key_date_id')->nullable();
    $table->enum('reminder_type', ['expiry', 'renewal_notice', 'payment', 'sla', 'obligation', 'custom']);
    $table->integer('lead_days');
    $table->enum('channel', ['email', 'teams', 'calendar'])->default('email');
    $table->string('recipient_email')->nullable();
    $table->string('recipient_user_id')->nullable();
    $table->timestamp('last_sent_at')->nullable();
    $table->timestamp('next_due_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->foreign('key_date_id')->references('id')->on('contract_key_dates')->cascadeOnDelete();
    $table->index('contract_id');
    $table->index(['next_due_at', 'is_active']);
});
```

### 2026_02_20_000020_create_escalation_tables.php
```php
// escalation_rules
Schema::create('escalation_rules', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('workflow_template_id');
    $table->string('stage_name', 100);
    $table->integer('sla_breach_hours');
    $table->integer('tier')->default(1);
    $table->string('escalate_to_role')->nullable();
    $table->string('escalate_to_user_id')->nullable();
    $table->timestamps();
    $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
    $table->index('workflow_template_id');
});

// escalation_events
Schema::create('escalation_events', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('workflow_instance_id');
    $table->uuid('rule_id');
    $table->uuid('contract_id');
    $table->string('stage_name', 100);
    $table->integer('tier');
    $table->timestamp('escalated_at')->useCurrent();
    $table->timestamp('resolved_at')->nullable();
    $table->string('resolved_by')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('workflow_instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
    $table->foreign('rule_id')->references('id')->on('escalation_rules')->cascadeOnDelete();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->index('workflow_instance_id');
    $table->index(['contract_id', 'resolved_at']);
});
```

### 2026_02_20_000021_create_notifications_table.php
```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->string('recipient_email')->nullable();
    $table->string('recipient_user_id')->nullable();
    $table->enum('channel', ['email', 'teams'])->default('email');
    $table->string('subject');
    $table->text('body')->nullable();
    $table->string('related_resource_type')->nullable();
    $table->string('related_resource_id')->nullable();
    $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->index('status');
    $table->index('recipient_email');
    $table->index('read_at');
});
```

### 2026_02_20_000022_create_contract_languages_table.php
```php
Schema::create('contract_languages', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('contract_id');
    $table->string('language_code', 10);
    $table->boolean('is_primary')->default(false);
    $table->string('storage_path')->nullable();
    $table->string('file_name')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
    $table->unique(['contract_id', 'language_code']);
    $table->index('contract_id');
});
```

### 2026_02_20_000023_create_override_requests_table.php
```php
Schema::create('override_requests', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('counterparty_id');
    $table->string('contract_title');
    $table->string('requested_by_email');
    $table->text('reason');
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->string('decided_by')->nullable();
    $table->timestamp('decided_at')->nullable();
    $table->text('comment')->nullable();
    $table->timestamps();
    $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
    $table->index(['status']);
});
```

### 2026_02_20_000024_create_counterparty_merges_table.php
```php
Schema::create('counterparty_merges', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
    $table->uuid('source_counterparty_id');
    $table->uuid('target_counterparty_id');
    $table->string('merged_by');
    $table->string('merged_by_email')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('target_counterparty_id')->references('id')->on('counterparties');
});
```

### 2026_02_20_000025_create_users_table.php

**Important:** Laravel has a default `users` migration. **Delete the default `create_users_table.php`** that `create-project` generates and replace it with this one. Also delete `create_password_reset_tokens_table.php` and `create_sessions_table.php` default migrations (sessions will use Redis; passwords are not used — Azure AD only).

```php
Schema::create('users', function (Blueprint $table) {
    $table->string('id')->primary(); // Azure AD Object ID (string UUID)
    $table->string('email')->unique()->nullable();
    $table->string('name')->nullable();
    $table->json('roles')->default(new \Illuminate\Database\Query\Expression("('[]')")); // legacy; Spatie manages roles
    $table->timestamps();
});
```

After creating this migration, update `app/Models/User.php`:
- Remove `$incrementing` default (already string PK)
- Add `protected $keyType = 'string'; public $incrementing = false;`
- Change `$fillable` to `['id', 'email', 'name']`
- Add `HasRoles` trait from `Spatie\Permission\Traits\HasRoles`
- Add `HasUuidPrimaryKey` trait (see below)
- Remove `password`, `remember_token` from fillable/hidden (not needed with Azure AD auth)

---

## Task 5: Create the `HasUuidPrimaryKey` Trait

Create `app/Traits/HasUuidPrimaryKey.php`:
```php
<?php

namespace App\Traits;

trait HasUuidPrimaryKey
{
    public function initializeHasUuidPrimaryKey(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
```

---

## Task 6: Create All 27 Eloquent Models

Create these models in `app/Models/`. Every model must:
1. Use `HasUuidPrimaryKey` trait
2. Have correct `$fillable` array
3. Have `$casts` for JSON columns (`array`), boolean columns (`bool`), date columns (`date`/`datetime`)
4. Have all relationship methods

### Models to create with their key relationships:

**Region** — hasMany: entities, contracts, wikiContracts, workflowTemplates

**Entity** — belongsTo: region; hasMany: projects, contracts, signingAuthorities, workflowTemplates

**Project** — belongsTo: entity (→ region via entity); hasMany: contracts, signingAuthorities

**Counterparty** — hasMany: contacts (CounterpartyContact), contracts, overrideRequests, counterpartyMerges (as target)

**CounterpartyContact** — belongsTo: counterparty

**Contract** — belongsTo: region, entity, project, counterparty, parentContract (self); hasMany: keyDates, reminders, obligations (ObligationsRegister), languages (ContractLanguage), links (via ContractLink parent+child), aiAnalyses (AiAnalysisResult), boldsignEnvelopes, workflowInstances, merchantAgreementInputs; cast `workflow_state` and `signing_status` as string, `file_version` as int

**AuditLog** — No relationships; override `boot()` to throw on update/delete (immutability):
```php
protected static function boot(): void {
    parent::boot();
    static::updating(fn() => throw new \RuntimeException('Audit log records are immutable'));
    static::deleting(fn() => throw new \RuntimeException('Audit log records cannot be deleted'));
}
```
Set `public $timestamps = false;` (only has `at` column, not standard `created_at`/`updated_at`)

**SigningAuthority** — belongsTo: entity, project

**WikiContract** — belongsTo: region; hasMany: merchantAgreementInputs

**WorkflowTemplate** — belongsTo: region, entity, project; hasMany: instances (WorkflowInstance), escalationRules; cast `stages` → `array`, `validation_errors` → `array`

**WorkflowInstance** — belongsTo: contract, template (WorkflowTemplate); hasMany: stageActions (WorkflowStageAction), escalationEvents

**WorkflowStageAction** — belongsTo: instance (WorkflowInstance); set `public $timestamps = false;` (only `created_at`)

**BoldsignEnvelope** — belongsTo: contract; cast `signers` → `array`, `webhook_payload` → `array`

**ContractLink** — belongsTo: parentContract (Contract), childContract (Contract); set `public $timestamps = false;` (only `created_at`)

**ContractKeyDate** — belongsTo: contract; hasMany: reminders; cast `reminder_days` → `array`, `date_value` → `'date'`

**MerchantAgreementInput** — belongsTo: contract, template (WikiContract); set `public $timestamps = false;` (only `created_at`)

**AiAnalysisResult** — belongsTo: contract; hasMany: extractedFields (AiExtractedField); cast `result` → `array`, `evidence` → `array`

**AiExtractedField** — belongsTo: contract, analysis (AiAnalysisResult)

**ObligationsRegister** — belongsTo: contract, analysis (AiAnalysisResult); cast `due_date` → `'date'`

**Reminder** — belongsTo: contract, keyDate (ContractKeyDate)

**EscalationRule** — belongsTo: template (WorkflowTemplate); hasMany: events (EscalationEvent)

**EscalationEvent** — belongsTo: workflowInstance, rule (EscalationRule), contract; set `public $timestamps = false;`

**Notification** — no FK relationships; use scoped queries in Resource

**ContractLanguage** — belongsTo: contract; set `public $timestamps = false;` (only `created_at`)

**OverrideRequest** — belongsTo: counterparty

**CounterpartyMerge** — set `public $timestamps = false;` (only `created_at`)

**User** — Use default Laravel User as base; add `HasRoles` (Spatie), `HasUuidPrimaryKey`; `protected string $guard_name = 'web';`

---

## Task 7: Configure Spatie Permission and Filament Shield

### In `config/permission.php` (published by vendor:publish):
Set `'column_names' => ['model_morph_key' => 'id']` to use string UUIDs as model keys.

### Run:
```bash
php artisan shield:install --fresh
```

### Create a database seeder `database/seeders/RoleSeeder.php`:
```php
use Spatie\Permission\Models\Role;

$roles = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];
foreach ($roles as $role) {
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
}
```

Register in `DatabaseSeeder.php` and run: `php artisan db:seed --class=RoleSeeder`

---

## Task 8: Create Filament AdminPanel Provider

Update `app/Providers/Filament/AdminPanelProvider.php` to:
1. Register all Resources (stub list — full implementation in Prompt B):
   - ContractResource, MerchantAgreementResource, CounterpartyResource, WorkflowTemplateResource, WikiContractResource, RegionResource, EntityResource, ProjectResource, SigningAuthorityResource, OverrideRequestResource, AuditLogResource, NotificationResource
2. Register all Pages:
   - Dashboard, ReportsPage, EscalationsPage, KeyDatesPage, RemindersPage, NotificationsPage
3. Register all Widgets:
   - ContractStatusWidget, ExpiryHorizonWidget, AiCostWidget, PendingWorkflowsWidget, ActiveEscalationsWidget
4. Configure auth guard: `->authGuard('web')`
5. Configure login: `->loginRouteSlug('login')` — will be replaced with Azure AD in Prompt D
6. Configure Horizon: `->plugin(FilamentShieldPlugin::make())`
7. Set `->path('admin')` and `->brandName('CCRS')`

Run `php artisan filament:make-resource` stubs for each resource (can be empty skeletons — content in Prompt B).

---

## Task 9: Extend Template Infrastructure

The template already provides a production-ready `Dockerfile`, nginx, and supervisor config at the repo root. **Do NOT recreate or overwrite them.** Instead, extend two files:

### 9a. Extend `docker/supervisor/supervisord.conf`

The template's supervisor only manages `php-fpm` and `nginx`. Open the existing file and **append** these two program sections — do not remove any existing sections:

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/html
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:scheduler]
command=/bin/sh -c "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction && sleep 60; done"
directory=/var/www/html
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### 9b. Update `deploy/k8s/deployment.yaml` environment variables

The template's deployment uses SQLite env vars. Replace them with MySQL + Redis + secrets for CCRS. Update the `env:` array in the container spec to:

```yaml
env:
  - name: APP_NAME
    value: "CCRS"
  - name: APP_ENV
    value: "production"
  - name: APP_KEY
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: APP_KEY
  - name: APP_DEBUG
    value: "false"
  - name: APP_URL
    value: "https://ccrs-sandbox.digittal.mobi"
  - name: DB_CONNECTION
    value: "mysql"
  - name: DB_HOST
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: DB_HOST
  - name: DB_PORT
    value: "3306"
  - name: DB_DATABASE
    value: "ccrs"
  - name: DB_USERNAME
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: DB_USERNAME
  - name: DB_PASSWORD
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: DB_PASSWORD
  - name: CACHE_STORE
    value: "redis"
  - name: QUEUE_CONNECTION
    value: "redis"
  - name: SESSION_DRIVER
    value: "redis"
  - name: REDIS_HOST
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: REDIS_HOST
  - name: REDIS_PORT
    value: "6379"
  - name: AI_WORKER_URL
    value: "http://ccrs-ai-worker:8001"
  - name: AI_WORKER_SECRET
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: AI_WORKER_SECRET
  - name: ANTHROPIC_API_KEY
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: ANTHROPIC_API_KEY
  - name: AZURE_AD_CLIENT_ID
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: AZURE_AD_CLIENT_ID
  - name: AZURE_AD_CLIENT_SECRET
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: AZURE_AD_CLIENT_SECRET
  - name: AZURE_AD_TENANT_ID
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: AZURE_AD_TENANT_ID
  - name: BOLDSIGN_API_KEY
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: BOLDSIGN_API_KEY
  - name: BOLDSIGN_WEBHOOK_SECRET
    valueFrom:
      secretKeyRef:
        name: ccrs-secrets
        key: BOLDSIGN_WEBHOOK_SECRET
```

Create `deploy/k8s/ccrs-secrets-template.yaml` (add to `.gitignore` — reference for ops team):

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: ccrs-secrets
  namespace: cco-sandbox
type: Opaque
stringData:
  APP_KEY: "base64:changeme"
  DB_HOST: "mysql-service"
  DB_USERNAME: "ccrs"
  DB_PASSWORD: "changeme"
  REDIS_HOST: "redis-service"
  AI_WORKER_SECRET: "changeme"
  ANTHROPIC_API_KEY: "sk-ant-..."
  AZURE_AD_CLIENT_ID: ""
  AZURE_AD_CLIENT_SECRET: ""
  AZURE_AD_TENANT_ID: ""
  BOLDSIGN_API_KEY: ""
  BOLDSIGN_WEBHOOK_SECRET: ""
```

### 9c. Create `ai-worker/Dockerfile`
```dockerfile
FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    libmupdf-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app/ ./app/

EXPOSE 8001

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001", "--workers", "2"]
```

### `ai-worker/requirements.txt`
```
fastapi>=0.115.0
uvicorn[standard]>=0.34.0
pydantic>=2.10.0
pydantic-settings>=2.7.0
anthropic>=0.52.0
sqlalchemy>=2.0.0
pymysql>=1.1.0
structlog>=24.4.0
PyMuPDF>=1.25.0
python-docx>=1.1.0
httpx>=0.28.0
```

Create `ai-worker/app/__init__.py` and `ai-worker/app/main.py` as a minimal FastAPI app (full implementation in Prompt C):
```python
from fastapi import FastAPI

app = FastAPI(title="CCRS AI Worker", version="1.0.0")

@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-worker"}
```

---

## Task 10: Create `docker-compose.yml` at Repo Root

This file is for **local development only** — K8s is handled by the Jenkinsfile and `deploy/k8s/`. The `app` service uses the template's `Dockerfile` at the repo root (context: `.`) and port 8080.

```yaml
version: "3.9"

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: ccrs_laravel
    restart: unless-stopped
    ports:
      - "8080:8080"
    env_file:
      - .env
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor          # exclude vendor from mount
      - /var/www/html/node_modules    # exclude node_modules from mount
      - laravel_storage:/var/www/html/storage
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - ccrs_net

  mysql:
    image: mysql:8.0
    container_name: ccrs_mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-rootpassword}"
      MYSQL_DATABASE: ccrs
      MYSQL_USER: ccrs
      MYSQL_PASSWORD: "${DB_PASSWORD:-ccrspassword}"
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u ccrs -p${DB_PASSWORD:-ccrspassword}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - ccrs_net

  redis:
    image: redis:7-alpine
    container_name: ccrs_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3
    networks:
      - ccrs_net

  ai-worker:
    build:
      context: ./ai-worker
      dockerfile: Dockerfile
    container_name: ccrs_ai_worker
    restart: unless-stopped
    # Not exposed to host — only accessible by app via ccrs_net
    environment:
      ANTHROPIC_API_KEY: "${ANTHROPIC_API_KEY}"
      AI_WORKER_SECRET: "${AI_WORKER_SECRET:-changeme}"
      DB_URL: "mysql+pymysql://ccrs:${DB_PASSWORD:-ccrspassword}@mysql:3306/ccrs"
      LOG_LEVEL: "info"
    depends_on:
      mysql:
        condition: service_healthy
    networks:
      - ccrs_net

volumes:
  mysql_data:
  redis_data:
  laravel_storage:

networks:
  ccrs_net:
    driver: bridge
```

Create `.env` at repo root (used by both `docker-compose` and Laravel — gitignored):

```dotenv
APP_NAME="CCRS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ccrs
DB_USERNAME=ccrs
DB_PASSWORD=ccrspassword
DB_ROOT_PASSWORD=rootpassword

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-2
AWS_BUCKET=ccrs-contracts
AWS_ENDPOINT=

AZURE_AD_CLIENT_ID=
AZURE_AD_CLIENT_SECRET=
AZURE_AD_TENANT_ID=
AZURE_AD_GROUP_SYSTEM_ADMIN=
AZURE_AD_GROUP_LEGAL=
AZURE_AD_GROUP_COMMERCIAL=
AZURE_AD_GROUP_FINANCE=
AZURE_AD_GROUP_OPERATIONS=
AZURE_AD_GROUP_AUDIT=

BOLDSIGN_API_KEY=
BOLDSIGN_API_URL=https://api.boldsign.com
BOLDSIGN_WEBHOOK_SECRET=

AI_WORKER_URL=http://ai-worker:8001
AI_WORKER_SECRET=changeme_to_random_secret

ANTHROPIC_API_KEY=your_key_here

MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@ccrs.digittal.com
MAIL_FROM_NAME="CCRS"
```

**Note:** The template's `docker/docker-entrypoint.sh` already runs `php artisan migrate --force` and `php artisan filament:assets` on container start. Supervisor (extended in Task 9) then manages nginx, php-fpm, queue worker, and scheduler. After first `docker compose up`, seed roles manually:
```bash
docker compose exec app php artisan db:seed --class=RoleSeeder
```

---

## Task 11: Update `.gitignore` at repo root

The template likely has a basic `.gitignore`. **Add** these entries (do not remove existing ones):

```
# Laravel (app lives at repo root)
.env
vendor/
node_modules/
public/build/
storage/app/public/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/*.php

# AI Worker
ai-worker/.env
ai-worker/__pycache__/
ai-worker/**/__pycache__/

# Docker local dev
docker-compose.override.yml

# K8s secrets (never commit)
deploy/k8s/ccrs-secrets*.yaml
deploy/k8s/ccrs-secrets-template.yaml
```

---

## Verification Checklist

After completing all tasks, verify:

1. **`docker compose up --build`** — all 4 services start without errors
2. Container logs show `php artisan migrate --force` completed (template entrypoint runs this automatically)
3. **`docker compose exec mysql mysql -u ccrs -pccrspassword ccrs -e "SHOW TABLES;"` shows all 27 tables**
4. **`curl http://localhost:8080/admin`** — returns Filament login page (HTTP 200 or redirect)
5. **`curl http://localhost:8001/health`** — returns `{"status": "ok", "service": "ai-worker"}`
6. **`docker compose exec app php artisan db:seed --class=RoleSeeder`** — creates 6 roles without error
7. **`docker compose exec app php artisan route:list`** — shows Filament routes
8. No PHP syntax errors: `docker compose exec app php artisan about`
9. **`docker compose exec app supervisorctl status`** — shows `queue-worker` and `scheduler` as RUNNING alongside `nginx` and `php-fpm`
