# Cursor Prompt — Laravel Migration Phase D: Azure AD Auth + Integrations + Polish

## Context

Phases A, B, and C are complete. The application has all Filament Resources, the AI worker is running, and Docker Compose works end-to-end. Phase D wires up:
1. Azure AD authentication replacing Filament's built-in login
2. Role assignment from Azure AD group memberships via Microsoft Graph
3. SendGrid email delivery
4. BoldSign webhook HMAC verification
5. Laravel Horizon queue monitoring
6. Filament Shield permission defaults
7. Feature tests for auth, webhooks, and AI job
8. Final polish (navigation icons, page titles, error pages)

---

## Task 1: Implement Azure AD Authentication

### 1.1 Add Socialite Provider

In `config/services.php`, add Azure AD configuration:
```php
'azure' => [
    'client_id' => env('AZURE_AD_CLIENT_ID'),
    'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
    'redirect' => env('APP_URL') . '/auth/azure/callback',
    'tenant' => env('AZURE_AD_TENANT_ID'),
    'proxy' => null,
],
```

In `config/app.php` providers array, add:
```php
\SocialiteProviders\Manager\ServiceProvider::class,
```

In `app/Providers/EventServiceProvider.php` `$listen` array:
```php
\SocialiteProviders\Manager\SocialiteWasCalled::class => [
    \SocialiteProviders\Azure\AzureExtendSocialite::class . '@handle',
],
```

### 1.2 Create `AzureAdController.php`

Create `app/Http/Controllers/Auth/AzureAdController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzureAdController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $socialiteUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
            Log::error('Azure AD callback failed', ['error' => $e->getMessage()]);
            return redirect('/admin/login')->withErrors(['auth' => 'Azure AD authentication failed. Please try again.']);
        }

        // Determine role from Azure AD group memberships
        $role = $this->resolveRole($socialiteUser->token);

        // Upsert user in database
        $user = User::updateOrCreate(
            ['id' => $socialiteUser->getId()],
            [
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
            ]
        );

        // Sync Spatie role
        if ($role) {
            $user->syncRoles([$role]);
        } else {
            // No matching group — revoke all roles (prevent unauthorized access)
            $user->syncRoles([]);
            Log::warning('Azure AD user has no CCRS group membership', ['email' => $user->email]);
            return redirect('/admin/login')->withErrors(['auth' => 'You do not have access to CCRS. Contact your administrator.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }

    private function resolveRole(?string $accessToken): ?string
    {
        if (!$accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://graph.microsoft.com/v1.0/me/memberOf', [
                    '$select' => 'id,displayName',
                ]);

            if (!$response->successful()) {
                Log::warning('Microsoft Graph memberOf call failed', ['status' => $response->status()]);
                return null;
            }

            $groups = $response->json('value', []);
            $groupIds = array_column($groups, 'id');
            $groupMap = array_filter(config('ccrs.azure_ad.group_map'));

            // First matching group wins (order matters: most privileged first)
            $priorityOrder = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];
            $userRoles = [];
            foreach ($groupMap as $groupId => $roleName) {
                if (in_array($groupId, $groupIds, true)) {
                    $userRoles[] = $roleName;
                }
            }

            foreach ($priorityOrder as $role) {
                if (in_array($role, $userRoles)) {
                    return $role;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to resolve Azure AD role', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
```

### 1.3 Register Azure Routes

In `routes/web.php`:
```php
use App\Http\Controllers\Auth\AzureAdController;

Route::get('/auth/azure/redirect', [AzureAdController::class, 'redirect'])->name('azure.redirect');
Route::get('/auth/azure/callback', [AzureAdController::class, 'callback'])->name('azure.callback');
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');
```

### 1.4 Override Filament Login Page

In `app/Providers/Filament/AdminPanelProvider.php`:

