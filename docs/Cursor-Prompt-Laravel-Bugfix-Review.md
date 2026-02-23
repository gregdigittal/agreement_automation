# Cursor Prompt — Post-Phase-G Code Review Bugfix

## Context

**Run this prompt BEFORE continuing to Phase H.** A comprehensive code review of Phases A-G found 19 critical/important bugs that must be fixed before proceeding.

- Working directory: `/Users/gregmorris/Development Projects/Agreement_automation`
- Branch: `laravel-migration`
- After each fix group, commit and push: `git push origin laravel-migration`

---

## Fix Group 1 — CRITICAL: Runtime Crashes

### Fix 1.1: TelemetryService `$span->end()` crashes when OpenTelemetry is not installed

`TelemetryService::startSpan()` returns `?object` (can be null). Three callers call `$span->end()` without null-checking.

**Files to fix:**
- `app/Jobs/ProcessAiAnalysis.php` — change `$span->end()` to `$span?->end()`
- `app/Services/AiWorkerClient.php` — change `$span->end()` to `$span?->end()`
- `app/Http/Controllers/Api/TitoController.php` — change `$span->end()` to `$span?->end()`

### Fix 1.2: `ContractFileService::getSignedUrl()` called with Contract object instead of string

**File:** `routes/web.php` (lines ~22 and ~38)

Change:
```php
$url = $service->getSignedUrl($contract);
```
To:
```php
$url = $service->getSignedUrl($contract->storage_path);
```

Do this for BOTH the `contract.download` route AND the `vendor.contract.download` route.

### Fix 1.3: `User` model missing `HasFactory` trait — DatabaseSeeder crashes

**File:** `app/Models/User.php`

Add `use Illuminate\Database\Eloquent\Factories\HasFactory;` import and add `HasFactory` to the trait list:
```php
use HasUuidPrimaryKey, HasRoles, HasFactory;
```

### Fix 1.4: `UserFactory` sets columns that don't exist on users table

**File:** `database/factories/UserFactory.php`

The `users` table only has: `id`, `email`, `name`, `remember_token`, `created_at`, `updated_at`. Remove `email_verified_at` and `password` from the factory definition. Also remove the `$password` static property. The definition should only set `name` and `email`.

### Fix 1.5: `VendorAuthController::requestLink()` calls NotificationService with wrong arguments

**File:** `app/Http/Controllers/VendorAuthController.php`

`NotificationService::create()` expects `(array $data)`. Replace the 6-positional-argument call with a single array argument matching the `Notification` model's fillable fields:
```php
app(NotificationService::class)->create([
    'recipient_email' => $vendor->email,
    'subject' => 'CCRS Vendor Portal Login',
    'body' => "Click this link to log in: {$loginUrl}",
    'channel' => 'email',
    'type' => 'vendor_login',
    'related_id' => $vendor->id,
]);
```

### Fix 1.6: `NotificationService::sendEmail()` passes stdClass to NotificationMail expecting Notification model

**File:** `app/Services/NotificationService.php` (in the `sendEmail` method)

The `NotificationMail` constructor expects a `Notification` model. Change `sendEmail` to accept and pass `$subject` and `$body` strings directly, or create a proper Notification record and pass that. Simplest fix: change `NotificationMail` to accept subject and body strings instead of a `Notification` model:

**File:** `app/Mail/NotificationMail.php` — change constructor:
```php
public function __construct(
    public string $subject,
    public string $body,
) {}
```

Then update `envelope()` to use `$this->subject` and update the Blade template to use `$body` directly.

And update `NotificationService::sendEmail()` to pass strings:
```php
new \App\Mail\NotificationMail($subject, $body)
```

### Fix 1.7: ContractResource missing RelationManagers namespace import

**File:** `app/Filament/Resources/ContractResource.php`

Add at the top of the file:
```php
use App\Filament\Resources\ContractResource\RelationManagers;
```

This is needed because `getRelationManagers()` references `RelationManagers\KeyDatesRelationManager::class` etc.

### Fix 1.8: MerchantAgreementResource `generate_docx` action calls generate() with wrong arguments

**File:** `app/Filament/Resources/MerchantAgreementResource.php`

`MerchantAgreementService::generate()` expects `(Contract $contract, array $inputs, User $actor)` but the resource passes only one array. Fix the action to pass the correct arguments, or create a separate method on MerchantAgreementService for generating from a MerchantAgreement record directly.

