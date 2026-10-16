<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\AdSlotResolver;
use App\Ads\Dto\AdResolution;
use App\Ads\Enums\AdDevice;
use App\Ads\Support\AdCache;
use App\Ads\Support\AdSlotCatalog;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Was laeuft in diesem Anker? (Ticket #4)
 *
 *   php artisan ads:resolve fliesenleger.io home_after_hero mobile
 *
 * Zeigt die gewonnene Belegung samt Rangfolge und das gerenderte Fragment —
 * das Werkzeug fuer die Abnahme auf Staging und fuer die Frage "warum ist der
 * Anker leer?". Jeder Grund, aus dem nichts ausgespielt wird, wird benannt.
 *
 * Laeuft im Kontext des Portals (`$tenant->run()`), damit Cache-Store und
 * Zustell-URL dieselben sind wie im echten Seitenaufruf.
 */
class AdsResolve extends Command
{
    protected $signature = 'ads:resolve
        {tenant : ID, UUID oder Domain des Portals}
        {slot : Schluessel aus config/ad-slots.php}
        {device=all : all|mobile|desktop}
        {--no-consent : ohne Einwilligung rendern (Fragment wird zurueckgehalten, #9)}
        {--editor : Editor-Modus — ohne Cache und ohne AdSense}
        {--fresh : Cache fuer dieses Portal vorher leeren}';

    protected $description = 'Loest einen Werbeplatz fuer ein Portal auf und zeigt das gerenderte Fragment';

    public function handle(AdSlotResolver $resolver): int
    {
        $tenant = $this->tenant((string) $this->argument('tenant'));

        if ($tenant === null) {
            $this->components->error('Portal nicht gefunden.');

            return self::FAILURE;
        }

        $slotKey = (string) $this->argument('slot');
        $slot = AdSlotCatalog::find($slotKey);

        if ($slot === null) {
            $this->components->error("Der Werbeplatz {$slotKey} steht nicht im Katalog (config/ad-slots.php).");

            return self::FAILURE;
        }

        $device = AdDevice::tryFrom((string) $this->argument('device'));

        if ($device === null) {
            $this->components->error('Geraet muss all, mobile oder desktop sein.');

            return self::FAILURE;
        }

        if (! AdSlotCatalog::allowsDevice($slotKey, $device)) {
            $this->components->warn(sprintf(
                'Der Anker %s laesst nur %s zu — auf %s bleibt er immer leer.',
                $slotKey,
                AdSlotCatalog::deviceOf($slotKey)->label(),
                $device->label(),
            ));
        }

        if ($this->option('fresh')) {
            AdCache::forgetTenant($tenant);
            $this->components->info('Cache dieses Portals geleert.');
        }

        // Im Portal-Kontext: der file-Store zeigt dort woanders hin, und die
        // Zustell-URL des HtmlProviders braucht die Portal-Domain.
        return $tenant->run(fn (): int => $this->report($resolver, $tenant, $slotKey, $device, $slot));
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function report(
        AdSlotResolver $resolver,
        Tenant $tenant,
        string $slotKey,
        AdDevice $device,
        array $slot,
    ): int {
        $domain = (string) $tenant->getAttribute('domain');

        if ($domain !== '') {
            URL::forceRootUrl('https://'.$domain);
        }

        $editor = (bool) $this->option('editor');
        $consent = ! $this->option('no-consent');

        $this->components->twoColumnDetail('<fg=gray>Portal</>', "{$tenant->getKey()} · {$tenant->getAttribute('name')} · {$domain}");
        $this->components->twoColumnDetail('<fg=gray>Anker</>', "{$slotKey} · ".(string) ($slot['label'] ?? ''));
        $this->components->twoColumnDetail('<fg=gray>Seitentyp</>', (string) ($slot['page_type'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>Geraet</>', $device->label());
        $this->components->twoColumnDetail('<fg=gray>Katalogversion</>', (string) AdSlotCatalog::version());
        $this->components->twoColumnDetail('<fg=gray>Einwilligung</>', $consent ? 'ja' : 'nein');
        $this->components->twoColumnDetail('<fg=gray>Editor-Modus</>', $editor ? 'ja' : 'nein');
        // Auto Ads haengen an keinem Anker (#18); hier steht nur, ob das
        // Portal sie ausspielt — sichtbar wird das im Layout, nicht hier.
        $this->components->twoColumnDetail('<fg=gray>Auto Ads</>', $resolver->autoAdsEnabled($tenant) ? 'ja' : 'nein');

        if (! $resolver->adsEnabled($tenant)) {
            $this->newLine();
            $this->components->warn('Dieses Portal hat Werbung nicht freigegeben (ad_settings.ads_enabled = false). Es wird nichts ausgespielt.');

            return self::SUCCESS;
        }

        $resolution = $resolver->resolution($slotKey, $tenant, $device, $editor);

        if ($resolution === null) {
            $this->newLine();
            $this->components->warn('Keine gueltige Belegung: kein aktives Placement im Zeitraum fuer dieses Portal, Geraet und Anker.');

            if ($device === AdDevice::ALL) {
                $this->deviceBoundHint($resolver, $tenant, $slotKey, $editor);
            }

            return self::SUCCESS;
        }

        $this->printResolution($resolution);

        $context = $resolver->context($slotKey, $tenant, $device, $consent, $editor);
        $rendered = $resolver->resolve($slotKey, $tenant, $device, $context);

        $this->newLine();

        if ($rendered === null) {
            $this->components->warn(
                'Die Belegung gewinnt, der Provider rendert aber nichts: '
                .'AdSense ohne Publisher-ID oder im Editor-Modus; '
                .'eigenes HTML ohne Code oder Groesse.'
            );

            return self::SUCCESS;
        }

        $this->line('<fg=gray>Fragment</>'
            .($rendered->requiresConsent
                ? ($consent
                    ? ''
                    : ' <fg=yellow>— wird ohne Einwilligung zurueckgehalten (bleibt im &lt;template&gt;, #9)</>')
                : ' <fg=gray>(ohne Einwilligung ausspielbar)</>'));
        $this->line($rendered->html);

        if ($rendered->hasHeadDependencies()) {
            $this->newLine();
            $this->line('<fg=gray>Head-Abhaengigkeiten (einmal pro Seite)</>');

            foreach ($rendered->headDependencies as $dependency) {
                $this->line("  {$dependency->handle}: {$dependency->src}"
                    .($dependency->requiresConsent ? ' (erst nach Einwilligung)' : ''));
            }
        }

        if ($rendered->meta !== []) {
            $this->newLine();
            $this->line('<fg=gray>Meta</>');

            foreach ($rendered->meta as $key => $value) {
                $this->components->twoColumnDetail("  {$key}", is_scalar($value) ? (string) $value : json_encode($value));
            }
        }

        return self::SUCCESS;
    }

    /**
     * `all` ist nur besetzt, wenn Anker und Belegung geraeteunabhaengig sind
     * (Ticket #20). Laeuft hier je Geraet etwas, wird es benannt — sonst
     * sieht die Abfrage ohne Geraeteangabe wie ein leerer Anker aus.
     */
    private function deviceBoundHint(AdSlotResolver $resolver, Tenant $tenant, string $slotKey, bool $editor): void
    {
        $found = [];

        foreach ([AdDevice::MOBILE, AdDevice::DESKTOP] as $device) {
            $resolution = $resolver->resolution($slotKey, $tenant, $device, $editor);

            if ($resolution !== null) {
                $found[] = sprintf(
                    '%s: Placement %d · %s · Prio %d',
                    $device->value,
                    $resolution->placementId,
                    $resolution->adUnitName,
                    $resolution->priority,
                );
            }
        }

        if ($found === []) {
            return;
        }

        $this->components->info(
            'Unter "all" steht nur, was auf beiden Geraeten laufen darf. '
            ."Hier gewinnt je Geraet etwas anderes — fragen mit `ads:resolve {$tenant->getKey()} {$slotKey} mobile|desktop`:"
        );

        foreach ($found as $line) {
            $this->line("  {$line}");
        }
    }

    private function printResolution(AdResolution $resolution): void
    {
        $this->newLine();
        $this->line('<fg=gray>Belegung</>');
        $this->components->twoColumnDetail('  Placement', (string) $resolution->placementId);
        $this->components->twoColumnDetail('  Werbemittel', "{$resolution->adUnitId} · {$resolution->adUnitName}");
        $this->components->twoColumnDetail('  Provider', $resolution->provider);
        $this->components->twoColumnDetail('  Prioritaet', (string) $resolution->priority);
        $this->components->twoColumnDetail('  Geraet der Belegung', $resolution->device->label());
        $this->components->twoColumnDetail('  Konfiguration', (string) json_encode($resolution->config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function tenant(string $needle): ?Tenant
    {
        // `id` ist bigint — ein Domainname darf dort nicht in den Vergleich.
        return Tenant::query()
            ->where(function ($query) use ($needle): void {
                $query->where('uuid', $needle)->orWhere('domain', $needle);

                if (ctype_digit($needle)) {
                    $query->orWhere('id', (int) $needle);
                }
            })
            ->first();
    }
}
