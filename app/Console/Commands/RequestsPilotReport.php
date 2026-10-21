<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Auswertung des Pilots (SUN-RQ #12).
 *
 *   php artisan requests:pilot-report --since=2026-10-27 --until=2026-11-03
 *   php artisan requests:pilot-report --since=2026-10-27 --tenant=4 --tenant=7
 *   php artisan requests:pilot-report --since=2026-10-27 --json > docs/messungen/...
 *
 * Liefert genau die vier Zahlen, die das Abnahmekriterium verlangt:
 *
 *  * **Anzahl** Anfragen im Zeitraum, je Portal, Typ und Quelle.
 *  * **Anteil verifiziert** — und auf welchem Weg (Domain-Match, Magic-Link,
 *    Handpruefung).
 *  * **Anteil automatisch erledigt** — `applied_at` gesetzt und
 *    `applied_by` leer heisst: eine Auto-Regel (#9) hat es getan, kein
 *    Mensch.
 *  * **Fehlzuordnungen der KI** — Vorgaenge, bei denen der Vorschlag einen
 *    Betrieb uebernommen hat (`company_id_uebernommen` im Ereignis
 *    `ai_suggested`) und danach ein Mensch im Panel einen anderen Betrieb
 *    zugeordnet hat. Das ist die Zahl mit dem Ziel unter 5 %.
 *
 * Dazu die **Bearbeitungszeit** je Anfrage (Eingang bis Abschluss) als
 * Median und Mittelwert, getrennt nach „automatisch" und „von Hand" — der
 * Vorher-Nachher-Vergleich des Vorhabens. Der Vorher-Wert steht in
 * docs/profile-requests.md 2.4 und wird hier nicht neu gemessen; er stammt
 * aus einem Zeitraum, in dem es diese Tabellen noch nicht gab.
 *
 * Reine Leseabfragen auf der Central-DB. Der Befehl aendert nichts.
 */
class RequestsPilotReport extends Command
{
    protected $signature = 'requests:pilot-report
        {--since= : Beginn des Zeitraums (Standard: 7 Tage zurueck)}
        {--until= : Ende des Zeitraums (Standard: jetzt)}
        {--tenant=* : Nur diese Portale (tenants.id), mehrfach angebbar}
        {--json : Ausgabe als JSON statt als Tabellen}';

    protected $description = 'Wertet den Anfragen-Pilot aus: Anzahl, Verifikationsquote, Automatikquote, KI-Fehlzuordnungen, Bearbeitungszeit';

    public function handle(TenantCompanyResolver $resolver): int
    {
        $since = Carbon::parse((string) ($this->option('since') ?: now()->subDays(7)->toDateString()))->startOfDay();
        $until = Carbon::parse((string) ($this->option('until') ?: now()->toDateTimeString()));

        $tenants = array_values(array_filter(array_map('intval', (array) $this->option('tenant'))));

        $requests = ProfileRequest::query()
            ->whereBetween('created_at', [$since, $until])
            ->when($tenants !== [], fn ($query) => $query->whereIn('tenant_id', $tenants))
            ->orderBy('id')
            ->get();

        $bericht = [
            'zeitraum' => ['von' => $since->toIso8601String(), 'bis' => $until->toIso8601String()],
            'portale' => $tenants === [] ? 'alle' : $tenants,
            'anzahl' => $requests->count(),
            'je_portal' => $this->jePortal($requests, $resolver),
            'je_typ' => $this->verteilung($requests, fn (ProfileRequest $r): string => $r->type instanceof RequestType ? $r->type->value : (string) $r->type),
            'je_quelle' => $this->verteilung($requests, fn (ProfileRequest $r): string => $r->source instanceof RequestSource ? $r->source->value : (string) $r->source),
            'je_status' => $this->verteilung($requests, fn (ProfileRequest $r): string => $r->status instanceof RequestStatus ? $r->status->value : (string) $r->status),
            'verifiziert' => $this->verifiziert($requests),
            'automatik' => $this->automatik($requests),
            'ki' => $this->kiZuordnung($requests),
            'bearbeitungszeit_stunden' => $this->bearbeitungszeit($requests),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($bericht, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->ausgeben($bericht);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ProfileRequest>  $requests
     * @return array<string, int>
     */
    private function jePortal(Collection $requests, TenantCompanyResolver $resolver): array
    {
        $zeilen = [];

        foreach ($requests->groupBy('tenant_id') as $tenantId => $gruppe) {
            $name = $tenantId === '' || $tenantId === null
                ? 'ohne Portal'
                : (string) ($resolver->tenant((int) $tenantId)?->domain ?? "tenant {$tenantId}");

            $zeilen[$name] = $gruppe->count();
        }

        arsort($zeilen);

        return $zeilen;
    }

    /**
     * @param  Collection<int, ProfileRequest>  $requests
     * @param  callable(ProfileRequest): string  $schluessel
     * @return array<string, int>
     */
    private function verteilung(Collection $requests, callable $schluessel): array
    {
        $zeilen = $requests->groupBy($schluessel)->map->count()->all();

        arsort($zeilen);

        /** @var array<string, int> $zeilen */
        return $zeilen;
    }

    /**
     * Verifikationsquote und die Wege dorthin.
     *
     * @param  Collection<int, ProfileRequest>  $requests
     * @return array<string, mixed>
     */
    private function verifiziert(Collection $requests): array
    {
        $verifiziert = $requests->filter(fn (ProfileRequest $r): bool => $r->verified_at !== null);

        return [
            'anzahl' => $verifiziert->count(),
            'anteil_prozent' => $this->anteil($verifiziert->count(), $requests->count()),
            'wege' => $verifiziert
                ->groupBy(fn (ProfileRequest $r): string => $r->verification_method?->value ?? 'unbekannt')
                ->map->count()
                ->all(),
        ];
    }

    /**
     * Automatisch erledigt heisst: `applied_at` steht, `applied_by` nicht —
     * so protokolliert die Auto-Regel (#9) ihre Arbeit.
     *
     * @param  Collection<int, ProfileRequest>  $requests
     * @return array<string, mixed>
     */
    private function automatik(Collection $requests): array
    {
        $erledigt = $requests->filter(fn (ProfileRequest $r): bool => $r->applied_at !== null);
        $automatisch = $erledigt->filter(fn (ProfileRequest $r): bool => $r->applied_by === null);

        return [
            'erledigt' => $erledigt->count(),
            'automatisch' => $automatisch->count(),
            'von_hand' => $erledigt->count() - $automatisch->count(),
            'anteil_automatisch_an_allen_prozent' => $this->anteil($automatisch->count(), $requests->count()),
            'anteil_automatisch_an_erledigten_prozent' => $this->anteil($automatisch->count(), $erledigt->count()),
        ];
    }

    /**
     * Fehlzuordnungen der KI.
     *
     * Gezaehlt wird gegen die Ereignisse: der Vorschlag hat einen Betrieb
     * uebernommen (`ai_suggested` mit `company_id_uebernommen = true`), und
     * danach hat ein Mensch zugeordnet (Notiz „Betrieb zugeordnet", Akteur
     * `user`). Genau diese Faelle gehoeren als Beispiele in den
     * Klassifikations-Prompt (SUN-RQ-005).
     *
     * @param  Collection<int, ProfileRequest>  $requests
     * @return array<string, mixed>
     */
    private function kiZuordnung(Collection $requests): array
    {
        $ids = $requests
            ->filter(fn (ProfileRequest $r): bool => ($r->source instanceof RequestSource ? $r->source : null) === RequestSource::EMAIL)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return ['mail_anfragen' => 0, 'mit_vorschlag' => 0, 'zuordnung_uebernommen' => 0, 'korrigiert' => 0, 'fehlerquote_prozent' => null, 'korrigierte_ids' => []];
        }

        $ereignisse = RequestEvent::query()
            ->whereIn('request_id', $ids)
            ->whereIn('event', [RequestEventType::AI_SUGGESTED->value, RequestEventType::NOTE->value])
            ->orderBy('id')
            ->get();

        $mitVorschlag = [];
        $uebernommen = [];
        $korrigiert = [];

        foreach ($ereignisse as $ereignis) {
            $payload = (array) ($ereignis->payload ?? []);
            $requestId = (int) $ereignis->request_id;

            if ($ereignis->event === RequestEventType::AI_SUGGESTED || $ereignis->event === RequestEventType::AI_SUGGESTED->value) {
                if (($payload['fallback'] ?? false) !== true) {
                    $mitVorschlag[$requestId] = true;
                }

                if (($payload['company_id_uebernommen'] ?? false) === true) {
                    $uebernommen[$requestId] = true;
                }

                continue;
            }

            if (($payload['note'] ?? '') === 'Betrieb zugeordnet' && $ereignis->actor_type === RequestEvent::ACTOR_USER
                && isset($uebernommen[$requestId])) {
                $korrigiert[$requestId] = true;
            }
        }

        return [
            'mail_anfragen' => count($ids),
            'mit_vorschlag' => count($mitVorschlag),
            'zuordnung_uebernommen' => count($uebernommen),
            'korrigiert' => count($korrigiert),
            'fehlerquote_prozent' => $this->anteil(count($korrigiert), count($uebernommen)),
            'korrigierte_ids' => array_keys($korrigiert),
        ];
    }

    /**
     * Bearbeitungszeit in Stunden: Eingang bis Abschluss. Offene Vorgaenge
     * zaehlen nicht mit — sie haetten keine Zahl, und ein Median ueber
     * „noch offen" ist keine Messung.
     *
     * @param  Collection<int, ProfileRequest>  $requests
     * @return array<string, mixed>
     */
    private function bearbeitungszeit(Collection $requests): array
    {
        $alle = [];
        $automatisch = [];
        $vonHand = [];

        foreach ($requests as $request) {
            $ende = $request->applied_at ?? $this->abschluss($request);

            if ($ende === null) {
                continue;
            }

            $stunden = round($request->created_at->diffInMinutes($ende) / 60, 2);

            $alle[] = $stunden;

            if ($request->applied_at !== null && $request->applied_by === null) {
                $automatisch[] = $stunden;

                continue;
            }

            $vonHand[] = $stunden;
        }

        return [
            'abgeschlossen' => count($alle),
            'median' => $this->median($alle),
            'mittel' => $this->mittel($alle),
            'median_automatisch' => $this->median($automatisch),
            'median_von_hand' => $this->median($vonHand),
        ];
    }

    /** Abschlusszeitpunkt eines Vorgangs, der nicht uebernommen wurde. */
    private function abschluss(ProfileRequest $request): ?Carbon
    {
        $status = $request->status instanceof RequestStatus ? $request->status : null;

        if ($status === null || $status->isOpen()) {
            return null;
        }

        $zeitpunkt = RequestEvent::query()
            ->where('request_id', $request->id)
            ->whereIn('event', [
                RequestEventType::REJECTED->value,
                RequestEventType::CANCELLED->value,
                RequestEventType::EXPIRED->value,
            ])
            ->orderByDesc('id')
            ->value('created_at');

        return $zeitpunkt === null ? null : Carbon::parse($zeitpunkt);
    }

    /** @param array<string, mixed> $bericht */
    private function ausgeben(array $bericht): void
    {
        $this->info(sprintf(
            'Pilot-Auswertung %s bis %s — %d Anfragen',
            Carbon::parse($bericht['zeitraum']['von'])->format('d.m.Y'),
            Carbon::parse($bericht['zeitraum']['bis'])->format('d.m.Y H:i'),
            $bericht['anzahl'],
        ));

        foreach (['je_portal' => 'Portal', 'je_typ' => 'Typ', 'je_quelle' => 'Quelle', 'je_status' => 'Status'] as $schluessel => $titel) {
            if ($bericht[$schluessel] === []) {
                continue;
            }

            $this->newLine();
            $this->table([$titel, 'Anfragen'], collect($bericht[$schluessel])->map(fn ($anzahl, $name): array => [$name, $anzahl])->values()->all());
        }

        $this->newLine();
        $this->table(['Kennzahl', 'Wert'], [
            ['verifiziert', sprintf('%d (%s %%)', $bericht['verifiziert']['anzahl'], $bericht['verifiziert']['anteil_prozent'] ?? '—')],
            ['Wege', json_encode($bericht['verifiziert']['wege'], JSON_UNESCAPED_UNICODE)],
            ['erledigt', $bericht['automatik']['erledigt']],
            ['davon automatisch', sprintf('%d (%s %% aller, %s %% der erledigten)',
                $bericht['automatik']['automatisch'],
                $bericht['automatik']['anteil_automatisch_an_allen_prozent'] ?? '—',
                $bericht['automatik']['anteil_automatisch_an_erledigten_prozent'] ?? '—',
            )],
            ['KI-Zuordnung übernommen', $bericht['ki']['zuordnung_uebernommen']],
            ['davon korrigiert', sprintf('%d (%s %%) — Ziel < 5 %%', $bericht['ki']['korrigiert'], $bericht['ki']['fehlerquote_prozent'] ?? '—')],
            ['korrigierte Vorgänge', implode(', ', $bericht['ki']['korrigierte_ids']) ?: '—'],
            ['Bearbeitungszeit Median (h)', $bericht['bearbeitungszeit_stunden']['median'] ?? '—'],
            ['Median automatisch (h)', $bericht['bearbeitungszeit_stunden']['median_automatisch'] ?? '—'],
            ['Median von Hand (h)', $bericht['bearbeitungszeit_stunden']['median_von_hand'] ?? '—'],
        ]);
    }

    private function anteil(int $teil, int $ganz): ?float
    {
        return $ganz === 0 ? null : round($teil / $ganz * 100, 1);
    }

    /** @param list<float> $werte */
    private function median(array $werte): ?float
    {
        if ($werte === []) {
            return null;
        }

        sort($werte);
        $mitte = intdiv(count($werte), 2);

        return count($werte) % 2 === 1
            ? round($werte[$mitte], 2)
            : round(($werte[$mitte - 1] + $werte[$mitte]) / 2, 2);
    }

    /** @param list<float> $werte */
    private function mittel(array $werte): ?float
    {
        return $werte === [] ? null : round(array_sum($werte) / count($werte), 2);
    }
}
