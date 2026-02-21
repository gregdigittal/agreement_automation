<?php

namespace Tests\Feature;

use App\Services\TeamsNotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsNotificationTest extends TestCase
{
    public function test_sends_message_to_teams_channel(): void
    {
        config([
            'ccrs.teams.team_id' => 'test-team-id',
            'ccrs.teams.channel_id' => 'test-channel-id',
            'ccrs.teams.token_endpoint' => 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token',
            'ccrs.teams.graph_base_url' => 'https://graph.microsoft.com/v1.0',
            'services.azure.client_id' => 'client-id',
            'services.azure.client_secret' => 'client-secret',
        ]);

        Http::fake([
            '*oauth2/v2.0/token*' => Http::response(['access_token' => 'fake-token'], 200),
            '*teams/*/channels/*/messages*' => Http::response(['id' => 'msg-123'], 201),
        ]);

        app(TeamsNotificationService::class)->sendToChannel(
            'Contract Approved',
            'Contract XYZ has been approved by Legal.'
        );

        Http::assertSent(fn ($req) => str_contains($req->url(), '/messages'));
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
}
