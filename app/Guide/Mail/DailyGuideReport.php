<?php

declare(strict_types=1);

namespace App\Guide\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tagesbericht des Ratgebersystems (#13) an die Inhaber (GuideOwners,
 * dieselbe Regel wie Panel und Alarm-Mail). Die Daten kommen fertig aus dem DailyReportBuilder; die Mail
 * rechnet nichts.
 */
class DailyGuideReport extends Mailable implements ShouldQueue
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
        $totals = (array) ($this->report['totals'] ?? []);

        return new Envelope(
            subject: __('Ratgeber-Tageslauf :date: :checked geprüft, :published veröffentlicht, :failed fehlgeschlagen', [
                'date' => (string) ($this->report['date'] ?? ''),
                'checked' => (int) ($totals['checked'] ?? 0),
                'published' => (int) ($totals['created'] ?? 0) + (int) ($totals['updated'] ?? 0),
                'failed' => (int) ($totals['failed'] ?? 0),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guide.daily-report',
        );
    }
}
