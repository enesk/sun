<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\CityContentTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stadtinhalte-Vorlage (Einleitung + FAQ) fuer das Elektriker-Portal (#11).
 *
 * Idempotent: legt die Vorlage nur an, wenn der Tenant noch keine hat. Eine im
 * Dashboard gepflegte Vorlage wird nie ueberschrieben.
 *
 * FACHLICHE FREIGABE: Die Antworten sind Entwurf und muessen vor dem Livegang
 * von Enes freigegeben werden. Bewusst ohne konkrete Preise oder Fristen als
 * Tatsachenbehauptung.
 *
 * php artisan db:seed --class=CityContentTemplateSeeder --force
 */
class CityContentTemplateSeeder extends Seeder
{
    /**
     * Domain-Stichwort => Vorlage.
     *
     * @var array<string, array{intro_template: string, faq_templates: list<array{question: string, answer: string}>}>
     */
    private const TEMPLATES = [
        'elektriker' => [
            'intro_template' => "Sie suchen einen Elektriker in {city}? Hier finden Sie Elektrobetriebe aus {city} und Umgebung mit Kontaktdaten, Öffnungszeiten und Bewertungen. Betriebe sind unter anderem in {districts} ansässig.\n\nTypische Einsätze sind Elektroinstallationen im Neubau und bei der Sanierung, der Austausch von Sicherungskästen und Zählerschränken, der E-Check für Vermieter und Gewerbe, Wallboxen für Elektroautos sowie die Störungssuche bei Stromausfall.",
            'faq_templates' => [
                [
                    'question' => 'Was kostet ein Elektriker in {city} pro Stunde?',
                    'answer' => "Der Stundensatz hängt von Region, Qualifikation (Geselle oder Meister) und Auftrag ab und unterscheidet sich zwischen Städten und ländlichen Gegenden deutlich. Als grobe Orientierung werden häufig Stundensätze im Bereich von etwa 50 bis 90 Euro netto genannt, dazu kommen oft Anfahrt und Material.\n\nVerbindlich ist nur das Angebot des Betriebs. Fragen Sie in {city} bei mehreren Betrieben einen Kostenvoranschlag an und vergleichen Sie, was jeweils enthalten ist.",
                ],
                [
                    'question' => 'Gibt es in {city} einen Elektro-Notdienst?',
                    'answer' => "Viele Elektrobetriebe bieten außerhalb der Geschäftszeiten einen Notdienst an, allerdings nicht jeder und nicht rund um die Uhr. Ob ein Betrieb Notdienst anbietet, steht in seinem Profil oder lässt sich telefonisch klären.\n\nFür Einsätze nachts, am Wochenende und an Feiertagen berechnen Betriebe in der Regel Zuschläge. Lassen Sie sich die Kosten vorab am Telefon nennen.",
                ],
                [
                    'question' => 'Was gehört zu einem E-Check?',
                    'answer' => "Beim E-Check prüft eine Elektrofachkraft elektrische Anlagen und Geräte auf Sicherheit. Dazu gehören eine Sichtprüfung auf Schäden, Messungen etwa von Isolationswiderstand und Schutzleiter sowie die Prüfung der Fehlerstrom-Schutzschalter.\n\nDas Ergebnis wird in einem Prüfprotokoll dokumentiert. Wie oft geprüft werden sollte, hängt von der Nutzung ab; für gewerbliche Anlagen und Vermietungen können Vorgaben von Versicherern oder Berufsgenossenschaften gelten.",
                ],
                [
                    'question' => 'Wie finde ich einen seriösen Elektriker in {city}?',
                    'answer' => "Achten Sie auf einen Eintrag in der Handwerksrolle, ein vollständiges Impressum mit Anschrift und nachvollziehbare Bewertungen. Ein seriöser Betrieb nennt die Kosten vor Beginn der Arbeiten, erstellt auf Wunsch einen schriftlichen Kostenvoranschlag und stellt eine ordentliche Rechnung.\n\nVorsicht ist geboten bei Pauschalpreisen am Telefon ohne Besichtigung, Barzahlung ohne Rechnung und Druck, sofort zu unterschreiben.",
                ],
            ],
        ],
    ];

    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            foreach (self::TEMPLATES as $needle => $template) {
                if (! Str::contains(Str::lower((string) $tenant->domain), $needle)) {
                    continue;
                }

                $tenant->run(function () use ($tenant, $template): void {
                    if (! Schema::hasTable('city_content_templates')) {
                        $this->command?->warn("{$tenant->domain}: city_content_templates fehlt, Tenant-Migrationen ausstehend.");

                        return;
                    }

                    if (CityContentTemplate::current() !== null) {
                        $this->command?->info("{$tenant->domain}: Stadtinhalte-Vorlage bereits vorhanden, unverändert.");

                        return;
                    }

                    CityContentTemplate::query()->create($template);

                    $this->command?->info("{$tenant->domain}: Stadtinhalte-Vorlage mit ".count($template['faq_templates']).' FAQ angelegt.');
                });
            }
        });
    }
}
