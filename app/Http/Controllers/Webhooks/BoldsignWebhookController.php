<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\BoldsignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @deprecated This webhook controller is deprecated in favour of in-house signing.
 *
 * The webhook route is only registered when FEATURE_IN_HOUSE_SIGNING=false.
 * Once all deployments have migrated to in-house signing, this controller
 * and its route can be safely removed.
 *
 * @see \App\Services\SigningService
 * @see \App\Helpers\Feature::inHouseSigning()
 */
class BoldsignWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('ccrs.boldsign_webhook_secret');
        $signature = $request->header('X-BoldSign-Signature') ?? $request->header('Boldsign-Signature') ?? '';

        if (!app(BoldsignService::class)->verifyWebhookSignature($request->getContent(), $signature, $secret ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        app(BoldsignService::class)->handleWebhook($request->all());
        return response()->json(['ok' => true]);
    }
}
