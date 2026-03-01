<?php

namespace App\Livewire\Portal;

use App\Models\Portal\NewsletterSubscriber;
use Livewire\Component;

class NewsletterSubscribeForm extends Component
{
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
        $this->validate();

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
                $this->submitted = true;
                return;
            }
        } else {
            NewsletterSubscriber::create([
                'email' => $this->email,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.newsletter-subscribe-form');
    }
}
