<?php

declare(strict_types=1);

namespace App\Content\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tagesbericht der Content-Pipeline (#22) an die Administratoren. Die Daten
 * kommen fertig aus dem DailyReportBuilder; die Mail rechnet nichts.
 */
class DailyContentReport extends Mailable implements ShouldQueue
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
        $totals = $this->report['totals'] ?? ['published' => 0, 'target' => 0];
        $date = (string) ($this->report['date'] ?? '');

        return new Envelope(
            subject: __('Ratgeber-Tagesbericht :date: :published von :target Artikeln', [
                'date' => $date,
                'published' => $totals['published'] ?? 0,
                'target' => $totals['target'] ?? 0,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.content.daily-report',
        );
    }
}
