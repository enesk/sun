<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Company;
use App\Models\Portal\CompanyEditSuggestion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

class SuggestEditModal extends Component
{
    public Company $company;

    public string $field = '';
    public string $suggestedValue = '';
    public string $reason = '';
    public string $reporterName = '';
    public string $reporterEmail = '';

    // Honeypot
    public string $website_url = '';

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
        // Honeypot — Bots füllen das versteckte Feld aus
        if (!empty($this->website_url)) {
            // Fake-Success für Bots
            $this->submitted = true;
            return;
        }

        $this->validate();

        // Rate Limiting: 5 Vorschläge pro IP pro Stunde
        $ip = request()->ip();
        $rateLimitKey = 'suggest-edit:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            $this->addError('field', "Zu viele Vorschläge. Bitte versuchen Sie es in {$minutes} Minuten erneut.");
            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        CompanyEditSuggestion::create([
            'company_id' => $this->company->id,
            'field' => $this->field,
            'suggested_value' => $this->suggestedValue,
            'reason' => $this->reason ?: null,
            'reporter_name' => $this->reporterName ?: null,
            'reporter_email' => $this->reporterEmail ?: null,
            'status' => CompanyEditSuggestion::STATUS_PENDING,
            'ip_address' => $ip,
        ]);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.suggest-edit-modal');
    }
}
