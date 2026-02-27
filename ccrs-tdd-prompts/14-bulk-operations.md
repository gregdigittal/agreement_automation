> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/14-bulk-operations.md and execute the instructions inside it`
> 
> Run after 13-reports.

# CCRS TDD — 14: Bulk Operations

```
@workspace Create Pest PHP feature tests in tests/Feature/BulkOps/BulkOperationsTest.php for bulk data and contract uploads. Only system_admin can access.

Access:
1. system_admin can access Bulk Data Upload page
2. system_admin can access Bulk Contract Upload page
3. All other roles get 403

Bulk Data Upload:
4. Valid Regions CSV creates Region records
5. Valid Entities CSV creates Entity records (with existing region_code)
6. Valid Projects CSV creates Project records (with existing entity_code)
7. Entities CSV before Regions exist fails with "Region code not found"
8. Duplicate records (same code) skipped
9. Download Template returns CSV with correct headers per data type
10. Results show success_count and failure_count with error details

Bulk Contract Upload:
11. Valid CSV manifest + ZIP with matching PDFs creates contract records
12. Each row creates Contract linked to correct region, entity, project, counterparty
13. Contracts created in "draft" state
14. File from ZIP linked in S3
15. Filename mismatch → "File not found in ZIP"
16. Non-PDF/DOCX → "Invalid file format"
17. >500 files returns error
18. Individual file >50MB → "File exceeds size limit"
19. Async processing: status pending → processing → completed
20. Each row has individual status tracking
21. Failed rows include JSON error logs

Use Storage::fake('s3'), Queue::fake(). Create test CSV/ZIP programmatically.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/15-vendor-portal.md and execute the instructions inside it**
