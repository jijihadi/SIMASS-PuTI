<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLunaApiKey
{
    /**
     * Normalize Indonesian WhatsApp phone number to canonical 628xxx format.
     */
    public static function normalizeWaPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Strip WhatsApp JID suffix e.g. @s.whatsapp.net or @c.us
        $cleaned = preg_replace('/@.*$/', '', trim($phone)) ?? '';
        // Strip non-digits
        $digits = preg_replace('/\D+/', '', $cleaned) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) (config('services.luna.api_key') ?: Setting::where('key', 'luna_api_key')->value('value'));
        $providedKey = (string) ($request->bearerToken() ?: $request->header('X-Luna-Api-Key', ''));

        if ($configuredKey === '' || $providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing Luna AI API key.',
            ], 401);
        }

        // Enforce single whitelisted WhatsApp number if phone is provided or required
        $allowedNumberRaw = (string) (config('services.luna.allowed_wa_number') ?: Setting::where('key', 'luna_allowed_wa_number')->value('value'));
        $allowedNumber = self::normalizeWaPhone($allowedNumberRaw);

        $requestPhoneRaw = (string) ($request->input('phone') ?: $request->header('X-Wa-Number', ''));
        $requestPhone = self::normalizeWaPhone($requestPhoneRaw);

        if ($allowedNumber !== '' && $requestPhone !== '' && ! hash_equals($allowedNumber, $requestPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Nomor WhatsApp ini tidak diizinkan untuk mengakses layanan tiket Luna AI.',
            ], 403);
        }

        if ($requestPhone !== '') {
            $request->merge(['normalized_phone' => $requestPhone]);
        }

        return $next($request);
    }
}
