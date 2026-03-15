<?php

namespace Tests\Feature;

use App\Services\TeamsNotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsNotificationTest extends TestCase
{
    private function configureTeams(): void
    {
        config([
            'ccrs.teams.team_id' => 'test-team-id',
            'ccrs.teams.channel_id' => 'test-channel-id',
            'ccrs.teams.token_endpoint' => 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token',
            'ccrs.teams.graph_base_url' => 'https://graph.microsoft.com/v1.0',
            'services.azure.client_id' => 'client-id',
            'services.azure.client_secret' => 'client-secret',
        ]);
    }

    public function test_sends_adaptive_card_to_teams_channel(): void
    {
        $this->configureTeams();

        Http::fake([
            '*oauth2/v2.0/token*' => Http::response(['access_token' => 'fake-token'], 200),
            '*teams/*/channels/*/messages*' => Http::response(['id' => 'msg-123'], 201),
        ]);

        app(TeamsNotificationService::class)->sendToChannel(
            'Contract Approved',
            'Contract XYZ has been approved by Legal.'
        );

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), '/messages')) {
                return false;
            }

            $data = $req->data();

            // Must use Adaptive Card attachment, not raw HTML content
            $contentType = $data['body']['contentType'] ?? '';
            $attachments = $data['attachments'] ?? [];
            $attachmentContentType = $attachments[0]['contentType'] ?? '';
            $cardContent = json_decode($attachments[0]['content'] ?? '{}', true);

            return $contentType === 'html'
                && $attachmentContentType === 'application/vnd.microsoft.card.adaptive'
                && ($cardContent['type'] ?? '') === 'AdaptiveCard'
                && str_contains($attachments[0]['content'], 'Contract Approved')
                && str_contains($attachments[0]['content'], 'CCRS');
        });
    }

    public function test_skips_when_not_configured(): void
    {
        config([
            'ccrs.teams.team_id' => '',
            'ccrs.teams.channel_id' => '',
        ]);

        Http::fake();

        app(TeamsNotificationService::class)->sendToChannel('Test', 'Body');

        Http::assertNothingSent();
    }

    public function test_adaptive_card_structure_is_valid(): void
    {
        $service = app(TeamsNotificationService::class);
        $card = $service->buildAdaptiveCard('Test Subject', 'Test body text');

        $this->assertSame('AdaptiveCard', $card['type']);
        $this->assertSame('http://adaptivecards.io/schemas/adaptive-card.json', $card['$schema']);
        $this->assertSame('1.5', $card['version']);
        $this->assertArrayHasKey('body', $card);
        $this->assertNotEmpty($card['body']);
    }

    public function test_adaptive_card_contains_subject_and_body(): void
    {
        $service = app(TeamsNotificationService::class);
        $card = $service->buildAdaptiveCard('SLA Breach Alert', 'Contract ABC exceeded SLA by 12 hours');

        $encoded = json_encode($card);

        $this->assertStringContainsString('SLA Breach Alert', $encoded);
        $this->assertStringContainsString('Contract ABC exceeded SLA by 12 hours', $encoded);
        $this->assertStringContainsString('CCRS', $encoded);
    }

    public function test_adaptive_card_is_valid_json(): void
    {
        $service = app(TeamsNotificationService::class);
        $card = $service->buildAdaptiveCard('Subject', 'Body');

        $json = json_encode($card);
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
