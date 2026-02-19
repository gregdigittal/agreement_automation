<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelemetryService
{
    private static ?object $tracer = null;

    public static function getTracer(): ?object
    {
        if (self::$tracer !== null) return self::$tracer;

        if (!class_exists(\OpenTelemetry\API\Globals::class)) {
            return null;
        }

        try {
            self::$tracer = \OpenTelemetry\API\Globals::tracerProvider()
                ->getTracer('ccrs-app', '1.0.0');
        } catch (\Throwable $e) {
            Log::debug('OTel tracer init failed: ' . $e->getMessage());
            self::$tracer = null;
        }

        return self::$tracer;
    }

    public static function startSpan(string $name, array $attributes = []): ?object
    {
        $tracer = self::getTracer();
        if (!$tracer) return null;

        try {
            $spanBuilder = $tracer->spanBuilder($name);
            foreach ($attributes as $key => $value) {
                $spanBuilder->setAttribute($key, (string) $value);
            }
            $span = $spanBuilder->startSpan();
            $span->activate();
            return $span;
        } catch (\Throwable $e) {
            Log::debug("OTel span creation failed: {$e->getMessage()}");
            return null;
        }
    }

    public static function endSpan(?object $span): void
    {
        if ($span === null) return;
        try {
            $span->end();
        } catch (\Throwable) {}
    }
}
