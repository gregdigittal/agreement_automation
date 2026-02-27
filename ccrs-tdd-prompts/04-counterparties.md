> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/04-counterparties.md and execute the instructions inside it`
> 
> Run after 03-org-structure.

# CCRS TDD — 04: Counterparty Management

```
@workspace Create Pest PHP feature tests in tests/Feature/Counterparties/CounterpartyTest.php for CCRS counterparty CRUD, duplicate detection, status management, override requests, merging, and KYC.

CRUD:
1. system_admin, legal, commercial can create counterparty with legal_name and registration_number
2. finance, operations, audit CANNOT create counterparties (403)
3. system_admin and legal can edit counterparties
4. commercial CANNOT edit counterparties
5. New counterparties created with status "active"

Duplicate Detection:
6. Exact registration_number match returns existing counterparty
7. Fuzzy name matching finds similar names (e.g., "Acme Corp" matches "Acme Corporation")
8. No duplicates returns empty result

Status Management:
9. system_admin and legal can change status to "suspended" or "blacklisted"
10. Contract creation with "suspended" counterparty is BLOCKED
11. Contract creation with "blacklisted" counterparty is BLOCKED
12. Contract creation with "active" counterparty succeeds

Override Requests:
13. commercial can submit override request with business_justification
14. Request created with status "pending"
15. legal can approve → status "approved"
16. legal can reject with comment → status "rejected"
17. After approval, commercial can create contract with restricted counterparty
18. commercial CANNOT approve their own override request

Merging (Admin only):
19. system_admin can merge source into target counterparty
20. All contracts transferred from source to target
21. Source status set to "merged" with reference to target
22. Audit log entry records the merge
23. Non-admin CANNOT merge (403)

KYC:
24. KYC template assigned to counterparty creates KYC Pack
25. Legal users can mark checklist items complete
26. Progress tracked (completed / total)

Use Counterparty, Contact, OverrideRequest, KycTemplate, KycPack factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/05-contract-crud.md and execute the instructions inside it**
