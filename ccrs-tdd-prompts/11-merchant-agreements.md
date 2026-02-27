> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/11-merchant-agreements.md and execute the instructions inside it`
> 
> Run after 10-linked-contracts.

# CCRS TDD — 11: Merchant Agreements

```
@workspace Create Pest PHP feature tests in tests/Feature/MerchantAgreements/MerchantAgreementTest.php for merchant agreement generation from WikiContract templates.

1. system_admin, legal, commercial can view merchant agreements
2. system_admin, legal, commercial can generate merchant agreements
3. Generation requires selecting a WikiContract template
4. Template fields pre-populated from counterparty and org context
5. Generated document can be reviewed before submission
6. Creates Contract record of type "merchant" in draft state
7. WikiContract template signing blocks auto-populate on signing session
8. Signing blocks map roles (company, counterparty, witness_1, etc.) to signers automatically

Use WikiContract, Contract factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/12-notifications.md and execute the instructions inside it**