```php
use Filament\Http\Middleware\Authenticate;

// Replace the default login route with Azure AD redirect
->login(false)  // disable Filament's built-in email/password form
->authMiddleware([
    Authenticate::class,
])
->authGuard('web')
```

Create a custom Filament Login page that immediately redirects to Azure:
```bash
php artisan make:filament-page AzureLoginPage
```

In `app/Filament/Pages/AzureLoginPage.php`:
```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Login;

class AzureLoginPage extends Login
{
    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(filament()->getHomeUrl());
        }
        // Immediately redirect to Azure AD
        $this->redirect(route('azure.redirect'));
    }
}
```

In `AdminPanelProvider.php`:
```php
->login(\App\Filament\Pages\AzureLoginPage::class)
```

---

## Task 2: Configure Filament Shield Permissions

After running `php artisan shield:generate --all`, configure default role permissions.

In a seeder `database/seeders/ShieldPermissionSeeder.php`, define default permissions per role:

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$rolePermissions = [
    'system_admin' => ['*'],  // handled by shield:generate --all --option=super-admin
    'legal' => [
        'view_any_contract', 'view_contract', 'create_contract', 'update_contract',
        'view_any_counterparty', 'view_counterparty', 'create_counterparty', 'update_counterparty',
        'view_any_wiki_contract', 'view_wiki_contract', 'create_wiki_contract', 'update_wiki_contract',
        'view_any_audit_log', 'view_audit_log',
        'view_any_override_request', 'view_override_request', 'update_override_request',
        'view_any_notification', 'view_notification',
        'page_Reports', 'page_EscalationsPage', 'page_KeyDatesPage', 'page_RemindersPage',
    ],
    'commercial' => [
        'view_any_contract', 'view_contract', 'create_contract',
        'view_any_counterparty', 'view_counterparty', 'create_counterparty',
        'view_any_merchant_agreement', 'view_merchant_agreement', 'create_merchant_agreement',
        'view_any_notification', 'view_notification',
        'page_KeyDatesPage', 'page_RemindersPage',
    ],
    'finance' => [
        'view_any_contract', 'view_contract',
        'page_Reports',
        'view_any_notification', 'view_notification',
    ],
    'operations' => [
        'view_any_contract', 'view_contract',
        'page_KeyDatesPage', 'page_RemindersPage',
        'view_any_notification', 'view_notification',
    ],
    'audit' => [
        'view_any_contract', 'view_contract',
        'view_any_audit_log', 'view_audit_log',
        'page_Reports',
        'view_any_notification', 'view_notification',
    ],
];

foreach ($rolePermissions as $roleName => $permissions) {
    if ($roleName === 'system_admin') continue; // system_admin has super-admin
    $role = Role::findByName($roleName);
    $permModels = Permission::whereIn('name', $permissions)->get();
    $role->syncPermissions($permModels);
}
```

Run after shield:generate:
```bash
php artisan shield:generate --all
php artisan db:seed --class=ShieldPermissionSeeder
```

---

## Task 3: Configure SendGrid Mail

In `config/mail.php`, ensure the default mailer uses SMTP:
```php
'default' => env('MAIL_MAILER', 'smtp'),
'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.sendgrid.net'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME', 'apikey'),
        'password' => env('MAIL_PASSWORD'),  // SendGrid API key
        'timeout' => null,
    ],
],
```

Ensure `app/Mail/NotificationMail.php` (created in Phase B) is complete:
```php
<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationMail extends Mailable
{
    public function __construct(public Notification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification', with: ['notification' => $this->notification]);
    }
}
```

Create `resources/views/emails/notification.blade.php`:
```html
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $notification->subject }}</title></head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #4f46e5;">CCRS Notification</h2>
    <p>{{ $notification->body }}</p>
    <hr>
    <p style="font-size: 12px; color: #6b7280;">
        This is an automated notification from CCRS.
        Do not reply to this email.
    </p>
