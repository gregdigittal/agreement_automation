> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/08b-signing-completion.md and execute the instructions inside it`
> 
> Run after 08a-signing-sessions.

# CCRS TDD — 08b: Electronic Signing — Submission, Completion & Audit

```
@workspace Create Pest PHP feature tests in tests/Feature/Signing/SigningCompletionTest.php for signature submission, declining, session completion, reminders, cancellation, and audit trail.

Signature Submission:
1. Method "typed" stores a rendered PNG
2. Method "drawn" stores canvas PNG
3. Method "uploaded" stores uploaded file
4. After submission, signer status changes to "signed"
5. "Save for future use" flag creates a StoredSignature record

Declining:
6. Signer can decline with required reason
7. Declining sets status to "declined" — signer cannot later sign
8. Initiator notified of decline

Session Completion:
9. All signed → signatures overlaid on PDF
10. Audit certificate PDF generated
11. SHA-256 hash of final signed document computed and stored
12. Completion emails sent to all signers and initiator
13. Contract state advances to next lifecycle stage

Reminders & Cancellation:
14. Initiator can send reminder to pending signer (recorded in audit)
15. Initiator can cancel session — pending tokens invalidated
16. Collected signatures preserved in audit but NOT applied to contract

Audit Trail:
17. Every action (create, view, sign, decline, complete, cancel, reminder) creates SigningAuditLog entry with IP and user agent

Use SigningSession, Signer, StoredSignature factories. Use Queue::fake() for async jobs. Mock S3 and Mail.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/09-ai-analysis.md and execute the instructions inside it**
