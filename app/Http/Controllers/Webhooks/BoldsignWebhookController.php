<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\BoldsignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
