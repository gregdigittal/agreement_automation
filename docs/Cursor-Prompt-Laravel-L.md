# Cursor Prompt — Laravel Migration Phase L: Deployment Readiness, Business Logic Gaps & Repo Sync

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through K were executed.

This is the final pre-deployment prompt. It closes all remaining gaps that would prevent a working first deploy to `digittaldotio/digittal-ccrs`. The CTO has stated:

> **"The application manages the database from code — you need to create Laravel migrations and the code and deployment pipeline will create the tables and links. The build + launch will set up the DB and all structures."**

This means:
1. **All schema comes from Laravel migrations** — `php artisan migrate --force` runs automatically on every deploy via the template's `docker/docker-entrypoint.sh`.
2. **Database seeders must be idempotent** and safe to run on every deploy (use `firstOrCreate`, never raw insert).
3. **No manual DB setup** — the pipeline builds the Docker image, deploys to K8s, and the entrypoint bootstraps everything.

This prompt covers:
1. Complete `.env.example` with **all** variables from Prompts A–K
2. Comprehensive `DatabaseSeeder` + `ccrs:create-admin` Artisan command
3. Signing Authority enforcement in WorkflowService
4. Contract immutability after BoldSign execution
5. Notification preferences (per-user email/Teams toggles)
6. CTO deployment template alignment — ensure entrypoint seeds on first deploy
7. Repo sync — push `laravel-migration` to `digittal-ccrs:main` + auto-sync workflow

---

## Task 1: Complete `.env.example` with All Variables

Open the existing `.env.example` (created in Prompt A) and **append** the following variables that were introduced in Prompts E through K. Do NOT remove any existing variables — only add what is missing.

Add these blocks after the existing `ANTHROPIC_API_KEY` line:

```dotenv
# --- TiTo Validation API (Prompt E) ---
TITO_API_KEY=

# --- Microsoft Teams Notifications (Prompt E) ---
TEAMS_TENANT_ID=
TEAMS_CLIENT_ID=
TEAMS_CLIENT_SECRET=
TEAMS_TEAM_ID=
TEAMS_CHANNEL_ID=

# --- Meilisearch / Laravel Scout (Prompt G) ---
SCOUT_DRIVER=null
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=

# --- OpenTelemetry (Prompt G) ---
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_SERVICE_NAME=ccrs

# --- Vendor Portal (Prompt J) ---
VENDOR_PORTAL_URL="${APP_URL}/vendor"

# --- Redis (extended — Prompt K) ---
REDIS_PASSWORD=

# --- Feature Flags (Prompt K) ---
FEATURE_VENDOR_PORTAL=true
FEATURE_MEILISEARCH=false
FEATURE_REDLINING=false
FEATURE_REGULATORY_COMPLIANCE=false
FEATURE_ADVANCED_ANALYTICS=false
```

Also update the K8s secrets template at `deploy/k8s/secrets.yaml.template` — add these keys in the `stringData:` block:

```yaml
  TITO_API_KEY: "CHANGE_ME"
  TEAMS_TENANT_ID: ""
  TEAMS_CLIENT_ID: ""
  TEAMS_CLIENT_SECRET: ""
  TEAMS_TEAM_ID: ""
  TEAMS_CHANNEL_ID: ""
  MEILISEARCH_HOST: "http://meilisearch:7700"
  MEILISEARCH_KEY: "CHANGE_ME"
```

---

## Task 2: Comprehensive DatabaseSeeder + Admin Bootstrap Command

### 2.1 Wire Up `DatabaseSeeder.php`

The `RoleSeeder` (Prompt A) and `ShieldPermissionSeeder` (Prompt D) already exist. Wire them into a single `DatabaseSeeder` that is safe to run on every deploy:

**Update `database/seeders/DatabaseSeeder.php`:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,              // Creates 6 roles (firstOrCreate — idempotent)
            ShieldPermissionSeeder::class,   // Syncs permissions per role (idempotent)
        ]);
    }
}
```

### 2.2 Ensure Both Seeders Are Idempotent

**Verify `RoleSeeder.php`** uses `firstOrCreate` (it should from Prompt A):
```php
$roles = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];
foreach ($roles as $role) {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
}
```

**Verify `ShieldPermissionSeeder.php`** uses `syncPermissions` (it should from Prompt D) — `syncPermissions` is inherently idempotent.

### 2.3 Update `docker/docker-entrypoint.sh` to Run Seeders on Deploy

The CTO's template entrypoint already runs `php artisan migrate --force`. **Append** the seeder call immediately after the migrate line. Open the existing `docker/docker-entrypoint.sh` and find the migrate line, then add the seed call right after it:

```bash
# After the existing migrate line:
php artisan migrate --force

