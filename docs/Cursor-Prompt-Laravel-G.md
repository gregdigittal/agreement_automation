# Cursor Prompt — Laravel Migration Phase G: Phase 2 Foundation

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through F were executed.

Phases A–F are complete. The application is production-ready for Phase 1. This prompt lays the Phase 2 technical foundations:

1. **Advanced Full-Text Search**: Meilisearch integration for fast, typo-tolerant cross-entity search across contracts, counterparties, and wiki articles — replacing MySQL `LIKE` queries in the search bar.
2. **Observability (OpenTelemetry)**: Distributed tracing and structured logging exported to an OTLP collector (Jaeger/Zipkin-compatible). Traces span HTTP requests, queue jobs, and the AI worker HTTP calls.
3. **Vendor Self-Service Portal**: A separate Filament panel (`/vendor`) with its own authentication (magic-link email), limited to vendors viewing their own contracts and uploading supporting documents.

These are scaffolded here but not yet fully fleshed out — the goal is to establish the correct architectural patterns so that Phase 2 can build on top without a re-architecture.

---

## Task 1: Meilisearch Full-Text Search

### 1.1 Install Laravel Scout + Meilisearch Driver

```bash
composer require laravel/scout meilisearch/meilisearch-php http-interop/http-factory-guzzle
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

### 1.2 Configure Scout

In `.env.example`, add:

```dotenv
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your-meilisearch-master-key
```

In `config/scout.php`, the published defaults are sufficient. Ensure `SCOUT_DRIVER` reads from env.

In `docker-compose.yml`, add a `meilisearch` service:

```yaml
meilisearch:
  image: getmeili/meilisearch:v1.7
  ports:
    - "7700:7700"
  environment:
    MEILI_MASTER_KEY: "${MEILISEARCH_KEY:-changeme}"
    MEILI_ENV: development
  volumes:
    - meilisearch_data:/meili_data
  networks:
    - ccrs_net
```

Add `meilisearch_data:` to the top-level `volumes:` block.

### 1.3 Add `Searchable` to Key Models

In `app/Models/Contract.php`:

```php
use Laravel\Scout\Searchable;

class Contract extends Model
{
    use HasUuidPrimaryKey, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'contract_type'  => $this->contract_type,
            'workflow_state' => $this->workflow_state,
            'counterparty'   => $this->counterparty?->legal_name,
            'region'         => $this->region?->name,
            'entity'         => $this->entity?->name,
            'project'        => $this->project?->name,
        ];
    }

    public function searchableAs(): string
    {
        return 'contracts';
    }
}
```

Apply `Searchable` + `toSearchableArray()` to `app/Models/Counterparty.php` (fields: `id`, `legal_name`, `registration_number`, `status`, `country_of_incorporation`) and `app/Models/WikiContract.php` (fields: `id`, `title`, `description`, `contract_type`).

### 1.4 Create Global Search Service

Create `app/Services/SearchService.php`:

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\WikiContract;

class SearchService
{
    /**
     * Search across contracts, counterparties, and wiki articles.
     * Returns a unified result set keyed by resource type.
     *
     * @return array{contracts: array, counterparties: array, wiki: array}
     */
    public function globalSearch(string $query, int $perType = 5): array
    {
        return [
            'contracts'     => Contract::search($query)->take($perType)->get()->toArray(),
            'counterparties'=> Counterparty::search($query)->take($perType)->get()->toArray(),
            'wiki'          => WikiContract::search($query)->take($perType)->get()->toArray(),
        ];
    }
}
```

### 1.5 Wire Search into Filament Global Search

In `app/Providers/Filament/AdminPanelProvider.php`, enable global search:

```php
->globalSearch(true)
->globalSearchKeyBindings(['command+k', 'ctrl+k'])
```

In each of the three `Searchable` models, add a static `getGloballySearchableAttributes()` method (Filament convention):

```php
// In Contract.php
public static function getGlobalSearchResultTitle(Model $record): string
{
    return $record->title;
}

public static function getGlobalSearchResultDetails(Model $record): array
{
    return [
        'Type'  => $record->contract_type,
        'State' => $record->workflow_state,
    ];
}

public static function getGlobalSearchResultUrl(Model $record): string
{
    return ContractResource::getUrl('edit', ['record' => $record]);
}
```

Apply equivalent methods to `CounterpartyResource` and `WikiContractResource`.

### 1.6 Index Existing Data

After Meilisearch is running, run:

```bash
php artisan scout:import "App\Models\Contract"
php artisan scout:import "App\Models\Counterparty"
php artisan scout:import "App\Models\WikiContract"
```

Add these commands to the post-deploy script in `deploy/k8s/deployment.yaml` as a `lifecycle.postStart` hook or a Kubernetes Job.

---

## Task 2: OpenTelemetry Observability

### 2.1 Install OpenTelemetry SDK

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp open-telemetry/opentelemetry-auto-laravel
```

### 2.2 Configure OTLP Exporter

In `.env.example`, add:

```dotenv
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_SERVICE_NAME=ccrs-laravel
OTEL_TRACES_SAMPLER=parentbased_always_on
OTEL_PHP_AUTOLOAD_ENABLED=true
```

### 2.3 Add OpenTelemetry Collector to Docker Compose

In `docker-compose.yml`, add:

```yaml
otel-collector:
  image: otel/opentelemetry-collector-contrib:0.98.0
  ports:
    - "4317:4317"   # OTLP gRPC
    - "4318:4318"   # OTLP HTTP
    - "16686:16686" # Jaeger UI (via collector)
  volumes:
    - ./docker/otel/otel-collector-config.yaml:/etc/otelcol-contrib/config.yaml
  networks:
    - ccrs_net
```

Create `docker/otel/otel-collector-config.yaml`:

```yaml
receivers:
  otlp:
    protocols:
      http:
        endpoint: 0.0.0.0:4318
      grpc:
        endpoint: 0.0.0.0:4317

exporters:
  debug:
    verbosity: normal
  # Add a Jaeger exporter if you have Jaeger running:
  # jaeger:
  #   endpoint: jaeger:14250

service:
  pipelines:
    traces:
      receivers: [otlp]
      exporters: [debug]
```

### 2.4 Create `TelemetryService` Wrapper

Create `app/Services/TelemetryService.php`:

```php
<?php

namespace App\Services;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;

class TelemetryService
{
    /**
     * Start a new named span. Caller is responsible for ending it.
     *
     * Usage:
     *   $span = TelemetryService::startSpan('ai-worker.analyze', ['contract_id' => $id]);
     *   try { ... } finally { $span->end(); }
     */
    public static function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $tracer = Globals::tracerProvider()->getTracer('ccrs');
        $span = $tracer->spanBuilder($name)->startSpan();
        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, (string) $value);
        }
        return $span;
    }
}
```

### 2.5 Instrument Key Operations

Add spans to the following (without changing business logic):

**In `app/Jobs/ProcessAiAnalysis.php`:**
```php
$span = TelemetryService::startSpan('job.process_ai_analysis', ['contract_id' => $this->contractId]);
try {
    // ... existing job logic ...
} finally {
    $span->end();
}
```

**In `app/Services/AiWorkerClient.php`:**
```php
$span = TelemetryService::startSpan('ai_worker.analyze', ['contract_id' => $contractId, 'analysis_type' => $type]);
try {
    // ... existing HTTP call ...
} finally {
    $span->end();
}
```

**In `app/Http/Controllers/Api/TitoController.php`:**
```php
$span = TelemetryService::startSpan('tito.validate', ['vendor_id' => $vendorId]);
try {
    // ... existing logic ...
} finally {
    $span->end();
}
```

### 2.6 Structured Logging

In `config/logging.php`, add a `json` channel:

```php
'json' => [
    'driver'  => 'single',
    'path'    => storage_path('logs/laravel.json.log'),
    'level'   => env('LOG_LEVEL', 'debug'),
    'formatter' => \Monolog\Formatter\JsonFormatter::class,
],
```

Set `LOG_CHANNEL=json` in production `.env`.

---

## Task 3: Vendor Self-Service Portal

### 3.1 Create a New Filament Panel

```bash
php artisan make:filament-panel vendor
```

This generates `app/Providers/Filament/VendorPanelProvider.php`. Configure it:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;

class VendorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('vendor')
            ->login()
            ->authGuard('vendor')
            ->colors(['primary' => \Filament\Support\Colors\Color::Emerald])
            ->discoverResources(in: app_path('Filament/Vendor/Resources'), for: 'App\\Filament\\Vendor\\Resources')
            ->discoverPages(in: app_path('Filament/Vendor/Pages'), for: 'App\\Filament\\Vendor\\Pages')
            ->navigationGroups(['Agreements', 'Documents']);
    }
}
```

Register in `bootstrap/providers.php`:

