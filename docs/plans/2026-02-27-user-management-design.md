# User Management: Create, Bulk Upload & Approval Workflow

> Design approved 2026-02-27 | Branch: laravel-migration

## Summary

Three new features for admin-controlled user provisioning:

1. **Admin create user & assign roles** — New UserResource in Filament
2. **Bulk user upload with CSV template** — Extend existing BulkDataUploadPage
3. **Pending user approval** — First-time SSO users enter a queue for admin review

## Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Auth model | Azure AD + pre-provisioning | Admins pre-create users with roles. Azure AD SSO still used for authentication, but role assignment is admin-controlled. |
| Unknown SSO users | Pending approval queue | First-time SSO users without a pre-provisioned record get `status=pending`. Admin approves and assigns roles. |
| CSV fields | Name, Email, Roles (multi) | Comma-separated roles in a single cell for users needing multiple roles. |
| Invite email | Yes, on creation | Pre-provisioned users receive an email inviting them to log in. |
| Data model approach | Status field on User model | Single `status` enum vs. separate PendingUser model. Simpler, no data migration on approval. |

## Data Model

### Migration: Add `status` to `users` table

| Column | Type | Default | Values |
|---|---|---|---|
| `status` | string (enum) | `active` | `active`, `pending`, `suspended` |

Existing users default to `active`. The `canAccessPanel()` method requires `status === 'active'` AND at least one role.

No new models. Reuses the existing User model.

## Azure AD Callback Changes

Modified `AzureAdController::callback()` logic:

```
User SSOs via Azure AD
  -> User exists in DB?
    -> YES: Is status 'active'?
      -> YES: Log in (update name/email as before)
      -> NO (pending/suspended): Show "awaiting approval" message
    -> NO user record at all:
      -> Create User with status='pending', no roles
      -> Show "Your access request has been submitted" screen
      -> Admin gets in-app notification of new pending user
```

Azure AD group-to-role resolution is **removed** from the callback. All role assignment is admin-controlled (pre-provisioned or approved after pending).

## Feature 1: Admin Create User & Assign Role

New **UserResource** in Filament (`system_admin` only):

- **List page**: Table with columns: Name, Email, Status (badge), Roles, Last Login, Created At
  - Tab groups: Active / Pending / Suspended
  - Filterable by status and role
- **Create page**: Form fields: Name, Email, Roles (multi-select from 6 roles)
  - Status defaults to `active`
  - On save, sends invite email
- **Edit page**: Same form + ability to change status
  - Role changes take effect immediately
- **Actions**: Suspend user, Reactivate user

## Feature 2: Bulk User Upload

Extends existing **BulkDataUploadPage** (the `users` upload type already stubbed).

### CSV Template (downloadable)

```csv
name,email,roles
Jane Smith,jane@company.com,legal
John Doe,john@company.com,"commercial,finance"
```

### Processing

- Validates: email format, email uniqueness, role names exist in the 6 valid roles
- Creates User with `status = active` and syncs roles
- Sends invite email to each successfully created user
- Returns: success count, failed count, per-row errors
- Skips existing users (reports "already exists" warning, does not overwrite)

### Template Download

Header action button on BulkDataUploadPage that streams the CSV template (headers + 2 example rows) when `users` type is selected.

## Feature 3: Pending User Approval

On the UserResource list page, **"Pending Approval" tab**:

- Shows users with `status = pending` (created by first-time SSO)
- **Approve action**: Modal with role multi-select. On confirm:
  1. Set `status` to `active`
  2. Sync selected roles
  3. Send approval email: "Your CCRS access has been approved. You have been assigned [role(s)]. Click here to log in."
- **Reject action**: Deletes the pending user record (they can SSO again to re-enter the queue)

## Email Notifications

Two new Mailable classes:

### UserInviteMail

- **Trigger**: Admin creates user (manual or bulk)
- **Subject**: "You've been granted access to CCRS"
- **Body**: "You have been assigned [roles]. Click here to log in via Azure AD."
- **Link**: App URL (Azure SSO handles auth)

### UserApprovedMail

- **Trigger**: Admin approves a pending user
- **Subject**: "Your CCRS access has been approved"
- **Body**: "You have been assigned the [roles] role(s). Click here to log in."
- **Link**: App URL

Both use existing SendGrid SMTP config.

## Files Affected

| File | Change |
|---|---|
| `database/migrations/new` | Add `status` column to `users` table |
| `app/Models/User.php` | Add `status` to fillable, update `canAccessPanel()` |
| `app/Http/Controllers/Auth/AzureAdController.php` | Remove group-to-role resolution, add pending user creation |
| `app/Filament/Resources/UserResource.php` | **New** — CRUD for internal users |
| `app/Filament/Resources/UserResource/Pages/*.php` | **New** — List, Create, Edit pages |
| `app/Filament/Pages/BulkDataUploadPage.php` | Add template download, implement user CSV processing |
| `app/Services/BulkDataImportService.php` | Implement `importUsers()` method |
| `app/Mail/UserInviteMail.php` | **New** — invite email |
| `app/Mail/UserApprovedMail.php` | **New** — approval email |
| `resources/views/mail/user-invite.blade.php` | **New** — email template |
| `resources/views/mail/user-approved.blade.php` | **New** — email template |
| `resources/views/auth/pending-approval.blade.php` | **New** — "awaiting approval" screen |
| `database/seeders/ShieldPermissionSeeder.php` | Add UserResource permissions |
| `tests/Feature/UserManagement/*.php` | **New** — tests for all three features |
