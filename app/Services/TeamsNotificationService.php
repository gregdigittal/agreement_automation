<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsNotificationService
{
    private function getGraphToken(): string
    {
        return Cache::remember('ms_graph_token', 3000, function () {
            $tenantId = config('ccrs.azure_ad.tenant_id', config('services.azure.tenant'));
            $clientId = config('services.azure.client_id');
            $clientSecret = config('services.azure.client_secret');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]
            );

            if (!$response->successful()) {
                Log::error('Failed to get Graph token', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException('Failed to obtain Microsoft Graph token');
            }

            return $response->json('access_token');
        });
    }

    public function send(string $teamId, string $channelId, string $subject, string $body): bool
    {
        $token = $this->getGraphToken();

        $response = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/teams/{$teamId}/channels/{$channelId}/messages", [
                'body' => [
                    'contentType' => 'html',
                    'content' => "<h3>{$subject}</h3><p>{$body}</p>",
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Teams message failed', [
                'status' => $response->status(),
                'team' => $teamId,
                'channel' => $channelId,
            ]);
            return false;
        }

        return true;
    }

    public function sendNotification(string $subject, string $body): bool
    {
        $teamId = config('ccrs.teams_team_id', '');
        $channelId = config('ccrs.teams_channel_id', '');

        if (!$teamId || !$channelId) {
            Log::warning('Teams notification skipped: team_id or channel_id not configured');
            return false;
        }

        return $this->send($teamId, $channelId, $subject, $body);
    }
}
