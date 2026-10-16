<?php

declare(strict_types=1);

namespace App\Ads\Jobs;

use App\Ads\Support\AdImpressionRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stuendliche Verdichtung der Impressionen (Ticket #12, docs/ad-slots.md 14).
 *
 * Nimmt aus `ad_impression_buffer` alle ABGESCHLOSSENEN Stunden, summiert sie
 * je Portal, Tag, Anker, Belegung und Geraet in `ad_impression_daily` und
 * loescht sie danach. Die laufende Stunde bleibt liegen — sonst waere der
 * Zaehler eines Portals halb verdichtet, waehrend der naechste Besucher noch
 * dieselbe Zeile hochzaehlt.
 *
 * Rohdaten gibt es nicht und entstehen auch hier nicht: der Puffer ist
 * bereits ein Stundenaggregat, nach dem Lauf ist er leer.
 *
 * Derselbe Lauf raeumt alte Tageszeilen ab
 * (`ad-slots.impressions.retention_days`). Das kostet ausserhalb des
 * Stichtags nichts und erspart einen zweiten Eintrag im Scheduler.
 *
 * ShouldBeUnique, damit zwei Worker nicht dieselbe Stunde verdichten: der
 * Upsert zaehlt hoch, ein doppelter Lauf wuerde die Zahlen verdoppeln. Die
 * Auswahl laeuft deshalb zusaetzlich in einer Transaktion mit
 * `lockForUpdate()` auf den Pufferzeilen.
 */
class AggregateAdImpressions implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /** Eine Runde je Durchgang; mehr Zeilen holt der naechste Durchlauf. */
    private const CHUNK = 500;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'ads-impressions-aggregate';
    }

    /**
     * @return array{rows: int, impressions: int, pruned: int}
     */
    public function handle(): array
    {
        if (! AdImpressionRecorder::enabled()) {
            return ['rows' => 0, 'impressions' => 0, 'pruned' => 0];
        }

        $cutoff = Carbon::now()->startOfHour();
        $rows = 0;
        $impressions = 0;

        while (true) {
            $runde = $this->roll($cutoff);

            $rows += $runde['rows'];
            $impressions += $runde['impressions'];

            if ($runde['rows'] < self::CHUNK) {
                break;
            }
        }

        return ['rows' => $rows, 'impressions' => $impressions, 'pruned' => $this->prune()];
    }

    /**
     * Eine Runde: hoechstens CHUNK Aggregatzeilen aus dem Puffer ins
     * Tagesaggregat, dann die verarbeiteten Pufferzeilen loeschen.
     *
     * @return array{rows: int, impressions: int}
     */
    private function roll(Carbon $cutoff): array
    {
        return DB::connection($this->connection())->transaction(function () use ($cutoff): array {
            /** @var list<object> $gruppen */
            $gruppen = $this->buffer()
                ->where('hour', '<', $cutoff->toDateTimeString())
                ->selectRaw('tenant_id, date(hour) as day, slot_key, placement_id, device, sum(impressions) as impressions, max(id) as max_id')
                ->groupBy('tenant_id', 'day', 'slot_key', 'placement_id', 'device')
                ->orderBy('max_id')
                ->limit(self::CHUNK)
                ->lockForUpdate()
                ->get()
                ->all();

            if ($gruppen === []) {
                return ['rows' => 0, 'impressions' => 0];
            }

            $impressions = 0;
            $bindings = [];

            foreach ($gruppen as $gruppe) {
                $summe = (int) $gruppe->impressions;
                $impressions += $summe;

                $bindings[] = (int) $gruppe->tenant_id;
                $bindings[] = (string) $gruppe->day;
                $bindings[] = (string) $gruppe->slot_key;
                $bindings[] = (int) $gruppe->placement_id;
                $bindings[] = (string) $gruppe->device;
                $bindings[] = $summe;
                $bindings[] = Carbon::now()->toDateTimeString();
                $bindings[] = Carbon::now()->toDateTimeString();
            }

            // Hochzaehlen statt setzen: eine Stunde kann schon verdichtet
            // sein, wenn der Job oefter als einmal je Stunde laeuft.
            // `upsert()` koennte nur setzen und wuerde fruehere Stunden
            // desselben Tages ueberschreiben.
            DB::connection($this->connection())->statement(
                'insert into ad_impression_daily (tenant_id, day, slot_key, placement_id, device, impressions, created_at, updated_at)'
                .' values '.implode(', ', array_fill(0, count($gruppen), '(?, ?, ?, ?, ?, ?, ?, ?)'))
                .' on duplicate key update impressions = impressions + values(impressions), updated_at = values(updated_at)',
                $bindings
            );

            // Je Gruppe genau die Zeilen, die eben eingegangen sind: `id <=
            // max_id`. Neue Zeilen koennen hier nicht dazwischenkommen — der
            // Beacon schreibt immer in die LAUFENDE Stunde, also nie unter
            // den Schnitt.
            foreach ($gruppen as $gruppe) {
                $this->buffer()
                    ->where('tenant_id', (int) $gruppe->tenant_id)
                    ->where('slot_key', (string) $gruppe->slot_key)
                    ->where('placement_id', (int) $gruppe->placement_id)
                    ->where('device', (string) $gruppe->device)
                    ->where('hour', '<', $cutoff->toDateTimeString())
                    ->where('id', '<=', (int) $gruppe->max_id)
                    ->delete();
            }

            return ['rows' => count($gruppen), 'impressions' => $impressions];
        });
    }

    /**
     * Tageszeilen jenseits der Aufbewahrung loeschen. Aggregate ohne
     * Personenbezug, darum grosszuegig (Vorgabe 400 Tage = ein
     * Jahresvergleich plus Puffer).
     */
    private function prune(): int
    {
        $tage = (int) config('ad-slots.impressions.retention_days', 400);

        if ($tage <= 0) {
            return 0;
        }

        return DB::connection($this->connection())
            ->table('ad_impression_daily')
            ->where('day', '<', Carbon::now()->subDays($tage)->toDateString())
            ->delete();
    }

    private function buffer(): Builder
    {
        return DB::connection($this->connection())->table(AdImpressionRecorder::TABLE);
    }

    private function connection(): string
    {
        return AdImpressionRecorder::connection();
    }
}
