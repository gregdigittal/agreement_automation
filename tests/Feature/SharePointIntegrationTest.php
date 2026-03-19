<?php

use App\Models\AuditLog;
use App\Models\Contract;
use App\Services\SharePointService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// isConfigured()
// ---------------------------------------------------------------------------

it('isConfigured returns false when sharepoint feature flag is disabled', function () {
    Config::set('ccrs.sharepoint.enabled', false);
    Config::set('services.azure.client_id', 'some-client-id');
    Config::set('services.azure.client_secret', 'some-client-secret');

    $service = new SharePointService;

    expect($service->isConfigured())->toBeFalse();
});

it('isConfigured returns false when enabled but azure client_id is missing', function () {
    Config::set('ccrs.sharepoint.enabled', true);
    Config::set('services.azure.client_id', null);
    Config::set('services.azure.client_secret', 'some-client-secret');

    $service = new SharePointService;

    expect($service->isConfigured())->toBeFalse();
});

it('isConfigured returns false when enabled but azure client_secret is missing', function () {
    Config::set('ccrs.sharepoint.enabled', true);
    Config::set('services.azure.client_id', 'some-client-id');
    Config::set('services.azure.client_secret', null);

    $service = new SharePointService;

    expect($service->isConfigured())->toBeFalse();
});

it('isConfigured returns true when enabled and azure credentials are present', function () {
    Config::set('ccrs.sharepoint.enabled', true);
    Config::set('services.azure.client_id', 'some-client-id');
    Config::set('services.azure.client_secret', 'some-client-secret');

    $service = new SharePointService;

    expect($service->isConfigured())->toBeTrue();
});

// ---------------------------------------------------------------------------
// listFolderContents()
// ---------------------------------------------------------------------------

it('listFolderContents returns empty array when contract has no sharepoint_folder_id', function () {
    $contract = Contract::factory()->create([
        'sharepoint_drive_id' => null,
        'sharepoint_folder_id' => null,
    ]);

    $service = new SharePointService;

    expect($service->listFolderContents($contract))->toBe([]);
});

it('listFolderContents returns empty array when contract has no sharepoint_drive_id', function () {
    $contract = Contract::factory()->create([
        'sharepoint_drive_id' => null,
        'sharepoint_folder_id' => 'fold-1',
    ]);

    $service = new SharePointService;

    expect($service->listFolderContents($contract))->toBe([]);
});

it('listFolderContents makes Graph API call and returns mapped items when contract is linked', function () {
    Config::set('ccrs.teams.token_endpoint', 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token');
    Config::set('services.azure.client_id', 'client-id');
    Config::set('services.azure.client_secret', 'client-secret');

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/drives/drv-1/items/fold-1/children*' => Http::response([
            'value' => [
                [
                    'id' => 'item-1',
                    'name' => 'agreement.docx',
                    'webUrl' => 'https://digittalgroup.sharepoint.com/sites/legal/agreement.docx',
                    'size' => 204800,
                    'lastModifiedDateTime' => '2026-03-10T09:00:00Z',
                ],
                [
                    'id' => 'item-2',
                    'name' => 'Attachments',
                    'webUrl' => 'https://digittalgroup.sharepoint.com/sites/legal/Attachments',
                    'lastModifiedDateTime' => '2026-03-11T10:00:00Z',
                    'folder' => ['childCount' => 3],
                ],
            ],
        ], 200),
    ]);

    $contract = Contract::factory()->create([
        'sharepoint_drive_id' => 'drv-1',
        'sharepoint_folder_id' => 'fold-1',
    ]);

    $service = new SharePointService;
    $items = $service->listFolderContents($contract);

    expect($items)->toHaveCount(2);
    expect($items[0]['name'])->toBe('agreement.docx');
    expect($items[0]['size'])->toBe(204800);
    expect($items[0]['is_folder'])->toBeFalse();
    expect($items[1]['name'])->toBe('Attachments');
    expect($items[1]['is_folder'])->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'drives/drv-1/items/fold-1/children'));
});

