# Cursor Prompt — Laravel Migration Phase E: TiTo API + DOCX Generation + Teams Notifications

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through D were executed.

Phases A–D are complete. The application has:
- Full Filament admin panel with all CRUD resources
- Azure AD authentication and role-based access control
- AI worker microservice for contract analysis
- BoldSign e-signature integration
- Laravel Horizon queue monitoring

Phase E implements three commercially critical features that were not covered in Phases A–D:

1. **R1 — TiTo Validation API**: A public REST endpoint consumed by POS terminals to verify a signed Merchant Agreement exists before allowing a vendor to trade. This is a hard blocker for POS deployment.
2. **R3 — Merchant Agreement DOCX Generation**: The core Merchant Agreement output — downloads a DOCX master template from S3, fills placeholders, saves output to S3, and creates a contract record.
3. **R2 — Teams Notifications**: Routes contract and workflow notifications to Microsoft Teams channels via Microsoft Graph API for the `channel = 'teams'` notification preference.

---

## Task 1: TiTo Validation API

### 1.1 Add `TitoController`

Create `app/Http/Controllers/Api/TitoController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TitoController extends Controller
{
    /**
     * Validate that a signed Merchant Agreement exists for the given vendor.
     *
     * Query params:
     *   vendor_id       (required) — counterparty.id UUID
     *   entity_id       (optional) — entity.id UUID filter
     *   region_id       (optional) — region.id UUID filter
     *   project_id      (optional) — project.id UUID filter
     */
    public function validate(Request $request)
    {
        $request->validate([
            'vendor_id'  => ['required', 'uuid'],
            'entity_id'  => ['sometimes', 'uuid'],
            'region_id'  => ['sometimes', 'uuid'],
            'project_id' => ['sometimes', 'uuid'],
        ]);

        $vendorId   = $request->query('vendor_id');
        $entityId   = $request->query('entity_id');
        $regionId   = $request->query('region_id');
        $projectId  = $request->query('project_id');

        // Build a deterministic cache key from all query parameters
        $cacheKey = 'tito:' . md5(implode('|', array_filter([
            $vendorId, $entityId, $regionId, $projectId
        ])));

        $result = Cache::remember($cacheKey, now()->addMinutes(5), function () use (
            $vendorId, $entityId, $regionId, $projectId
        ) {
            $query = Contract::query()
                ->where('contract_type', 'merchant_agreement')
                ->where('counterparty_id', $vendorId)
                ->whereHas('boldsignEnvelopes', fn ($q) =>
                    $q->where('status', 'completed')
                );

            if ($entityId)  $query->where('entity_id', $entityId);
            if ($regionId)  $query->where('region_id', $regionId);
            if ($projectId) $query->where('project_id', $projectId);

            $contract = $query
                ->orderByDesc('created_at')
                ->select(['id', 'workflow_state', 'created_at'])
                ->with(['boldsignEnvelopes' => fn ($q) =>
                    $q->where('status', 'completed')
                      ->orderByDesc('updated_at')
                      ->select(['id', 'contract_id', 'status', 'updated_at'])
                ])
                ->first();

            if (! $contract) {
                return [
                    'valid'       => false,
                    'status'      => 'no_signed_agreement',
                    'contract_id' => null,
                    'signed_at'   => null,
                ];
            }

            $envelope = $contract->boldsignEnvelopes->first();

            return [
                'valid'       => true,
                'status'      => 'signed',
                'contract_id' => $contract->id,
                'signed_at'   => $envelope?->updated_at?->toIso8601String(),
            ];
        });

        // Log every TiTo validation call to audit_log
        AuditService::log(
            action: 'tito.validate',
            resourceType: 'contract',
            resourceId: $result['contract_id'],
            details: [
                'vendor_id'  => $vendorId,
                'entity_id'  => $entityId,
                'region_id'  => $regionId,
                'project_id' => $projectId,
                'valid'      => $result['valid'],
            ]
        );

        return response()->json($result);
    }
}
```

### 1.2 Create `TitoApiKeyMiddleware`

