<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharePointService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function isConfigured(): bool
    {
        return (bool) config('ccrs.sharepoint.enabled', false)
            && config('services.azure.client_id')
            && config('services.azure.client_secret');
    }

    /**
     * Get an access token using client credentials flow.
     * Token cached for 50 minutes (expires at 60).
     * Same pattern as TeamsNotificationService.
     */
    private function getToken(): string
    {
        return Cache::remember('sharepoint_graph_token', now()->addMinutes(50), function () {
            $response = Http::asForm()->post(config('ccrs.teams.token_endpoint'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            if (! $response->successful()) {
                Log::error('SharePoint: failed to obtain Graph token', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('Microsoft Graph token request failed');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Resolve a SharePoint sharing URL to site_id, drive_id, and folder_id
     * via the Graph /shares endpoint.
     */
    public function resolveShareUrl(string $shareUrl): array
    {
        $token = $this->getToken();
        $encoded = 'u!' . rtrim(base64_encode($shareUrl), '=');

        $response = Http::withToken($token)
            ->get(self::GRAPH_BASE . "/shares/{$encoded}/driveItem", [
                '$select' => 'id,name,parentReference',
            ]);

        if (! $response->successful()) {
            Log::error('SharePoint: failed to resolve share URL', [
                'url' => $shareUrl,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \RuntimeException('Could not resolve SharePoint URL. Ensure the link is a valid sharing URL.');
        }

        $item = $response->json();
        $parentRef = $item['parentReference'] ?? [];

        return [
            'folder_id' => $item['id'],
            'drive_id' => $parentRef['driveId'] ?? null,
            'site_id' => $parentRef['siteId'] ?? null,
        ];
    }

    /**
     * List contents of the linked SharePoint folder for a contract.
     */
    public function listFolderContents(Contract $contract): array
    {
        if (! $contract->sharepoint_drive_id || ! $contract->sharepoint_folder_id) {
            return [];
        }

        $token = $this->getToken();

        $url = sprintf(
            '%s/drives/%s/items/%s/children',
            self::GRAPH_BASE,
            $contract->sharepoint_drive_id,
            $contract->sharepoint_folder_id
        );

        $response = Http::withToken($token)->get($url, [
            '$select' => 'id,name,webUrl,size,lastModifiedDateTime,folder',
        ]);

        if (! $response->successful()) {
            Log::warning('SharePoint: failed to list folder contents', [
                'contract_id' => $contract->id,
                'status' => $response->status(),
            ]);
            return [];
        }

        return collect($response->json('value', []))->map(fn (array $item) => [
            'name' => $item['name'],
            'web_url' => $item['webUrl'],
            'size' => $item['size'] ?? 0,
            'last_modified' => $item['lastModifiedDateTime'] ?? null,
            'is_folder' => isset($item['folder']),
        ])->all();
    }

    /**
     * Link a SharePoint folder to a contract by resolving its sharing URL.
     */
    public function linkFolder(Contract $contract, string $shareUrl): void
    {
        $resolved = $this->resolveShareUrl($shareUrl);

        $contract->update([
            'sharepoint_folder_id' => $resolved['folder_id'],
            'sharepoint_drive_id' => $resolved['drive_id'],
            'sharepoint_site_id' => $resolved['site_id'],
            'sharepoint_url' => $shareUrl,
        ]);

        AuditService::log('sharepoint.folder_linked', 'contract', $contract->id, [
            'share_url' => $shareUrl,
            'folder_id' => $resolved['folder_id'],
        ]);
    }
}
