> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/16-restricted-contracts.md and execute the instructions inside it`
> 
> Run after 15-vendor-portal.

# CCRS TDD — 16: Restricted Contracts

```
@workspace Create Pest PHP feature tests in tests/Feature/Contracts/RestrictedContractTest.php for restricted contract access control.

Restricting:
1. system_admin can flag a contract as restricted
2. legal can flag as restricted
3. commercial/finance/operations/audit CANNOT restrict (403)

Access Enforcement:
4. Unrestricted contract visible to any user with role permission
5. Restricted contract HIDDEN from users not on authorized list
6. Restricted contract visible to users ON authorized list
7. system_admin ALWAYS has access regardless of list
8. legal NOT on list is DENIED access

Managing Authorized Users:
9. system_admin can add users to authorized list
10. legal can add users to list
11. Removing user immediately revokes access
12. Access Control tab visible only to system_admin and legal

Unrestricting:
13. Removing restriction makes contract visible to all with standard permissions

Use Contract factory with is_restricted flag. Use ContractUserAccess factory.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/17-compliance-audit.md and execute the instructions inside it**
