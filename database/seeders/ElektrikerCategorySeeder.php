<?php

namespace Database\Seeders;

use App\Models\Portal\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Leistungskategorien fuer das Elektriker-Portal (elektrikerportal.com,
 * lokal elektriker.test).
 *
 * Idempotent ueber den Slug: bestehende Kategorien werden aktualisiert, nichts
 * wird geloescht, Zuordnungen zu Firmen bleiben unberuehrt. Laeuft nur im
 * Kontext eines Elektriker-Portals, damit kein anderes Portal diese Liste bekommt.
 *
 * Usage:
 *   php artisan tenants:run db:seed --option="class=ElektrikerCategorySeeder" --option=force --tenants=<uuid>
 */
class ElektrikerCategorySeeder extends Seeder
{
    /**
     * Hauptkategorie => [Icon, Beschreibung, Unterkategorien].
     *
     * @var array<string, array{icon: string, description: string, children: list<string>}>
     */
    private const CATEGORIES = [
        'Elektroinstallation' => [
            'icon' => 'plug',
            'description' => 'Neubau, Altbausanierung und Erweiterung der Hausinstallation.',
            'children' => ['Neubauinstallation', 'Altbausanierung', 'Steckdosen & Schalter', 'Zählerschrank & Sicherungskasten'],
        ],
        'Reparatur & Störungsdienst' => [
            'icon' => 'wrench',
            'description' => 'Fehlersuche und Reparatur bei Stromausfall, Kurzschluss oder defekten Leitungen.',
            'children' => ['Fehlersuche', 'Elektro-Notdienst'],
        ],
        'E-Mobilität' => [
            'icon' => 'car',
            'description' => 'Ladelösungen für Elektroautos zu Hause und im Betrieb.',
            'children' => ['Wallbox-Installation', 'Ladeinfrastruktur für Gewerbe'],
        ],
        'Photovoltaik & Speicher' => [
            'icon' => 'sun',
            'description' => 'Anschluss von Solaranlagen und Batteriespeichern.',
            'children' => ['PV-Anlagen', 'Batteriespeicher', 'Balkonkraftwerke'],
        ],
        'Smart Home & Gebäudeautomation' => [
            'icon' => 'cpu',
            'description' => 'Vernetzte Steuerung von Licht, Heizung und Rollläden.',
            'children' => ['KNX', 'Funk-Systeme', 'Rollladen- und Jalousiesteuerung'],
        ],
        'Beleuchtung' => [
            'icon' => 'lightbulb',
            'description' => 'Planung und Montage von Innen- und Außenbeleuchtung.',
            'children' => ['LED-Umrüstung', 'Außenbeleuchtung'],
        ],
        'Prüfung & Sicherheit' => [
            'icon' => 'shield-check',
            'description' => 'Vorgeschriebene Prüfungen und Schutz vor Überspannung und Blitz.',
            'children' => ['E-Check', 'DGUV V3 Prüfung', 'Blitz- und Überspannungsschutz', 'FI-Schutzschalter'],
        ],
        'Netzwerk & Kommunikation' => [
            'icon' => 'network',
            'description' => 'Daten- und Telefonleitungen, Antennen und Türsprechanlagen.',
            'children' => ['Netzwerkverkabelung', 'SAT- und Antennentechnik', 'Türsprechanlagen'],
        ],
        'Sicherheitstechnik' => [
            'icon' => 'bell',
            'description' => 'Alarm-, Video- und Brandmeldeanlagen.',
            'children' => ['Alarmanlagen', 'Videoüberwachung', 'Rauchwarnmelder'],
        ],
        'Haushaltsgeräte & Anschlüsse' => [
            'icon' => 'zap',
            'description' => 'Anschluss von Herd, Durchlauferhitzer und Wärmepumpe.',
            'children' => ['Herdanschluss', 'Durchlauferhitzer', 'Wärmepumpen-Anschluss'],
        ],
    ];

    private const SOURCE_KEY = 'elektriker';

    public function run(): void
    {
        $domain = (string) (tenant()?->domain ?? '');

        if (! str_contains($domain, 'elektriker')) {
            $this->command?->warn("Kein Elektriker-Portal ('{$domain}'), Kategorien nicht angelegt.");

            return;
        }

        $parentOrder = 0;

        foreach (self::CATEGORIES as $name => $data) {
            $parent = $this->upsert($name, [
                'icon' => $data['icon'],
                'description' => $data['description'],
                'parent_id' => null,
                'sort_order' => $parentOrder++,
            ]);

            foreach ($data['children'] as $childOrder => $childName) {
                $this->upsert($childName, [
                    'icon' => $data['icon'],
                    'parent_id' => $parent->id,
                    'sort_order' => $childOrder,
                ]);
            }
        }

        $total = count(self::CATEGORIES) + array_sum(array_map(fn (array $data): int => count($data['children']), self::CATEGORIES));
        $this->command?->info("{$total} Elektriker-Kategorien angelegt oder aktualisiert.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(string $name, array $attributes): Category
    {
        return Category::updateOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'source_key' => self::SOURCE_KEY, ...$attributes],
        );
    }
}
