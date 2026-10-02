<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\FAQ;
use Illuminate\Database\Seeder;

/**
 * FAQ-Inhalte fuer findegutachter.de (#49). Das Portal hatte bisher keine.
 * Idempotent, Erkennung an der Frage.
 *
 * Die Branche grenzt an Rechts- und Versicherungsfragen. Die Antworten
 * bleiben deshalb bei allgemein Gueltigem und dem Ablauf, nennen keine
 * Einzelfallbewertung und verweisen im Streitfall auf Rechtsberatung.
 *
 *   php artisan tenants:run db:seed --tenants=<uuid> --option=class=GutachterFaqSeeder --option=force=1
 */
class GutachterFaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->faqs() as $index => $faq) {
            FAQ::updateOrCreate(
                ['question' => $faq[0]],
                [
                    'answer' => $faq[1],
                    'page' => 'faq',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info(count($this->faqs()).' FAQs gesetzt.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'Kostet die Nutzung des Portals etwas?',
                'Nein. Gutachterbüros suchen, Bewertungen lesen, Fachgebiete, Öffnungszeiten und Telefonnummern einsehen – alles kostenlos und ohne Anmeldung. Das Portal finanziert sich über die freiwilligen Premium-Einträge der Büros.',
            ],
            [
                'Woran erkenne ich einen qualifizierten Sachverständigen?',
                '„Sachverständiger“ und „Gutachter“ sind keine geschützten Bezeichnungen – entscheidend sind die Nachweise. Aussagekräftig sind die öffentliche Bestellung und Vereidigung durch eine Industrie- und Handelskammer oder Handwerkskammer, eine Zertifizierung nach DIN EN ISO/IEC 17024 und bei Fahrzeugen die Anerkennung als Prüfingenieur. Fragen Sie danach, bevor Sie beauftragen; seriöse Büros nennen ihre Qualifikation unaufgefordert.',
            ],
            [
                'Darf ich nach einem Unfall den Gutachter selbst aussuchen?',
                'Wenn die Gegenseite den Unfall verschuldet hat, wählen Sie Gutachter, Werkstatt und Anwalt grundsätzlich selbst – die Versicherung des Verursachers darf Ihnen keinen Sachverständigen vorschreiben. Melden Sie den Schaden trotzdem zügig und lassen Sie ihn dokumentieren, bevor etwas repariert wird. Geht es um Ihren eigenen Kasko-Schaden, gelten die Bedingungen Ihres Vertrags; dort ist oft ein Gutachter der Versicherung vorgesehen.',
            ],
            [
                'Wer zahlt das Gutachten?',
                'Bei einem unverschuldeten Unfall gehören die Kosten eines Schadengutachtens in der Regel zum erstattungsfähigen Schaden und werden von der Versicherung des Verursachers getragen. Bei Bagatellschäden reicht oft ein Kostenvoranschlag der Werkstatt, dann kann die Erstattung entfallen. Privat beauftragte Gutachten zu Immobilien, Bau oder Schimmel zahlt der Auftraggeber selbst – im Streitfall kann das Geld über die Hauptsache zurückkommen. Wie es in Ihrem Fall ausgeht, klärt eine Rechtsberatung.',
            ],
            [
                'Was kostet ein Gutachten?',
                'Bei Fahrzeugschäden richtet sich das Honorar meist nach der Schadenhöhe, bei Bau- und Immobiliengutachten nach Aufwand und Stundensatz oder nach dem Objektwert. Ein kurzes Kurzgutachten liegt deutlich unter einem gerichtsfesten Verkehrswertgutachten. Lassen Sie sich vorab schriftlich geben, was das Gutachten umfasst – Ortstermin, Fotos, Berechnung, Anzahl der Ausfertigungen – und was die Anfahrt kostet.',
            ],
            [
                'Wie läuft ein Ortstermin ab?',
                'Der Sachverständige nimmt den Zustand auf, fotografiert und dokumentiert, misst nach und befragt Sie zur Vorgeschichte. Halten Sie alles bereit, was dazugehört: Kaufvertrag, Baupläne, frühere Rechnungen, Schadensmeldungen, bei Fahrzeugen Fahrzeugschein und Serviceheft. Der Termin dauert je nach Umfang eine bis mehrere Stunden; das schriftliche Gutachten folgt meist innerhalb von einigen Tagen bis zwei Wochen.',
            ],
            [
                'Was steht in einem Kfz-Schadengutachten?',
                'Üblich sind Angaben zum Fahrzeug, eine Fotodokumentation, die Reparaturkosten, die voraussichtliche Reparaturdauer, die Wertminderung sowie Wiederbeschaffungswert und Restwert. Daraus ergibt sich auch, ob ein wirtschaftlicher Totalschaden vorliegt. Je sauberer diese Posten belegt sind, desto weniger Spielraum hat die Versicherung bei der Abrechnung.',
            ],
            [
                'Brauche ich beim Hauskauf einen Bausachverständigen?',
                'Bei älteren Gebäuden lohnt sich die Begleitung fast immer: Feuchte Keller, undichte Dächer, alte Elektrik oder Risse im Mauerwerk fallen Laien bei der Besichtigung selten auf, kosten später aber oft fünfstellige Beträge. Der Sachverständige geht mit Ihnen durch das Objekt und schätzt den Instandhaltungsbedarf. Vereinbaren Sie den Termin vor der Beurkundung beim Notar – danach helfen Mängel nur noch bei arglistiger Täuschung weiter.',
            ],
            [
                'Was ist der Unterschied zwischen Kurzgutachten, Verkehrswertgutachten und Gerichtsgutachten?',
                'Ein Kurzgutachten ist eine knappe Einschätzung für den eigenen Gebrauch, etwa zur Preisfindung. Ein ausführliches Verkehrswertgutachten ist nach anerkannten Verfahren aufgebaut und wird von Banken, Finanzämtern und Gerichten akzeptiert – entsprechend aufwendig und teurer. Ein Gerichtsgutachten erstellt ein vom Gericht bestellter Sachverständiger im laufenden Verfahren; beauftragen können Sie das nicht selbst.',
            ],
            [
                'Wir haben Schimmel in der Wohnung – was bringt ein Gutachten?',
                'Ein Sachverständiger stellt fest, wo die Feuchtigkeit herkommt: Baumangel, Leitungsschaden, Wärmebrücke oder Nutzerverhalten. Genau das ist meist der Streitpunkt zwischen Mietern, Vermietern und Versicherungen – und ohne Messwerte und Dokumentation kaum zu klären. Das Gutachten benennt auch die nötige Sanierung. Die rechtliche Bewertung, etwa zur Mietminderung, gehört in eine Rechtsberatung.',
            ],
            [
                'Sind die Bewertungen echt?',
                'Jede Bewertung wird geprüft, bevor sie öffentlich wird; Spam, Beleidigungen und offensichtliche Fälschungen sortieren wir aus. Eine Garantie, dass hinter jeder Bewertung ein echter Auftrag steckt, kann niemand geben. Bewertungen mit konkretem Inhalt sagen mehr aus als eine reine Sternezahl.',
            ],
            [
                'Ich bin Sachverständiger – wie komme ich ins Portal?',
                'Über „Büro eintragen“ im Kopf der Seite: Büro und Anschrift angeben, Konto anlegen, E-Mail bestätigen. Das Portal-Team schaltet den Eintrag frei, danach bekommen Sie eine E-Mail. Ist Ihr Büro schon gelistet, übernehmen Sie den Eintrag mit „Ist das Ihr Betrieb?“ und einem kurzen Nachweis. Danach pflegen Sie Fachgebiete, Qualifikationen und Kontaktdaten selbst – gerade die Fachgebiete entscheiden darüber, ob Auftraggeber Sie finden.',
            ],
        ];
    }
}
