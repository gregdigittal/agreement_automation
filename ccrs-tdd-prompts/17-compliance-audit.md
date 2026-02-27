> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/17-compliance-audit.md and execute the instructions inside it`
> 
> Run after 16-restricted-contracts.

# CCRS TDD — 17: Compliance & Audit

```
@workspace Create Pest PHP feature tests in tests/Feature/Compliance/ComplianceTest.php for audit logging, regulatory frameworks, compliance findings, and document integrity.

Audit Logging:
1. Creating contract generates audit log with event="created", new_values JSON
2. Updating generates log with event="updated", old_values and new_values
3. Deleting generates log with event="deleted", old_values
4. Each entry captures: user, event, record_type, record_id, ip_address, user_agent, timestamp
5. Audit entries are IMMUTABLE — update/delete fails
6. system_admin, legal, audit can VIEW logs
7. commercial/finance/operations CANNOT view (403)

Contract-Level:
8. Contract Activity tab shows only entries for that contract

Signing Audit Trail:
9. Creating session → SigningAuditLog entry
10. Sending invitation → entry with signer email
11. Document viewed → entry with IP and user agent
12. Signature submitted → entry with method
13. Declined → entry with reason
14. Completed → entry with final hash
15. Cancelled → entry

Regulatory Frameworks:
16. system_admin and legal can create framework with: name, description, jurisdiction_id, requirements, is_active
17. Compliance check evaluates contract against matching active frameworks
18. Produces ComplianceFinding records with: framework_id, finding_type, severity, description, status

Finding Status:
19. Start as "open"
20. Progress: open → in_progress → resolved
21. Can be "waived" with justification
22. All changes recorded in audit log

Document Integrity:
23. Session creation computes SHA-256 of original PDF
24. Session completion computes SHA-256 of signed PDF
25. Hashes comparable for tamper detection

Audit Certificate:
26. Completion generates certificate PDF
27. Contains: contract details, session info, signer info, hashes, timeline

Use AuditLog, SigningAuditLog, RegulatoryFramework, ComplianceFinding factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/18-global-search.md and execute the instructions inside it**
