# SharePoint Integration — Governance & Configuration Guide

> **Status:** Feature-flagged OFF by default (`FEATURE_SHAREPOINT=false`).
> **Owner:** CTO (infrastructure/Azure AD) + System Admin (per-contract enablement)
> **Component:** `App\Services\SharePointService`, `SharePointRelationManager`

---

## 1. What This Feature Does

When enabled, CCRS can link a SharePoint folder to a contract. Once linked:

- The contract's **SharePoint Documents** tab (Filament relation manager) displays the folder's file listing (name, size, last modified, link).
- Users can **link** a folder by pasting a SharePoint sharing URL.
- Users can **unlink** a folder (clears the stored IDs, does not delete SharePoint data).
- File listings are fetched live via Microsoft Graph API on every page load.

CCRS does **not** upload or modify files in SharePoint. It is read-only plus folder linking.

---

## 2. Architecture

```
User pastes SharePoint sharing URL
    ↓
SharePointService::linkFolder()
    ↓ resolves URL to site_id, drive_id, folder_id
    ↓ via Graph API /shares/{encoded_url}/driveItem
Stores IDs on Contract (sharepoint_folder_id, sharepoint_drive_id, sharepoint_site_id)
    ↓
SharePointRelationManager::table()
    ↓ on page render
SharePointService::listFolderContents()
    ↓ GET /drives/{drive_id}/items/{folder_id}/children
Returns file list for display
```

**Auth flow:** Client credentials grant (app-only). The CCRS app authenticates as itself — no user delegation. The same Azure AD App Registration used for user SSO (`AZURE_CLIENT_ID` / `AZURE_CLIENT_SECRET`) is reused.

**Token caching:** Access token cached 50 minutes (expires at 60) in Redis via `TenantCache::key('sharepoint_graph_token')`.

---

## 3. Azure AD App Registration Requirements (CTO Action Required)

The feature will not function until these Graph API permissions are granted to the CCRS Azure AD App Registration.

### Required Microsoft Graph Application Permissions

| Permission | Type | Purpose |
|---|---|---|
| `Sites.Read.All` | Application | Resolve sharing URLs via `/shares` endpoint |
| `Files.Read.All` | Application | List folder contents via `/drives/{id}/items/{id}/children` |

> **Application permissions** (not delegated) are required because CCRS uses the client credentials flow — there is no signed-in user context for these calls.

### Steps (CTO)

1. Navigate to **Azure Portal → App Registrations → [CCRS App] → API Permissions**
2. Click **Add a permission → Microsoft Graph → Application permissions**
3. Search for and add `Sites.Read.All` and `Files.Read.All`
4. Click **Grant admin consent** for the tenant — required for application permissions to take effect
5. Verify both permissions show "Granted for [tenant]" with a green tick

> **No new App Registration is needed.** These permissions are added to the existing CCRS application (`AZURE_CLIENT_ID`).

---

## 4. Environment Variables

| Variable | Required | Purpose |
|---|---|---|
| `FEATURE_SHAREPOINT` | Yes | Set to `true` to enable the feature. Default: `false` |
| `AZURE_CLIENT_ID` | Yes (shared) | Azure AD App client ID — already used for SSO |
| `AZURE_CLIENT_SECRET` | Yes (shared) | Azure AD App client secret — already used for SSO |
| `AZURE_AD_TENANT_ID` | Yes (shared) | Tenant ID — already used for Teams token endpoint |

No new credentials are required beyond what Teams notifications already use.

---

## 5. Per-Contract Enablement

The feature has two levels of control:

1. **Global flag** (`FEATURE_SHAREPOINT=true`) — must be true for the SharePoint tab to appear for any contract.
2. **Per-contract flag** (`sharepoint_enabled = true`) — the SharePoint Documents tab is only shown when `Feature::sharePoint() && $contract->sharepoint_enabled`.

This means a system admin must explicitly enable SharePoint on each contract before users can link folders. This prevents accidental data exposure on contracts where SharePoint linkage is undesired.