</body>
</html>
```

---

## Task 4: Finalize BoldSign Webhook with HMAC Verification

Ensure `BoldsignService::verifyWebhookSignature()` is fully implemented:
```php
public function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
{
    if (empty($signature) || empty($secret)) {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
```

In `BoldsignWebhookController::handle()`, ensure the signature is read from the correct header:
```php
$signature = $request->header('X-BoldSign-Signature')
    ?? $request->header('Boldsign-Signature')
    ?? '';
```

Test with an invalid signature: should return HTTP 401 with `{"error": "Invalid signature"}`.

---

## Task 5: Configure Laravel Horizon

Ensure Horizon is fully configured for Docker Compose.

In `app/Providers/HorizonServiceProvider.php`, restrict dashboard access:
```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return $user instanceof \App\Models\User && $user->hasRole('system_admin');
    });
}
```

In `docker-compose.yml`, the `app` service command should include Horizon instead of plain queue:work:
```yaml
command: >
  sh -c "php artisan migrate --force &&
         php artisan horizon &
         php artisan schedule:work &
         nginx -g 'daemon off;' &
         php-fpm"
```

Ensure `config/horizon.php` has the correct `environments` config (from Phase B Task 10).

---

## Task 6: Polish — Navigation Icons and Sidebar Order

In each Filament Resource, set appropriate Heroicon icons using `protected static ?string $navigationIcon`:

| Resource | Icon |
|---|---|
| ContractResource | `heroicon-o-document-text` |
| MerchantAgreementResource | `heroicon-o-clipboard-document` |
| CounterpartyResource | `heroicon-o-building-office-2` |
| WorkflowTemplateResource | `heroicon-o-arrow-path` |
| WikiContractResource | `heroicon-o-book-open` |
| RegionResource | `heroicon-o-map` |
| EntityResource | `heroicon-o-building-library` |
| ProjectResource | `heroicon-o-folder` |
| SigningAuthorityResource | `heroicon-o-pen-nib` |
| OverrideRequestResource | `heroicon-o-exclamation-triangle` |
| AuditLogResource | `heroicon-o-shield-check` |
| NotificationResource | `heroicon-o-bell` |

For Custom Pages:
| Page | Icon |
|---|---|
| ReportsPage | `heroicon-o-chart-bar` |
| EscalationsPage | `heroicon-o-fire` |
| KeyDatesPage | `heroicon-o-calendar-days` |
| RemindersPage | `heroicon-o-clock` |
| NotificationsPage | `heroicon-o-bell-alert` |

Set navigation sort order via `protected static ?int $navigationSort`:
- Contracts: 1, MerchantAgreements: 2, Counterparties: 3, WikiContracts: 4
- Regions: 10, Entities: 11, Projects: 12, SigningAuthority: 13
- WorkflowTemplates: 20, OverrideRequests: 21, Escalations: 22, KeyDates: 23, Reminders: 24
- AuditLog: 30, Reports: 31, Notifications: 32

---

## Task 7: Configure Error Pages

Create `resources/views/errors/403.blade.php`:
```html
<!DOCTYPE html>
<html>
<head><title>Access Denied — CCRS</title></head>
<body style="font-family: sans-serif; text-align: center; padding: 60px;">
    <h1>403 — Access Denied</h1>
    <p>You do not have permission to access this resource.</p>
    <a href="/admin">Return to Dashboard</a>
</body>
</html>
```

Create `resources/views/errors/500.blade.php` similarly.

---

## Task 8: Write Feature Tests

Create the following in `tests/Feature/`:

### `AzureAuthTest.php`
```php
use Laravel\Socialite\Facades\Socialite;
use Mockery;

it('redirects to azure on login page visit', function () {
    $response = $this->get('/auth/azure/redirect');
    $response->assertRedirect(); // redirects to Microsoft login
});

