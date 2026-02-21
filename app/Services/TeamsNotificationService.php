<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsNotificationService
{
    /**
     * Get an access token for Microsoft Graph using client credentials flow.
     * Token is cached for 50 minutes (tokens expire at 60 min).
     */
    private function getAccessToken(): string
    {
        return Cache::remember('ms_graph_token', now()->addMinutes(50), function () {
            $response = Http::asForm()->post(config('ccrs.teams.token_endpoint'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'scope' => config('ccrs.teams.graph_scope', 'https://graph.microsoft.com/.default'),
            ]);

            if (! $response->successful()) {
                Log::error('Failed to obtain Microsoft Graph token', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('Microsoft Graph token request failed');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Post a message to the configured Teams channel.
     *
     * @param  string  $subject  Bold header line
     * @param  string  $body  Message body (plain text or simple HTML)
     */
    public function sendToChannel(string $subject, string $body): void
    {
        $teamId = config('ccrs.teams.team_id');
        $channelId = config('ccrs.teams.channel_id');

        if (! $teamId || ! $channelId) {
            Log::warning('Teams notification skipped — TEAMS_TEAM_ID or TEAMS_CHANNEL_ID not configured');
            return;
        }

        $token = $this->getAccessToken();

        $url = sprintf(
            '%s/teams/%s/channels/%s/messages',
            config('ccrs.teams.graph_base_url'),
            $teamId,
            $channelId
        );

        $response = Http::withToken($token)->post($url, [
            'body' => [
                'contentType' => 'html',
                'content' => "<b>{$subject}</b><br/>{$body}",
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Failed to send Teams notification', [
                'status' => $response->status(),
                'response' => $response->json(),
                'subject' => $subject,
            ]);
            throw new \RuntimeException('Teams notification failed: ' . $response->status());
        }
    }
}