To enable for a contract: edit the contract in Filament and toggle the **SharePoint Enabled** field.

---

## 6. Data Stored in CCRS

When a folder is linked, four fields are written to the `contracts` table:

| Column | Purpose |
|---|---|
| `sharepoint_url` | The original sharing URL provided by the user |
| `sharepoint_folder_id` | Graph API item ID of the linked folder |
| `sharepoint_drive_id` | Graph API drive ID |
| `sharepoint_site_id` | Graph API site ID |

CCRS stores **folder metadata only** — no file content, no file metadata beyond what is displayed. Graph API calls are made at page-load time; results are not persisted.

All folder-link and folder-unlink actions are written to the `audit_log` table via `AuditService::log('sharepoint.folder_linked', ...)`.

---

## 7. Data Governance Considerations

| Concern | Detail |
|---|---|
| **Data residency** | CCRS stores only SharePoint folder IDs (GUIDs). Actual document content stays in SharePoint. No documents transit through CCRS servers. |
| **Access control** | Anyone with CCRS access to a contract can view the linked SharePoint folder's file list. This is intentional — SharePoint folder access itself is governed by SharePoint permissions (not CCRS roles). |
| **Restricted contracts** | `is_restricted` contracts are protected by CCRS's `StorageServeController` auth but the SharePoint file listing does not apply additional SharePoint-side checks. Ensure restricted-contract SharePoint folders have appropriate SharePoint-side permissions. |
| **Token scope** | The `Files.Read.All` permission grants the CCRS app read access to **all** OneDrive/SharePoint files in the tenant. This is broad by design (Graph API doesn't support per-folder app permissions). Ensure the CCRS App Registration is secured: client secret should have a short expiry (90 days max) and be rotated on schedule. |
| **Audit trail** | `sharepoint.folder_linked` events in `audit_log` provide a record of which user linked which folder to which contract, and when. |
| **Unlink does not delete** | Unlinking a folder in CCRS clears the stored IDs but does not remove, modify, or alter access to the SharePoint folder itself. |

---

## 8. Activation Checklist

Before setting `FEATURE_SHAREPOINT=true` in production:

- [ ] Graph API permissions (`Sites.Read.All`, `Files.Read.All`) granted and admin-consented in Azure AD
- [ ] Confirmed the existing CCRS App Registration client secret is valid and not near expiry
- [ ] CTO has reviewed the `Files.Read.All` scope implication (tenant-wide read access)
- [ ] System Admin has identified which contracts should have `sharepoint_enabled = true`
- [ ] Spot-tested on sandbox: link a folder, verify file listing renders, verify audit log entry is created

---

## 9. Disabling the Feature

Set `FEATURE_SHAREPOINT=false` (or remove the env var — it defaults to `false`).

- The SharePoint Documents tab will disappear from all contracts immediately.
- Stored `sharepoint_folder_id` / `sharepoint_drive_id` / `sharepoint_site_id` data is preserved in the DB.
- Re-enabling restores the linked folders without any user action.

Revoking `Files.Read.All` from the App Registration without first disabling the feature will cause Graph API errors logged as warnings in the application log — the tab will silently show as empty.

---

## 10. Known Tech Debt

| Item | Location | Notes |
|---|---|---|
| Token cache key not tenant-scoped pre-P0-3 | `SharePointService::getToken()` | Fixed in P0-3: now uses `TenantCache::key('sharepoint_graph_token')` |
| File listing not paginated | `listFolderContents()` | Graph API returns up to 200 items by default. Folders with >200 files will silently truncate. Add `$top` / `$skipToken` paging if needed. |
| No timeout on Graph API calls | `SharePointService` | `Http::withToken($token)->get(...)` has no explicit timeout. Should add `->timeout(15)` to prevent slow UI on Graph API latency. |
| `sharepoint_enabled` not set by default | Contract model | New contracts default to `false`. Admin must toggle per-contract — this is correct behaviour but not documented in the UI. |
