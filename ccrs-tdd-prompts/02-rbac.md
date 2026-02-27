> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/02-rbac.md and execute the instructions inside it`
> 
> Run after 01-authentication.

# CCRS TDD — 02: Role-Based Access Control

```
@workspace Create Pest PHP feature tests in tests/Feature/Auth/RoleAccessTest.php for CCRS RBAC. 6 roles: system_admin, legal, commercial, finance, operations, audit.

Navigation visibility tests:
1. system_admin can access ALL navigation groups
2. legal can access: Contracts, Counterparties, KYC, Compliance, Escalations, Reports
3. commercial can access: Contracts, Counterparties, Merchant Agreements, Key Dates, Reminders
4. finance has VIEW-ONLY on Contracts, can access: Reports, Analytics
5. operations has VIEW-ONLY on Contracts, can access: Key Dates, Reminders
6. audit has VIEW-ONLY on Contracts, can access: Compliance, Audit Logs, Reports

Permission enforcement tests:
7. finance user CANNOT create a contract (403)
8. operations user CANNOT edit a contract (403)
9. audit user CANNOT modify any data — PUT/PATCH/DELETE on contracts returns 403
10. commercial user CANNOT approve override requests (403)
11. Only system_admin can access Bulk Operations pages
12. Only system_admin and legal can trigger AI analysis

Use actingAs() with User factory states for each role. Test both HTTP responses and Filament Livewire component authorization.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/03-org-structure.md and execute the instructions inside it**