# Add this line immediately below:
php artisan db:seed --force
```

The `--force` flag on `db:seed` allows it to run in production. Because both seeders use `firstOrCreate`/`syncPermissions`, running them repeatedly is safe and ensures roles + permissions are always current after a deploy.

### 2.4 Create `ccrs:create-admin` Artisan Command

This command bootstraps the first admin user for initial setup before Azure AD is configured, or for emergency access.

**Create `app/Console/Commands/CreateAdminUser.php`:**

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'ccrs:create-admin {email} {--name= : Display name for the user}';
    protected $description = 'Create or promote a user to system_admin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? explode('@', $email)[0];

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'id' => User::where('email', $email)->value('id') ?? Str::uuid()->toString(),
                'name' => $name,
            ]
        );

        $user->syncRoles(['system_admin']);

        $this->info("User '{$user->name}' ({$user->email}) is now system_admin.");
        $this->info("User ID: {$user->id}");

        return self::SUCCESS;
    }
}
```

This command is idempotent — running it twice on the same email just confirms the role.

---

## Task 3: Signing Authority Enforcement in WorkflowService

In `app/Services/WorkflowService.php`, the `recordAction()` method (from Prompt B) already checks actor authorization, but it does not explicitly query the `signing_authority` table when the stage type is `signing`.

### 3.1 Add `checkSigningAuthority()` Private Method

Add this method to `WorkflowService`:

```php
/**
 * Verify that the actor has signing authority for this contract.
 * Called when advancing through a signing-type workflow stage.
 *
 * @throws \RuntimeException if no matching signing authority exists
 */
private function checkSigningAuthority(Contract $contract, User $actor, string $stageName): void
{
    $query = \App\Models\SigningAuthority::query()
        ->where('entity_id', $contract->entity_id)
        ->where(function ($q) use ($contract) {
            $q->whereNull('project_id')
              ->orWhere('project_id', $contract->project_id);
        })
        ->where('user_id', $actor->id);

    // If the signing authority has a contract_type_pattern, check it matches
    $authority = $query->first();

    if (!$authority) {
        throw new \RuntimeException(
            "No signing authority for user {$actor->email} on entity {$contract->entity_id}" .
            ($contract->project_id ? " / project {$contract->project_id}" : '') .
            ". A signing authority record must exist before contracts can be signed at stage '{$stageName}'."
        );
    }

    // If pattern is set, verify contract type matches
    if ($authority->contract_type_pattern) {
        $pattern = strtolower($authority->contract_type_pattern);
        $type = strtolower($contract->contract_type);
        if ($pattern !== '*' && $pattern !== $type) {
            throw new \RuntimeException(
                "Signing authority for {$actor->email} is restricted to '{$authority->contract_type_pattern}' " .
                "contracts, but this contract is '{$contract->contract_type}'."
            );
        }
    }
}
```

### 3.2 Call It from `recordAction()`

In the `recordAction()` method, after the existing authorization check (line that says "Check actor authorization"), add:

```php
// Determine the stage type from the template
$stages = collect($instance->template->stages);
$currentStageConfig = $stages->firstWhere('name', $stageName);

if ($currentStageConfig && ($currentStageConfig['type'] ?? null) === 'signing') {
    $this->checkSigningAuthority($instance->contract, $actor, $stageName);
}
```

