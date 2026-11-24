<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyNingWebhook
{
    /**
     * Handle an incoming NING network monitoring webhook request.
     *
     * Supports either HMAC-SHA256 signature (`X-Ning-Signature`) or constant-time
     * bearer/header secret (`Authorization: Bearer ...` / `X-Ning-Webhook-Secret`).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) (config('services.ning.webhook_secret') ?: Setting::where('key', 'ning_webhook_secret')->value('value'));

        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: NING webhook secret is not configured.',
            ], 401);
        }

        $signatureHeader = trim((string) $request->header('X-Ning-Signature', ''));
        if ($signatureHeader !== '') {
            $providedHash = str_starts_with($signatureHeader, 'sha256=')
                ? substr($signatureHeader, 7)
                : $signatureHeader;
            $expectedHash = hash_hmac('sha256', (string) $request->getContent(), $secret);

            if (hash_equals($expectedHash, strtolower($providedHash))) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid NING webhook signature.',
            ], 401);
        }

        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Ning-Webhook-Secret', ''));
        if ($providedToken !== '' && hash_equals($secret, $providedToken)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Missing or invalid NING webhook credentials.',
        ], 401);
    }
}
