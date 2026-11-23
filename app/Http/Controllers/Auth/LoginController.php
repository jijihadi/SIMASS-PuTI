<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Maximum login attempts before lockout.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Lockout duration in seconds (3 minutes).
     */
    private const DECAY_SECONDS = 180;

    public function index(): View
    {
        return view('pages.auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $rateLimiterKey = $this->throttleKey($request);

        // Check if currently locked out
        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $minutes = ceil($seconds / 60);

            return back()->withErrors([
                'username' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$minutes} menit ({$seconds} detik).",
            ])->onlyInput('username');
        }

        // Find user case-insensitively by username or email (handles capital letters & extra spaces)
        $loginIdentifier = strtolower(trim((string) $request->username));
        $user = User::whereRaw('LOWER(username) = ?', [$loginIdentifier])
            ->orWhereRaw('LOWER(email) = ?', [$loginIdentifier])
            ->first();

        // Check if user account is active
        if ($user && ! $user->is_active) {
            return back()->withErrors([
                'username' => 'Akun Anda berstatus nonaktif. Silakan hubungi administrator.',
            ])->onlyInput('username');
        }

        if (! $user || ! Auth::attempt(
            ['email' => $user->email, 'password' => $request->password],
            $request->boolean('remember')
        )) {
            // Increment failed attempts & track key so Admin can unlock from any IP
            RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);
            $this->trackThrottleKey($rateLimiterKey, $loginIdentifier, $user);

            return back()->withErrors([
                'username' => 'Username atau password yang Anda masukkan salah.',
            ])->onlyInput('username');
        }

        // Login success — clear rate limiter
        RateLimiter::clear($rateLimiterKey);
        self::clearLockoutForUser($user);

        $request->session()->regenerate();

        return redirect()->intended(route('portal'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Check whether a user currently has any active login lockout across tracked IPs.
     */
    public static function isUserLockedOut(User $user): bool
    {
        foreach (self::trackedKeysForUser($user) as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a user currently has any recorded failed login attempts across tracked IPs.
     */
    public static function hasFailedAttempts(User $user): bool
    {
        foreach (self::trackedKeysForUser($user) as $key) {
            if (RateLimiter::attempts($key) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all login attempt lockouts for a user across all tracked IPs (Admin action).
     */
    public static function clearLockoutForUser(User $user): void
    {
        foreach (self::trackedKeysForUser($user) as $key) {
            RateLimiter::clear($key);
        }

        foreach (self::userIdentifiers($user) as $identifier) {
            Cache::forget("login:keys:{$identifier}");
        }
    }

    /**
     * Get the rate limiter key for the request.
     * Combines normalized username/email + IP to avoid blocking different users on shared campus Wi-Fi.
     */
    private function throttleKey(Request $request): string
    {
        return 'login:'.strtolower(trim((string) $request->input('username'))).'|'.$request->ip();
    }

    private function trackThrottleKey(string $rateLimiterKey, string $loginIdentifier, ?User $user): void
    {
        $identifiers = array_filter(array_unique(array_merge(
            [$loginIdentifier],
            $user ? self::userIdentifiers($user) : []
        )));

        foreach ($identifiers as $identifier) {
            $cacheKey = "login:keys:{$identifier}";
            /** @var list<string> $keys */
            $keys = Cache::get($cacheKey, []);
            if (! in_array($rateLimiterKey, $keys, true)) {
                $keys[] = $rateLimiterKey;
            }
            Cache::put($cacheKey, $keys, self::DECAY_SECONDS);
        }
    }

    /**
     * @return list<string>
     */
    private static function trackedKeysForUser(User $user): array
    {
        $allKeys = [];
        foreach (self::userIdentifiers($user) as $identifier) {
            /** @var list<string> $keys */
            $keys = Cache::get("login:keys:{$identifier}", []);
            $allKeys = array_merge($allKeys, $keys);
        }

        return array_values(array_unique($allKeys));
    }

    /**
     * @return list<string>
     */
    private static function userIdentifiers(User $user): array
    {
        return array_values(array_filter(array_unique([
            $user->username ? strtolower(trim($user->username)) : null,
            $user->email ? strtolower(trim($user->email)) : null,
        ])));
    }
}
