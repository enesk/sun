<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\FAQ;
use Illuminate\Database\Seeder;

/**
 * FAQ-Inhalte fuer kfzwerkstatt.io (#44). Laeuft im Tenant-Kontext:
 *   php artisan tenants:run db:seed --tenants=<id> --option=class=KfzFaqSeeder --option=force
 *
 * Idempotent: bestehende Eintraege werden an der Frage erkannt und
 * aktualisiert, von Hand ergaenzte FAQs bleiben unberuehrt. Preise stehen
 * bewusst nicht im Text, sondern auf der Preisseite.
 */
class KfzFaqSeeder extends Seeder
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
            // ── Für Autofahrerinnen und Autofahrer ──
            [
                'Kostet die Nutzung des Portals etwas?',
                'Nein. Werkstätten suchen, Bewertungen lesen, Öffnungszeiten und Telefonnummern einsehen – all das ist kostenlos und ohne Anmeldung möglich. Das Portal finanziert sich über die freiwilligen Premium-Einträge der Werkstätten.',
            ],
            [
                'Wie finde ich eine Werkstatt in meiner Nähe?',
                'Geben Sie oben in die Suche Ihren Ort oder Ihre Postleitzahl ein, dazu die Leistung, die ansteht – zum Beispiel „Inspektion“ oder „Klimaservice“. Die Trefferliste lässt sich nach Bewertung sortieren und auf Betriebe eingrenzen, die gerade geöffnet haben. Über die Städteseiten sehen Sie alle Werkstätten eines Ortes auf einen Blick.',
            ],
            [
                'Darf eine freie Werkstatt mein Auto warten, ohne dass die Herstellergarantie erlischt?',
                'Ja. Seit der EU-Gruppenfreistellungsverordnung dürfen Wartung und Reparatur in jeder Fachwerkstatt erledigt werden, ohne dass die gesetzliche Gewährleistung oder die Herstellergarantie verfällt. Voraussetzung ist, dass nach Herstellervorgabe gearbeitet wird und Teile in Erstausrüsterqualität verwendet werden. Lassen Sie sich die Arbeiten im Serviceheft oder digital im Serviceplan bestätigen. Bei einer Anschlussgarantie des Herstellers können abweichende Bedingungen im Vertrag stehen – die lohnt sich vorher zu lesen.',
            ],
            [
                'Was kostet eine Inspektion?',
                'Das hängt vom Fahrzeug, vom Umfang und vom Stundensatz der Werkstatt ab. Eine kleine Inspektion liegt meist im unteren dreistelligen Bereich, eine große mit Zahnriemen, Bremsflüssigkeit oder Zündkerzen deutlich darüber. Lassen Sie sich vorab eine Kostenschätzung geben und fragen Sie, was genau enthalten ist – „Inspektion“ ist kein geschützter Begriff, der Umfang unterscheidet sich von Betrieb zu Betrieb.',
            ],
            [
                'Wie oft muss mein Auto zur Hauptuntersuchung?',
                'Neuwagen fahren drei Jahre bis zur ersten Hauptuntersuchung, danach alle zwei Jahre. Wohnmobile über 3,5 Tonnen und Fahrzeuge zur Personenbeförderung haben eigene Fristen. Verpasste Termine kosten ab zwei Monaten Überziehung eine Gebühr für die erweiterte Prüfung, ab mehr Verzug kommen Bußgeld und ein Punkt dazu. Viele Werkstätten nehmen die Hauptuntersuchung mit einem Prüfdienst im Haus ab und prüfen das Fahrzeug vorab durch.',
            ],
            [
                'Was tun, wenn das Auto bei der Hauptuntersuchung durchfällt?',
                'Bei erheblichen Mängeln bekommen Sie die Plakette nicht, haben aber einen Monat Zeit für die Nachprüfung – und dort wird nur noch der beanstandete Punkt geprüft, das ist deutlich günstiger als eine komplette Wiederholung. Sie dürfen die Mängel in jeder Werkstatt Ihrer Wahl beheben lassen, nicht zwingend dort, wo geprüft wurde.',
            ],
            [
                'Muss ich jedes Jahr die Reifen wechseln lassen?',
                'In Deutschland gilt eine situative Winterreifenpflicht: Bei Glatteis, Schneeglätte, Schneematsch, Eis- oder Reifglätte müssen Reifen mit dem Alpine-Symbol montiert sein. Die Faustregel „von Oktober bis Ostern“ hat sich dafür eingebürgert. Beim Wechsel prüft die Werkstatt Profiltiefe und Alter mit – unter 1,6 Millimeter ist der Reifen gesetzlich am Ende, bei Winterreifen wird meist schon ab 4 Millimetern zum Tausch geraten.',
            ],
            [
                'Wann ist ein Klimaservice fällig?',
                'Die meisten Hersteller empfehlen alle zwei Jahre einen Klimaservice. Die Anlage verliert jährlich Kältemittel, bei zu geringer Füllmenge kühlt sie schlechter und der Kompressor läuft trocken – das wird teuer. Riecht die Lüftung muffig, hilft zusätzlich eine Desinfektion und ein neuer Innenraumfilter.',
            ],
            [
                'Was mache ich nach einem Unfall?',
                'Sichern Sie die Unfallstelle, dokumentieren Sie den Schaden mit Fotos und tauschen Sie die Daten aus. Sind Sie nicht schuld, dürfen Sie Werkstatt und Sachverständigen frei wählen – die gegnerische Versicherung darf Sie nicht in eine Partnerwerkstatt zwingen. Viele Betriebe auf diesem Portal übernehmen die Unfallinstandsetzung und die Abwicklung mit der Versicherung gleich mit.',
            ],
            [
                'Bekomme ich einen Kostenvoranschlag, bevor repariert wird?',
                'Fragen Sie danach – seriöse Betriebe machen das selbstverständlich. Ein Kostenvoranschlag ist keine Festpreisgarantie, darf aber nur in engen Grenzen überschritten werden; bei deutlich höheren Kosten muss die Werkstatt Sie vorher fragen. Halten Sie den Auftrag schriftlich fest und lassen Sie sich auf Wunsch die ausgetauschten Altteile zeigen.',
            ],
            [
                'Sind die Bewertungen echt?',
                'Jede Bewertung durchläuft eine Prüfung, bevor sie öffentlich wird; Spam, Beleidigungen und offensichtliche Fälschungen sortieren wir aus. Eine hundertprozentige Garantie, dass hinter jeder Bewertung ein echter Werkstattbesuch steckt, kann allerdings niemand geben. Achten Sie auf Bewertungen mit konkretem Inhalt – die sagen mehr aus als eine reine Sternezahl.',
            ],
            [
                'Wie melde ich falsche Angaben zu einer Werkstatt?',
                'Auf jedem Profil gibt es den Punkt „Änderung vorschlagen“. Dort können Sie Adresse, Telefonnummer, Öffnungszeiten oder Leistungen korrigieren. Wir prüfen den Hinweis und pflegen ihn ein. Unangemessene Bewertungen melden Sie direkt an der Bewertung.',
            ],

            // ── Für Werkstätten ──
            [
                'Was kostet ein Eintrag für meine Werkstatt?',
                'Der Basiseintrag ist dauerhaft kostenlos: Name, Adresse, Kontaktdaten, Öffnungszeiten, Beschreibung und Logo. Wer mehr möchte – werbefreies Profil, Fotogalerie, Leistungskatalog, Antworten auf Bewertungen, Statistiken und Kundenanfragen direkt im eigenen Bereich –, findet die aktuellen Pakete und Preise auf der Seite „Für Werkstätten“.',
            ],
            [
                'Meine Werkstatt ist schon gelistet. Wie übernehme ich den Eintrag?',
                'Rufen Sie Ihr Profil auf und wählen Sie „Ist das Ihr Betrieb?“. Nach der Anmeldung laden Sie einen kurzen Nachweis hoch, zum Beispiel die Gewerbeanmeldung oder einen Handelsregisterauszug. Nach der Prüfung gehört der Eintrag Ihnen und Sie pflegen ihn selbst – in der Regel innerhalb von zwei Werktagen.',
            ],
            [
                'Wie trage ich eine neue Werkstatt ein?',
                'Über „Betrieb eintragen“ im Kopf der Seite. Sie geben Betrieb und Anschrift an, legen ein Konto an und bestätigen Ihre E-Mail-Adresse. Der Eintrag wird vom Portal-Team freigeschaltet; sobald er online ist, bekommen Sie eine E-Mail.',
            ],
            [
                'Wie bekomme ich Bewertungen von meinen Kunden?',
                'In Ihrem Betriebsbereich finden Sie einen Bewertungslink und einen QR-Code zum Ausdrucken – beides führt direkt zum Bewertungsformular Ihres Profils. Am besten funktioniert die Bitte bei der Fahrzeugübergabe oder in der Rechnungsmail. Auf veröffentlichte Bewertungen können Sie öffentlich antworten.',
            ],
            [
                'Kann ich Stellenanzeigen schalten?',
                'Ja. Offene Stellen für Kfz-Mechatronikerinnen, Servicetechniker, Auszubildende oder Meister erscheinen in der Jobbörse des Portals und auf Ihrem Profil. Das Schalten von Anzeigen gehört zu den kostenpflichtigen Paketen, die Verwaltung läuft über Ihren Betriebsbereich.',
            ],
        ];
    }
}
