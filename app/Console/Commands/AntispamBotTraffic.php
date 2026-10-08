<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\Support\BotTraffic;
use App\AntiSpam\Support\TenantSelection;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Maschineller GET-Verkehr je Portal auswerten (#17, docs/bot-traffic.md).
 *
 *   php artisan antispam:bot-traffic --tenant=elektrikerportal.com
 *   php artisan antispam:bot-traffic --days=7 --top=40
 *
 * Quelle ist `tracking_events` (Tenant-DB) — die einzige Tabelle mit
 * User-Agent und Netz. Der Lauf zeigt, welcher Anteil der Ereignisse die
 * Liste aus config/antispam.php `bot_traffic` trifft und welche grossen
 * User-Agents und /24-Netze sie noch NICHT kennt. Daraus werden die
 * Cloudflare-Regeln in docs/bot-traffic.md §2 gepflegt.
 *
 * Der Lauf liest nur. Die Spalte `ip_address` ist auf /24 gekuerzt
 * (docs/bot-traffic.md §4), die Netz-Spalte zeigt also das Netz, nie eine
 * Einzeladresse.
 */
class AntispamBotTraffic extends Command
{
    protected $signature = 'antispam:bot-traffic
        {--tenant=* : Portale (ID, UUID, Name oder Domain); "*" oder ohne Angabe = alle}
        {--days=30 : Zeitraum in Tagen}
        {--top=25 : Anzahl der aufgelisteten User-Agents und Netze}
        {--min=100 : Mindestzahl Ereignisse, ab der ein Eintrag aufgelistet wird}';

    protected $description = 'Zeigt den maschinellen Anteil der Seitenaufrufe je Portal und die noch unbekannten User-Agents und Netze';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $top = max(1, (int) $this->option('top'));
        $min = max(1, (int) $this->option('min'));

        $tenants = TenantSelection::resolve((array) $this->option('tenant'));

        if ($tenants->isEmpty()) {
            $this->components->warn('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $fehler = false;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(fn () => $this->portal($tenant, $days, $top, $min));
            } catch (Throwable $e) {
                $this->components->error("{$tenant->name}: {$e->getMessage()}");
                $fehler = true;
            }
        }

        return $fehler ? self::FAILURE : self::SUCCESS;
    }

    private function portal(Tenant $tenant, int $days, int $top, int $min): void
    {
        $seit = now()->subDays($days);

        $agents = DB::connection('tenant')->table('tracking_events')
            ->selectRaw('user_agent, COUNT(*) as treffer, COUNT(DISTINCT ip_address) as netze, COUNT(DISTINCT company_id) as betriebe')
            ->where('created_at', '>=', $seit)
            ->groupBy('user_agent')
            ->orderByDesc('treffer')
            ->get();

        if ($agents->isEmpty()) {
            $this->components->info("{$tenant->name}: keine Ereignisse in {$days} Tagen.");

            return;
        }

        $gesamt = 0;
        $erkannt = 0;
        $offen = [];

        foreach ($agents as $zeile) {
            $treffer = (int) $zeile->treffer;
            $gesamt += $treffer;
            $grund = BotTraffic::matchUserAgent($zeile->user_agent);

            if ($grund !== null) {
                $erkannt += $treffer;

                continue;
            }

            if ($treffer >= $min) {
                $offen[] = [
                    $this->kurz((string) $zeile->user_agent),
                    $treffer,
                    (int) $zeile->netze,
                    (int) $zeile->betriebe,
                ];
            }
        }

        $quote = $gesamt > 0 ? round($erkannt / $gesamt * 100, 1) : 0.0;

        $this->newLine();
        $this->components->info(sprintf(
            '%s — %s Ereignisse in %d Tagen, davon %s als maschinell erkannt (%s %%).',
            $tenant->name,
            number_format($gesamt, 0, ',', '.'),
            $days,
            number_format($erkannt, 0, ',', '.'),
            number_format($quote, 1, ',', '.'),
        ));

        if ($offen !== []) {
            $this->line('Noch nicht auf der Liste (User-Agent):');
            $this->table(['User-Agent', 'Ereignisse', '/24-Netze', 'Betriebe'], array_slice($offen, 0, $top));
        }

        $this->netze($seit, $top, $min);
    }

    /**
     * /24-Netze mit auffallend breitem Zugriff: viele verschiedene Betriebe
     * aus einem Netz ist das Muster eines Scrapers, unabhaengig vom
     * User-Agent (die Alibaba-Netze geben sich als Android-Chrome aus).
     */
    private function netze(CarbonInterface $seit, int $top, int $min): void
    {
        $netze = DB::connection('tenant')->table('tracking_events')
            ->selectRaw('ip_address, COUNT(*) as treffer, COUNT(DISTINCT company_id) as betriebe')
            ->where('created_at', '>=', $seit)
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->orderByDesc('betriebe')
            ->limit($top)
            ->get();

        if ($netze->isEmpty()) {
            return;
        }

        $rows = [];

        foreach ($netze as $zeile) {
            $rows[] = [
                (string) $zeile->ip_address,
                (int) $zeile->treffer,
                (int) $zeile->betriebe,
                BotTraffic::matchIp((string) $zeile->ip_address) !== null ? 'auf der Liste' : '—',
            ];
        }

        $this->line('Netze nach Zahl verschiedener Betriebe:');
        $this->table(['/24-Netz', 'Ereignisse', 'Betriebe', 'Bewertung'], $rows);
    }

    private function kurz(string $wert): string
    {
        return mb_strlen($wert) > 70 ? mb_substr($wert, 0, 67).'...' : $wert;
    }
}