Create `app/Http/Middleware/TitoApiKeyMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TitoApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-TiTo-API-Key');

        if (! $apiKey || ! hash_equals(config('ccrs.tito_api_key'), $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php` (Laravel 11 style):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tito.auth' => \App\Http\Middleware\TitoApiKeyMiddleware::class,
        // ... existing aliases
    ]);
})
```

### 1.3 Register Route

In `routes/api.php`:

```php
use App\Http\Controllers\Api\TitoController;

Route::middleware('tito.auth')->group(function () {
    Route::get('/tito/validate', [TitoController::class, 'validate']);
});
```

Ensure `routes/api.php` is loaded in `bootstrap/app.php`:

```php
->withRouting(function () {
    Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/api.php'));
    // ... existing web routes
})
```

### 1.4 Add Config Key

In `config/ccrs.php`, add:

```php
'tito_api_key' => env('TITO_API_KEY', ''),
```

In `.env.example`, add:

```
TITO_API_KEY=your-secret-tito-api-key
```

### 1.5 Write Feature Test

Create `tests/Feature/TitoValidationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\BoldsignEnvelope;
use App\Models\Counterparty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TitoValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-tito-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ccrs.tito_api_key' => $this->apiKey]);
    }

    public function test_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/tito/validate?vendor_id=' . Str::uuid());
        $response->assertStatus(401);
    }

    public function test_returns_invalid_when_no_signed_agreement(): void
    {
        $vendorId = Str::uuid()->toString();

        $response = $this->withHeader('X-TiTo-API-Key', $this->apiKey)
            ->getJson("/api/tito/validate?vendor_id={$vendorId}");

        $response->assertStatus(200)
            ->assertJson([
                'valid'  => false,
                'status' => 'no_signed_agreement',
            ]);
    }

    public function test_returns_valid_when_signed_agreement_exists(): void
    {
        $counterparty = Counterparty::factory()->create();
        $contract = Contract::factory()->create([
            'contract_type'  => 'merchant_agreement',
            'counterparty_id' => $counterparty->id,
        ]);
        BoldsignEnvelope::factory()->create([
            'contract_id' => $contract->id,
            'status'      => 'completed',
        ]);

        $response = $this->withHeader('X-TiTo-API-Key', $this->apiKey)
            ->getJson("/api/tito/validate?vendor_id={$counterparty->id}");

        $response->assertStatus(200)
            ->assertJson([
                'valid'       => true,
                'status'      => 'signed',
                'contract_id' => $contract->id,
            ]);
    }

    public function test_caches_result_for_five_minutes(): void
    {
        $vendorId = Str::uuid()->toString();
        config(['ccrs.tito_api_key' => $this->apiKey]);

        // First call — no contract
        $r1 = $this->withHeader('X-TiTo-API-Key', $this->apiKey)
            ->getJson("/api/tito/validate?vendor_id={$vendorId}");
        $r1->assertJson(['valid' => false]);

        // Second call within cache window should return same result
        $r2 = $this->withHeader('X-TiTo-API-Key', $this->apiKey)
            ->getJson("/api/tito/validate?vendor_id={$vendorId}");
        $r2->assertJson(['valid' => false]);
    }
}
```

---

## Task 2: Merchant Agreement DOCX Generation

### 2.1 Install PHPWord

```bash
composer require phpoffice/phpword
```

### 2.2 Upload Template Placeholder Instructions

Document this convention in `app/Services/MerchantAgreementService.php` (at the top of the file as a docblock):

The S3 master DOCX template must use PHPWord variable syntax: `${vendor_name}`, `${merchant_fee}`, `${effective_date}`, `${region_terms}`, `${entity_name}`, `${project_name}`, `${signing_authority_name}`, `${signing_authority_title}`.

### 2.3 Implement `MerchantAgreementService::generate()`

Replace or add to the existing `app/Services/MerchantAgreementService.php`:

```php
<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MerchantAgreement;
use App\Models\Counterparty;
use App\Models\SigningAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class MerchantAgreementService
{
    /**
     * Generate a filled Merchant Agreement DOCX from the master template.
     *
     * Downloads the master template from S3 key `config('ccrs.merchant_agreement_template_s3_key')`,
     * fills all placeholders, uploads the output DOCX to S3, and creates a Contract record.
     *
     * @return Contract  The newly created contract record with storage_path set.
     */
    public function generate(MerchantAgreement $agreement, User $actor): Contract
    {
        // 1. Resolve related entities
        $counterparty    = Counterparty::findOrFail($agreement->counterparty_id);
        $signingAuth     = SigningAuthority::where('region_id', $agreement->region_id)
            ->orderByDesc('created_at')
            ->firstOrFail();

        // 2. Download master DOCX template from S3 to a local temp file
        $templateS3Key = config('ccrs.merchant_agreement_template_s3_key');
        $tempTemplatePath = tempnam(sys_get_temp_dir(), 'ma_template_') . '.docx';

        $templateContent = Storage::disk('s3')->get($templateS3Key);
        if (! $templateContent) {
            throw new \RuntimeException("Master template not found at S3 key: {$templateS3Key}");
        }
        file_put_contents($tempTemplatePath, $templateContent);

        // 3. Build placeholder values
        $values = [
            'vendor_name'            => $counterparty->legal_name,
            'merchant_fee'           => number_format((float) ($agreement->merchant_fee ?? 0), 2),
            'effective_date'         => now()->format('d F Y'),
            'region_terms'           => $agreement->region_terms ?? '',
            'entity_name'            => $agreement->entity->name ?? '',
            'project_name'           => $agreement->project->name ?? '',
            'signing_authority_name' => $signingAuth->name,
            'signing_authority_title'=> $signingAuth->title,
        ];

        // 4. Fill placeholders using PHPWord TemplateProcessor
        $processor = new TemplateProcessor($tempTemplatePath);
        foreach ($values as $key => $value) {
            $processor->setValue($key, htmlspecialchars($value));
        }

        // 5. Save filled DOCX to a temp file
        $outputTempPath = tempnam(sys_get_temp_dir(), 'ma_output_') . '.docx';
        $processor->saveAs($outputTempPath);

        // 6. Upload filled DOCX to S3
        $s3OutputKey = sprintf(
            'merchant_agreements/%s/%s.docx',
            $counterparty->id,
            now()->format('Ymd_His') . '_' . Str::random(6)
        );
        Storage::disk('s3')->put($s3OutputKey, file_get_contents($outputTempPath));

        // 7. Cleanup temp files
        @unlink($tempTemplatePath);
        @unlink($outputTempPath);

        // 8. Create Contract record
        $contract = Contract::create([
            'id'              => Str::uuid()->toString(),
            'title'           => 'Merchant Agreement — ' . $counterparty->legal_name,
            'contract_type'   => 'merchant_agreement',
            'counterparty_id' => $counterparty->id,
            'region_id'       => $agreement->region_id,
            'entity_id'       => $agreement->entity_id,
            'project_id'      => $agreement->project_id,
            'workflow_state'  => 'draft',
            'storage_path'    => $s3OutputKey,
            'created_by'      => $actor->id,
        ]);

        AuditService::log(
            action: 'merchant_agreement.generated',
            resourceType: 'contract',
            resourceId: $contract->id,
            details: ['s3_key' => $s3OutputKey, 'counterparty_id' => $counterparty->id],
            actor: $actor,
        );

        return $contract;
    }
}
```

### 2.4 Add Config Key

In `config/ccrs.php`, add:

```php
'merchant_agreement_template_s3_key' => env('MA_TEMPLATE_S3_KEY', 'templates/merchant_agreement_master.docx'),
```

In `.env.example`, add:

```
MA_TEMPLATE_S3_KEY=templates/merchant_agreement_master.docx
```

### 2.5 Wire Generation into Filament `MerchantAgreementResource`

In `app/Filament/Resources/MerchantAgreementResource.php`, add a table row `Action`:

```php
use App\Services\MerchantAgreementService;
use App\Models\Contract;

