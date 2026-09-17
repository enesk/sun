<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\StoreCompanyInquiry;
use App\Models\Portal\CompanyInquiry;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Holt eine verlorene Anfrage nach (#38), ohne den Webhook.
 *
 * Eine erneute Zustellung ueber /webhooks/leads greift nicht: der Empfang
 * merkt sich jede angenommene Lead-UUID sieben Tage und meldet sie danach als
 * doppelt. Dieser Befehl gibt die Nutzlast deshalb direkt an
 * StoreCompanyInquiry, im Kontext des Portals und synchron.
 *
 * Eingabe ist `webhook_deliveries.payload` aus dem Leadsystem (ganzer
 * Umschlag) oder nur das Objekt `lead`, als Datei oder mit `-` ueber STDIN.
 * Vorgabe ist der Trockenlauf, gespeichert wird nur mit --apply.
 */
class ReplayCompanyInquiry extends Command
{
    protected $signature = 'leads:inquiries:replay
        {file : JSON-Datei mit der Nutzlast, "-" fuer STDIN}
        {--tenant= : Portal (ID, UUID oder Domain)}
        {--apply : Anfrage tatsaechlich speichern}';

    protected $description = 'Speichert eine Anfrage aus einer Webhook-Nutzlast des Leadsystems nachtraeglich am Betrieb';

    public function handle(): int
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $payload = $this->payload();

        if ($payload === null) {
            return self::FAILURE;
        }

        $lead = is_array($payload['data']['lead'] ?? null) ? $payload['data']['lead'] : $payload;
        $uuid = trim((string) ($lead['uuid'] ?? ''));

        if ($uuid === '') {
            $this->error('Die Nutzlast enthaelt keine Lead-UUID (data.lead.uuid bzw. uuid).');

            return self::FAILURE;
        }

        $funnelToken = $payload['funnel']['public_token'] ?? null;

        if (is_string($funnelToken) && $funnelToken !== $tenant->leadFunnelToken()) {
            $this->error("Die Nutzlast stammt aus einem fremden Funnel, nicht aus dem von {$tenant->domain}.");

            return self::FAILURE;
        }

        $job = new StoreCompanyInquiry($lead);

        return $tenant->run(function () use ($job, $uuid): int {
            if (CompanyInquiry::withTrashed()->where('lead_uuid', $uuid)->exists()) {
                $this->info("Lead {$uuid} ist bereits gespeichert, nichts zu tun.");

                return self::SUCCESS;
            }

            $company = $job->company();

            if ($company === null) {
                $this->error("Lead {$uuid}: kein Betrieb zuzuordnen, es wird nichts gespeichert.");

                return self::FAILURE;
            }

            $target = "{$company->name} (ID {$company->id})";

            if (! $this->option('apply')) {
                $this->comment("Trockenlauf: Lead {$uuid} landet bei {$target}. Zum Speichern: --apply");

                return self::SUCCESS;
            }

            $job->handle();

            $this->info("Lead {$uuid} bei {$target} gespeichert.");

            return self::SUCCESS;
        });
    }

    private function tenant(): ?Tenant
    {
        $needle = trim((string) $this->option('tenant'));

        if ($needle === '') {
            $this->error('--tenant fehlt (ID, UUID oder Domain des Portals).');

            return null;
        }

        $tenant = Tenant::query()
            ->where('id', $needle)
            ->orWhere('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();

        if ($tenant === null) {
            $this->error("Kein Portal zu \"{$needle}\" gefunden.");
        }

        return $tenant;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(): ?array
    {
        $file = (string) $this->argument('file');
        $contents = $file === '-' ? stream_get_contents(STDIN) : @file_get_contents($file);

        if ($contents === false) {
            $this->error("Datei nicht lesbar: {$file}");

            return null;
        }

        $payload = json_decode($contents, true);

        if (! is_array($payload)) {
            $this->error('Die Nutzlast ist kein JSON-Objekt.');

            return null;
        }

        return $payload;
    }
}
