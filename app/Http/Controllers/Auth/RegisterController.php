<?php

namespace App\Http\Controllers\Auth;

use App\AntiSpam\SpamGuard;
use App\AntiSpam\Support\RateLimitGuard;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Support\IntendedUrl;
use App\Turnstile\Enums\TurnstileAction;
use App\Validator\RegisterValidator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers {
        RegistersUsers::register as traitRegister;
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    //    protected $redirectTo = '/email/verify';

    public function __construct(
        protected RegisterValidator $registerValidator,
        protected UserService $userService,
    ) {
        $this->middleware('guest');
    }

    /**
     * Honeypot und Mindest-Ausfuellzeit laufen VOR der Validierung und weisen
     * STILL ab (#8): der Besucher sieht denselben Ablauf wie bei einer
     * erfolgreichen Registrierung, es entsteht aber kein Konto. Ein Bot soll
     * nicht lernen, woran er gescheitert ist — eine Validierungsmeldung wuerde
     * genau das verraten.
     *
     * Der Treffer steht in turnstile_verifications (outcome failed,
     * error_codes ['honeypot'] bzw. ['too_fast']), geschrieben vom SpamGuard.
     *
     * Alles Weitere bleibt bei Illuminate\Foundation\Auth\RegistersUsers.
     */
    public function register(Request $request)
    {
        // Rate-Limit (#8): 5/Stunde je IP-Hash und 3/Tag je E-Mail, Schluessel
        // mit Portal-Praefix. Wirft bei einem Treffer 429 mit Retry-After und
        // deutscher Meldung. Steht hier und nicht als `throttle`-Middleware,
        // weil die erst VOR der Tenancy laeuft — Begruendung im Docblock von
        // RateLimitGuard.
        RateLimitGuard::enforce(RateLimitGuard::REGISTRATION, TurnstileAction::Registration, [
            'email' => is_string($request->input('email')) ? $request->input('email') : null,
        ]);

        if (app(SpamGuard::class)->rejectsSilently(TurnstileAction::Registration, $request->all())) {
            return $this->fakeRegistrationSuccess();
        }

        return $this->traitRegister($request);
    }

    /**
     * Die vorgespielte Erfolgserwiderung: genau dieselbe Antwort, die
     * RegistersUsers::register() nach einer echten Anmeldung schickt — 201
     * bei JSON, sonst die Weiterleitung auf redirectPath(). Angemeldet ist
     * dabei niemand, ein Gast landet also auf der Anmeldeseite; von aussen
     * ist das nicht von einem Erfolg zu unterscheiden.
     *
     * Bewusst OHNE eigene Meldung in der Session: die Anmeldeseite des Themes
     * sun-v2 liest session('status') als "Passwort-Link verschickt" und wuerde
     * die falsche Karte oeffnen.
     */
    private function fakeRegistrationSuccess()
    {
        if (request()->wantsJson()) {
            return response()->json([], 201);
        }

        return redirect($this->redirectPath());
    }

    public function redirectPath()
    {
        if (($intended = Redirect::getIntendedUrl()) && IntendedUrl::isOwn($intended)) {
            return $intended;
        }

        // Im Tenant-Kontext: nach Registration ins Dashboard leiten
        if (tenant()) {
            return '/dashboard';
        }

        return route('home');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return $this->registerValidator->validate($data);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @return User
     */
    protected function create(array $data)
    {
        return $this->userService->createUser($data);
    }

    /**
     * Show the application registration form.
     *
     * @return View
     */
    public function showRegistrationForm()
    {
        if (url()->previous() != route('login') && Redirect::getIntendedUrl() === null) {
            IntendedUrl::rememberPrevious(); // make sure we redirect back to the page we came from (own hosts only)
        }

        return view('auth.register', [
            'isOtpLoginEnabled' => config('app.otp_login_enabled'),
        ]);
    }
}