This ensures that any workflow stage of type `signing` (as defined in the template's `stages` JSON) cannot be advanced without a matching `signing_authority` record.

---

## Task 4: Contract Immutability After BoldSign Execution

### 4.1 Update BoldsignWebhookController

In `app/Http/Controllers/Api/BoldsignWebhookController.php`, when the webhook fires `DocumentCompleted`, update the contract's `workflow_state` to `executed` and `signing_status` to `completed`:

Find the webhook event handler for `DocumentCompleted` and ensure it includes:

```php
// Inside the DocumentCompleted handler (after updating the BoldsignEnvelope):
$contract = $envelope->contract;
$contract->update([
    'signing_status' => 'completed',
    'workflow_state' => 'executed',
]);
```

### 4.2 Lock ContractResource Form for Executed Contracts

In `app/Filament/Resources/ContractResource.php`, make all form fields read-only when the contract is executed.

In the `form()` method, after defining all form fields, wrap the form sections with a disabled check:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        // ... existing form sections ...
    ])->disabled(fn (?Contract $record): bool =>
        $record !== null && in_array($record->workflow_state, ['executed', 'completed'])
    );
}
```

### 4.3 Conditionally Show Edit Action on Table

In the `table()` method's actions array, add a visibility condition to the edit action:

```php
Tables\Actions\EditAction::make()
    ->visible(fn (Contract $record): bool =>
        !in_array($record->workflow_state, ['executed', 'completed'])
    ),
```

### 4.4 Add Status Banner on View Page

In `ContractResource/Pages/ViewContract.php` (or `EditContract.php` if view uses edit), add an infolist banner:

```php
// At the top of the view page content:
use Filament\Infolists\Components\TextEntry;

// Add to the infolist schema:
\Filament\Infolists\Components\Section::make('Contract Status')
    ->schema([
        TextEntry::make('workflow_state')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'executed' => 'success',
                'completed' => 'success',
                'cancelled' => 'danger',
                default => 'warning',
            }),
    ])
    ->visible(fn (Contract $record): bool =>
        in_array($record->workflow_state, ['executed', 'completed'])
    )
    ->columnSpanFull(),
```

---

## Task 5: Notification Preferences

### 5.1 Add Migration for User Preferences

Create `database/migrations/XXXX_add_notification_preferences_to_users.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
```

The JSON structure:
```json
{
    "email": true,
    "teams": true,
    "channels": {
        "workflow_actions": ["email", "teams"],
        "escalations": ["email", "teams"],
        "reminders": ["email"],
        "contract_status": ["email"]
    }
}
```

### 5.2 Update User Model

In `app/Models/User.php`, add `notification_preferences` to `$fillable` and `$casts`:

```php
protected $fillable = ['id', 'email', 'name', 'notification_preferences'];

protected $casts = [
    'roles' => 'array',
    'notification_preferences' => 'array',
];

/**
 * Check if user wants notifications on a given channel for a given category.
 */
public function wantsNotification(string $category, string $channel): bool
{
    $prefs = $this->notification_preferences ?? [];

    // If no preferences set, default to email=true, teams=true
    if (empty($prefs)) {
        return true;
    }

    // Check global channel toggle first
    if (isset($prefs[$channel]) && $prefs[$channel] === false) {
        return false;
    }

    // Check category-specific channels
    $categoryChannels = $prefs['channels'][$category] ?? [$channel];
    return in_array($channel, $categoryChannels);
}
```

### 5.3 Create NotificationPreferencesPage

Create `app/Filament/Pages/NotificationPreferencesPage.php`:

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

class NotificationPreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Notification Preferences';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 40;
    protected static string $view = 'filament.pages.notification-preferences';

    public bool $email_enabled = true;
    public bool $teams_enabled = true;
    public array $workflow_actions = ['email', 'teams'];
    public array $escalations = ['email', 'teams'];
    public array $reminders = ['email'];
    public array $contract_status = ['email'];

    public function mount(): void
    {
        $prefs = auth()->user()->notification_preferences ?? [];

        $this->email_enabled = $prefs['email'] ?? true;
        $this->teams_enabled = $prefs['teams'] ?? true;
        $this->workflow_actions = $prefs['channels']['workflow_actions'] ?? ['email', 'teams'];
        $this->escalations = $prefs['channels']['escalations'] ?? ['email', 'teams'];
        $this->reminders = $prefs['channels']['reminders'] ?? ['email'];
        $this->contract_status = $prefs['channels']['contract_status'] ?? ['email'];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Global Toggles')
                ->description('Master switches for each notification channel.')
                ->schema([
                    Toggle::make('email_enabled')->label('Email notifications'),
                    Toggle::make('teams_enabled')->label('Microsoft Teams notifications'),
                ]),

            Section::make('Per-Category Channels')
                ->description('Choose which channels to use for each notification type.')
                ->schema([
                    CheckboxList::make('workflow_actions')
                        ->label('Workflow Actions (approvals, rejections, rework)')
                        ->options(['email' => 'Email', 'teams' => 'Teams']),
                    CheckboxList::make('escalations')
                        ->label('SLA Escalations')
                        ->options(['email' => 'Email', 'teams' => 'Teams']),
                    CheckboxList::make('reminders')
                        ->label('Key Date Reminders')
                        ->options(['email' => 'Email', 'teams' => 'Teams', 'calendar' => 'Calendar (ICS)']),
                    CheckboxList::make('contract_status')
                        ->label('Contract Status Changes')
                        ->options(['email' => 'Email', 'teams' => 'Teams']),
                ]),
        ]);
    }

    public function save(): void
    {
        auth()->user()->update([
            'notification_preferences' => [
                'email' => $this->email_enabled,
                'teams' => $this->teams_enabled,
                'channels' => [
                    'workflow_actions' => $this->workflow_actions,
                    'escalations' => $this->escalations,
                    'reminders' => $this->reminders,
                    'contract_status' => $this->contract_status,
                ],
            ],
        ]);

        Notification::make()->title('Preferences saved.')->success()->send();
    }
}
```

