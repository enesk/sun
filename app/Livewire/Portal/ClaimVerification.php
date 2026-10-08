<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Support\RateLimitGuard;
use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Turnstile\Enums\TurnstileAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ClaimVerification extends Component
{
    use WithFileUploads;

    public Company $company;
    public ?ClaimRequest $claimRequest = null;

    // Upload-State
    public array $documents = [];
    public string $comment = '';
    public bool $submitted = false;

    // Status-States
    public string $state = 'upload'; // upload, pending, rejected, no_request

    public function mount(Company $company): void
    {
        $this->company = $company;

        $user = Auth::user();

        if (!$user) {
            $this->state = 'no_request';
            return;
        }

        // User besitzt bereits eine Firma — Verifizierung blockieren
        if (Company::where('user_id', $user->id)->exists()) {
            abort(403, 'Sie verwalten bereits ein Unternehmen.');
        }

        // Aktuellen Claim-Request laden
        $this->claimRequest = ClaimRequest::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$this->claimRequest) {
            $this->state = 'no_request';
            return;
        }

        if ($this->claimRequest->isPending() && $this->claimRequest->has_documents) {
            // Bereits Dokumente hochgeladen — zeige Warten-Status
            $this->state = 'pending';
        } elseif ($this->claimRequest->isRejected()) {
            $this->state = 'rejected';
        } elseif ($this->claimRequest->isApproved()) {
            // Redirect ins Dashboard — Firma ist bereits zugewiesen
            $this->redirect('/verwaltung', navigate: false);
            return;
        } else {
            $this->state = 'upload';
        }
    }

    /**
     * Dokumente hochladen und Claim-Request aktualisieren.
     */
    public function submit(): void
    {
        // Rate-Limit je IP-Hash, Nutzer und Portal (#27), Grenzen in
        // config/antispam.php. Ersetzt den handgeschriebenen Zaehler, der auf
        // der Klartext-IP und ohne Portal-Praefix lief. Kein Captcha und kein
        // Honigtopf: der Weg setzt einen angemeldeten Nutzer mit offenem
        // Antrag voraus, ein Bot kaeme hier gar nicht an.
        $wartezeit = RateLimitGuard::exceeded(RateLimitGuard::CLAIM_UPLOAD);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::CLAIM_UPLOAD, TurnstileAction::CompanyListing, Auth::user()?->email);
            $minuten = max(1, (int) ceil($wartezeit / 60));
            $this->addError('documents', trans_choice('antispam.throttled', $minuten, ['minuten' => $minuten]));
            return;
        }

        $this->validate([
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,jpg,jpeg,png',
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'documents.required' => 'Bitte laden Sie mindestens ein Dokument hoch.',
            'documents.min' => 'Bitte laden Sie mindestens ein Dokument hoch.',
            'documents.max' => 'Maximal 5 Dokumente erlaubt.',
            'documents.*.max' => 'Jede Datei darf maximal 10 MB groß sein.',
            'documents.*.mimes' => 'Erlaubte Formate: PDF, JPG, PNG.',
        ]);

        if (!$this->claimRequest) {
            $this->addError('documents', 'Kein Claim-Request gefunden.');
            return;
        }

        // Bei Resubmit: Status zurück auf pending setzen
        if ($this->claimRequest->isRejected()) {
            $this->claimRequest->resubmit($this->comment ?: null);
            // Alte Dokumente löschen
            $this->claimRequest->clearMediaCollection('claim_documents');
        }

        // Kommentar aktualisieren
        if ($this->comment) {
            $this->claimRequest->update(['comment' => $this->comment]);
        }

        // Dokumente zur Media Library hinzufügen
        foreach ($this->documents as $document) {
            $this->claimRequest
                ->addMedia($document->getRealPath())
                ->usingFileName($document->getClientOriginalName())
                ->toMediaCollection('claim_documents');
        }

        // Erst jetzt zaehlen: ein zu grosses Dokument oder ein falsches
        // Format soll keinen Versuch kosten.
        RateLimitGuard::hit(RateLimitGuard::CLAIM_UPLOAD);

        $this->submitted = true;
        $this->state = 'pending';

        $this->dispatch('toast', type: 'success', message: 'Ihre Dokumente wurden eingereicht. Wir prüfen innerhalb von 48 Stunden.');
    }

    /**
     * Dokument aus der temporären Liste entfernen.
     */
    public function removeDocument(int $index): void
    {
        if (isset($this->documents[$index])) {
            unset($this->documents[$index]);
            $this->documents = array_values($this->documents);
        }
    }

    public function render()
    {
        return view('livewire.portal.claim-verification-upload');
    }
}