it('creates user on callback and assigns role', function () {
    $mockUser = Mockery::mock('Laravel\Socialite\Contracts\User');
    $mockUser->shouldReceive('getId')->andReturn('azure-object-id-123');
    $mockUser->shouldReceive('getEmail')->andReturn('user@digittal.com');
    $mockUser->shouldReceive('getName')->andReturn('Test User');
    $mockUser->token = 'fake-token';

    Socialite::shouldReceive('driver->user')->andReturn($mockUser);

    // Mock Graph API call
    Http::fake([
        'graph.microsoft.com/*' => Http::response([
            'value' => [['id' => config('ccrs.azure_ad.group_map') ? array_key_first(config('ccrs.azure_ad.group_map')) : 'test-group-id', 'displayName' => 'CCRS System Admin']]
        ], 200),
    ]);

    $response = $this->get('/auth/azure/callback');
    $response->assertRedirect('/admin');

    $user = \App\Models\User::find('azure-object-id-123');
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('user@digittal.com');
});

it('rejects users with no matching Azure group', function () {
    $mockUser = Mockery::mock('Laravel\Socialite\Contracts\User');
    $mockUser->shouldReceive('getId')->andReturn('unknown-user-456');
    $mockUser->shouldReceive('getEmail')->andReturn('noaccess@example.com');
    $mockUser->shouldReceive('getName')->andReturn('No Access User');
    $mockUser->token = 'fake-token';

    Socialite::shouldReceive('driver->user')->andReturn($mockUser);

    Http::fake([
        'graph.microsoft.com/*' => Http::response(['value' => []], 200),
    ]);

    $response = $this->get('/auth/azure/callback');
    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors('auth');
});
```

### `BoldsignWebhookTest.php`
```php
use App\Services\BoldsignService;

it('returns 401 for invalid webhook signature', function () {
    $response = $this->postJson('/api/webhooks/boldsign', ['event' => 'signed'], [
        'X-BoldSign-Signature' => 'invalidsignature',
    ]);
    $response->assertStatus(401);
});

it('processes valid webhook payload', function () {
    $secret = config('ccrs.boldsign_webhook_secret', 'test-secret');
    $payload = json_encode(['event' => 'DocumentCompleted', 'document_id' => 'doc123', 'status' => 'completed']);
    $signature = hash_hmac('sha256', $payload, $secret);

    $mockService = Mockery::mock(BoldsignService::class);
    $mockService->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $mockService->shouldReceive('handleWebhook')->once();
    app()->instance(BoldsignService::class, $mockService);

    $response = $this->postJson('/api/webhooks/boldsign',
        json_decode($payload, true),
        ['X-BoldSign-Signature' => $signature]
    );
    $response->assertOk()->assertJson(['ok' => true]);
});
```

### `ProcessAiAnalysisJobTest.php`
```php
use App\Jobs\ProcessAiAnalysis;
use App\Models\AiAnalysisResult;
use App\Models\Contract;
use App\Services\AiWorkerClient;

it('creates analysis result on successful ai worker response', function () {
    $contract = Contract::factory()->create(['storage_path' => 'contracts/test/sample.pdf']);

    $mockClient = Mockery::mock(AiWorkerClient::class);
    $mockClient->shouldReceive('analyze')->andReturn([
        'result' => ['summary' => 'Test contract summary'],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'cost_usd' => 0.001, 'processing_time_ms' => 1500, 'model_used' => 'claude-sonnet-4-6'],
    ]);
    app()->instance(AiWorkerClient::class, $mockClient);

    ProcessAiAnalysis::dispatchSync($contract->id, 'summary');

    $analysis = AiAnalysisResult::where('contract_id', $contract->id)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
    expect($analysis->result)->toBe(['summary' => 'Test contract summary']);
});