Create the Blade view at `resources/views/filament/pages/notification-preferences.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Preferences
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

### 5.4 Update NotificationService to Check Preferences

Wherever notifications are dispatched (e.g., in `NotificationService`, `SendReminders` job, `CheckSlaBreaches` job), add a preference check before sending:

```php
// Before dispatching an email notification:
$user = User::find($recipientUserId);
if ($user && !$user->wantsNotification($category, 'email')) {
    // Skip — user has opted out of email for this category
    return;
}

// Before dispatching a Teams notification:
if ($user && !$user->wantsNotification($category, 'teams')) {
    return;
}
```

The `$category` parameter should be one of: `'workflow_actions'`, `'escalations'`, `'reminders'`, `'contract_status'`.

---

## Task 6: CTO Deployment Template Alignment

The CTO's deployment template (`sandbox-template-laravel-filament`) was fetched in Prompt A. The live repo `digittaldotio/digittal-ccrs` at commit `2eb248c9` is built from this template. Verify and ensure our code meshes with it.

### 6.1 Verify Template Files Are Preserved

Run these checks — if any file is missing, the CTO's Jenkinsfile CI pipeline will break:

```bash
# These files must exist at the repo root (from template):
ls -la Dockerfile          # Multi-stage PHP 8.3 build, EXPOSE 8080
ls -la Jenkinsfile         # CTO's CI/CD pipeline — DO NOT MODIFY
ls -la docker/docker-entrypoint.sh    # Runs migrate + filament:assets
ls -la docker/nginx/default.conf      # Listens on 8080
ls -la docker/supervisor/supervisord.conf  # php-fpm + nginx + queue-worker + scheduler
```

If any of these are missing, re-fetch them from the template:
```bash
git remote add template https://github.com/digittaldotio/sandbox-template-laravel-filament.git 2>/dev/null || true
git fetch template main
git checkout template/main -- Dockerfile Jenkinsfile docker/
git remote remove template
```

### 6.2 Confirm Entrypoint Runs Migrations + Seeds

Open `docker/docker-entrypoint.sh` and verify it contains both migration and seed commands. The file should include at minimum:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan filament:assets
```

If the entrypoint only has `migrate --force` and `filament:assets`, add `php artisan db:seed --force` between them. This is critical — the CTO's directive is that the build + launch sets up the DB and all structures.

### 6.3 Confirm Port 8080 Everywhere

Verify these all use port 8080 (not 8000 or 80):
- `docker/nginx/default.conf` → `listen 8080`
- `Dockerfile` → `EXPOSE 8080`
- `docker-compose.yml` → ports `"8080:8080"`
- `deploy/k8s/deployment.yaml` → `containerPort: 8080`
- Health probes → port `8080`

---

## Task 7: Repo Sync — Push to `digittal-ccrs` + Auto-Sync Workflow

### 7.1 Verify Access

The `gregdigittal` GitHub account now has write access to `digittaldotio/digittal-ccrs`. Verify:

```bash
git ls-remote git@github.com:digittaldotio/digittal-ccrs.git HEAD 2>&1
```

If SSH fails, try HTTPS:
```bash
git ls-remote https://github.com/digittaldotio/digittal-ccrs.git HEAD 2>&1
```

If both fail, the `gregdigittal` account may not yet have collaborator access. Stop here and tell the user to check with the CTO.

### 7.2 Inspect Target Repo