```php
App\Providers\Filament\VendorPanelProvider::class,
```

### 3.2 Create Vendor Auth Guard

In `config/auth.php`, add:

```php
'guards' => [
    // ... existing guards ...
    'vendor' => [
        'driver'   => 'session',
        'provider' => 'vendors',
    ],
],

'providers' => [
    // ... existing providers ...
    'vendors' => [
        'driver' => 'eloquent',
        'model'  => App\Models\VendorUser::class,
    ],
],
```

### 3.3 Create `VendorUser` Model

Create `app/Models/VendorUser.php`:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorUser extends Authenticatable implements FilamentUser
{
    use HasUuidPrimaryKey, Notifiable;

    protected $table = 'vendor_users';

    protected $fillable = [
        'id', 'email', 'name', 'counterparty_id', 'login_token',
        'login_token_expires_at', 'last_login_at',
    ];

    protected $hidden = ['login_token'];

    protected $casts = [
        'login_token_expires_at' => 'datetime',
        'last_login_at'          => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'vendor';
    }

    public function counterparty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }
}
```

### 3.4 Create `vendor_users` Migration

Create `database/migrations/XXXX_create_vendor_users_table.php`:

```php
Schema::create('vendor_users', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->string('email')->unique();
    $table->string('name');
    $table->char('counterparty_id', 36)->index();
    $table->string('login_token', 64)->nullable()->unique();
    $table->timestamp('login_token_expires_at')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->timestamps();

    $table->foreign('counterparty_id')->references('id')->on('counterparties')->onDelete('cascade');
});
```

### 3.5 Implement Magic-Link Login

Create `app/Http/Controllers/Vendor/MagicLinkController.php`:

```php
<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $vendor = VendorUser::where('email', $request->email)->first();

        if ($vendor) {
            $token = Str::random(64);
            $vendor->update([
                'login_token'            => hash('sha256', $token),
                'login_token_expires_at' => now()->addMinutes(15),
            ]);

            $link = route('vendor.magic-link.verify', ['token' => $token]);

            Mail::to($vendor->email)->send(
                new \App\Mail\VendorMagicLink($vendor, $link)
            );
        }

        // Always return success to prevent email enumeration
        return back()->with('status', 'If an account exists for that email, a login link has been sent.');
    }

    public function verify(Request $request, string $token)
    {
        $hashed = hash('sha256', $token);

        $vendor = VendorUser::where('login_token', $hashed)
            ->where('login_token_expires_at', '>', now())
            ->first();

        if (! $vendor) {
            return redirect('/vendor/login')->withErrors(['token' => 'This login link is invalid or has expired.']);
        }

        $vendor->update([
            'login_token'            => null,
            'login_token_expires_at' => null,
            'last_login_at'          => now(),
        ]);

        Auth::guard('vendor')->login($vendor, remember: true);

        return redirect('/vendor');
    }
}
```

Add routes in `routes/web.php`:

```php
Route::post('/vendor/magic-link', [\App\Http\Controllers\Vendor\MagicLinkController::class, 'request'])
    ->name('vendor.magic-link.request');

Route::get('/vendor/magic-link/{token}', [\App\Http\Controllers\Vendor\MagicLinkController::class, 'verify'])
    ->name('vendor.magic-link.verify');
```

Create the Mailable `app/Mail/VendorMagicLink.php` (standard Laravel Mailable with a Blade template at `resources/views/mail/vendor-magic-link.blade.php` that outputs the login link).

### 3.6 Scaffold Vendor Resources

Create the directory `app/Filament/Vendor/Resources/` and add two stub resources:

**`VendorContractResource.php`** — Lists only the contracts where `counterparty_id = auth('vendor')->user()->counterparty_id`. Read-only (no create/edit/delete). Columns: title, contract_type, workflow_state (badge), signing_status, created_at.

```php
<?php

namespace App\Filament\Vendor\Resources;

