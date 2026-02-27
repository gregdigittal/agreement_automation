> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/13-reports.md and execute the instructions inside it`
> 
> Run after 12-notifications.

# CCRS TDD — 13: Reports & Analytics

```
@workspace Create Pest PHP feature tests in tests/Feature/Reports/ReportsTest.php for reporting and analytics.

Reports Page Access:
1. finance, legal, audit, system_admin can access Reports page
2. commercial and operations CANNOT access (403)

Table & Filtering:
3. Table displays: title, type, counterparty, region, entity, state, expiry, created
4. Filter by state returns only matching contracts
5. Filter by type returns only matching
6. Filter by region returns only matching
7. Combined filters work (state + region)
8. Clearing filters returns full dataset

Export:
9. Excel export generates .xlsx with filtered data
10. PDF export generates formatted report
11. Both exports respect applied filters

Analytics Dashboard:
12. system_admin, legal, finance, audit can access (when feature flag enabled)
13. Hidden when advanced_analytics flag disabled
14. Displays 6 widgets: Pipeline Funnel, Risk Distribution, Compliance Overview, Obligation Tracker, AI Usage, Workflow Performance

Main Dashboard:
15. All authenticated users see main Dashboard
16. Widgets: Contract Status, Expiry Horizon, Pending Workflows, Active Escalations, AI Cost, Obligation Tracker
17. Compliance Overview appears only when regulatory_compliance flag enabled

AI Cost Report:
18. Only finance and system_admin can access
19. Shows per-analysis data with filters
20. Summary stats: total_cost, total_tokens, total_analyses, average_cost

Use Contract factory with various states. Test Filament table rendering and exports.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/14-bulk-operations.md and execute the instructions inside it**
