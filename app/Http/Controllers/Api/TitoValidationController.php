<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TitoValidationController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-TiTo-API-Key');
        if (!$apiKey || $apiKey !== config('ccrs.tito_api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'registration_number' => 'required|string|max:100',
            'country_code' => 'sometimes|string|max:5',
        ]);

        $regNumber = $request->input('registration_number');
        $countryCode = $request->input('country_code', 'AU');

        $cacheKey = "tito:validate:{$countryCode}:{$regNumber}";
        $result = Cache::remember($cacheKey, 300, function () use ($regNumber, $countryCode) {
            return $this->lookupRegistration($regNumber, $countryCode);
        });

        AuditService::log('tito_validation', 'counterparty', null, [
            'registration_number' => $regNumber,
            'country_code' => $countryCode,
            'match' => $result['match'] ?? false,
        ]);

        return response()->json($result);
    }

    private function lookupRegistration(string $regNumber, string $countryCode): array
    {
        $counterparty = \App\Models\Counterparty::where('registration_number', $regNumber)->first();

        if ($counterparty) {
            return [
                'match' => true,
                'source' => 'internal',
                'counterparty' => [
                    'id' => $counterparty->id,
                    'legal_name' => $counterparty->legal_name,
                    'status' => $counterparty->status,
                    'jurisdiction' => $counterparty->jurisdiction,
                ],
            ];
        }

        return [
            'match' => false,
            'source' => 'internal',
            'message' => 'No counterparty found with this registration number',
        ];
    }
}
