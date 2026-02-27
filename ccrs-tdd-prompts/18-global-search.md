> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/18-global-search.md and execute the instructions inside it`
> 
> Run after 17-compliance-audit.

# CCRS TDD — 18: Global Search

```
@workspace Create Pest PHP feature tests in tests/Feature/Search/GlobalSearchTest.php for the CCRS global search (Cmd+K / Ctrl+K).

1. Searching by contract title returns matching contracts
2. Searching by contract reference number returns the contract
3. Searching by counterparty name returns matching counterparties
4. Searching by counterparty registration number returns match
5. Results respect role-based access — finance user doesn't see admin-only records
6. Results respect restricted contract access — hidden contracts don't appear
7. Empty search returns no results
8. Search is case-insensitive

Use Contract, Counterparty factories with known searchable values.
```


---
> **🎉 All test prompts complete!**
