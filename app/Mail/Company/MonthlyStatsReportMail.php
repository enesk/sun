<?php

declare(strict_types=1);

namespace App\Mail\Company;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Monatsreport an Pro/Premium-Betriebe (#16). Bekommt nur Skalare; versendet
 * wird synchron aus SendMonthlyReportBatch heraus, der Job selbst laeuft
 * bereits ueber die Queue.
 */
class MonthlyStatsReportMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, int>  $totals
     * @param  array<string, float|null>  $changes
     * @param  array{position: int, total: int, city: string}|null  $ranking
     */
    public function __construct(
        public string $companyName,
        public ?string $recipientName,
        public string $monthLabel,
        public array $totals,
        public array $changes,
        public ?array $ranking,
        public string $dashboardUrl,
        public string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('portal.owner.statistics.report.mail.subject', ['monat' => $this->monthLabel, 'firma' => $this->companyName]),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->settingsUrl}>",
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.premium.monthly-report');
    }
}
