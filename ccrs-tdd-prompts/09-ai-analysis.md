> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/09-ai-analysis.md and execute the instructions inside it`
> 
> Run after 08b-signing-completion.

# CCRS TDD — 09: AI Analysis & Redlining

```
@workspace Create Pest PHP feature tests in tests/Feature/AI/AiAnalysisTest.php for AI-powered contract analysis. AI Worker is a Python FastAPI microservice called via HTTP.

Triggering:
1. system_admin and legal can trigger AI analysis on draft contract
2. system_admin and legal can trigger on review-state contract
3. commercial/finance/operations/audit CANNOT trigger (403)
4. Creates a ProcessAiAnalysis queued job
5. Status progresses: pending → processing → completed

Five Types:
6. "summary" returns executive summary text
7. "extraction" returns AiExtractedField records with field_name, value, confidence_score (0-100)
8. "risk_assessment" returns risk factors with severity scores
9. "deviation" requires WikiContract template and compares clause-by-clause
10. "obligations" returns obligations with due_dates and responsible_parties
11. Multiple types on same contract create separate AiAnalysisResult records

Results:
12. Each AiAnalysisResult stores: analysis_type, status, model_used, tokens_used, cost_usd
13. Failed analyses store error and can be retried

Cost Tracking:
14. AI Cost Report accessible only to finance and system_admin
15. Shows breakdown by type, contract, time_period
16. Summary: total_cost, total_tokens, total_analyses, average_cost

Redline Review:
17. legal and system_admin can start Redline Review
18. Requires WikiContract template selection
19. Each clause gets recommendation: accept, modify, reject
20. Users can override AI recommendations
21. All clauses reviewed → session marked complete

Mock AI Worker HTTP calls. Use Queue::fake(). Use AiAnalysisResult, RedlineSession, WikiContract factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/10-linked-contracts.md and execute the instructions inside it**
