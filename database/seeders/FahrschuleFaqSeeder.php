<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\FAQ;
use Illuminate\Database\Seeder;

/**
 * FAQ-Inhalte fuer fahrschulefinder.de (#48). Das Portal hatte bisher keine.
 * Idempotent, Erkennung an der Frage. Preise und Stundenzahlen stehen nur dort
 * im Text, wo sie bundesweit geregelt sind (Sonderfahrten, Fristen); eigene
 * Paketpreise nennt die Preisseite.
 *
 *   php artisan tenants:run db:seed --tenants=<uuid> --option=class=FahrschuleFaqSeeder --option=force=1
 */
class FahrschuleFaqSeeder extends Seeder
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
                'Nein. Fahrschulen suchen, Bewertungen lesen, Öffnungszeiten und Telefonnummern einsehen – alles kostenlos und ohne Anmeldung. Das Portal finanziert sich über die freiwilligen Premium-Einträge der Fahrschulen.',
            ],
            [
                'Was kostet der Führerschein insgesamt?',
                'Das hängt vor allem davon ab, wie viele Fahrstunden du brauchst – und das ist von Mensch zu Mensch sehr verschieden. Vergleichbar wird es erst, wenn du alle Posten zusammenrechnest: Grundgebühr, Preis je Fahrstunde, die gesetzlich vorgeschriebenen Sonderfahrten, Vorstellungsentgelt für Theorie- und Praxisprüfung, Lernmaterial sowie die Gebühren von Behörde, TÜV oder Dekra und der Sehtest. Lass dir die komplette Liste geben, bevor du unterschreibst.',
            ],
            [
                'Wie viele Sonderfahrten sind vorgeschrieben?',
                'Für die Klasse B sind es zwölf: fünf Überlandfahrten, vier auf der Autobahn und drei bei Dunkelheit. Sie sind gesetzlich festgelegt, kommen also zu den normalen Übungsstunden dazu und kosten meist mehr als eine reguläre Fahrstunde. Bei anderen Klassen gelten eigene Vorgaben.',
            ],
            [
                'Ab welchem Alter kann ich mit der Fahrschule anfangen?',
                'Den Theorieunterricht darfst du sechs Monate vor dem Mindestalter beginnen, die Praxisprüfung frühestens einen Monat vorher ablegen. Für Klasse B mit Begleitetem Fahren ab 17 heißt das: Anmeldung meist mit 16 Jahren und sechs Monaten. Den Antrag stellst du bei der Führerscheinstelle, die Fahrschule hilft dir dabei.',
            ],
            [
                'Was brauche ich für die Anmeldung?',
                'Ein biometrisches Passbild, den Sehtest, die Bescheinigung über die Schulung in Erster Hilfe sowie Personalausweis oder Pass. Der Antrag läuft über die Führerscheinstelle deines Wohnorts; die Bearbeitung dauert je nach Behörde einige Wochen – früh anmelden lohnt sich.',
            ],
            [
                'Wie lange ist die Theorieprüfung gültig?',
                'Nach bestandener Theorieprüfung hast du zwölf Monate Zeit für die praktische Prüfung. Danach verfällt die Theorie und du musst sie wiederholen. Der Antrag bei der Führerscheinstelle selbst ist in der Regel zwei Jahre gültig.',
            ],
            [
                'Ich bin durch die Prüfung gefallen – wie geht es weiter?',
                'Nach einer nicht bestandenen Prüfung gilt eine Sperrfrist von zwei Wochen, danach kannst du erneut antreten. Fällig werden erneut Prüfungsgebühr und Vorstellungsentgelt, meist dazu ein paar Übungsstunden. Beim dritten Fehlversuch in der Praxis wird es aufwendiger, das bespricht die Fahrschule dann individuell mit dir.',
            ],
            [
                'Kann ich die Fahrschule wechseln?',
                'Ja, jederzeit. Du bekommst eine Ausbildungsbescheinigung über den bisherigen Stand, die neue Fahrschule setzt darauf auf. Prüf vorher den Vertrag: Manche Fahrschulen berechnen eine Abmeldegebühr, bereits bezahlte Leistungen werden abgerechnet. Nachgewiesener Theorieunterricht bleibt dir erhalten.',
            ],
            [
                'Was ist Begleitetes Fahren ab 17?',
                'Du machst den Führerschein der Klasse B regulär und darfst nach bestandener Prüfung mit 17 fahren – allerdings nur mit einer eingetragenen Begleitperson. Die muss mindestens 30 Jahre alt sein, seit fünf Jahren die Klasse B besitzen und darf höchstens einen Punkt im Fahreignungsregister haben. Mit 18 fällt die Auflage weg.',
            ],
            [
                'Lohnt sich ein Intensiv- oder Ferienkurs?',
                'Wenn du Zeit am Stück hast und zügig fahren lernst, ja: Der Theorieteil ist in ein bis zwei Wochen erledigt, die Praxis folgt dicht darauf. Teurer ist es nicht automatisch, dafür brauchst du Termindisziplin. Wer unsicher ist oder nur am Wochenende Zeit hat, fährt mit dem normalen Ablauf besser.',
            ],
            [
                'Sind die Bewertungen echt?',
                'Jede Bewertung wird geprüft, bevor sie öffentlich wird; Spam, Beleidigungen und offensichtliche Fälschungen sortieren wir aus. Eine Garantie, dass hinter jeder Bewertung ein echter Fahrschulbesuch steckt, kann niemand geben. Bewertungen mit konkretem Inhalt sagen mehr als eine reine Sternezahl.',
            ],
            [
                'Ich betreibe eine Fahrschule – wie komme ich ins Portal?',
                'Über „Fahrschule eintragen“ im Kopf der Seite: Betrieb und Anschrift angeben, Konto anlegen, E-Mail bestätigen. Das Portal-Team schaltet den Eintrag frei, danach bekommst du eine E-Mail. Ist deine Fahrschule schon gelistet, übernimmst du den Eintrag mit „Ist das Ihr Betrieb?“ und einem kurzen Nachweis – danach pflegst du Profil, Fotos und Bewertungsantworten selbst.',
            ],
        ];
    }
}
