<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Company;
use App\Models\Portal\CompanyEditSuggestion;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * PROF-1: Standalone-Formular für die "Änderung vorschlagen" Landingpage.
 * Identische Logik wie SuggestEditModal, aber ohne Modal-Wrapper.
 */
class SuggestEditForm extends Component
{
    public Company $company;

    public string $field = '';
    public string $suggestedValue = '';
    public string $reason = '';
    public string $reporterName = '';
    public string $reporterEmail = '';

    // Honeypot
    public string $website_url = '';

    public bool $submitted = false;

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

    public function submit(): void
    {
        // Honeypot
        if (!empty($this->website_url)) {
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
        return view('livewire.portal.suggest-edit-form');
    }
}