Tables\Actions\Action::make('generate_docx')
    ->label('Generate Agreement')
    ->icon('heroicon-o-document-arrow-down')
    ->color('success')
    ->requiresConfirmation()
    ->modalHeading('Generate Merchant Agreement DOCX')
    ->modalDescription('This will download the master template from S3, fill placeholders, and create a contract record. Proceed?')
    ->action(function (MerchantAgreement $record) {
        try {
            $contract = app(MerchantAgreementService::class)->generate($record, auth()->user());
            \Filament\Notifications\Notification::make()
                ->title('Agreement generated successfully')
                ->body("Contract ID: {$contract->id}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }),
```

### 2.6 Write Feature Test

Create `tests/Feature/MerchantAgreementGenerationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Models\MerchantAgreement;
use App\Models\Region;
use App\Models\Entity;
use App\Models\Project;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Services\MerchantAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MerchantAgreementGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_docx_and_creates_contract(): void
    {
        Storage::fake('s3');

        // Put a minimal real DOCX into the fake S3 disk
        // (Generate a real blank DOCX so PHPWord can open it)
        $templatePath = tempnam(sys_get_temp_dir(), 'test_ma_') . '.docx';
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Vendor: ${vendor_name}, Fee: ${merchant_fee}');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($templatePath);

        Storage::disk('s3')->put('templates/merchant_agreement_master.docx', file_get_contents($templatePath));
        @unlink($templatePath);

        $region = Region::factory()->create();
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $project = Project::factory()->create(['entity_id' => $entity->id]);
        $counterparty = Counterparty::factory()->create();
        $signingAuth = SigningAuthority::factory()->create(['region_id' => $region->id]);
        $user = User::factory()->create();

        $agreement = MerchantAgreement::factory()->create([
            'counterparty_id' => $counterparty->id,
            'region_id'       => $region->id,
            'entity_id'       => $entity->id,
            'project_id'      => $project->id,
        ]);

        $contract = app(MerchantAgreementService::class)->generate($agreement, $user);

        $this->assertNotNull($contract->id);
        $this->assertEquals('merchant_agreement', $contract->contract_type);
        $this->assertNotNull($contract->storage_path);
        Storage::disk('s3')->assertExists($contract->storage_path);
    }
}
```

---

## Task 3: Teams Notifications via Microsoft Graph

### 3.1 Add Config

In `config/ccrs.php`, add:

```php
'teams' => [
    'team_id'          => env('TEAMS_TEAM_ID', ''),
    'channel_id'       => env('TEAMS_CHANNEL_ID', ''),
    'graph_scope'      => 'https://graph.microsoft.com/.default',
    'graph_base_url'   => 'https://graph.microsoft.com/v1.0',
    'token_endpoint'   => 'https://login.microsoftonline.com/' . env('AZURE_AD_TENANT_ID') . '/oauth2/v2.0/token',
],
```

In `.env.example`, add:

```
TEAMS_TEAM_ID=your-teams-team-id
TEAMS_CHANNEL_ID=your-teams-channel-id
```

### 3.2 Add `TeamsNotificationService`

Create `app/Services/TeamsNotificationService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsNotificationService
{
    /**
     * Get an access token for Microsoft Graph using client credentials flow.
     * Token is cached for 50 minutes (tokens expire at 60 min).
     */
    private function getAccessToken(): string
    {
        return Cache::remember('ms_graph_token', now()->addMinutes(50), function () {
            $response = Http::asForm()->post(config('ccrs.teams.token_endpoint'), [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'scope'         => config('ccrs.teams.scope', 'https://graph.microsoft.com/.default'),
            ]);

            if (! $response->successful()) {
                Log::error('Failed to obtain Microsoft Graph token', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('Microsoft Graph token request failed');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Post a message to the configured Teams channel.
     *
     * @param  string  $subject  Bold header line
     * @param  string  $body     Message body (plain text or simple HTML)
     */
    public function sendToChannel(string $subject, string $body): void
    {
        $teamId    = config('ccrs.teams.team_id');
        $channelId = config('ccrs.teams.channel_id');

        if (! $teamId || ! $channelId) {
            Log::warning('Teams notification skipped — TEAMS_TEAM_ID or TEAMS_CHANNEL_ID not configured');
            return;
        }

        $token = $this->getAccessToken();

        $url = sprintf(
            '%s/teams/%s/channels/%s/messages',
            config('ccrs.teams.graph_base_url'),
            $teamId,
            $channelId
        );

        $response = Http::withToken($token)->post($url, [
            'body' => [
                'contentType' => 'html',
                'content'     => "<b>{$subject}</b><br/>{$body}",
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Failed to send Teams notification', [
                'status'     => $response->status(),
                'response'   => $response->json(),
                'subject'    => $subject,
            ]);
            throw new \RuntimeException('Teams notification failed: ' . $response->status());
        }
    }
}
```

### 3.3 Update `NotificationService` to Route Teams Channel

In `app/Services/NotificationService.php`, add a branch in the `send()` method for `channel = 'teams'`:

```php
use App\Services\TeamsNotificationService;

// Inside NotificationService::send() or dispatch():

private function dispatchChannel(string $channel, string $subject, string $body, ?string $userId): void
{
    match ($channel) {
        'email' => $this->sendEmail($userId, $subject, $body),
        'teams' => $this->sendTeams($subject, $body),
        'in_app'=> $this->createInAppNotification($userId, $subject, $body),
        default => Log::warning("Unknown notification channel: {$channel}"),
    };
}

private function sendTeams(string $subject, string $body): void
{
    app(TeamsNotificationService::class)->sendToChannel($subject, $body);
}
```

Ensure the `Notification` model's `channel` field accepts `'teams'` as a valid value. If there is a validation enum anywhere, add `'teams'` to it.

### 3.4 Update `SendPendingNotifications` Job

In `app/Jobs/SendPendingNotifications.php`, ensure the job fetches unsent notifications where `channel = 'teams'` as well as `'email'` and `'in_app'`. The query should not filter by channel — it should process all pending notifications regardless of channel, delegating routing to `NotificationService::dispatchChannel()`.

### 3.5 Write Feature Test

Create `tests/Feature/TeamsNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\TeamsNotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsNotificationTest extends TestCase
{
    public function test_sends_message_to_teams_channel(): void
    {
        config([
            'ccrs.teams.team_id'    => 'test-team-id',
            'ccrs.teams.channel_id' => 'test-channel-id',
            'ccrs.teams.token_endpoint' => 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token',
        ]);

        Http::fake([
            '*/oauth2/v2.0/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*/teams/*/channels/*/messages' => Http::response(['id' => 'msg-123'], 201),
        ]);

        app(TeamsNotificationService::class)->sendToChannel(
            'Contract Approved',
            'Contract XYZ has been approved by Legal.'
        );

        Http::assertSent(fn ($req) => str_contains($req->url(), '/messages'));
    }

    public function test_skips_when_not_configured(): void
    {
        config([
            'ccrs.teams.team_id'    => '',
            'ccrs.teams.channel_id' => '',
        ]);

        Http::fake();

        // Should not throw, just log warning
        app(TeamsNotificationService::class)->sendToChannel('Test', 'Body');

        Http::assertNothingSent();
    }
}
```

---

## Task 4: Update Environment Files

### 4.1 Update `.env.example`

Consolidate all new env vars added in this phase into `.env.example`. Ensure the following are present:

```dotenv
# TiTo API
TITO_API_KEY=your-secret-tito-api-key

# Merchant Agreement Template
MA_TEMPLATE_S3_KEY=templates/merchant_agreement_master.docx

# Microsoft Teams Notifications
TEAMS_TEAM_ID=your-teams-team-id
TEAMS_CHANNEL_ID=your-teams-channel-id
```

---

## Verification Checklist

After completing all tasks, verify the following:

1. **TiTo Validation:**
   - `curl -H "X-TiTo-API-Key: <key>" "http://localhost:8080/api/tito/validate?vendor_id=<uuid>"` returns `{"valid":false,"status":"no_signed_agreement",...}` when no signed agreement exists.
   - Same call with a real signed agreement returns `{"valid":true,"status":"signed",...}`.
   - Missing or wrong API key returns `{"error":"Unauthorized"}` with status 401.
   - All calls appear in the `audit_log` table.

2. **DOCX Generation:**
   - In Filament, navigate to Merchant Agreements → select any row → click "Generate Agreement".
   - No error notification appears.
   - A new record appears in the Contracts table with `contract_type = 'merchant_agreement'` and a non-null `storage_path`.
   - The `storage_path` key exists in S3.

3. **Teams Notifications:**
   - `php artisan tinker` → `app(\App\Services\TeamsNotificationService::class)->sendToChannel('Test', 'Hello Teams')` completes without exception when Teams env vars are set.
   - The Graph token is cached (second call does not re-request token).

4. **Tests:**
   ```bash
   php artisan test --filter=TitoValidationTest
   php artisan test --filter=MerchantAgreementGenerationTest
   php artisan test --filter=TeamsNotificationTest
   ```
   All pass.
