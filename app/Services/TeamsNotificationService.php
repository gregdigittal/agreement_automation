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
     * @param  string  $color  Accent color hex (default: indigo)
     */
    public function sendToChannel(string $subject, string $body, string $color = '#4f46e5'): void
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

        $html = $this->formatCard($subject, $body, $color);

        $response = Http::withToken($token)->post($url, [
            'body' => [
                'contentType' => 'html',
                'content' => $html,
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

    /**
     * Format a structured HTML card for Teams.
     */
    private function formatCard(string $subject, string $body, string $color): string
    {
        $escapedSubject = e($subject);
        $escapedBody = e($body);
        $timestamp = now()->format('d M Y H:i');

        return '<table style="border-collapse:collapse;width:100%;max-width:500px;">'
            . '<tr>'
            . '<td style="background-color:' . $color . ';width:4px;"></td>'
            . '<td style="padding:12px 16px;">'
            . '<div style="font-size:16px;font-weight:bold;color:#1a1a1a;margin-bottom:8px;">' . $escapedSubject . '</div>'
            . '<div style="font-size:14px;color:#4a4a4a;line-height:1.5;">' . $escapedBody . '</div>'
            . '<div style="font-size:11px;color:#9ca3af;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:8px;">CCRS &middot; ' . $timestamp . '</div>'
            . '</td>'
            . '</tr>'
            . '</table>';
    }
}