use App\Models\Contract;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationGroup = 'Agreements';
    protected static ?string $navigationLabel = 'My Agreements';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getEloquentQuery(): Builder
    {
        $counterpartyId = auth('vendor')->user()?->counterparty_id;
        return parent::getEloquentQuery()->where('counterparty_id', $counterpartyId);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('contract_type'),
                Tables\Columns\BadgeColumn::make('workflow_state')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'review',
                        'success' => 'active',
                        'danger'  => 'terminated',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Contract $record) =>
                        route('vendor.contract.download', $record)
                    )
                    ->openUrlInNewTab(),
            ]);
    }

    // No create/edit pages for vendors
    public static function canCreate(): bool { return false; }
}
```

**`VendorDocumentUploadResource.php`** — A simple Filament page (not CRUD) where the vendor can upload supporting documents (PDF/DOCX) that get stored to S3 under `vendor_documents/{counterparty_id}/` and create a record in a `vendor_documents` table. This resource is a placeholder scaffold — implement the full form in Phase 2.

Add a `vendor_documents` table migration:

```php
Schema::create('vendor_documents', function (Blueprint $table) {
    $table->char('id', 36)->primary();
    $table->char('counterparty_id', 36)->index();
    $table->char('contract_id', 36)->nullable()->index();
    $table->string('filename');
    $table->string('storage_path');
    $table->string('document_type', 50)->default('supporting');
    $table->char('uploaded_by_vendor_user_id', 36)->nullable();
    $table->timestamps();
});
```

### 3.7 Add Vendor Contract Download Route

In `routes/web.php`:

```php
Route::middleware('auth:vendor')->group(function () {
    Route::get('/vendor/contracts/{contract}/download', function (\App\Models\Contract $contract) {
        // Ensure vendor can only download their own counterparty's contracts
        $counterpartyId = auth('vendor')->user()->counterparty_id;
        abort_unless($contract->counterparty_id === $counterpartyId, 403);

        $url = \Illuminate\Support\Facades\Storage::disk('s3')
            ->temporaryUrl($contract->storage_path, now()->addMinutes(10));

        return redirect($url);
    })->name('vendor.contract.download');
});
```

---

## Task 4: Update Docker Compose and Documentation

### 4.1 Final `docker-compose.yml`

The docker-compose.yml at repo root should now declare these services:

| Service | Image | Purpose |
|---|---|---|
| `app` | Custom PHP 8.3-fpm + nginx | Laravel + Filament admin + vendor panel |
| `mysql` | mysql:8.0 | Primary database |
| `redis` | redis:7-alpine | Queue + cache + sessions |
| `ai-worker` | Custom Python 3.12 | AI microservice (internal-only) |
| `meilisearch` | getmeili/meilisearch:v1.7 | Full-text search |
| `otel-collector` | otel/opentelemetry-collector-contrib:0.98.0 | Observability (dev only) |

For production K8s, `meilisearch` and `otel-collector` should be separate deployments with persistent storage — add a comment in `docker-compose.yml` noting this.

### 4.2 Update `.env.example`

Append to `.env.example`:

```dotenv
# Meilisearch
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=changeme

# OpenTelemetry
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_SERVICE_NAME=ccrs-laravel
OTEL_TRACES_SAMPLER=parentbased_always_on
OTEL_PHP_AUTOLOAD_ENABLED=true
```

---

## Verification Checklist

After completing all tasks, verify the following:

1. **Meilisearch:**
   - `docker compose up meilisearch` starts without error.
   - `php artisan scout:import "App\Models\Contract"` indexes contracts (output: `[X] All [N] records have been imported`).
   - In Filament admin panel, press `Cmd+K` (or `Ctrl+K`) — a global search box appears.
   - Typing a partial contract title returns results from Meilisearch (not a SQL LIKE query).

2. **OpenTelemetry:**
   - `docker compose up otel-collector` starts without error.
   - Trigger a queue job (`ProcessAiAnalysis`) via Filament.
   - `docker compose logs otel-collector` shows trace data received.
   - No errors in `storage/logs/laravel.json.log` related to OpenTelemetry.

3. **Vendor Portal:**
   - Navigate to `http://localhost:8080/vendor` — a Filament login page with the emerald color scheme appears.
   - Create a `vendor_users` row manually via `php artisan tinker` with a real email.
   - POST to `/vendor/magic-link` with that email — a login email is queued (check `storage/logs/laravel.log` for mail output in dev).
   - Clicking the magic link logs in and redirects to `/vendor`.
   - Only contracts belonging to that vendor's `counterparty_id` are visible.
   - A contract from a different counterparty returns 403 on download.

4. **Migrations:**
   - `php artisan migrate` runs all new migrations without error.
   - Tables `vendor_users`, `vendor_documents`, `bulk_uploads`, `bulk_upload_rows` exist in MySQL.

5. **All tests:**
   ```bash
   php artisan test
   ```
   All tests pass, including Phase E tests (TiTo, DOCX, Teams).
