<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\Models\Portal\NewsletterSubscriber;
use App\Turnstile\Concerns\InteractsWithTurnstile;
use App\Turnstile\Enums\TurnstileAction;
use Livewire\Component;

class NewsletterSubscribeForm extends Component
{
    // Honigtopf und Mindest-Ausfuellzeit (#22); die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht.
    use InteractsWithAntiSpam;
    use InteractsWithTurnstile;

    public string $email = '';
    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ];
    }

    public function subscribe(): void
    {
        // Honigtopf und Mindest-Ausfuellzeit (#22): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsmeldung, es entsteht aber kein
        // Eintrag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::Contact, $this->email)) {
            $this->submitted = true;
            return;
        }

        $this->validate();

        // Rate-Limit je IP-Hash, E-Mail-Hash und Portal (#22), Grenzen in
        // config/antispam.php. In Livewire wird daraus eine Meldung am Feld
        // und kein 429 — sonst stuende der Besucher vor einem toten Formular.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::NEWSLETTER, ['email' => $this->email]);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::NEWSLETTER, TurnstileAction::Contact, $this->email);
            $this->addError('email', $this->antiSpamThrottleMessage($wartezeit));
            return;
        }

        // Turnstile zuletzt: erst wenn die Adresse stimmt, wird ein Token
        // eingeloest — Cloudflare nimmt jedes Token nur einmal an.
        $this->validateTurnstile(TurnstileAction::Contact);

        $existing = NewsletterSubscriber::where('email', $this->email)->first();

        if ($existing) {
            if ($existing->unsubscribed_at) {
                $existing->update([
                    'unsubscribed_at' => null,
                    'subscribed_at' => now(),
                    'ip_address' => request()->ip(),
                ]);
            } else {
                // Already subscribed — still show success (no info leak)
                $this->antiSpamCount(RateLimitGuard::NEWSLETTER, ['email' => $this->email]);
                $this->submitted = true;
                return;
            }
        } else {
            NewsletterSubscriber::create([
                'email' => $this->email,
                'ip_address' => request()->ip(),
            ]);
        }

        // Erst jetzt zaehlen: ein Tippfehler in der Adresse soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::NEWSLETTER, ['email' => $this->email]);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.newsletter-subscribe-form');
    }
}
