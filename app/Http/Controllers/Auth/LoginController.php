<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // Increment failed attempts
            RateLimiter::hit($rateLimiterKey, self::DECAY_SECONDS);

            return back()->withErrors([
                'username' => 'Username atau password yang Anda masukkan salah.',
            ])->onlyInput('username');
        }

        // Login success — clear rate limiter
        RateLimiter::clear($rateLimiterKey);

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

    public function resetLockout(Request $request): RedirectResponse
    {
        $username = $request->input('username');
        if ($username) {
            $rateLimiterKey = 'login:'.strtolower($username).'|'.$request->ip();
            RateLimiter::clear($rateLimiterKey);
        }

        RateLimiter::clear('login:|'.$request->ip());

        return back()->with('status', 'Kunci percobaan login berhasil direset. Silakan coba masuk kembali.');
    }

    /**
     * Get the rate limiter key for the request.
     * Combines username + IP to avoid blocking different users from same IP.
     */
    private function throttleKey(Request $request): string
    {
        return 'login:'.strtolower($request->input('username')).'|'.$request->ip();
    }
}
