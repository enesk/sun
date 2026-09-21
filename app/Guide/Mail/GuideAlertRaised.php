<?php

declare(strict_types=1);

namespace App\Guide\Mail;

use App\Guide\Filament\Pages\DailyRunMonitor;
use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Models\Central\GuideAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Sofortmeldung eines kritischen Guide-Alarms (#38 G1,
 * design/guide-dashboard.md §11.3) an die Inhaber (GuideOwners). Hoechstens
 * eine Mail je Alarmcode, Portal und Tag (GuideAlert::notifyOwners());
 * weitere Vorkommen stehen im Tagesbericht.
 */
class GuideAlertRaised extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public GuideAlert $alert,
        public ?string $portal = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Ratgeber-Alarm: :portal — :title', [
                'portal' => $this->portal ?? __('alle Portale'),
                'title' => $this->title(),
            ]),
        );
    }

    public function content(): Content
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $since = $this->alert->created_at ?? $this->alert->last_seen_at ?? Carbon::now();

        return new Content(
            view: 'emails.guide.alert',
            with: [
                'title' => $this->title(),
                'since' => Carbon::parse($since)->timezone($timezone)->format('H:i'),
                'consequence' => $this->consequence(),
                'url' => DailyRunMonitor::getUrl(
                    $this->alert->tenant_id !== null ? ['portal' => (int) $this->alert->tenant_id] : [],
                    panel: (string) config('content.panel.id', 'content'),
                ),
            ],
        );
    }

    /**
     * Kurztext fuer Betreff und Ueberschrift.
     */
    public function title(): string
    {
        return match ($this->alert->key) {
            GuideAlert::KEY_PROVIDER_DOWN => __('Modellanbieter nicht erreichbar'),
            GuideAlert::KEY_BUDGET_EXCEEDED => __('Tagesbudget erreicht'),
            GuideAlert::KEY_FAILURE_RATE => __('Fehlerquote über :percent %', [
                'percent' => (int) round((float) config('guide.orchestrator.failure_alert_ratio', 0.10) * 100),
            ]),
            GuideAlert::KEY_RUN_STUCK => __('Lauf hängt'),
            default => (string) $this->alert->key,
        };
    }

    /**
     * "Was jetzt passiert" ohne Eingriff — beschreibt das Verhalten von
     * HandlesGuideRun, RunWatchdog und Topic::recordFailure().
     */
    private function consequence(): string
    {
        $online = __('Veröffentlichte Artikel bleiben online.');

        return match ($this->alert->key) {
            GuideAlert::KEY_PROVIDER_DOWN => __('Betroffene Läufe werden angehalten und mit wachsendem Abstand (höchstens alle :minutes Minuten) erneut versucht, längstens 26 Stunden.', [
                'minutes' => (int) ceil((int) config('guide.concurrency.rate_limit_backoff.max_seconds', 1800) / 60),
            ]).' '.$online,
            GuideAlert::KEY_BUDGET_EXCEEDED => (($this->alert->context_json['scope'] ?? null) === BudgetExceededException::SCOPE_RUN
                ? __('Der Lauf endet als fehlgeschlagen, das Thema wird morgen erneut versucht.')
                : __('Recherchen werden zurückgestellt und stündlich erneut versucht, bis wieder Budget frei ist (spätestens nach dem Tageswechsel um 00:00). Begonnene Schreibschritte enden als fehlgeschlagen, das Thema wird morgen erneut versucht.')).' '.$online,
            GuideAlert::KEY_FAILURE_RATE => __('Der Tageslauf läuft weiter. Fehlgeschlagene Themen werden morgen erneut versucht und nach mehreren Fehlschlägen in Folge pausiert.').' '.$online,
            GuideAlert::KEY_RUN_STUCK => __('Der Lauf wurde nach :restarts Neustarts als fehlgeschlagen beendet, das Thema wird morgen erneut versucht.', [
                'restarts' => (int) config('guide.orchestrator.watchdog.max_restarts', 2),
            ]).' '.$online,
            default => $online,
        };
    }
}
