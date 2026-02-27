> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/10-linked-contracts.md and execute the instructions inside it`
> 
> Run after 09-ai-analysis.

# CCRS TDD — 10: Linked Contracts (Amendments, Renewals, Side Letters)

```
@workspace Create Pest PHP feature tests in tests/Feature/Contracts/LinkedContractTest.php for amendments, renewals, and side letters created from executed contracts.

Creating Linked Contracts:
1. Amendment can be created from executed contract's action menu
2. Renewal can be created from executed contract's action menu
3. Side letter can be created from executed contract's action menu
4. Linked contracts CANNOT be created from non-executed contracts (403)

Amendments:
5. Linked with relationship_type = "amendment"
6. Follows own independent lifecycle (draft → executed)
7. Parent contract remains unchanged and read-only

Renewals:
8. Linked with relationship_type = "renewal"
9. Once executed, original can be archived
10. Carries forward context from original

Side Letters:
11. Linked with relationship_type = "side_letter"
12. Multiple side letters can link to single parent
13. Each has own independent lifecycle

Parent Immutability:
14. Creating linked contract does NOT modify parent's fields
15. Parent audit trail shows no modification from linked contract creation

Use Contract factory with "executed" state. Use ContractLink model/factory.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/11-merchant-agreements.md and execute the instructions inside it**
