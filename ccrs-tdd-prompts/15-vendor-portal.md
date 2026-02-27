> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/15-vendor-portal.md and execute the instructions inside it`
> 
> Run after 14-bulk-operations.

# CCRS TDD — 15: Vendor Portal

```
@workspace Create Pest PHP feature tests in tests/Feature/VendorPortal/VendorPortalTest.php for the external vendor portal with magic-link authentication.

Magic Link Auth:
1. POST /vendor/login with registered email sends magic link
2. Unregistered email returns error
3. Valid token authenticates vendor user and creates session
4. Token expired (>24 hours) returns error
5. Only SHA-256 hash stored in DB
6. Logout invalidates session immediately

Dashboard:
7. Vendor sees contracts for their counterparty only
8. CANNOT see other counterparties' contracts (data isolation)
9. Can download contract files (PDF/DOCX)
10. Sees their uploaded documents with status

Document Upload:
11. Can upload PDF associated with contract
12. Can upload DOCX
13. Stored securely in S3
14. Internal CCRS users notified of upload

Notifications:
15. CCRS users can send notifications to vendor users
16. Appear on vendor dashboard
17. Unread highlighted
18. Click marks as read

Admin Management:
19. system_admin creates vendor user with: name, email, counterparty_id, phone, is_active
20. Email must be unique across vendor users
21. Deactivated user cannot log in
22. Filter vendor users by counterparty

Use VendorUser, Counterparty factories. Mail::fake(), Storage::fake('s3').
```


---
> **Next: @workspace Read ccrs-tdd-prompts/16-restricted-contracts.md and execute the instructions inside it**