The target repo already has commit `2eb248c9` on `main` (the CTO's template scaffold). Fetch and inspect:

```bash
git remote remove ccrs 2>/dev/null || true
git remote add ccrs git@github.com:digittaldotio/digittal-ccrs.git
git fetch ccrs main
git log --oneline ccrs/main | head -10
```

### 7.3 Push `laravel-migration` to `digittal-ccrs:main`

Because the CTO's template files (Dockerfile, Jenkinsfile, docker/, deploy/) were already fetched into `laravel-migration` in Prompt A, our branch is a superset of the template. Force-push is safe for this initial sync:

```bash
git push ccrs laravel-migration:main --force-with-lease
```

If `--force-with-lease` is rejected (someone pushed to `digittal-ccrs:main` since `2eb248c9`), fetch first and verify:

```bash
git fetch ccrs main
git log --oneline ccrs/main | head -5
# If it's only the original template commit(s), force is safe:
git push ccrs laravel-migration:main --force
```

### 7.4 Create GitHub Actions Auto-Sync Workflow

Create `.github/workflows/sync-to-digittal-ccrs.yml` at the repo root:

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

### 7.5 Add PAT Secret to `agreement_automation` Repo

1. Go to: `https://github.com/gregdigittal/agreement_automation/settings/secrets/actions`
2. Click **New repository secret**
3. Name: `DIGITTAL_CCRS_PAT`
4. Value: a GitHub Personal Access Token (Classic) with `repo` scope, from an account that has write access to `digittaldotio/digittal-ccrs`
5. Click **Add secret**

### 7.6 Commit and Push the Workflow

```bash
git add .github/workflows/sync-to-digittal-ccrs.yml
git commit -m "ci: add GitHub Actions auto-sync to digittal-ccrs on laravel-migration push"
git push origin laravel-migration
```

This push itself will trigger the workflow and mirror everything to `digittal-ccrs:main`.

### 7.7 Verify Sync

After the push, check:
1. `https://github.com/gregdigittal/agreement_automation/actions` — workflow should show green
2. `https://github.com/digittaldotio/digittal-ccrs` — should show all Laravel code on `main`
3. The CTO's Jenkins pipeline should automatically detect the new push and build

---

## Verification Checklist

### Database & Seeders
1. **`php artisan migrate:fresh --seed`** — all 27+ tables created, 6 roles seeded, Shield permissions synced, zero errors.
2. **`php artisan ccrs:create-admin admin@digittal.com --name="Test Admin"`** — user created with `system_admin` role.
3. **Run `php artisan db:seed --force` twice** — no errors on second run (idempotent).
4. **`docker compose up --build`** — entrypoint runs migrate + seed automatically, app accessible at `http://localhost:8080/admin`.

### Signing Authority Enforcement
5. Create a workflow template with a stage of type `signing`. Start a workflow on a contract. Attempt to approve the signing stage as a user **without** a matching `signing_authority` record → should throw RuntimeException.
6. Create a `signing_authority` record for the user + entity → approve signing stage → succeeds.

### Contract Immutability
7. Set a contract's `workflow_state` to `executed` via tinker. Open the contract in Filament → all form fields disabled, edit action hidden.
8. Set `workflow_state` back to `draft` → form fields re-enable.

### Notification Preferences
9. Navigate to `/admin/notification-preferences` → form renders with default toggles.
10. Toggle email off, save → user record's `notification_preferences.email` is `false`.
11. Trigger a notification for that user → email is skipped (check logs).

### Template Alignment
12. `Dockerfile`, `Jenkinsfile`, `docker/docker-entrypoint.sh`, `docker/nginx/default.conf`, `docker/supervisor/supervisord.conf` all exist at expected paths.
13. Entrypoint includes `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan filament:assets`.
14. Port 8080 used consistently across Dockerfile, nginx, Docker Compose, and K8s deployment.

### Repo Sync
15. `git ls-remote ccrs HEAD` returns a commit hash.
16. `https://github.com/digittaldotio/digittal-ccrs` shows Laravel code on `main`.
17. GitHub Actions workflow at `https://github.com/gregdigittal/agreement_automation/actions` shows successful sync run.

### .env.example
18. `.env.example` contains **all** of: `TITO_API_KEY`, `TEAMS_*`, `MEILISEARCH_*`, `OTEL_*`, `FEATURE_*`, `REDIS_PASSWORD`.