it('marks analysis as failed when ai worker throws', function () {
    $contract = Contract::factory()->create(['storage_path' => 'contracts/test/sample.pdf']);

    $mockClient = Mockery::mock(AiWorkerClient::class);
    $mockClient->shouldReceive('analyze')->andThrow(new \RuntimeException('AI worker timeout'));
    app()->instance(AiWorkerClient::class, $mockClient);

    ProcessAiAnalysis::dispatchSync($contract->id, 'risk');

    $analysis = AiAnalysisResult::where('contract_id', $contract->id)->where('analysis_type', 'risk')->first();
    expect($analysis->status)->toBe('failed');
    expect($analysis->error_message)->toContain('AI worker timeout');
});
```

### Factories for tests

Create `database/factories/ContractFactory.php`:
```php
public function definition(): array {
    $region = \App\Models\Region::factory()->create();
    $entity = \App\Models\Entity::factory()->for($region)->create();
    $project = \App\Models\Project::factory()->for($entity)->create();

    return [
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => \App\Models\Counterparty::factory(),
        'contract_type' => 'Commercial',
        'title' => fake()->sentence(4),
        'workflow_state' => 'draft',
        'storage_path' => null,
        'file_name' => null,
    ];
}
```
Create similar minimal factories for Region, Entity, Project, Counterparty, User.

---

## Task 9: Final README for Laravel App

Create `README.md` documenting:
1. **Prerequisites**: Docker, Docker Compose, Azure AD app registration
2. **Quick start**: `cp .env.example .env && docker compose up --build`
3. **Environment variables**: reference table of all required vars
4. **Architecture**: Laravel + Filament + MySQL + Redis + Python AI Worker
5. **Azure AD setup**: required OAuth2 scopes, redirect URI, group IDs
6. **Kubernetes deployment**: reference to CTO's build pipeline (coming)
7. **Migrating from FastAPI**: note that `apps/api/` and `apps/web/` are the legacy implementation

---

## Verification Checklist

After completing all tasks:

1. **Azure AD login:**
   - Open `http://localhost:8000/admin` — redirected to Microsoft login
   - Authenticate with Azure AD credentials that belong to a CCRS group
   - After login, land on Dashboard with correct username displayed
   - Verify role is set: `docker compose exec app php artisan tinker` → `User::find('your-azure-id')->getRoleNames()`

2. **RBAC enforcement:**
   - Log in as a `commercial` user → verify no access to `/admin/regions`, `/admin/audit-logs`, `/admin/shield`
   - Log in as `system_admin` → verify all resources are accessible

3. **BoldSign webhook:**
   ```bash
   SECRET="your_webhook_secret"
   PAYLOAD='{"event":"DocumentCompleted","document_id":"test123","status":"completed"}'
   SIG=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
   curl -X POST http://localhost:8000/api/webhooks/boldsign \
     -H "Content-Type: application/json" \
     -H "X-BoldSign-Signature: $SIG" \
     -d "$PAYLOAD"
   # Should return {"ok": true}
   ```

4. **Unauthorized webhook:**
   ```bash
   curl -X POST http://localhost:8000/api/webhooks/boldsign \
     -H "Content-Type: application/json" \
     -H "X-BoldSign-Signature: badsignature" \
     -d '{"event":"test"}'
   # Should return {"error": "Invalid signature"} with HTTP 401
   ```

5. **Horizon:** Open `http://localhost:8000/horizon` (System Admin session) → shows queue metrics

6. **Email:** `docker compose exec app php artisan tinker` → `Mail::to('test@example.com')->send(new \App\Mail\NotificationMail(\App\Models\Notification::factory()->make()))` → no exceptions

7. **All tests pass:** `docker compose exec app php artisan test`

8. **Schedule works:** `docker compose exec app php artisan schedule:run` — no errors, jobs dispatched

9. **Full end-to-end flow:**
   - Login as system_admin
   - Create Region → Entity → Project → Counterparty
   - Upload a PDF Contract
   - Start Workflow (create a WorkflowTemplate first, publish it, then start)
   - Trigger AI Analysis on the contract
   - Check queue worker processes the job
   - Verify AI analysis results appear in the AiAnalysis tab on the Contract view
   - Check audit log records all actions
