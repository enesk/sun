<?php

declare(strict_types=1);

namespace App\Content\Support;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Ordnet einen Tenant seiner Branche zu (#13).
 *
 * Es gibt keine eigene Spalte fuer die Branche eines Mandanten: Jeder Tenant
 * ist ein Branchenportal fuer genau ein Gewerk, erkennbar am Namen bzw. an der
 * Domain (z. B. "sanitaerfinden.de"). Die Zuordnung laeuft ueber denselben
 * Slug-Abgleich wie `TenantContentSettingSeeder::isYmyl()`, nur mit einer
 * Branchen- statt einer Ja/Nein-Liste. Ergebnis ist einer der Schluessel aus
 * self::BRANCHES oder null, wenn keine Regel greift (Fallback-Styleguide).
 */
final class BranchResolver
{
    /**
     * Branchen-Schluessel => (Anzeigename, Slug-Stichwoerter). Die Reihenfolge
     * ist relevant: "zahnarzt" muss vor "arzt" geprueft werden, sonst matcht
     * die allgemeinere Regel zuerst.
     *
     * @var array<string, array{label: string, needles: array<int, string>}>
     */
    private const BRANCHES = [
        'fliesen' => ['label' => 'Fliesenleger', 'needles' => ['fliesen', 'bodenleger']],
        'metallbau' => ['label' => 'Metallbau', 'needles' => ['metallbau', 'schlosser']],
        'geruestbau' => ['label' => 'Gerüstbau', 'needles' => ['geruest']],
        'gartenbau' => ['label' => 'Garten- und Landschaftsbau', 'needles' => ['garten', 'landschaftsbau']],
        'hoch-tiefbau' => ['label' => 'Hoch- und Tiefbau', 'needles' => ['hochbau', 'tiefbau', 'bauunternehmen']],
        'solar-pv' => ['label' => 'Solar/Photovoltaik', 'needles' => ['solar', 'photovoltaik', 'pv-anlage']],
        'energieberatung' => ['label' => 'Energieberatung', 'needles' => ['energieberat', 'energieausweis']],
        'sanitaer' => ['label' => 'Sanitär/Heizung', 'needles' => ['sanitaer', 'heizung', 'installateur', 'klempner']],
        'elektro' => ['label' => 'Elektro', 'needles' => ['elektro', 'elektriker']],
        'maler' => ['label' => 'Maler/Lackierer', 'needles' => ['maler', 'lackier']],
        'kfz' => ['label' => 'Kfz-Werkstatt', 'needles' => ['kfz', 'autowerkstatt', 'autohaus']],
        'spedition' => ['label' => 'Spedition/Logistik', 'needles' => ['spedition', 'logistik', 'transport']],
        'fahrschule' => ['label' => 'Fahrschule', 'needles' => ['fahrschule']],
        'tierarzt' => ['label' => 'Tierarzt', 'needles' => ['tierarzt', 'tierklinik']],
        'gutachter' => ['label' => 'Gutachter/Sachverständige', 'needles' => ['gutachter', 'sachverstaendig']],
        'medizin' => ['label' => 'Arzt/Zahnarzt/Unfallarzt/Apotheke', 'needles' => ['zahnarzt', 'unfallarzt', 'apotheke', 'arzt', 'klinik']],
    ];

    /**
     * @return array<string, string> Branchen-Schluessel => Anzeigename
     */
    public static function all(): array
    {
        return array_map(fn (array $branch): string => $branch['label'], self::BRANCHES);
    }

    public static function label(string $branch): ?string
    {
        return self::BRANCHES[$branch]['label'] ?? null;
    }

    /**
     * Branchen-Schluessel des Mandanten, oder null ohne Treffer.
     */
    public static function resolve(Tenant $tenant): ?string
    {
        return self::resolveFrom((string) $tenant->name, (string) $tenant->domain);
    }

    /**
     * Dieselbe Zuordnung fuer Aufrufer ohne Tenant-Modell — die Connectoren
     * (#11) bekommen nur den TenantContext.
     */
    public static function resolveFrom(string $name, ?string $domain = null): ?string
    {
        $slug = Str::slug($name).'-'.Str::slug((string) $domain);

        foreach (self::BRANCHES as $key => $branch) {
            foreach ($branch['needles'] as $needle) {
                if (str_contains($slug, $needle)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