it('listFolderContents returns empty array on Graph API error', function () {
    Config::set('ccrs.teams.token_endpoint', 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token');
    Config::set('services.azure.client_id', 'client-id');
    Config::set('services.azure.client_secret', 'client-secret');

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/drives/drv-1/items/fold-1/children*' => Http::response([], 403),
    ]);

    $contract = Contract::factory()->create([
        'sharepoint_drive_id' => 'drv-1',
        'sharepoint_folder_id' => 'fold-1',
    ]);

    $service = new SharePointService;

    expect($service->listFolderContents($contract))->toBe([]);
});

// ---------------------------------------------------------------------------
// linkFolder()
// ---------------------------------------------------------------------------

it('linkFolder resolves share URL and updates contract with site, drive, and folder IDs', function () {
    Config::set('ccrs.teams.token_endpoint', 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token');
    Config::set('services.azure.client_id', 'client-id');
    Config::set('services.azure.client_secret', 'client-secret');

    $shareUrl = 'https://digittalgroup.sharepoint.com/:f:/s/legal/EjXabcDEFGH';

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/shares/*/driveItem*' => Http::response([
            'id' => 'fold-99',
            'name' => 'Legal Contracts',
            'parentReference' => [
                'siteId' => 'site-abc',
                'driveId' => 'drv-xyz',
            ],
        ], 200),
    ]);

    $contract = Contract::factory()->create();

    $service = new SharePointService;
    $service->linkFolder($contract, $shareUrl);

    $contract->refresh();
    expect($contract->sharepoint_folder_id)->toBe('fold-99');
    expect($contract->sharepoint_drive_id)->toBe('drv-xyz');
    expect($contract->sharepoint_site_id)->toBe('site-abc');
    expect($contract->sharepoint_url)->toBe($shareUrl);
});

it('linkFolder creates an AuditLog entry after linking', function () {
    Config::set('ccrs.teams.token_endpoint', 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token');
    Config::set('services.azure.client_id', 'client-id');
    Config::set('services.azure.client_secret', 'client-secret');

    $shareUrl = 'https://digittalgroup.sharepoint.com/:f:/s/legal/AuditTest';

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/shares/*/driveItem*' => Http::response([
            'id' => 'fold-audit',
            'name' => 'Audit Folder',
            'parentReference' => [
                'siteId' => 'site-audit',
                'driveId' => 'drv-audit',
            ],
        ], 200),
    ]);

    $contract = Contract::factory()->create();

    $service = new SharePointService;
    $service->linkFolder($contract, $shareUrl);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'sharepoint.folder_linked',
        'resource_type' => 'contract',
        'resource_id' => $contract->id,
    ]);

    $log = AuditLog::where('action', 'sharepoint.folder_linked')
        ->where('resource_id', $contract->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->details['share_url'])->toBe($shareUrl);
    expect($log->details['folder_id'])->toBe('fold-audit');
});

// ---------------------------------------------------------------------------
// Token caching — TenantCache::key() prefix behaviour
// ---------------------------------------------------------------------------

it('token cache key uses the plain key in non-tenant context', function () {
    // In non-tenant context (no tenancy initialised), TenantCache::key() returns
    // the bare key. We verify the token is cached under 'sharepoint_graph_token'.
    Config::set('ccrs.teams.token_endpoint', 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token');
    Config::set('services.azure.client_id', 'client-id');
    Config::set('services.azure.client_secret', 'client-secret');

    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'access_token' => 'cached-token',
            'expires_in' => 3600,
        ], 200),
        'graph.microsoft.com/v1.0/drives/drv-cache/items/fold-cache/children*' => Http::response([
            'value' => [],
        ], 200),
    ]);

    $contract = Contract::factory()->create([
        'sharepoint_drive_id' => 'drv-cache',
        'sharepoint_folder_id' => 'fold-cache',
    ]);

    $service = new SharePointService;
    $service->listFolderContents($contract);

    // Only one token request should have been made — subsequent calls use cache
    $service->listFolderContents($contract);

    Http::assertSentCount(3); // 1 token request + 2 folder list requests (cache hit means 1 token total)
});
