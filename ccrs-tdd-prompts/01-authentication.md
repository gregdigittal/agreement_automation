> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/01-authentication.md and execute the instructions inside it`
> 
> Run after 00-shared-setup.

# CCRS TDD — 01: Authentication & User Provisioning

```
@workspace Create Pest PHP feature tests in tests/Feature/Auth/AzureAdLoginTest.php for Azure AD SSO authentication using Laravel Socialite.

Tests to write:

1. GET /auth/redirect returns a redirect to Microsoft's OAuth endpoint
2. Azure AD callback with email matching "legal" group creates User with role "legal" and authenticates them
3. Callback for existing user does not create duplicate — authenticates existing record
4. Role mapping: each Azure AD group maps to one CCRS role (system_admin, legal, commercial, finance, operations, audit). Test at least 3 groups
5. User whose Azure AD group doesn't map to any CCRS role gets 403 and no User record created
6. After successful callback, user has active session and can access /admin (Filament panel)

Mock the Socialite driver. Use UserFactory with role field. Use RefreshDatabase trait.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/02-rbac.md and execute the instructions inside it**
