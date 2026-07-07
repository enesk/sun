<?php

namespace App\Mail;

use App\Models\Portal\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReviewNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Review $review,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->review->company->name ?? 'Unbekannt';

        return new Envelope(
            subject: "Neue Bewertung: {$companyName} ({$this->review->rating} Sterne)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-review-notification',
        );
    }
}
