<?php

namespace App\Mail;

use App\Models\Portal\CompanyEditSuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EditSuggestionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CompanyEditSuggestion $suggestion,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->suggestion->company->name ?? 'Unbekannt';

        return new Envelope(
            subject: "Neuer Änderungsvorschlag: {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.edit-suggestion-notification',
        );
    }
}
