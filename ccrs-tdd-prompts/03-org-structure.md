> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/03-org-structure.md and execute the instructions inside it`
> 
> Run after 02-rbac.

# CCRS TDD — 03: Organization Setup

```
@workspace Create Pest PHP feature tests in tests/Feature/OrgStructure/OrgStructureTest.php for the CCRS organizational hierarchy: Regions → Entities → Projects, plus Jurisdictions and Signing Authorities.

Access Control:
1. Only system_admin can create/edit/delete Regions, Entities, Projects, Jurisdictions, Signing Authorities
2. All roles can VIEW the Organization Visualization page

Regions:
3. Creating a Region with name and code succeeds
4. Region code must be unique — duplicate codes fail validation

Entities:
5. Creating an Entity requires selecting a Region
6. Entity code must be unique
7. Parent Entity field creates hierarchical relationship (holding → subsidiary)
8. Jurisdictions can be attached to an Entity via relation manager

Projects:
9. Creating a Project requires selecting an Entity
10. Project code must be unique

Dependencies:
11. Cannot create Entity if no Regions exist
12. Cannot create Project if no Entities exist

Jurisdictions:
13. Jurisdiction has: name, country_code, regulatory_body, description

Signing Authorities:
14. Links user + entity + optional project + authority_level + max_contract_value + currency
15. User WITH authority for entity AND value ≤ max_value → signing allowed
16. User WITHOUT authority → signing blocked
17. Contract value EXCEEDS max_value → signing blocked
18. Project-scoped authority overrides entity-scoped for that project

Use Region, Entity, Project, Jurisdiction, SigningAuthority factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/04-counterparties.md and execute the instructions inside it**
