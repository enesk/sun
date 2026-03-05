<?php

namespace Database\Seeders;

use App\Models\Portal\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::count() > 0) {
            $this->command->warn('FAQs already exist — skipping.');
            return;
        }

        $faqs = $this->getFaqs();

        foreach ($faqs as $index => $faq) {
            Faq::create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'page' => $faq['page'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        $count = count($faqs);
        $this->command?->info("Seeded {$count} FAQs.");
    }

    private function getFaqs(): array
    {
        return [
            // === TEIL A: Startseite + FAQ-Seite (Top 10) ===

            [
                'question' => 'Ist die Nutzung des Portals für mich als Suchende kostenlos?',
                'answer' => 'Ja, absolut. Sie können sämtliche Firmeneinträge durchsuchen, Bewertungen lesen und Kontaktdaten einsehen — ohne Registrierung und ohne versteckte Kosten. Das Portal finanziert sich über optionale Premium-Einträge der Firmeninhaber.',
                'page' => 'home',
            ],
            [
                'question' => 'Was kostet ein Firmeneintrag?',
                'answer' => 'Der Basiseintrag ist dauerhaft kostenlos. Sie können Ihren Firmennamen, Adresse, Kontaktdaten, eine Beschreibung und Ihr Logo hinterlegen — ohne Zeitlimit. Wer mehr möchte, kann auf Premium upgraden: für 9,90 EUR pro Monat oder 99 EUR im Jahr bekommen Sie unter anderem hervorgehobene Platzierung in den Suchergebnissen, eine Bildergalerie und die Möglichkeit, auf Bewertungen zu antworten.',
                'page' => 'home',
            ],
            [
                'question' => 'Sind die Bewertungen auf dem Portal echt?',
                'answer' => 'Wir nehmen die Qualität der Bewertungen ernst. Jede Bewertung wird von unserem Team geprüft, bevor sie veröffentlicht wird. Spam, Beleidigungen oder offensichtliche Fake-Bewertungen werden aussortiert. Trotzdem ein ehrlicher Hinweis: Wir können nicht zu 100 % garantieren, dass jede Bewertung von einem tatsächlichen Kunden stammt — aber wir tun unser Bestes.',
                'page' => 'home',
            ],
            [
                'question' => 'Wie kann ich meinen bestehenden Eintrag übernehmen?',
                'answer' => 'Wenn Ihre Firma bereits im Portal gelistet ist, können Sie den Eintrag kostenlos übernehmen. Rufen Sie einfach Ihr Firmenprofil auf und klicken Sie auf "Ist das Ihr Unternehmen?". Nach einer kurzen Registrierung und Verifizierung — wir prüfen z. B. Ihre Gewerbeanmeldung — gehört der Eintrag Ihnen. Das dauert in der Regel weniger als 48 Stunden.',
                'page' => 'home',
            ],
            [
                'question' => 'Wie finde ich einen bestimmten Dienstleister in meiner Stadt?',
                'answer' => 'Am schnellsten geht es über die Suchleiste auf der Startseite — geben Sie einfach ein, was Sie suchen, zum Beispiel "Maler" oder "Zahnarzt". Sie können die Ergebnisse anschließend nach Stadt, Kategorie und Bewertung filtern. Alternativ finden Sie über die Städteseiten direkt alle Unternehmen in Ihrer Region.',
                'page' => 'home',
            ],
            [
                'question' => 'Was bringt mir Premium konkret?',
                'answer' => 'Mit Premium stehen Sie in den Suchergebnissen über den kostenlosen Einträgen — das allein bringt Ihnen deutlich mehr Sichtbarkeit. Dazu können Sie auf Kundenbewertungen antworten, bis zu 20 Bilder in einer Galerie hochladen, Ihre Öffnungszeiten anzeigen und detaillierte Statistiken zu Ihren Profilaufrufen einsehen. Außerdem können Sie als Premium-Nutzer Stellenanzeigen veröffentlichen.',
                'page' => 'home',
            ],
            [
                'question' => 'Kann ich eine Bewertung abgeben, ohne mich zu registrieren?',
                'answer' => 'Ja, das geht. Sie müssen sich nicht registrieren, um eine Bewertung zu schreiben. Wählen Sie einfach Ihre Sternebewertung, schreiben Sie Ihren Erfahrungsbericht und geben Sie optional Ihren Namen an. Wenn Sie anonym bleiben möchten, ist das auch in Ordnung.',
                'page' => 'home',
            ],
            [
                'question' => 'Kann ich Premium erst einmal kostenlos testen?',
                'answer' => 'Ja — und zwar 30 Tage lang, ohne Kreditkarte. Wenn Sie Ihre Firma neu eintragen oder einen bestehenden Eintrag übernehmen, startet automatisch eine kostenlose Testphase. Sie haben vollen Zugriff auf alle Premium-Funktionen. Nach 30 Tagen entscheiden Sie, ob Sie dabei bleiben oder zum kostenlosen Basiseintrag wechseln.',
                'page' => 'home',
            ],
            [
                'question' => 'Wer steckt hinter diesem Portal?',
                'answer' => 'Das Portal wird von einem unabhängigen Team betrieben, das sich auf lokale Branchenverzeichnisse spezialisiert hat. Unser Ziel: Lokale Unternehmen sichtbarer machen und Menschen dabei helfen, die richtigen Dienstleister in ihrer Nähe zu finden. Im Impressum finden Sie alle Angaben zum Betreiber.',
                'page' => 'home',
            ],
            [
                'question' => 'Wie trage ich meine Firma ein?',
                'answer' => 'Das geht in wenigen Minuten. Klicken Sie auf "Firma eintragen" und folgen Sie dem Schritt-für-Schritt-Formular: Firmendaten, Adresse, Kontakt — fertig. Sie können direkt ein Logo hochladen und eine Beschreibung hinterlegen. Ihr Eintrag ist sofort nach dem Absenden sichtbar.',
                'page' => 'home',
            ],

            // === TEIL B: Nur FAQ-Seite (10 weitere) ===

            [
                'question' => 'Kann ich auf Bewertungen antworten?',
                'answer' => 'Ja — wenn Sie Premium-Nutzer sind. Im Dashboard finden Sie unter "Bewertungen" alle eingegangenen Kundenmeinungen. Dort können Sie direkt antworten, zum Beispiel um sich für positives Feedback zu bedanken oder bei Kritik Ihre Sicht der Dinge darzustellen. Für Free-Nutzer ist diese Funktion gesperrt, aber Sie sehen, dass Bewertungen eingegangen sind.',
                'page' => 'faq',
            ],
            [
                'question' => 'Was bedeutet das Premium-Badge neben einem Firmennamen?',
                'answer' => 'Das Badge zeigt an, dass der Firmeninhaber seinen Eintrag aktiv pflegt und in ein erweitertes Profil investiert. Premium-Einträge haben in der Regel mehr Informationen: Bildergalerien, Öffnungszeiten, Antworten auf Bewertungen. Für Sie als Suchende ist das ein gutes Zeichen — der Betrieb nimmt seinen Auftritt ernst.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie lade ich Bilder in mein Firmenprofil hoch?',
                'answer' => 'Ein Logo können Sie bereits im kostenlosen Eintrag hochladen. Für eine vollständige Bildergalerie mit bis zu 20 Fotos und einem Cover-Bild benötigen Sie Premium. Gehen Sie dafür in Ihr Dashboard unter "Profil bearbeiten" und nutzen Sie den Upload-Bereich. Unterstützt werden JPG, PNG und WebP — idealerweise in guter Qualität.',
                'page' => 'faq',
            ],
            [
                'question' => 'Kann ich eine Bewertung nachträglich ändern oder löschen?',
                'answer' => 'Eine direkte Bearbeitung durch Sie ist aktuell nicht möglich. Wenn Sie Ihre Bewertung korrigieren oder entfernen lassen möchten, schreiben Sie uns bitte über das Kontaktformular. Unser Team kümmert sich zeitnah darum.',
                'page' => 'faq',
            ],
            [
                'question' => 'Kann ich mein Premium-Abo jederzeit kündigen?',
                'answer' => 'Ja, ohne Wenn und Aber. Sie können Ihr Abo jederzeit im Dashboard unter "Einstellungen" kündigen — kein Anruf nötig, kein Kleingedrucktes. Ihr Premium-Status bleibt bis zum Ende des bezahlten Zeitraums aktiv. Danach wechseln Sie automatisch zum kostenlosen Basiseintrag, und Ihre Grunddaten bleiben erhalten.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie kann ich eine Stellenanzeige veröffentlichen?',
                'answer' => 'Stellenanzeigen sind ein Premium-Feature. Im Dashboard finden Sie unter "Stellenanzeigen" die Möglichkeit, einen Job zu erstellen: Titel, Beschreibung, Beschäftigungsart und optional eine Gehaltsspanne angeben — fertig. Ihre Anzeige erscheint auf Ihrem Firmenprofil und in der Jobbörse des Portals. Sie läuft automatisch nach 30 Tagen ab und kann jederzeit verlängert werden.',
                'page' => 'faq',
            ],
            [
                'question' => 'Gibt es eine App für das Portal?',
                'answer' => 'Eine eigene App gibt es derzeit nicht. Aber das Portal ist vollständig für Smartphones optimiert — Sie können es einfach im Browser auf Ihrem Handy nutzen. Tipp: Speichern Sie die Seite als Lesezeichen auf Ihrem Startbildschirm, dann haben Sie quasi eine App.',
                'page' => 'faq',
            ],
            [
                'question' => 'Kann ich meine Öffnungszeiten hinterlegen?',
                'answer' => 'Ja, als Premium-Nutzer können Sie Ihre Öffnungszeiten für jeden Wochentag hinterlegen. Diese werden auf Ihrem Firmenprofil angezeigt, inklusive einer Live-Anzeige, ob Sie gerade geöffnet oder geschlossen haben. Das ist besonders hilfreich für Laufkundschaft, die spontan vorbeikommen möchte.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie aktuell sind die Firmendaten im Portal?',
                'answer' => 'Firmeninhaber können ihre Daten jederzeit selbst aktualisieren — und viele tun das auch regelmäßig. Bei Einträgen, die noch nicht von ihrem Inhaber übernommen wurden, können die Daten aus dem ursprünglichen Import stammen und vereinzelt veraltet sein. Sie können in solchen Fällen über den Button "Änderung vorschlagen" auf der Firmenseite eine Korrektur melden.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie sehe ich, ob mein Eintrag erfolgreich ist?',
                'answer' => 'Im Dashboard finden Sie unter "Statistiken" eine Übersicht Ihrer Profilaufrufe und Kontaktklicks. Im kostenlosen Eintrag sehen Sie die Gesamtzahlen. Premium-Nutzer bekommen zusätzlich Wochen-Trends, Herkunftsanalysen und Vergleichswerte — so erkennen Sie auf einen Blick, ob Ihr Eintrag Ihnen neue Kunden bringt.',
                'page' => 'faq',
            ],
        ];
    }
}
