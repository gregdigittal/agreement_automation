> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/05-contract-crud.md and execute the instructions inside it`
> 
> Run after 04-counterparties.

# CCRS TDD — 05: Contract CRUD & Creation Flow

```
@workspace Create Pest PHP feature tests in tests/Feature/Contracts/ContractCrudTest.php for CCRS contract creation, reading, updating, and deletion.

Creation:
1. legal user can create Commercial contract with all required fields (title, description, start_date, end_date, value, currency, region_id, entity_id, project_id, counterparty_id) — saves in "draft" state
2. commercial user can create a contract
3. finance user CANNOT create (403)
4. Entity dropdown filtered by selected region
5. Project dropdown filtered by selected entity
6. PDF file upload stores file in S3 and links to contract
7. DOCX upload also works
8. Auto-assigns WorkflowInstance if matching published WorkflowTemplate exists
9. No matching template → contract stays in draft with no workflow

Reading:
10. All authenticated users can view contract list
11. Contract detail page loads with all fields

Updating:
12. legal user can edit a draft contract's fields
13. commercial CANNOT edit contracts
14. File replacement works in draft state
15. File replacement blocked once contract leaves draft

Deletion:
16. Only system_admin can delete a contract
17. Deletion only possible in draft state
18. Deleting executed contract returns 403

Contract Types:
19. "Commercial" type requires file upload (PDF/DOCX)
20. "Merchant" type triggers generation from WikiContract template

Use factories for Region, Entity, Project, Counterparty, Contract, WorkflowTemplate. Test Filament form submissions via Livewire::test().
```


---
> **Next: @workspace Read ccrs-tdd-prompts/06-contract-lifecycle.md and execute the instructions inside it**
