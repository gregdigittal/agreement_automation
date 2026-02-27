> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/06-contract-lifecycle.md and execute the instructions inside it`
> 
> Run after 05-contract-crud.

# CCRS TDD — 06: Contract Lifecycle State Machine

```
@workspace Create Pest PHP feature tests in tests/Feature/Contracts/ContractLifecycleTest.php for the contract state machine. States: draft, review, approval, signing, countersign, executed, archived, cancelled.

Valid transitions:
1. draft → review via "Submit for Review"
2. review → approval via "Approve Review"
3. review → draft via "Request Changes"
4. approval → signing via "Approve"
5. approval → review via "Reject"
6. signing → countersign when external party signs
7. countersign → executed when countersigned
8. executed → archived

Cancellation:
9. draft → cancelled
10. review → cancelled
11. approval → cancelled
12. signing → cancelled
13. executed CANNOT be cancelled
14. archived CANNOT be cancelled

Invalid transitions:
15. draft CANNOT jump to approval (must go through review)
16. executed CANNOT go back to any editable state
17. archived CANNOT transition to any state

Immutability:
18. Executed contract fields (title, dates, value, description) cannot be updated
19. Executed contract file cannot be replaced or deleted
20. Archived contract is permanently read-only — all updates return 403

Use Contract factory with state traits. Assert new state AND audit log entry for each transition.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/07-workflow-templates.md and execute the instructions inside it**