**Commit:** `git commit -am "fix(critical): runtime crashes — null-safe spans, getSignedUrl type, User factory, notification args, RelationManagers import, MerchantAgreement generate"` then `git push origin laravel-migration`

---

## Fix Group 2 — CRITICAL: Database & Enum Mismatches

### Fix 2.1: AuditLogResource references `created_at` column that doesn't exist

**File:** `app/Filament/Resources/AuditLogResource.php`

The `audit_log` table uses `at` (not `created_at`). Change:
- `TextColumn::make('created_at')` → `TextColumn::make('at')`
- `->defaultSort('created_at')` → `->defaultSort('at', 'desc')`

### Fix 2.2: WikiContractResource status enum mismatch

**File:** `app/Filament/Resources/WikiContractResource.php`

The `wiki_contracts.status` enum is `['draft', 'review', 'published', 'deprecated']`, NOT `['active', 'draft', 'archived']`.

Fix the form Select options and table badge colors to match the database enum values.

### Fix 2.3: WorkflowTemplateResource status enum mismatch

**File:** `app/Filament/Resources/WorkflowTemplateResource.php`

Same issue. The `workflow_templates.status` enum is `['draft', 'published', 'deprecated']`, NOT `['active', 'draft', 'archived']`.

Fix the form Select options and table badge colors to match.

### Fix 2.4: WikiContractResource form uses `file_path` but model uses `storage_path`

**File:** `app/Filament/Resources/WikiContractResource.php`

Change `FileUpload::make('file_path')` to `FileUpload::make('storage_path')`.

### Fix 2.5: ContractLinkService updates non-existent `expiry_date` column

**File:** `app/Services/ContractLinkService.php`

The `contracts` table has no `expiry_date` column. Check the contracts migration for the actual column name (likely `end_date` or similar) and update the renewal logic accordingly. If no expiry column exists, either add a migration for it or remove this update.

### Fix 2.6: VendorContractResource badge colors use non-existent workflow states

**File:** `app/Filament/Vendor/Resources/VendorContractResource.php`

The system uses `['draft', 'review', 'approval', 'signing', 'executed', 'archived']` — NOT `'active'` or `'terminated'`. Update badge color mapping to match actual states.

**Commit:** `git commit -am "fix(critical): database/enum mismatches — audit_log.at, wiki/workflow status enums, storage_path, expiry_date, badge colors"` then `git push origin laravel-migration`

---

## Fix Group 3 — CRITICAL: Security & Infrastructure

### Fix 3.1: AI Worker auth uses non-constant-time string comparison

**File:** `ai-worker/app/middleware/auth.py`

Replace:
```python
if x_ai_worker_secret != settings.ai_worker_secret:
```
With:
```python
import hmac
if not hmac.compare_digest(x_ai_worker_secret, settings.ai_worker_secret):
```

### Fix 3.2: docker-entrypoint.sh missing `db:seed --force`

**File:** `docker/docker-entrypoint.sh`

After the `php artisan migrate --force` line, add:
```bash
php artisan db:seed --force
```

This is a CTO directive: "The application manages the database from code."

### Fix 3.3: Supervisor Horizon command uses invalid flags

**File:** `docker/supervisor/supervisord.conf`

Change:
```ini
command=php /var/www/html/artisan horizon --sleep=3 --tries=3 --max-time=3600
```
To:
```ini
command=php /var/www/html/artisan horizon
```

Horizon manages its own workers and does not accept queue:work flags.

### Fix 3.4: Delete duplicate BoldsignWebhookController (root-level has security bypass)

**Delete:** `app/Http/Controllers/BoldsignWebhookController.php`

The `Webhooks\BoldsignWebhookController` is the correct version (always verifies signatures). The root-level one skips verification when the secret is empty. Remove it:
```bash
git rm app/Http/Controllers/BoldsignWebhookController.php
```

**Commit:** `git commit -am "fix(security): timing-safe AI auth, db:seed in entrypoint, horizon flags, remove insecure webhook controller"` then `git push origin laravel-migration`

---

## Fix Group 4 — IMPORTANT: Logic & Consistency Fixes

### Fix 4.1: AdminPanelProvider duplicate Dashboard registration

**File:** `app/Providers/Filament/AdminPanelProvider.php`

