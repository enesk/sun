<?php

declare(strict_types=1);

namespace App\Guide\Mail;

use App\Guide\Models\Central\ContentAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sofortmeldung eines kritischen Alarms der Content-Pipeline (#22) an die
 * Administratoren. Der Tagesbericht um 20:00 fuehrt dieselben Alarme noch
 * einmal gesammelt auf; diese Mail geht direkt raus,
 * damit ein gerissener Slot nicht bis zum Abend unbemerkt bleibt.
 */
class ContentAlertRaised extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContentAlert $alert,
        public ?string $portal = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Ratgeber-Alarm: :message', ['message' => (string) $this->alert->message]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.content.alert',
        );
    }
}
