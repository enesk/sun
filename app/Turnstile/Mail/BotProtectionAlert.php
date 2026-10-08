<?php

declare(strict_types=1);

namespace App\Turnstile\Mail;

use App\Turnstile\Monitoring\VerificationAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alarm des Bot-Schutzes (#12) an die Betreiber
 * ({@see \App\Turnstile\Support\BotProtectionRecipients}).
 *
 * Hoechstens eine Mail je Portal, Alarmtyp und Stunde — entdoppelt wird im
 * VerificationMonitor, nicht hier. Die Mail rechnet nichts; alle Zahlen und
 * Texte kommen fertig im Alarm an.
 */
class BotProtectionAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public VerificationAlert $alert,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Bot-Schutz :portal: :headline', [
                'portal' => $this->alert->tenantName,
                'headline' => $this->alert->headline,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.turnstile.alert',
        );
    }
}
