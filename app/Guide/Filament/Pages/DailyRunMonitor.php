<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Filament\Content\Pages\Performance;
use App\Filament\Content\Pages\Settings;
use App\Guide\Enums\RunDisplay;
use App\Guide\Filament\Resources\ReviewRunResource;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Services\ContentTenantContext;
use App\Guide\Services\RunOverviewService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\CheckedQuote;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * Heute — Tageslauf-Monitor, Startseite des Panels (#16,
 * design/guide-dashboard.md §3).
 *
 * Zahlen aus RunOverviewService (aggregiert je Portal, 60 s gecacht). Waehrend
 * des Laufzeitfensters oder solange Laeufe unterwegs sind, fragt die Seite
 * alle 15 s neu (isPolling()); ausserhalb steht sie still.
 *
 * Jeder Zaehler verweist in die Themenliste, gefiltert auf das Laufergebnis;
 * ein Zaehler eines einzelnen Portals setzt dafuer zuerst den Portalfilter
 * (openTopics()).
 */
class DailyRunMonitor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = '/';

    protected string $view = 'content.guide.daily-monitor';

    /** Mindestzahl abgeschlossener Laeufe fuer die Prognose der Fertigzeit. */
    private const FORECAST_MIN_DONE = 5;

    /** @var array<string, mixed>|null */
    private ?array $overview = null;

    public static function getNavigationLabel(): string
    {
        return __('Heute');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    /**
     * `?portal=<id>` (Alarm-Mail, #38 G1) setzt den Portalfilter und laedt
     * die Seite ohne Parameter neu, damit der Tenant-Kontext greift.
     */
    public function mount(): void
    {
        $portal = request()->query('portal');

        if (! is_string($portal) || ! ctype_digit($portal)) {
            return;
        }

        $this->context()->select((int) $portal);

        $this->redirect(static::getUrl());
    }

    public function getTitle(): string|Htmlable
    {
        return __('Heute, :date', ['date' => Carbon::now(config('guide.timezone'))->translatedFormat('j. F Y')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        return $this->overview ??= app(RunOverviewService::class)->overview($this->directory()->tenants());
    }

    /**
     * 15-s-Polling nur im Laufzeitfenster oder solange Laeufe unterwegs sind.
     */
    public function isPolling(): bool
    {
        $overview = $this->getOverview();

        return $overview['window']['phase'] === 'running'
            || $overview['totals']['counts']['in-arbeit'] > 0;
    }

    /**
     * Zustandszeile unter der Ueberschrift (§3.1 Punkt 1).
     */
    public function getStateLine(): string
    {
        $overview = $this->getOverview();
        $window = $overview['window'];
        $totals = $overview['totals'];
        $tz = config('guide.timezone');

        if (($overview['paused'] ?? null) !== null) {
            $since = Carbon::parse($overview['paused']['at'])->timezone($tz)->format('d.m., H:i');

            return filled($overview['paused']['by'])
                ? __('Tageslauf global pausiert seit :since (:name)', ['since' => $since, 'name' => $overview['paused']['by']])
                : __('Tageslauf global pausiert seit :since', ['since' => $since]);
        }

        if ($window['phase'] === 'before') {
            return __('Nächster Lauf heute ab :time', ['time' => $window['start']]);
        }

        $forecast = $this->forecastFinish();

        if ($window['phase'] === 'running') {
            return $forecast !== null
                ? __('Tageslauf läuft seit :start · voraussichtlich fertig gegen :eta', ['start' => $window['start'], 'eta' => $forecast->timezone($tz)->format('H:i')])
                : __('Tageslauf läuft seit :start · Fenster bis :end', ['start' => $window['start'], 'end' => $window['end']]);
        }

        if ($totals['counts']['in-arbeit'] > 0) {
            return $forecast !== null
                ? __('Tageslauf läuft noch · :count Läufe unterwegs · voraussichtlich fertig gegen :eta', ['count' => $totals['counts']['in-arbeit'], 'eta' => $forecast->timezone($tz)->format('H:i')])
                : __('Tageslauf läuft noch · :count Läufe unterwegs', ['count' => $totals['counts']['in-arbeit']]);
        }

        if ($totals['finished_at'] !== null) {
            return __('Tageslauf abgeschlossen um :time', [
                'time' => Carbon::parse($totals['finished_at'])->timezone(config('guide.timezone'))->format('H:i'),
            ]);
        }

        return __('Heute ist kein Lauf gestartet. Nächster Lauf morgen ab :time', ['time' => $window['start']]);
    }

    /**
     * Verweis hinter der Zustandszeile: bei globaler Pause "In den
     * Einstellungen fortsetzen" (nur Inhaber).
     *
     * @return array{url: string, label: string}|null
     */
    public function getStateLink(): ?array
    {
        if (($this->getOverview()['paused'] ?? null) === null || ! $this->canManageSettings()) {
            return null;
        }

        return ['url' => DailyRunSettings::getUrl(), 'label' => __('In den Einstellungen fortsetzen')];
    }

    /**
     * Prognose "voraussichtlich fertig gegen" (§3.1): Durchsatz seit dem
     * ersten Start heute (abgeschlossene Laeufe je Sekunde), hochgerechnet
     * auf die noch offenen und laufenden. Ohne mindestens
     * FORECAST_MIN_DONE abgeschlossene Laeufe keine Prognose. Auf 5 Minuten
     * aufgerundet; auf morgen verschobene Themen zaehlen nicht mit.
     */
    public function forecastFinish(): ?Carbon
    {
        $totals = $this->getOverview()['totals'];
        $remaining = $totals['counts']['in-arbeit'] + $totals['counts']['offen'] - $totals['deferred'];

        if ($remaining <= 0 || $totals['done'] < self::FORECAST_MIN_DONE || $totals['first_started_at'] === null) {
            return null;
        }

        $now = Carbon::now();
        $elapsed = max(1, (int) Carbon::parse($totals['first_started_at'])->diffInSeconds($now));
        $eta = $now->copy()->addSeconds((int) ceil($remaining * $elapsed / $totals['done']));

        return $eta->setTime((int) $eta->format('H'), (int) (ceil((int) $eta->format('i') / 5) * 5));
    }

    /**
     * Zeile "Ausserhalb des Plans" (§3.1 Punkt 6): Themen, die seit mehr als
     * Pruefabstand + 2 Tage nicht geprueft wurden. Keine Stoerung des
     * Tageslaufs, deshalb kein Band.
     *
     * @return array{text: string, url: string}|null
     */
    public function getOutOfPlanHint(): ?array
    {
        $overdue = (int) $this->getOverview()['totals']['overdue'];

        if ($overdue === 0) {
            return null;
        }

        return [
            'text' => trans_choice('{1} Ein Thema überfällig|[2,*] :count Themen überfällig', $overdue, ['count' => $overdue]),
            'url' => TopicResource::getUrl('index', ['filters' => ['ueberfaellig' => ['value' => '1']]]),
        ];
    }

    /**
     * Stoerungsbaender nach §3.1 Punkt 2: failed vor review, je Band ein
     * Satz mit Zahl und genau ein Verweis.
     *
     * @return list<array{tone: string, text: string, link: string|null, label: string|null}>
     */
    public function getBands(): array
    {
        $overview = $this->getOverview();
        $totals = $overview['totals'];
        $bands = [];

        if ($totals['stuck'] > 0) {
            $bands[] = [
                'tone' => 'failed',
                'text' => trans_choice('{1} Ein Lauf hängt seit über :minutes Minuten im selben Schritt.|[2,*] :count Läufe hängen seit über :minutes Minuten im selben Schritt.', $totals['stuck'], [
                    'count' => $totals['stuck'],
                    'minutes' => (int) config('guide.schedule.stuck_after_minutes', 45),
                ]),
                'link' => $this->topicsUrl(RunDisplay::IN_PROGRESS->value),
                'label' => __('Läufe ansehen'),
            ];
        }

        $budget = (float) $overview['costs']['budget'];
        $spent = (float) $overview['costs']['today'];

        if ($budget > 0 && $spent >= $budget) {
            $bands[] = [
                'tone' => 'failed',
                'text' => __('Tagesbudget erreicht.').' '.trans_choice('{0} Kein fälliges Thema wurde ausgelassen.|{1} Ein fälliges Thema wurde ausgelassen und läuft morgen zuerst.|[2,*] :count fällige Themen wurden ausgelassen und laufen morgen zuerst.', $totals['deferred'], ['count' => $totals['deferred']]),
                'link' => $this->canSeeCosts() ? Costs::getUrl() : null,
                'label' => $this->canSeeCosts() ? __('Kosten ansehen') : null,
            ];
        }

        $failed = $totals['counts']['fehlgeschlagen'];

        if ($failed > 0) {
            $bands[] = [
                'tone' => 'failed',
                'text' => trans_choice('{1} Ein Lauf ist heute fehlgeschlagen.|[2,*] :count Läufe sind heute fehlgeschlagen.', $failed, ['count' => $failed]),
                'link' => $this->topicsUrl(RunDisplay::FAILED->value),
                'label' => __('Fehlgeschlagene ansehen'),
            ];
        }

        if ($budget > 0 && $spent < $budget && $spent >= $budget * (float) config('guide.budget.warn_threshold', 0.8)) {
            $bands[] = [
                'tone' => 'review',
                'text' => __('Tagesbudget zu :percent % verbraucht.', ['percent' => (int) floor($spent / $budget * 100)]),
                'link' => $this->canSeeCosts() ? Costs::getUrl() : null,
                'label' => $this->canSeeCosts() ? __('Kosten ansehen') : null,
            ];
        }

        if ($totals['review_overdue'] > 0) {
            $bands[] = [
                'tone' => 'review',
                'text' => trans_choice('{1} Ein Lauf wartet seit über 24 Stunden auf Prüfung.|[2,*] :count Läufe warten seit über 24 Stunden auf Prüfung.', $totals['review_overdue'], ['count' => $totals['review_overdue']]),
                'link' => ReviewRunResource::getUrl(),
                'label' => __('Zur Prüfung'),
            ];
        }

        if ($totals['outlines_pending'] > 0) {
            $bands[] = [
                'tone' => 'review',
                'text' => trans_choice('{1} Eine Gliederung ist offen.|[2,*] :count Gliederungen sind offen.', $totals['outlines_pending'], ['count' => $totals['outlines_pending']]),
                'link' => ConfirmOutlines::getUrl(),
                'label' => __('Gliederungen bestätigen'),
            ];
        }

        return $bands;
    }

    /**
     * Verweis in die Themenliste mit Laufergebnis-Filter (Portalfilter wie
     * gesetzt).
     */
    public function topicsUrl(?string $lauf = null): string
    {
        return TopicResource::getUrl('index', $lauf !== null ? ['filters' => ['lauf' => ['values' => [$lauf]]]] : []);
    }

    /**
     * Zaehler eines Portals: Portalfilter setzen, dann in die Themenliste.
     */
    public function openTopics(int $tenantId, ?string $lauf = null): void
    {
        $this->context()->select($tenantId);
        $this->directory()->forget();

        $this->redirect($this->topicsUrl($lauf !== null && RunDisplay::tryFrom($lauf) !== null ? $lauf : null));
    }

    /**
     * Klick auf eine Portalzeile setzt den Portalfilter (§3.4).
     */
    public function selectPortal(int $tenantId): void
    {
        $this->context()->select($tenantId);

        $this->redirect(static::getUrl());
    }

    public function canSeeCosts(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canSeeContentCosts();
    }

    public function canManageSettings(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    public function isNetworkWide(): bool
    {
        return $this->directory()->isNetworkWide();
    }

    /**
     * Themen mit mindestens einem offenen Altartikel-Paar im Portalfilter
     * (§3.1 Punkt 5, §5.6.7). Zaehlt Themen, nicht Paare; keine Stoerung
     * des Tageslaufs, deshalb kein Band.
     *
     * @return array{text: string, url: string}|null
     */
    public function getLegacyOverlapHint(): ?array
    {
        $topics = LegacyOverlaps::openTopicCount();

        if ($topics === 0) {
            return null;
        }

        return [
            'text' => trans_choice(
                '{1} Ein Thema überschneidet sich mit Altartikeln — Entscheidung offen|[2,*] :count Themen überschneiden sich mit Altartikeln — Entscheidung offen',
                $topics,
                ['count' => $topics],
            ),
            'url' => LegacyOverlaps::getUrl(),
        ];
    }

    /**
     * Seit dem Rueckbau der alten Pipeline (#23) nur noch Leistung und
     * Portal-Einstellungen, bis #16 sie uebernimmt. Inhaber erreichen sie nur
     * von hier aus, nicht ueber die Navigation.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function getLegacyPipelineLinks(): array
    {
        if (! $this->canManageSettings()) {
            return [];
        }

        return [
            ['label' => __('Leistung'), 'url' => Performance::getUrl()],
            ['label' => __('Einstellungen'), 'url' => Settings::getUrl()],
        ];
    }

    /**
     * Beschriftung der sieben Segmente (§3.2, Legende und aria-label).
     *
     * @return array<string, string>
     */
    public static function segmentLabels(): array
    {
        return [
            'neu' => __('Neu erschienen'),
            'aktualisiert' => __('Aktualisiert'),
            'unveraendert' => __('Geprüft, unverändert'),
            'pruefung' => __('Zur Prüfung'),
            'fehlgeschlagen' => __('Fehlgeschlagen'),
            'in-arbeit' => __('In Arbeit'),
            'offen' => __('Offen'),
        ];
    }

    /**
     * Breiten der Segmente in Prozent; Segmente unter 0,5 % bekommen die
     * Mindestbreite ueber CSS (§3.2).
     *
     * @param  array<string, int>  $counts
     * @return array<string, float>
     */
    public static function segmentWidths(array $counts): array
    {
        $total = array_sum($counts);

        return array_map(fn (int $count): float => $total > 0 ? round($count / $total * 100, 2) : 0.0, $counts);
    }

    /**
     * aria-label des Balkens (§3.2).
     *
     * @param  array<string, int>  $counts
     */
    /**
     * Warnfarbe der Quote erst nach dem Laufzeitfenster (§11.2): solange der
     * Tageslauf laeuft, waere sie sonst jeden Morgen gelb.
     */
    public function quoteMayWarn(): bool
    {
        return $this->getOverview()['window']['phase'] !== 'running';
    }

    public static function barLabel(array $counts, int $checked, int $total): string
    {
        $parts = [];

        foreach (self::segmentLabels() as $key => $label) {
            $parts[] = ($counts[$key] ?? 0).' '.mb_strtolower($label);
        }

        $percent = CheckedQuote::percent($checked, $total);

        return $percent !== null
            ? __(':checked von :total fälligen Themen geprüft, :percent Prozent: :parts.', [
                'checked' => $checked,
                'total' => $total,
                'percent' => $percent,
                'parts' => implode(', ', $parts),
            ])
            : __(':checked von :total fälligen Themen geprüft: :parts.', [
                'checked' => $checked,
                'total' => $total,
                'parts' => implode(', ', $parts),
            ]);
    }

    private function directory(): TopicDirectory
    {
        return app(TopicDirectory::class);
    }

    private function context(): ContentTenantContext
    {
        return app(ContentTenantContext::class);
    }
}
