<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Zeitfenster und Slots der Veroeffentlichung (#21).
 *
 * Ein Mandant veroeffentlicht `articles_per_day` Artikel innerhalb seines
 * Fensters (Vorgabe 07:00-19:00). Die Slots entstehen nicht zufaellig pro
 * Aufruf, sondern deterministisch aus Mandant und Datum: derselbe Tag liefert
 * immer dieselben Zeiten, ein zweiter Lauf verschiebt also nichts. Weil der
 * Tenant-Schluessel in den Startwert eingeht, liegen die Slots zweier
 * Mandanten am selben Tag verschieden — 20 Portale veroeffentlichen nicht auf
 * dieselbe Minute.
 *
 * Aufbau eines Tages: das Fenster wird in so viele gleich grosse Abschnitte
 * geteilt, wie Artikel geplant sind; in jedem Abschnitt liegt genau ein Slot
 * an einer festen, aber je Mandant unterschiedlichen Stelle. Danach wird der
 * Mindestabstand (Vorgabe 4 h) durchgesetzt und der letzte Slot so weit vom
 * Fensterende weggehalten, dass die Reserve-Pruefung noch greifen kann.
 *
 * Alle Methoden, die Entwuerfe lesen, laufen im Tenant-Kontext.
 */
class PublishScheduler
{
    /**
     * Slots eines Tages, aufsteigend.
     *
     * @return array<int, CarbonImmutable>
     */
    public function slotsFor(Tenant $tenant, ?\DateTimeInterface $date = null, ?TenantContentSetting $settings = null): array
    {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();
        $settings ??= TenantContentSetting::current();

        $count = max(1, (int) ($settings->articles_per_day ?: config('content.targets.articles_per_tenant_per_day', 2)));
        [$start, $end] = $this->window($day, $settings);

        $lead = max(0, (int) config('content.publishing.reserve_lead_minutes', 30));
        $latest = $end->subMinutes($lead);

        if ($latest->lessThanOrEqualTo($start)) {
            $latest = $end;
        }

        $span = max(1, (int) $start->diffInMinutes($latest));
        $segment = (int) floor($span / $count);
        $offsets = $this->offsets($tenant, $day, $count, max(1, $segment));

        $slots = [];
        $previous = null;
        $gap = $this->gap($span, $count);

        foreach ($offsets as $index => $offset) {
            $minute = $index * $segment + $offset;
            $slot = $start->addMinutes(min($minute, $span));

            // Mindestabstand zum vorherigen Slot. Der letzte Slot bleibt in
            // jedem Fall innerhalb des Fensters.
            if ($previous !== null && $slot->lessThan($previous->addMinutes($gap))) {
                $slot = $previous->addMinutes($gap);
            }

            if ($slot->greaterThan($latest)) {
                $slot = $latest;
            }

            $slots[] = $slot;
            $previous = $slot;
        }

        return $slots;
    }

    /**
     * Der naechste freie Slot ab jetzt — die Zeit, auf die ein freigegebener
     * Entwurf eingeplant wird.
     *
     * Belegt ist ein Slot, wenn zu dieser Minute bereits ein anderer Entwurf
     * eingeplant oder veroeffentlicht ist. Sind alle Slots des Tages vergeben
     * oder ist das Tageskontingent erschoepft, wandert der Entwurf auf den
     * ersten Slot des naechsten Tages.
     */
    public function nextFreeSlot(Tenant $tenant, ArticleDraft $draft, ?\DateTimeInterface $from = null): CarbonImmutable
    {
        $now = CarbonImmutable::parse($from ?? now());
        $settings = TenantContentSetting::current();

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->addDays($offset)->startOfDay();

            if ($this->remainingToday($tenant, $day, $draft, $settings) <= 0) {
                continue;
            }

            foreach ($this->freeSlots($tenant, $day, $draft, $settings) as $slot) {
                if ($slot->greaterThanOrEqualTo($now)) {
                    return $slot;
                }
            }
        }

        // Sollte nicht vorkommen; lieber eine Woche spaeter als gar nicht.
        return $this->slotsFor($tenant, $now->addDays(7), $settings)[0];
    }

    /**
     * Slots des Tages, die kein anderer Entwurf belegt.
     *
     * @return array<int, CarbonImmutable>
     */
    public function freeSlots(Tenant $tenant, ?\DateTimeInterface $date = null, ?ArticleDraft $except = null, ?TenantContentSetting $settings = null): array
    {
        $settings ??= TenantContentSetting::current();
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();
        $taken = $this->takenSlots($day, $except);

        return array_values(array_filter(
            $this->slotsFor($tenant, $day, $settings),
            static fn (CarbonImmutable $slot): bool => ! in_array($slot->format('Y-m-d H:i'), $taken, true),
        ));
    }

    /**
     * Wie viele Artikel der Mandant heute noch veroeffentlichen darf.
     * Gezaehlt wird alles, was fuer den Tag eingeplant oder bereits
     * veroeffentlicht ist — auch von Hand verschobene Entwuerfe.
     */
    public function remainingToday(Tenant $tenant, ?\DateTimeInterface $date = null, ?ArticleDraft $except = null, ?TenantContentSetting $settings = null): int
    {
        $settings ??= TenantContentSetting::current();
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();

        $limit = max(1, (int) ($settings->articles_per_day ?: config('content.targets.articles_per_tenant_per_day', 2)));

        return max(0, $limit - $this->plannedCount($day, $except));
    }

    /**
     * Ist die Reserve-Frist erreicht, ohne dass das Tagesziel gedeckt ist?
     * Genau dann laesst content:publish:due einen Reserve-Kandidaten
     * generieren.
     */
    public function needsReserve(Tenant $tenant, ?\DateTimeInterface $now = null, ?TenantContentSetting $settings = null): bool
    {
        $settings ??= TenantContentSetting::current();
        $moment = CarbonImmutable::parse($now ?? now());
        $day = $moment->startOfDay();

        if ($this->remainingToday($tenant, $day, null, $settings) <= 0) {
            return false;
        }

        [, $end] = $this->window($day, $settings);
        $lead = max(0, (int) config('content.publishing.reserve_lead_minutes', 30));

        return $moment->greaterThanOrEqualTo($end->subMinutes($lead)) && $moment->lessThan($end);
    }

    /**
     * Fenster des Tages. Der Mandant sticht die Konfiguration; unbrauchbare
     * Angaben (Ende vor Anfang) fallen auf die Vorgabe zurueck.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(CarbonImmutable $day, ?TenantContentSetting $settings = null): array
    {
        $settings ??= TenantContentSetting::current();

        $start = $this->time($day, (string) $settings->publish_window_start, (string) config('content.publishing.window.start', '07:00'));
        $end = $this->time($day, (string) $settings->publish_window_end, (string) config('content.publishing.window.end', '19:00'));

        if ($end->lessThanOrEqualTo($start)) {
            $start = $this->time($day, '', (string) config('content.publishing.window.start', '07:00'));
            $end = $this->time($day, '', (string) config('content.publishing.window.end', '19:00'));
        }

        return [$start, $end];
    }

    /*
    |--------------------------------------------------------------------------
    | Innenleben
    |--------------------------------------------------------------------------
    */

    private function time(CarbonImmutable $day, string $value, string $fallback): CarbonImmutable
    {
        foreach ([trim($value), trim($fallback)] as $candidate) {
            if (preg_match('/^(\d{1,2}):(\d{2})/', $candidate, $matches) === 1) {
                return $day->setTime(min(23, (int) $matches[1]), min(59, (int) $matches[2]));
            }
        }

        return $day->setTime(7, 0);
    }

    /**
     * Mindestabstand zweier Slots. Passt der konfigurierte Abstand nicht in
     * das Fenster, gilt die groesstmoegliche gleichmaessige Verteilung — ein
     * kurzes Fenster soll nicht dazu fuehren, dass Slots aufeinanderfallen.
     */
    private function gap(int $span, int $count): int
    {
        $configured = max(0, (int) config('content.publishing.min_gap_minutes', 240));

        if ($count < 2) {
            return 0;
        }

        return min($configured, (int) floor($span / ($count - 1)));
    }

    /**
     * Feste Verschiebung je Slot innerhalb seines Abschnitts. Der Startwert
     * haengt an Mandant und Datum: gleiche Eingabe, gleiche Ausgabe — aber
     * zwei Mandanten treffen sich nicht.
     *
     * @return array<int, int>
     */
    private function offsets(Tenant $tenant, CarbonImmutable $day, int $count, int $segment): array
    {
        $seed = crc32((string) $tenant->getTenantKey().'|'.$day->toDateString());
        $offsets = [];

        for ($index = 0; $index < $count; $index++) {
            // Ein billiger, aber gut streuender Folgewert je Slot; kein
            // mt_srand, das wuerde den globalen Zufallsgenerator umstellen.
            $value = crc32($seed.'|'.$index);
            $offsets[] = $segment <= 1 ? 0 : (int) ($value % $segment);
        }

        return $offsets;
    }

    /**
     * Belegte Slotzeiten des Tages (Minutengenauigkeit).
     *
     * @return array<int, string>
     */
    private function takenSlots(CarbonImmutable $day, ?ArticleDraft $except): array
    {
        return ArticleDraft::query()
            ->notWithdrawn()
            ->whereIn('status', [DraftStatus::SCHEDULED->value, DraftStatus::PUBLISHED->value])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->whereRaw('COALESCE(published_at, scheduled_for) BETWEEN ? AND ?', [$day->startOfDay(), $day->endOfDay()])
            ->get(['id', 'scheduled_for', 'published_at'])
            ->map(fn (ArticleDraft $draft): ?string => ($draft->published_at ?? $draft->scheduled_for)?->format('Y-m-d H:i'))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Fuer den Tag eingeplante oder veroeffentlichte Entwuerfe.
     */
    private function plannedCount(CarbonImmutable $day, ?ArticleDraft $except): int
    {
        return ArticleDraft::query()
            ->notWithdrawn()
            ->whereIn('status', [DraftStatus::SCHEDULED->value, DraftStatus::PUBLISHED->value])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->whereRaw('COALESCE(published_at, scheduled_for) BETWEEN ? AND ?', [$day->startOfDay(), $day->endOfDay()])
            ->count();
    }

    /**
     * Bequemer Zugriff fuer Aufrufer, die mit Carbon statt CarbonImmutable
     * weiterarbeiten (Queue-Delay, Konsolenausgabe).
     */
    public static function toCarbon(CarbonImmutable $moment): Carbon
    {
        return Carbon::instance($moment->toDateTime());
    }
}
