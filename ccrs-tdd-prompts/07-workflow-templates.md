> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/07-workflow-templates.md and execute the instructions inside it`
> 
> Run after 06-contract-lifecycle.

# CCRS TDD — 07: Workflow Templates

```
@workspace Create Pest PHP feature tests in tests/Feature/Workflows/WorkflowTemplateTest.php for workflow template management. Only system_admin can create/edit/publish/delete.

CRUD:
1. system_admin can create template with name, description, contract_type, and stages
2. legal CANNOT create templates (403)
3. commercial/finance/operations/audit CANNOT create templates
4. system_admin can edit a draft template
5. system_admin can delete a draft template
6. ALL roles can VIEW templates

Stages:
7. Template can have multiple stages with: name, responsible_role, duration_days, requires_approval
8. Stages ordered sequentially
9. Each stage can have up to 3 escalation tiers

Publishing & Versioning:
10. New template starts in "draft"
11. Publishing sets version to 1 and makes it active
12. Re-publishing increments version
13. Editing published template creates new draft; published version stays active
14. Only published templates eligible for auto-assignment

Template Matching (priority order):
15. Project-scoped > Entity-scoped
16. Entity-scoped > Region-scoped
17. Region-scoped > global
18. Global template (no scope) matches any contract of correct type
19. No match → contract gets no workflow
20. Must match contract_type (Commercial vs Merchant)

Escalation:
21. Stage SLA expiry creates Tier 1 EscalationEvent
22. Unresolved after Tier 2 threshold → Tier 2 escalation
23. Events recorded for audit
24. Escalated items appear on Escalations page

Use WorkflowTemplate, WorkflowStage, EscalationRule factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/08a-signing-sessions.md and execute the instructions inside it**
