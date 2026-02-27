> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/08a-signing-sessions.md and execute the instructions inside it`
> 
> Run after 07-workflow-templates.

# CCRS TDD — 08a: Electronic Signing — Sessions & Flow

```
@workspace Create Pest PHP feature tests in tests/Feature/Signing/SigningSessionTest.php for the CCRS signing system — session creation, sequential/parallel flow, and external signer experience.

Session Creation:
1. Signing session can be created from a contract in "signing" state
2. Supports "sequential" and "parallel" signing orders
3. Signers added with name, email, type (internal/external), order
4. Session valid for 30 days
5. Individual signer tokens expire after 7 days
6. Tokens are CSPRNG-generated; only SHA-256 hashes stored in DB

Sequential Signing:
7. Only first signer receives email upon activation
8. After signer 1 completes, signer 2 gets invitation
9. If signer 1 declines, signer 2 does NOT get invitation

Parallel Signing:
10. ALL signers receive emails simultaneously upon activation
11. Signers can sign in any order
12. Session auto-completes when all have signed

External Signer:
13. Valid magic link token grants access to signing page (no auth required)
14. Expired token (>7 days) returns 403
15. Used token (already signed) cannot be reused

Page Enforcement:
16. require_all_pages_viewed=true → submit rejected if not all pages viewed
17. require_page_initials=true → submit rejected if not all pages initialed
18. Enforcement disabled → signature submits without viewing all pages

Use SigningSession, Signer factories. Mock S3 and Mail.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/08b-signing-completion.md and execute the instructions inside it**
