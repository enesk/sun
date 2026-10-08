<?php

namespace App\Http\Controllers\Auth;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\RateLimitGuard;
use App\Http\Controllers\Auth\Trait\RedirectAwareTrait;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginService;
use App\Support\IntendedUrl;
use App\Turnstile\Models\TurnstileVerification;
use App\Validator\LoginValidator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers {
        AuthenticatesUsers::login as traitLogin;
    }
    use RedirectAwareTrait;

    public function __construct(
        private LoginValidator $loginValidator,
        private LoginService $loginService,
    ) {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Rate-Limit (#8): 10/Minute je IP-Hash UND E-Mail, Schluessel mit
     * Portal-Praefix. Wirft 429 mit Retry-After und deutscher Meldung; Grenze
     * in config/antispam.php.
     *
     * In der Aktion und nicht als `throttle`-Middleware, weil die vor der
     * Tenancy laeuft — Begruendung im Docblock von RateLimitGuard.
     */
    public function login(Request $request)
    {
        // null als Aktion: der Anmeldeversuch gehoert nicht nach
        // turnstile_verifications — die Spalte `action` kennt nur die vier
        // TurnstileAction-Werte, `login` ist keiner davon.
        RateLimitGuard::enforce(RateLimitGuard::LOGIN, null, [
            'email' => is_string($request->input($this->username())) ? $request->input($this->username()) : null,
        ]);

        return $this->traitLogin($request);
    }

    /**
     * ThrottlesLogins aus laravel/ui zaehlt zusaetzlich mit (5/Minute als
     * Vorgabe) und wuerde sonst mit einer anderen Grenze und einer anderen
     * Meldung zuerst greifen. Beide lesen deshalb denselben Wert aus
     * config/antispam.php.
     */
    public function maxAttempts(): int
    {
        return max(1, AntiSpamConfig::int('limits.login.ip_email.max', 10));
    }

    public function decayMinutes(): int
    {
        return max(1, AntiSpamConfig::int('limits.login.ip_email.minutes', 1));
    }

    /**
     * Schluessel von ThrottlesLogins mit Portal-Praefix und IP-Hash statt
     * Klartext-IP — dieselbe Form wie im benannten Limiter, damit beide
     * Zaehler nicht aus verschiedenen Raeumen kommen.
     */
    protected function throttleKey(Request $request): string
    {
        return RateLimitGuard::key(
            RateLimitGuard::LOGIN,
            'throttles_logins',
            Str::lower((string) $request->input($this->username())).'|'.(TurnstileVerification::hashIp($request->ip()) ?? 'ohne-ip'),
        );
    }

    public function showLoginForm()
    {
        if (url()->previous() != route('register') && Redirect::getIntendedUrl() === null) {
            IntendedUrl::rememberPrevious(); // make sure we redirect back to the page we came from (own hosts only)
        }

        return view('auth.login', [
            'isOtpLoginEnabled' => config('app.otp_login_enabled'),
        ]);
    }

    protected function authenticated(Request $request, User $user)
    {
        if ($user->is_blocked) {
            $this->guard()->logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been blocked. Please contact support.',
            ]);
        }

        return redirect($this->getRedirectUrl($user));
    }

    protected function validateLogin(Request $request)
    {
        $this->loginValidator->validateRequest($request);
    }

    protected function attemptLogin(Request $request)
    {
        return $this->loginService->attempt($this->credentials($request), $request->boolean('remember'));
    }
}
