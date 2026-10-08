<?php

declare(strict_types=1);

namespace App\Turnstile\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tagesbericht des Bot-Schutzes (#12) an die Betreiber
 * ({@see \App\Turnstile\Support\BotProtectionRecipients}).
 *
 * Die Daten kommen fertig aus dem {@see \App\Turnstile\Monitoring\DailyReport};
 * die Mail rechnet nichts.
 */
class BotProtectionDailyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        public array $report,
    ) {}

    public function envelope(): Envelope
    {
        $summen = (array) ($this->report['totals'] ?? []);

        return new Envelope(
            subject: __('Bot-Schutz :date: :registrations Registrierungen, :listings Einträge, :blocked blockiert', [
                'date' => (string) ($this->report['date_label'] ?? $this->report['date'] ?? ''),
                'registrations' => (int) ($summen['registrations'] ?? 0),
                'listings' => (int) ($summen['listings'] ?? 0),
                'blocked' => (int) ($summen['blocked'] ?? 0),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.turnstile.daily-report',
        );
    }
}
