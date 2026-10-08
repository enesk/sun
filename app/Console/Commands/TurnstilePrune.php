<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Turnstile\Support\TurnstileLogConnection;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raeumt das Verifikations-Log (#12): loescht je Portal die Zeilen, die aelter
 * sind als `turnstile.log.retention_days` (Vorgabe 90 Tage, docs/turnstile.md
 * §4).
 *
 *   php artisan turnstile:prune
 *   php artisan turnstile:prune --days=30
 *   php artisan turnstile:prune --dry-run
 *
 * Geloescht wird in Runden von `turnstile.log.prune_chunk` Zeilen (DELETE mit
 * LIMIT), nicht in einem Zug: die Tabelle liegt je Portal in der Tenant-DB auf
 * rotierenden Platten, und ein grosses DELETE haelt sie zu lange gesperrt.
 *
 * Laeuft ZENTRAL ueber alle Portale (TurnstileLogConnection), nicht ueber
 * tenants:run — dasselbe Muster wie Kennzahlen, Monitor und Tagesbericht.
 * Ein Portal ohne lesbares Log wird gemeldet und uebersprungen.
 */
class TurnstilePrune extends Command
{
    protected $signature = 'turnstile:prune
        {--days= : Aufbewahrung in Tagen, Vorgabe aus turnstile.log.retention_days}
        {--chunk= : Zeilen je Runde, Vorgabe aus turnstile.log.prune_chunk}
        {--dry-run : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht Turnstile-Log-Zeilen aelter als die Aufbewahrungsfrist (#12)';

    public function handle(): int
    {
        $tage = (int) ($this->option('days') ?? config('turnstile.log.retention_days', 90));
        $runde = (int) ($this->option('chunk') ?? config('turnstile.log.prune_chunk', 1000));
        $probe = (bool) $this->option('dry-run');

        if ($tage < 1) {
            $this->components->error('--days muss mindestens 1 sein.');

            return self::FAILURE;
        }

        $grenze = now()->subDays($tage);
        $zeilen = [];
        $gesamt = 0;

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            /** @var Tenant $tenant */
            try {
                TurnstileLogConnection::point($tenant);

                $geloescht = $probe
                    ? $this->countOlderThan($grenze)
                    : $this->deleteOlderThan($grenze, max(1, $runde));
            } catch (Throwable $exception) {
                $this->components->warn(sprintf('%s: Log nicht lesbar (%s)', $tenant->name, $exception::class));

                continue;
            }

            $gesamt += $geloescht;

            if ($geloescht > 0) {
                $zeilen[] = [(string) $tenant->name, number_format($geloescht, 0, ',', '.')];
            }
        }

        TurnstileLogConnection::forget();

        if ($zeilen !== []) {
            $this->table(['Portal', $probe ? 'Zu löschen' : 'Gelöscht'], $zeilen);
        }

        $this->components->info(sprintf(
            '%s Zeilen älter als %s (%d Tage)%s.',
            number_format($gesamt, 0, ',', '.'),
            $grenze->format('d.m.Y H:i'),
            $tage,
            $probe ? ' — Probelauf, nichts gelöscht' : ' gelöscht'
        ));

        if (! $probe && $gesamt > 0) {
            Log::info('Turnstile-Log geraeumt', ['rows' => $gesamt, 'retention_days' => $tage]);
        }

        return self::SUCCESS;
    }

    private function countOlderThan(DateTimeInterface $grenze): int
    {
        return (int) DB::connection(TurnstileLogConnection::NAME)
            ->table('turnstile_verifications')
            ->where('created_at', '<', $grenze)
            ->count();
    }

    /**
     * Runde fuer Runde, bis keine alte Zeile mehr uebrig ist. Die Obergrenze
     * von 10.000 Runden ist eine Reissleine gegen eine Endlosschleife, falls
     * der Treiber kein LIMIT beim DELETE unterstuetzt und 0 zurueckmeldet.
     */
    private function deleteOlderThan(DateTimeInterface $grenze, int $runde): int
    {
        $gesamt = 0;

        for ($i = 0; $i < 10_000; $i++) {
            $geloescht = DB::connection(TurnstileLogConnection::NAME)
                ->table('turnstile_verifications')
                ->where('created_at', '<', $grenze)
                ->limit($runde)
                ->delete();

            $gesamt += $geloescht;

            if ($geloescht < $runde) {
                break;
            }
        }

        return $gesamt;
    }
}
