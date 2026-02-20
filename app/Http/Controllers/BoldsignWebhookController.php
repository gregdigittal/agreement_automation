<?php
namespace App\Http\Controllers;

use App\Services\BoldsignService;
use Illuminate\Http\Request;

class BoldsignWebhookController extends Controller
{
    public function handle(Request $request, BoldsignService $service)
    {
        $secret = config('ccrs.boldsign_webhook_secret');
        $signature = $request->header('X-BoldSign-Signature') ?? $request->header('Boldsign-Signature') ?? '';
        if ($secret && ! $service->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
            abort(401, 'Invalid webhook signature');
        }
        $service->handleWebhook($request->all());
        return response()->json(['ok' => true]);
    }
}
