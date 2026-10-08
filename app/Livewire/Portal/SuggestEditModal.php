<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\Mail\EditSuggestionNotification;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyEditSuggestion;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Rules\TurnstileRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\On;
use Livewire\Component;

class SuggestEditModal extends Component
{
    // Honigtopf und Mindest-Ausfuellzeit (#22); die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht. Der
    // frueher hier handgeschriebene Honigtopf `website_url` ist damit weg —
    // sein Feldname war fest und seine Treffer landeten in keinem Log.
    use InteractsWithAntiSpam;

    public Company $company;

    /** Turnstile-Token, gesetzt von <x-turnstile wire="turnstileToken" />. */
    public string $turnstileToken = '';

    public string $field = '';
    public string $suggestedValue = '';
    public string $reason = '';
    public string $reporterName = '';
    public string $reporterEmail = '';

    public bool $showModal = false;
    public bool $submitted = false;
    public bool $hideTrigger = false;

    protected function rules(): array
    {
        return [
            'field' => ['required', 'string', 'in:address,phone,hours,description,other'],
            'suggestedValue' => ['required', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
            'reporterName' => ['nullable', 'string', 'max:100'],
            'reporterEmail' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'field.required' => 'Bitte wählen Sie aus, was geändert werden soll.',
            'field.in' => 'Bitte wählen Sie eine gültige Option.',
            'suggestedValue.required' => 'Bitte beschreiben Sie die Änderung.',
            'suggestedValue.max' => 'Der Vorschlag darf maximal 2.000 Zeichen lang sein.',
            'reporterEmail.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ];
    }

    #[On('openSuggestEditModal')]
    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function submit(): void
    {
        // Honigtopf und Mindest-Ausfuellzeit (#22): still abweisen. Der
        // Besucher sieht dieselbe Erfolgsmeldung, es entsteht aber kein
        // Vorschlag. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::Contact, $this->reporterEmail ?: null)) {
            $this->submitted = true;
            return;
        }

        $this->validate();

        $ip = request()->ip();

        // Rate-Limit je IP-Hash und Portal (#22), Grenze in
        // config/antispam.php. Ersetzt den handgeschriebenen Zaehler, der auf
        // der Klartext-IP und portaluebergreifend lief.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::SUGGEST_EDIT);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::SUGGEST_EDIT, TurnstileAction::Contact, $this->reporterEmail ?: null);
            $this->addError('field', $this->antiSpamThrottleMessage($wartezeit));
            return;
        }

        // Turnstile zuletzt: erst wenn alle Felder stimmen, wird ein Token
        // eingeloest — Cloudflare nimmt jedes Token nur einmal an.
        $this->validate([
            'turnstileToken' => [new TurnstileRule(TurnstileAction::Contact, 'reporterEmail')],
        ]);

        $suggestion = CompanyEditSuggestion::create([
            'company_id' => $this->company->id,
            'field' => $this->field,
            'suggested_value' => $this->suggestedValue,
            'reason' => $this->reason ?: null,
            'reporter_name' => $this->reporterName ?: null,
            'reporter_email' => $this->reporterEmail ?: null,
            'status' => CompanyEditSuggestion::STATUS_PENDING,
            'ip_address' => $ip,
        ]);

        try {
            Mail::to('hello@eneskul.com')->send(new EditSuggestionNotification($suggestion));
        } catch (\Exception $e) {
            Log::warning('SuggestEditModal: Failed to send edit suggestion notification email', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::SUGGEST_EDIT);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.suggest-edit-modal');
    }
}
