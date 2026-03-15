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
     * Post an Adaptive Card message to the configured Teams channel.
     *
     * @param  string  $subject  Bold header displayed in the card title
     * @param  string  $body  Card body text (plain text)
     * @param  string  $color  Accent colour hex — mapped to Adaptive Card accent colour (default: indigo)
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

        $attachmentId = substr(md5($subject.$body.now()->timestamp), 0, 20);
        $card = $this->buildAdaptiveCard($subject, $body, $color);

        $response = Http::withToken($token)->post($url, [
            'body' => [
                'contentType' => 'html',
                'content' => "<attachment id=\"{$attachmentId}\"></attachment>",
            ],
            'attachments' => [
                [
                    'id' => $attachmentId,
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => json_encode($card),
                ],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Failed to send Teams notification', [
                'status' => $response->status(),
                'response' => $response->json(),
                'subject' => $subject,
            ]);
            throw new \RuntimeException('Teams notification failed: '.$response->status());
        }
    }

    /**
     * Build an Adaptive Card payload (v1.5) for a CCRS notification.
     *
     * @return array<string, mixed>
     */
    public function buildAdaptiveCard(string $subject, string $body, string $color = '#4f46e5'): array
    {
        return [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.5',
            'body' => [
                [
                    'type' => 'ColumnSet',
                    'columns' => [
                        [
                            'type' => 'Column',
                            'width' => 'auto',
                            'style' => 'emphasis',
                            'items' => [
                                [
                                    'type' => 'TextBlock',
                                    'text' => ' ',
                                    'color' => 'Accent',
                                ],
                            ],
                        ],
                        [
                            'type' => 'Column',
                            'width' => 'stretch',
                            'items' => [
                                [
                                    'type' => 'TextBlock',
                                    'text' => $subject,
                                    'weight' => 'Bolder',
                                    'size' => 'Medium',
                                    'wrap' => true,
                                    'color' => 'Default',
                                ],
                                [
                                    'type' => 'TextBlock',
                                    'text' => $body,
                                    'wrap' => true,
                                    'spacing' => 'Small',
                                    'color' => 'Default',
                                ],
                                [
                                    'type' => 'TextBlock',
                                    'text' => 'CCRS · '.now()->format('d M Y H:i'),
                                    'size' => 'Small',
                                    'color' => 'Light',
                                    'spacing' => 'Medium',
                                    'wrap' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
