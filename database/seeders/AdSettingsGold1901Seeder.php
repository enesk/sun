<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ads\Models\AdSetting;
use App\Ads\Support\AdsTxtBuilder;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Portal-Einstellungen der acht Portale des Kontos ca-pub-1901800422608466
 * (Ticket #8 des Vorhabens "GOLD-Bloecke auf dem Firmenprofil, 8 Portale").
 *
 * Setzt je Portal die Publisher-ID dieses Kontos und den Hauptschalter
 * `ads_enabled`. Auto Ads wird bewusst **nicht** angefasst (Festlegung vom
 * 16.10.2026: bleibt wie eingestellt); fehlt die Zeile ganz, entsteht sie mit
 * {@see AdSetting::defaults()} und damit Auto Ads aus.
 *
 * Eigener Seeder, eigene Portalmenge: {@see AdSettingsGoldSeeder} haelt die
 * neun Portale des anderen Kontos ca-pub-2563111817853634 fest. Die beiden
 * Mengen sind disjunkt, die Seeder stoeren sich also nicht — und dieselben
 * Klassen umzuschreiben haette die Freigabe dort aufgehoben.
 *
 * Zugeordnet wird ueber die Domain des Tenants (Spalte `tenants.domain`, es
 * gibt keine Stancl-`domains`-Tabelle und keine domains()-Relation in diesem
 * Projekt): die Domain selbst oder eine Subdomain davon.
 *
 * Die Google-Zeile der ads.txt entsteht daraus automatisch
 * ({@see AdsTxtBuilder}) — genau einmal, auch wenn der Altbestand sie in
 * `ads_txt_extra` mitbringt (#41). Deshalb wird hier nichts an den
 * Zusatzzeilen geaendert.
 *
 * Idempotent; den Resolver- und ads.txt-Cache der betroffenen Portale leert
 * der Observer (App\Ads\Observers\AdCacheInvalidator) beim Speichern.
 *
 * Aufruf: php artisan db:seed --class=AdSettingsGold1901Seeder
 */
class AdSettingsGold1901Seeder extends Seeder
{
    /** Konto, auf das diese acht Portale freigegeben werden. */
    public const PUBLISHER_ID = 'ca-pub-1901800422608466';

    /** Die Google-Zeile dieses Kontos in der ads.txt. */
    public const ADS_TXT_LINE = 'google.com, pub-1901800422608466, DIRECT, f08c47fec0942fa0';

    /**
     * Die acht Domains aus der Vorgabe. Keine Wildcards.
     *
     * @var list<string>
     */
    public const DOMAINS = [
        'geruestbauer.gmbh',
        'fliesenleger.io',
        'kfzwerkstatt.io',
        'elektrikerportal.com',
        'fahrschulefinder.de',
        'malerfinder.de',
        'metallbauer.io',
        'mjet.net',
    ];

    /**
     * Die betroffenen Portale, nach Domain sortiert — auch fuer die
     * Belegungen aus #10 die eine Quelle der Wahrheit.
     *
     * @return array<int, Tenant>
     */
    public static function tenants(): array
    {
        /** @var array<int, Tenant> $tenants */
        $tenants = Tenant::all()
            ->filter(fn (Tenant $tenant) => self::matches((string) $tenant->domain))
            ->sortBy(fn (Tenant $tenant) => (string) $tenant->domain)
            ->values()
            ->all();

        return $tenants;
    }

    public function run(): void
    {
        $builder = new AdsTxtBuilder;
        $touched = 0;

        foreach (self::tenants() as $tenant) {
            $setting = AdSetting::query()->forTenant($tenant->getKey())->first()
                ?? new AdSetting(['tenant_id' => $tenant->getKey()] + AdSetting::defaults());

            $setting->ads_enabled = true;
            $setting->adsense_publisher_id = self::PUBLISHER_ID;
            $setting->save();

            $googleLines = count(array_keys(
                $builder->lines($tenant->getKey()),
                self::ADS_TXT_LINE,
                true,
            ));

            $this->command?->info(sprintf(
                '%s (#%s): freigegeben, %s, ads.txt-Zeile %s',
                $tenant->domain,
                $tenant->getKey(),
                self::PUBLISHER_ID,
                match ($googleLines) {
                    0 => 'FEHLT',
                    1 => 'vorhanden',
                    default => "DOPPELT ({$googleLines}x)",
                },
            ));

            $touched++;
        }

        $this->command?->info(sprintf(
            'Werbe-Einstellungen pub-1901800422608466: %d von %d Portalen gesetzt.',
            $touched,
            count(self::DOMAINS),
        ));
    }

    private static function matches(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        foreach (self::DOMAINS as $base) {
            if ($domain === $base || str_ends_with($domain, '.'.$base)) {
                return true;
            }
        }

        return false;
    }
}
