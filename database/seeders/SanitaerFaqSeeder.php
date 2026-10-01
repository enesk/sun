<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\FAQ;
use Illuminate\Database\Seeder;

/**
 * Branchenfragen fuer sanitaerfinden.com (#47). Ergaenzt die allgemeinen
 * Portalfragen, die dort schon stehen; die rutschen dafuer nach hinten.
 * Idempotent, Erkennung an der Frage. Preise stehen bewusst nicht im Text.
 *
 *   php artisan tenants:run db:seed --tenants=<uuid> --option=class=SanitaerFaqSeeder --option=force=1
 */
class SanitaerFaqSeeder extends Seeder
{
    public function run(): void
    {
        $questions = array_column($this->faqs(), 0);

        // Vorhandene allgemeine Fragen hinter die Branchenfragen schieben
        FAQ::whereNotIn('question', $questions)
            ->where('sort_order', '<', 100)
            ->increment('sort_order', 100);

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

        $this->command?->info(count($this->faqs()).' Branchenfragen gesetzt.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'Was tue ich bei einem Wasserrohrbruch, bis der Notdienst da ist?',
                'Zuerst den Hauptabsperrhahn schließen – er sitzt meist im Keller oder im Hauswirtschaftsraum direkt hinter der Wasseruhr. Steht Wasser in der Nähe von Steckdosen oder dem Sicherungskasten, zusätzlich den Strom abschalten. Dann einen Betrieb mit Notdienst aus Ihrer Nähe anrufen und Fotos vom Schaden machen, bevor Sie aufwischen – die braucht die Versicherung.',
            ],
            [
                'Was kostet ein Notdienst am Wochenende?',
                'Zuschläge für Abend, Wochenende und Feiertag sind üblich und dürfen auch deutlich ausfallen. Seriöse Betriebe nennen Anfahrtspauschale und Stundensatz am Telefon, bevor sie losfahren – fragen Sie ausdrücklich danach und lassen Sie sich den Preis bestätigen. Wer das verweigert, ist der falsche Betrieb.',
            ],
            [
                'Wer darf an meiner Trinkwasser- oder Gasleitung arbeiten?',
                'Nur ein Fachbetrieb, der im Installateurverzeichnis Ihres örtlichen Wasser- oder Gasversorgers eingetragen ist. Das gilt für alle Arbeiten ab dem Hausanschluss. Fragen Sie im Zweifel danach – die Eintragung ist schnell belegt und im Schadensfall entscheidend für Versicherung und Gewährleistung.',
            ],
            [
                'Was kostet eine Badsanierung?',
                'Das hängt von Größe, Ausstattung und Zustand der Leitungen ab. Gerechnet wird in der Regel pro Quadratmeter, und die reinen Sanitärarbeiten sind nur ein Teil – Fliesen, Elektrik und Trockenbau kommen dazu. Holen Sie zwei bis drei Angebote ein, in denen Material, Arbeitszeit und Nebenkosten getrennt ausgewiesen sind, und klären Sie vorab, wer Abriss und Entsorgung übernimmt.',
            ],
            [
                'Wie oft muss die Heizung gewartet werden?',
                'Einmal im Jahr ist die Regel, bei Gas- und Ölheizungen meist vor der Heizperiode. Die Wartung hält den Verbrauch niedrig, ist oft Bedingung der Herstellergarantie und deckt Verschleiß auf, bevor die Anlage im Winter ausfällt. Viele Betriebe bieten dafür Wartungsverträge mit festem Termin an.',
            ],
            [
                'Lohnt sich der Umstieg auf eine Wärmepumpe?',
                'Das entscheidet sich am Gebäude: Dämmung, Heizflächen und die nötige Vorlauftemperatur sind wichtiger als das Baujahr. Ein Fachbetrieb rechnet das vorab durch, oft zusammen mit einem hydraulischen Abgleich. Für den Tausch gibt es staatliche Förderung; der Antrag muss vor der Beauftragung gestellt werden – viele Betriebe übernehmen das mit.',
            ],
            [
                'Mein Abfluss ist verstopft – selbst versuchen oder gleich anrufen?',
                'Bei einem einzelnen Abfluss hilft oft schon eine Saugglocke oder das Reinigen des Siphons. Chemische Rohrreiniger sind mit Vorsicht zu genießen: Sie greifen Dichtungen an und machen die spätere Arbeit für den Betrieb gefährlich. Läuft mehr als ein Abfluss nicht ab oder kommt Wasser zurück, liegt das Problem in der Grundleitung – dann gehört eine Fachfirma mit Rohrreinigung ran.',
            ],
            [
                'Wie werde ich Kalk im Wasser los?',
                'Gegen harten Kalk hilft eine Enthärtungsanlage im Hausanschluss; sie muss nach Trinkwasserverordnung regelmäßig gewartet werden. Wie hart Ihr Wasser ist, sagt Ihnen Ihr Versorger. Ein Fachbetrieb berechnet die passende Größe – zu klein dimensionierte Anlagen bringen wenig und verbrauchen trotzdem Salz.',
            ],
            [
                'Was ist ein hydraulischer Abgleich und brauche ich den?',
                'Beim hydraulischen Abgleich wird eingestellt, wie viel Heizwasser jeder Heizkörper bekommt. Ohne ihn werden die Räume nahe am Kessel zu warm und die entfernten kalt, die Pumpe läuft unnötig hoch. Er senkt den Verbrauch, ist bei vielen Förderungen Pflicht und für einen Fachbetrieb Routine.',
            ],
            [
                'Zahlt meine Versicherung den Wasserschaden?',
                'Leitungswasserschäden am Gebäude deckt in der Regel die Wohngebäudeversicherung, Schäden am Hausrat die Hausratversicherung. Melden Sie den Schaden sofort, dokumentieren Sie ihn mit Fotos und sichern Sie ihn ab – die Reparaturrechnung des Fachbetriebs und ein Trocknungsprotokoll gehören zur Abrechnung dazu.',
            ],
            [
                'Wie finde ich schnell einen Betrieb in meiner Nähe?',
                'Geben Sie oben Ihren Ort oder Ihre Postleitzahl ein und dazu die Leistung, zum Beispiel „Heizung“ oder „Notdienst“. Die Trefferliste lässt sich nach Bewertung sortieren und auf Betriebe eingrenzen, die gerade geöffnet haben. Über die Städteseiten sehen Sie alle Betriebe eines Ortes auf einen Blick.',
            ],
            [
                'Ich habe einen Sanitärbetrieb – wie komme ich ins Portal?',
                'Über „Betrieb eintragen“ im Kopf der Seite: Betrieb und Anschrift angeben, Konto anlegen, E-Mail bestätigen. Das Portal-Team schaltet den Eintrag frei, danach bekommen Sie eine E-Mail. Ist Ihr Betrieb schon gelistet, übernehmen Sie den Eintrag mit „Ist das Ihr Betrieb?“ und einem kurzen Nachweis.',
            ],
        ];
    }
}