Remove `Pages\Dashboard::class` from the `->pages([])` array since auto-discovery already finds the custom Dashboard. Change to:
```php
->pages([])
```

### Fix 4.2: VendorPanelProvider login shows password form but vendor auth is magic-link

**File:** `app/Providers/Filament/VendorPanelProvider.php`

The vendor panel `->login()` renders a password form, but vendor users have no passwords. Create a custom `VendorLoginPage` that shows only the magic link email form, or set `->login(false)` and redirect unauthenticated vendor users to the magic link page.

### Fix 4.3: VendorPanelProvider uses wrong AuthenticateSession middleware

**File:** `app/Providers/Filament/VendorPanelProvider.php`

Change:
```php
use Illuminate\Session\Middleware\AuthenticateSession;
```
To:
```php
use Filament\Http\Middleware\AuthenticateSession;
```

### Fix 4.4: Duplicate vendor auth controllers — pick one

**Files:**
- `app/Http/Controllers/VendorAuthController.php` — uses `VendorLoginToken` model (more secure)
- `app/Http/Controllers/Vendor/MagicLinkController.php` — stores token on `VendorUser` model

Keep the `VendorAuthController` (dedicated token table with `used_at` tracking is more secure). Remove or consolidate `MagicLinkController`. Update routes to use the chosen controller consistently.

### Fix 4.5: Add rate limiting to vendor magic link routes

**File:** `routes/web.php`

Add `throttle:5,1` middleware to the magic link request routes to prevent email flooding.

### Fix 4.6: Delete dead `CheckRole` middleware

**File:** `app/Http/Middleware/CheckRole.php`

This middleware is defined but never registered or used. Delete it:
```bash
git rm app/Http/Middleware/CheckRole.php
```

### Fix 4.7: Remove unused `vendor_users` auth provider

**File:** `config/auth.php`

Remove the `vendor_users` provider block (lines ~73-76). Only the `vendors` provider is used by the `vendor` guard.

### Fix 4.8: Add `DB_ROOT_PASSWORD` to .env.example

**File:** `.env.example`

Add `DB_ROOT_PASSWORD=` to the database section (docker-compose.yml references it with a hardcoded fallback of `rootpassword`).

### Fix 4.9: Clear TITO_API_KEY default value in .env.example

**File:** `.env.example`

Change `TITO_API_KEY=your-secret-tito-api-key` to `TITO_API_KEY=` (empty) to prevent accidental use of the placeholder as a real key.

### Fix 4.10: ContractResource `canDelete` checks non-existent `completed` state

**File:** `app/Filament/Resources/ContractResource.php`

Remove `'completed'` from the `in_array` check — the system uses `['draft', 'review', 'approval', 'signing', 'executed', 'archived']`. Only `executed` and `archived` should prevent deletion.

### Fix 4.11: Add AiCostWidget to Dashboard

**File:** `app/Filament/Pages/Dashboard.php`

Add `\App\Filament\Widgets\AiCostWidget::class` to the `getWidgets()` array.

**Commit:** `git commit -am "fix(important): admin panel, vendor auth consolidation, rate limiting, dead code cleanup, config corrections"` then `git push origin laravel-migration`

---

## Fix Group 5 — IMPORTANT: Filament v3 Deprecations

### Fix 5.1: Replace BadgeColumn with TextColumn->badge()

`Tables\Columns\BadgeColumn` is deprecated in Filament v3. Search the entire codebase for `BadgeColumn` and replace with `TextColumn::make(...)->badge()`. The `->colors([...])` syntax also changes to `->color(fn ($state) => match($state) { ... })`.

### Fix 5.2: Replace `reactive()` with `live()`

Search the entire codebase for `->reactive()` and replace with `->live()`.

**Commit:** `git commit -am "fix(deprecation): replace BadgeColumn with TextColumn->badge(), reactive() with live()"` then `git push origin laravel-migration`

---

## Verification

After all fixes, confirm:
```bash
echo "origin:  $(git ls-remote origin refs/heads/laravel-migration | cut -f1)"
echo "ccrs:    $(git ls-remote ccrs refs/heads/main | cut -f1)"
echo "local:   $(git rev-parse HEAD)"
```

Then run a syntax check on all PHP files:
```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

If no syntax errors are reported, the bugfix phase is complete. Proceed with Phase H.
