<?php

namespace Database\Seeders;

use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Portal\PostTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        if (Post::count() > 0) {
            $this->command?->warn('Blog posts already exist — skipping.');
            return;
        }

        // 1. Kategorien anlegen
        $categories = $this->seedCategories();
        $this->command?->info('Created ' . count($categories) . ' blog categories.');

        // 2. Tags anlegen
        $tags = $this->seedTags();
        $this->command?->info('Created ' . count($tags) . ' blog tags.');

        // 3. Artikel anlegen
        $articles = $this->getArticles();
        $count = 0;

        foreach ($articles as $article) {
            $category = $categories[$article['category']] ?? null;

            $post = Post::create([
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'body' => $article['body'],
                'category_id' => $category?->id,
                'author_id' => 1,
                'status' => 'published',
                'published_at' => now()->subDays(count($articles) - $count),
                'meta_title' => $article['meta_title'],
                'meta_description' => $article['meta_description'],
                'reading_time_minutes' => $article['reading_time'],
            ]);

            // Tags zuweisen
            $tagIds = [];
            foreach ($article['tags'] as $tagName) {
                $slug = Str::slug($tagName);
                if (isset($tags[$slug])) {
                    $tagIds[] = $tags[$slug]->id;
                }
            }
            if ($tagIds) {
                $post->tags()->sync($tagIds);
            }

            $count++;
        }

        $this->command?->info("Seeded {$count} blog posts.");
    }

    private function seedCategories(): array
    {
        $definitions = [
            ['name' => 'Ratgeber', 'slug' => 'ratgeber', 'description' => 'Praktische Tipps rund um Handwerker, Dienstleister und lokale Firmen', 'sort_order' => 1],
            ['name' => 'Bewertungen', 'slug' => 'bewertungen', 'description' => 'Alles zum Thema Online-Bewertungen — schreiben, lesen, antworten', 'sort_order' => 2],
            ['name' => 'Kosten', 'slug' => 'kosten', 'description' => 'Was kosten Handwerker und Dienstleistungen? Preisübersichten und Kalkulationshilfen', 'sort_order' => 3],
            ['name' => 'Online-Präsenz', 'slug' => 'online-praesenz', 'description' => 'Tipps für Firmeninhaber: Sichtbarkeit im Netz, Einträge, lokales Marketing', 'sort_order' => 4],
        ];

        $map = [];
        foreach ($definitions as $def) {
            $map[$def['name']] = PostCategory::create($def);
        }
        return $map;
    }

    private function seedTags(): array
    {
        $tagNames = [
            'Handwerker', 'Tipps', 'Suche', 'Vertrauen', 'Bewertungen',
            'Kundenmeinung', 'Online-Reputation', 'Maler', 'Kosten', 'Preise',
            'Handwerkerpreise', 'Checkliste', 'Beauftragung', 'Vertrag',
            'Firmeneintrag', 'Kostenlos', 'Online-Sichtbarkeit', 'Selbstständige',
            'Rechnung', 'Verbraucherschutz', 'SEO', 'Lokale Suche', 'Google',
            'Online-Marketing', 'Firmeninhaber', 'Elektriker', 'Elektroinstallation',
            'Sicherheit', 'Negative Bewertung', 'Antwort', 'Reputation',
            'Renovierung', 'Planung', 'Budget',
        ];

        $map = [];
        foreach ($tagNames as $name) {
            $slug = Str::slug($name);
            $tag = PostTag::create(['name' => $name, 'slug' => $slug]);
            $map[$slug] = $tag;
        }
        return $map;
    }

    private function getArticles(): array
    {
        return [
            // ARTIKEL 1
            [
                'title' => 'Guten Handwerker finden: 7 Tipps die wirklich helfen',
                'slug' => 'guten-handwerker-finden-tipps',
                'meta_title' => 'Guten Handwerker finden: 7 Tipps die wirklich helfen (2026)',
                'meta_description' => 'Sie suchen einen zuverlässigen Handwerker? Diese 7 praxiserprobten Tipps helfen Ihnen, den richtigen Betrieb zu finden — ohne böse Überraschungen.',
                'excerpt' => 'Einen guten Handwerker zu finden ist schwieriger als es klingt. Mit diesen 7 Tipps vermeiden Sie die häufigsten Fehler und finden einen Betrieb, dem Sie vertrauen können.',
                'category' => 'Ratgeber',
                'tags' => ['Handwerker', 'Tipps', 'Suche', 'Vertrauen'],
                'reading_time' => 7,
                'body' => <<<'BODY'
# Guten Handwerker finden: 7 Tipps die wirklich helfen

Hand aufs Herz: Wann haben Sie das letzte Mal einen Handwerker gebraucht und sofort den Richtigen gefunden? Wenn Sie jetzt zögern — willkommen im Club. Die Suche nach einem zuverlässigen Handwerker fühlt sich manchmal an wie die Suche nach der Nadel im Heuhaufen. Nur dass der Heuhaufen auch noch Angebote schickt, die man nicht versteht.

Ich beschäftige mich seit Jahren mit der Frage, wie man lokale Dienstleister findet, denen man vertrauen kann. Und ehrlich gesagt: Es gibt kein Patentrezept. Aber es gibt ein paar Dinge, die die Suche deutlich einfacher machen.

## 1. Fragen Sie im Bekanntenkreis — aber richtig

Der Klassiker, klar. Aber die meisten fragen falsch. "Kennst du einen guten Maler?" bringt selten brauchbare Antworten. Fragen Sie stattdessen konkret: "Wer hat bei dir zuletzt die Wohnung gestrichen und was hat das ungefähr gekostet?" Das liefert sofort verwertbare Informationen.

Ein Tipp aus eigener Erfahrung: Die besten Empfehlungen kommen von Leuten, die in den letzten 12 Monaten einen Handwerker hatten. Alles darüber hinaus ist oft veraltet — Betriebe ändern sich, Mitarbeiter wechseln, Qualität schwankt.

## 2. Nutzen Sie Branchenportale statt nur Google

Google zeigt Ihnen Ergebnisse basierend auf SEO-Budget und Anzeigen. Das heißt nicht automatisch, dass der erste Treffer auch der beste Handwerker ist. Branchenportale funktionieren anders: Dort sind Betriebe nach Kategorien und Regionen sortiert, und Sie sehen echte Bewertungen von anderen Kunden.

Der Vorteil gegenüber Google Maps? Branchenportale zeigen oft detailliertere Profile — mit Öffnungszeiten, Leistungsbeschreibungen und Fotos von tatsächlichen Projekten. Das gibt ein deutlich besseres Bild als ein Punkt auf der Karte.

## 3. Lesen Sie Bewertungen — aber zwischen den Zeilen

Bewertungen sind Gold wert. Aber nicht jede Fünf-Sterne-Bewertung ist echt, und nicht jede Ein-Stern-Bewertung ist fair. Achten Sie auf diese Muster:

- **Detaillierte Bewertungen** sind wertvoller als kurze. "Super, gerne wieder!" sagt wenig. "Hat das Bad in 4 Tagen komplett gefliest, pünktlich angefangen, sauber hinterlassen" — das ist eine Aussage.
- **Antworten des Inhabers** auf negative Bewertungen zeigen Professionalität. Wer sachlich auf Kritik eingeht, nimmt seinen Job ernst.
- **Das Verhältnis zählt.** 50 Bewertungen mit 4,2 Sternen sind aussagekräftiger als 3 Bewertungen mit 5,0 Sternen.

Ein Betrieb ohne eine einzige negative Bewertung? Da werde ich ehrlich gesagt eher skeptisch als begeistert.

## 4. Holen Sie mindestens drei Angebote ein

Ja, das kostet Zeit. Aber es lohnt sich. Drei Angebote geben Ihnen ein Gefühl für den Marktpreis und schützen Sie vor überhöhten Rechnungen. Wichtig dabei:

- Beschreiben Sie die Arbeit bei allen drei Betrieben identisch
- Bitten Sie um eine Aufschlüsselung nach Material und Arbeitszeit
- Fragen Sie nach dem voraussichtlichen Zeitrahmen

Das günstigste Angebot ist übrigens nicht automatisch das beste. Wenn ein Angebot deutlich unter den anderen liegt, fragen Sie nach warum. Manchmal fehlen Positionen, manchmal wird an der Qualität gespart.

## 5. Prüfen Sie die Basics

Bevor Sie jemanden beauftragen, checken Sie:

- **Gewerbeanmeldung:** Ein seriöser Betrieb hat eine. Punkt.
- **Meisterbrief:** Bei zulassungspflichtigen Gewerken (Elektro, Sanitär, Heizung) ist der Meisterbrief Pflicht.
- **Versicherung:** Fragen Sie nach einer Betriebshaftpflicht. Wenn beim Arbeiten etwas kaputt geht, sollte das abgesichert sein.
- **Impressum auf der Website:** Klingt banal, aber ein fehlendes Impressum ist ein Warnsignal.

Übertrieben? Vielleicht. Aber ich kenne genug Geschichten von Leuten, die sich das gespart haben und es bereut haben.

## 6. Achten Sie auf Kommunikation

Der beste Indikator für die spätere Zusammenarbeit ist die Kommunikation VOR dem Auftrag. Meldet sich der Betrieb zeitnah zurück? Werden Ihre Fragen verständlich beantwortet? Gibt es einen festen Ansprechpartner?

Eine Faustregel, die sich bewährt hat: Wenn ein Handwerker drei Tage braucht, um auf Ihre Anfrage zu antworten, wird er während der Arbeit nicht plötzlich kommunikativer.

## 7. Vertrauen Sie Ihrem Bauchgefühl

Nach all den rationalen Tipps klingt das vielleicht seltsam. Aber wenn beim Vor-Ort-Termin irgendetwas nicht stimmt — der Handwerker hört nicht richtig zu, macht unrealistische Versprechungen oder drückt auf schnelle Unterschrift — dann ist Ihr Bauchgefühl wahrscheinlich richtig.

Ein guter Handwerker nimmt sich Zeit für Ihre Fragen, erklärt was er vorhat und gibt Ihnen Bedenkzeit. Druck ist nie ein gutes Zeichen.

## Fazit

Den perfekten Handwerker gibt es nicht. Aber mit diesen sieben Schritten erhöhen Sie Ihre Chancen enorm, einen zu finden, mit dem die Zusammenarbeit funktioniert. Das Wichtigste: Nehmen Sie sich die Zeit für die Suche. Eine Stunde investiert in Recherche spart Ihnen im Zweifel tausende Euro und sehr viel Ärger.
BODY,
            ],

            // ARTIKEL 2
            [
                'title' => 'Gute Bewertung schreiben: So helfen Sie anderen wirklich',
                'slug' => 'gute-bewertung-schreiben-tipps',
                'meta_title' => 'Gute Bewertung schreiben: So helfen Sie anderen wirklich',
                'meta_description' => 'Eine hilfreiche Bewertung schreiben ist gar nicht schwer. Wir zeigen Ihnen, worauf es ankommt — mit konkreten Beispielen für verschiedene Branchen.',
                'excerpt' => 'Bewertungen helfen anderen bei der Entscheidung — aber nur, wenn sie gut geschrieben sind. Was eine wirklich hilfreiche Bewertung ausmacht und welche Fehler Sie vermeiden sollten.',
                'category' => 'Bewertungen',
                'tags' => ['Bewertungen', 'Tipps', 'Kundenmeinung', 'Online-Reputation'],
                'reading_time' => 6,
                'body' => <<<'BODY'
# Gute Bewertung schreiben: So helfen Sie anderen wirklich

Sie waren beim Friseur und es war großartig. Oder beim Elektriker und es war eine Katastrophe. In beiden Fällen denken Sie: "Da sollte ich mal eine Bewertung schreiben." Und dann sitzen Sie da und tippen "Alles super, gerne wieder" — oder "Nie wieder!!!". Beides nett gemeint. Beides hilft niemandem.

Eine gute Bewertung zu schreiben ist keine Raketenwissenschaft. Aber es gibt ein paar Dinge, die den Unterschied machen zwischen "naja, noch eine Bewertung" und "okay, das hat mir bei meiner Entscheidung wirklich geholfen".

## Warum Ihre Bewertung wichtig ist

Kurzer Realitätscheck: 87% der Deutschen lesen Online-Bewertungen, bevor sie einen lokalen Dienstleister beauftragen. Ihre Bewertung beeinflusst also tatsächlich, ob jemand den Betrieb kontaktiert oder weiterscrollt.

Das ist eine Verantwortung, die man ernst nehmen sollte. Nicht im Sinne von "schreiben Sie nur Positives" — sondern im Sinne von "schreiben Sie etwas, das anderen bei der Entscheidung hilft".

## Die 5 Elemente einer hilfreichen Bewertung

### 1. Kontext geben

Was haben Sie machen lassen? "Maler bewertet" sagt wenig. "Zwei Zimmer (zusammen ca. 45qm) streichen lassen, Altbau mit hohen Decken" — das können andere einordnen.

Warum das wichtig ist: Ein Maler, der kleine Räume perfekt streicht, muss nicht automatisch der Richtige für eine 200qm-Wohnung sein. Kontext hilft bei der Einschätzung.

### 2. Konkret werden

Statt: "War pünktlich und ordentlich."
Besser: "Termin war für 8 Uhr vereinbart, Handwerker stand um 7:55 Uhr vor der Tür. Nach Abschluss der Arbeiten wurde alles mit Folie abgedeckte wieder freigeräumt, Boden gesaugt."

Der Unterschied? Die zweite Version zeichnet ein Bild. Man kann sich vorstellen, wie die Zusammenarbeit ablief. Die erste ist austauschbar.

### 3. Preistransparenz

Das ist der Punkt, den die meisten weglassen — und der für andere am wertvollsten ist. Sie müssen keine exakte Rechnung veröffentlichen. Aber eine Einordnung wie "für zwei Zimmer haben wir etwa 800 Euro bezahlt, inklusive Material" hilft enorm.

Warum? Weil die häufigste Frage vor einer Beauftragung lautet: "Was wird das ungefähr kosten?" Und Google kann diese Frage nur beantworten, wenn Menschen wie Sie Preise teilen.

### 4. Ehrlich bleiben — auch bei Kritik

Niemand erwartet Perfektion. Wenn der Handwerker eine Stunde zu spät kam, aber danach hervorragende Arbeit geliefert hat — schreiben Sie genau das. "Pünktlichkeit war nicht seine Stärke (kam eine Stunde später als vereinbart), aber die Arbeit selbst war einwandfrei."

Das ist fairer als fünf Sterne oder zwei Sterne. Es ist die Wahrheit. Und die Wahrheit hilft anderen am meisten.

### 5. Zeitnah schreiben

Schreiben Sie die Bewertung innerhalb einer Woche nach Abschluss der Arbeit. Warum? Weil Details verblassen. Nach drei Monaten erinnern Sie sich an "war gut" oder "war schlecht" — aber nicht mehr an die konkreten Dinge, die es gut oder schlecht gemacht haben.

Mein persönlicher Trick: Ich mache direkt nach dem Termin eine Notiz auf dem Handy — drei Stichpunkte. Die baue ich dann abends zur Bewertung aus.

## Was Sie vermeiden sollten

- **Emotionale Ausbrüche.** "Der schlimmste Betrieb ALLER ZEITEN!!!" hilft niemandem. Atmen Sie durch, warten Sie einen Tag, dann schreiben Sie sachlich.
- **Drohungen.** "Wenn Sie mir nicht X erstatten, schreibe ich eine schlechte Bewertung" — das ist Erpressung und in manchen Fällen sogar strafbar.
- **Bewertungen für Dinge, die nicht der Betrieb zu verantworten hat.** Ein Stern weil der Parkplatz voll war? Unfair.
- **Copy-Paste-Bewertungen.** Den gleichen Text für verschiedene Betriebe nutzen? Bitte nicht.

## Wie viele Sterne sind angemessen?

Meine Daumenregel:

| Sterne | Bedeutung |
|--------|-----------|
| 5 | Alles war hervorragend, nichts zu beanstanden |
| 4 | Gute Arbeit, Kleinigkeiten hätten besser sein können |
| 3 | Okay, aber deutliche Schwächen |
| 2 | Überwiegend unzufrieden, einzelne positive Aspekte |
| 1 | Gravierende Probleme, würde ich nicht empfehlen |

Die meisten Bewertungen sollten irgendwo zwischen 3 und 5 landen. Wenn Sie ehrlich sind, ist das auch völlig in Ordnung.

## Fazit

Eine gute Bewertung kostet Sie fünf Minuten. Aber sie hilft dutzenden anderen Menschen bei einer Entscheidung, die sie sonst im Dunkeln treffen müssten. Schreiben Sie, was Sie sich selbst gewünscht hätten zu lesen, bevor Sie den Betrieb beauftragt haben. Mehr braucht es nicht.
BODY,
            ],

            // ARTIKEL 3
            [
                'title' => 'Was kostet ein Maler? Preise pro Stunde und Quadratmeter (2026)',
                'slug' => 'was-kostet-ein-maler-preise',
                'meta_title' => 'Was kostet ein Maler? Preise pro Stunde & qm (2026)',
                'meta_description' => 'Malerkosten 2026: Was kostet ein Maler pro Stunde und pro Quadratmeter? Aktuelle Preisübersicht mit Beispielrechnungen für typische Arbeiten.',
                'excerpt' => 'Maler beauftragen, aber keine Ahnung was das kostet? Hier finden Sie aktuelle Preise pro Stunde und Quadratmeter — mit konkreten Beispielrechnungen für verschiedene Arbeiten.',
                'category' => 'Kosten',
                'tags' => ['Maler', 'Kosten', 'Preise', 'Handwerkerpreise'],
                'reading_time' => 8,
                'body' => <<<'BODY'
# Was kostet ein Maler? Preise pro Stunde und Quadratmeter (2026)

"Was kostet das ungefähr?" — die Frage, die jeder stellt und auf die niemand eine klare Antwort gibt. Maler sagen "kommt drauf an", Google zeigt Spannen von 30 bis 80 Euro pro Stunde, und am Ende steht man mit der gleichen Unsicherheit da wie vorher.

Ich hab mir die Mühe gemacht, aktuelle Malerpreise zusammenzutragen — nicht aus Werbeprospekten, sondern aus echten Angeboten und Erfahrungsberichten. Die Zahlen gelten für 2026 in Deutschland, wobei es regionale Unterschiede gibt (dazu gleich mehr).

## Malerkosten auf einen Blick

### Stundensätze

| Qualifikation | Stundensatz (netto) |
|---------------|--------------------|
| Malergeselle | 35–50 EUR |
| Malermeister | 45–65 EUR |
| Speziallackierer | 50–75 EUR |
| Lehrling (mit Geselle) | 25–35 EUR |

**Wichtig:** Netto heißt ohne Mehrwertsteuer. Auf Ihrer Rechnung kommen noch 19% drauf. Ein Stundensatz von 45 EUR netto sind also knapp 54 EUR brutto.

### Quadratmeterpreise für typische Arbeiten

| Arbeit | Preis pro qm (inkl. Material) |
|--------|-------------------------------|
| Wände streichen (einfarbig weiß) | 8–15 EUR |
| Decke streichen | 10–18 EUR |
| Tapezieren (Raufaser) | 12–20 EUR |
| Tapezieren (Mustertapete) | 18–35 EUR |
| Alte Tapete entfernen + neu | 20–40 EUR |
| Lackieren (Türen, Fensterrahmen) | 60–120 EUR pro Stück |
| Fassade streichen | 25–45 EUR |

## Was beeinflusst den Preis?

### Region

Der größte Faktor, über den niemand spricht. Ein Maler in München oder Hamburg verlangt 20–30% mehr als einer in Sachsen oder Thüringen. Das liegt nicht an Gier, sondern an Mieten, Löhnen und Lebenshaltungskosten.

Grobe Orientierung:
- **Ballungsräume** (München, Hamburg, Frankfurt, Stuttgart): oberes Drittel der Preisspanne
- **Mittelstädte** (Hannover, Leipzig, Nürnberg): mittleres Drittel
- **Ländliche Regionen**: unteres Drittel

### Zustand der Oberflächen

Eine glatte, bereits gestrichene Wand nochmal weiß streichen? Das geht schnell und günstig. Eine verrauchte Altbauwand mit drei Schichten Tapete abreißen, Risse spachteln und dann streichen? Das dauert dreimal so lang.

Faustregel: Je mehr Vorarbeit nötig ist, desto teurer wird es. Fragen Sie bei der Angebotseinholung explizit, ob Vorarbeiten enthalten sind.

### Raumhöhe und Zugänglichkeit

Altbauwohnungen mit 3,50m Deckenhöhe kosten mehr als Neubau mit 2,50m. Logisch — der Maler braucht Gerüst oder längere Leitern, arbeitet langsamer, verbraucht mehr Farbe. Rechnen Sie mit einem Aufschlag von 15–25% bei Deckenhöhen über 3 Metern.

### Farbwahl

Weiß ist am günstigsten. Farbige Wände kosten mehr — nicht nur weil die Farbe teurer ist, sondern weil oft ein zusätzlicher Anstrich nötig ist, damit die Farbe deckt. Besonders bei kräftigen Tönen wie Rot oder Dunkelblau.

## Beispielrechnungen

### Beispiel 1: 2-Zimmer-Wohnung streichen (65 qm Wandfläche)

| Position | Berechnung | Kosten |
|----------|-----------|--------|
| Wände streichen (weiß, 2x) | 65 qm x 12 EUR | 780 EUR |
| Decken streichen | 28 qm x 14 EUR | 392 EUR |
| Türen lackieren (3 Stück) | 3 x 80 EUR | 240 EUR |
| Abkleben + Abdecken | Pauschal | 120 EUR |
| **Gesamt (netto)** | | **1.532 EUR** |
| **Gesamt (brutto, inkl. MwSt.)** | | **~1.823 EUR** |

### Beispiel 2: Treppenhaus streichen (Altbau, 3 Etagen)

| Position | Kosten |
|----------|--------|
| Wände + Decken | 2.800–4.200 EUR |
| Geländer lackieren | 400–800 EUR |
| Gerüst/Rollgerüst | 200–400 EUR |
| **Gesamt (brutto)** | **~4.000–6.400 EUR** |

## Wie Sie Geld sparen — ohne an Qualität zu verlieren

1. **Vorarbeiten selbst machen.** Möbel rücken, abdecken, alte Tapete abreißen — das können Sie selbst. Spart 2–4 Stunden Arbeitszeit.
2. **Außerhalb der Saison beauftragen.** November bis Februar ist für Maler ruhiger. Manche bieten dann 10–15% Rabatt.
3. **Farbe selbst kaufen.** Manche Maler berechnen einen Aufschlag auf Material. Fragen Sie, ob Sie die Farbe selbst stellen können.
4. **Mehrere Räume auf einmal.** An- und Abfahrt, Auf- und Abbau — das fällt nur einmal an, wenn Sie mehrere Zimmer zusammen machen lassen.

## Wie finde ich einen Maler in meiner Nähe?

Auf Branchenportalen können Sie gezielt nach Malerbetrieben in Ihrer Stadt suchen und Bewertungen von anderen Kunden lesen. Das gibt Ihnen ein realistisches Bild — deutlich besser als blind den erstbesten Treffer bei Google anzurufen.

## Fazit

Malerarbeiten kosten in Deutschland zwischen 8 und 45 Euro pro Quadratmeter — je nach Arbeit, Region und Zustand. Für eine durchschnittliche 2-Zimmer-Wohnung sollten Sie mit 1.500 bis 2.500 Euro brutto rechnen. Holen Sie sich drei Angebote ein, vergleichen Sie Leistungen (nicht nur Preise), und lesen Sie Bewertungen anderer Kunden. Das ist der beste Schutz vor Überraschungen.
BODY,
            ],

            // ARTIKEL 4
            [
                'title' => 'Handwerker beauftragen: Die komplette Checkliste',
                'slug' => 'handwerker-beauftragen-checkliste',
                'meta_title' => 'Handwerker beauftragen: Die komplette Checkliste (2026)',
                'meta_description' => 'Handwerker beauftragen ohne Fehler: Unsere Checkliste führt Sie von der Suche über das Angebot bis zur Abnahme — Schritt für Schritt.',
                'excerpt' => 'Von der Suche bis zur Abnahme — diese Checkliste deckt jeden Schritt ab, wenn Sie einen Handwerker beauftragen. Inklusive Vorlagen für Anfragen und Tipps zur Rechnungsprüfung.',
                'category' => 'Ratgeber',
                'tags' => ['Handwerker', 'Checkliste', 'Beauftragung', 'Vertrag'],
                'reading_time' => 9,
                'body' => <<<'BODY'
# Handwerker beauftragen: Die komplette Checkliste

Sie haben einen Wasserschaden, die Küche soll renoviert werden oder das Bad braucht neue Fliesen. Irgendwann steht jeder vor der Aufgabe, einen Handwerker zu beauftragen. Und die meisten machen dabei Fehler — nicht aus Dummheit, sondern weil ihnen niemand gesagt hat, worauf sie achten sollen.

Diese Checkliste ist Ihr roter Faden. Von der ersten Idee bis zur bezahlten Rechnung. Drucken Sie sie aus, haken Sie ab, und Sie werden deutlich entspannter durch die nächste Renovierung kommen.

## Phase 1: Vor der Beauftragung

### Bedarf klären

- [ ] Was genau soll gemacht werden? Schreiben Sie es auf — so konkret wie möglich
- [ ] Welches Gewerk brauchen Sie? (Maler, Elektriker, Sanitär, Tischler...)
- [ ] Gibt es einen festen Termin oder Zeitdruck?
- [ ] Budget grob definiert?

### Handwerker finden

- [ ] Empfehlungen im Bekanntenkreis eingeholt
- [ ] Branchenportal nach Betrieben in Ihrer Region durchsucht
- [ ] Bewertungen gelesen (mindestens 3–5 Bewertungen pro Betrieb)
- [ ] Meisterbetrieb bei zulassungspflichtigen Gewerken verifiziert

### Angebote einholen

- [ ] Mindestens 3 Angebote angefragt
- [ ] Identische Leistungsbeschreibung an alle Betriebe geschickt
- [ ] Um Aufschlüsselung nach Material und Arbeitszeit gebeten
- [ ] Zeitrahmen abgefragt
- [ ] Referenzen erbeten (bei größeren Projekten)

Ein Tipp, der mir viel Ärger erspart hat: Schicken Sie Ihre Anfrage schriftlich — per E-Mail oder über das Kontaktformular im Firmenprofil. So haben beide Seiten eine Dokumentation.

## Phase 2: Angebot prüfen

- [ ] Sind alle besprochenen Leistungen aufgeführt?
- [ ] Ist Material spezifiziert? (Nicht nur "Farbe", sondern welche Farbe, welche Qualität)
- [ ] Sind Vorarbeiten enthalten? (Abdecken, altes Material entfernen, Entsorgung)
- [ ] Gibt es einen Festpreis oder eine Kostenschätzung?
- [ ] Ist die Mehrwertsteuer ausgewiesen?
- [ ] Zahlungsbedingungen klar?
- [ ] Gültigkeitsdauer des Angebots angegeben?

**Festpreis vs. Kostenschätzung:** Ein Festpreis ist bindend — der Betrieb darf nicht mehr verlangen. Eine Kostenschätzung darf um bis zu 20% überschritten werden. Achten Sie darauf, was Sie bekommen.

## Phase 3: Beauftragung

- [ ] Auftrag schriftlich erteilt (E-Mail reicht rechtlich aus)
- [ ] Leistungsumfang nochmal konkret benannt
- [ ] Start- und Endtermin vereinbart
- [ ] Ansprechpartner auf beiden Seiten festgelegt
- [ ] Zugangsmöglichkeiten geklärt (Schlüssel? Anwesenheit nötig?)
- [ ] Nachbarn informiert (bei lauten Arbeiten)

## Phase 4: Während der Arbeit

- [ ] Starttermin eingehalten?
- [ ] Arbeitsbereich ordentlich abgedeckt/geschützt?
- [ ] Bei Abweichungen: Sofort ansprechen, nicht erst bei Fertigstellung
- [ ] Zusätzliche Arbeiten nur nach schriftlicher Vereinbarung (Nachtrag)
- [ ] Fotodokumentation machen (vorher/nachher, Zwischenstände)

Der wichtigste Punkt in dieser Phase: Wenn etwas anders gemacht werden soll als vereinbart, IMMER schriftlich festhalten.

## Phase 5: Abnahme

- [ ] Arbeit gemeinsam mit dem Handwerker durchgehen
- [ ] Checkliste mit allen vereinbarten Leistungen abgleichen
- [ ] Mängel sofort dokumentieren (Fotos + schriftlich)
- [ ] Abnahmeprotokoll unterschreiben (mit Mängelvermerken falls nötig)
- [ ] Gewährleistungsfrist notieren (5 Jahre bei Bauwerken, 2 Jahre sonst)

## Phase 6: Nach der Arbeit

- [ ] Rechnung mit Angebot abgleichen
- [ ] Zahlungsfrist einhalten (meist 14 Tage)
- [ ] Rechnung aufbewahren (Handwerkerleistungen sind steuerlich absetzbar!)
- [ ] Bewertung schreiben — hilft anderen bei der Entscheidung

**Steuertipp:** Handwerkerleistungen können Sie in Ihrer Steuererklärung angeben. 20% der Arbeitskosten (nicht Material), maximal 1.200 Euro pro Jahr. Die Rechnung muss per Überweisung bezahlt werden.

## Fazit

Diese Checkliste klingt nach viel Aufwand. Ist es auch — beim ersten Mal. Aber nach ein, zwei Handwerker-Beauftragungen haben Sie den Dreh raus. Und der Aufwand zahlt sich aus: in weniger Überraschungen, weniger Streit und einem Ergebnis, mit dem Sie zufrieden sind.
BODY,
            ],

            // ARTIKEL 5
            [
                'title' => 'Firmeneintrag erstellen: Kostenlos und in 5 Minuten online',
                'slug' => 'firmeneintrag-erstellen-kostenlos',
                'meta_title' => 'Firmeneintrag erstellen: Kostenlos und in 5 Minuten online',
                'meta_description' => 'Erstellen Sie Ihren kostenlosen Firmeneintrag im Branchenportal. Schritt-für-Schritt erklärt: Was Sie brauchen, wie es funktioniert, was es bringt.',
                'excerpt' => 'Ein kostenloser Firmeneintrag macht Ihr Unternehmen für potenzielle Kunden sichtbar. Wie Sie in wenigen Minuten online gehen — und warum sich das gerade für kleine Betriebe lohnt.',
                'category' => 'Online-Präsenz',
                'tags' => ['Firmeneintrag', 'Kostenlos', 'Online-Sichtbarkeit', 'Selbstständige'],
                'reading_time' => 6,
                'body' => <<<'BODY'
# Firmeneintrag erstellen: Kostenlos und in 5 Minuten online

Sie haben einen Betrieb und keine Website? Oder eine Website, die seit 2019 nicht aktualisiert wurde? Dann wird es Zeit, zumindest einen Firmeneintrag in einem Branchenportal anzulegen. Das dauert fünf Minuten, kostet nichts und bringt Ihnen potenzielle Kunden, die genau nach Ihrem Angebot suchen.

Klingt nach Werbung? Ist es nicht. Lassen Sie mich erklären, warum ein Firmeneintrag gerade für kleine und mittlere Betriebe sinnvoll ist — und warum Sie dabei keinen Cent ausgeben müssen.

## Warum überhaupt ein Firmeneintrag?

Ein Malermeister aus Oschersleben — nennen wir ihn Thomas — hat mich mal gefragt, ob sich ein Eintrag in einem Branchenportal lohnt. Er hatte keine Website, nur eine Visitenkarte und Mundpropaganda. Meine Antwort: "Wenn 87% Ihrer potenziellen Kunden online nach Handwerkern suchen, und Sie online nicht existieren — dann existieren Sie für 87% nicht."

Thomas hat seinen Eintrag erstellt. Drei Wochen später hatte er den ersten Auftrag darüber.

### Was ein Firmeneintrag bietet

- **Auffindbarkeit:** Kunden suchen nach Ihrer Branche + Stadt und finden Sie
- **Vertrauensaufbau:** Bewertungen von zufriedenen Kunden überzeugen andere
- **Kontaktmöglichkeit:** Telefon, E-Mail, Adresse — alles auf einen Blick
- **Kostenlose Sichtbarkeit:** Kein Werbebudget nötig, keine Vertragslaufzeiten
- **SEO-Effekt:** Ihr Firmeneintrag kann bei Google ranken, auch wenn Sie keine eigene Website haben

## Was Sie für den Eintrag brauchen

| Was | Warum |
|-----|-------|
| Firmenname | Offensichtlich |
| Adresse | Für die Standortsuche und Google Maps |
| Telefonnummer | Der wichtigste Kontaktkanal für lokale Kunden |
| E-Mail-Adresse | Für Anfragen und die Registrierung |
| Kurze Beschreibung (2–3 Sätze) | Was macht Ihr Betrieb? Wer sind Ihre Kunden? |
| Logo (optional, aber empfohlen) | Macht Ihren Eintrag professioneller |
| Kategorie | In welcher Branche sind Sie tätig? |

## Schritt-für-Schritt: So erstellen Sie Ihren Eintrag

### Schritt 1: Registrieren

Name, E-Mail-Adresse, Passwort — drei Felder. Keine Kreditkarte, kein Kleingedrucktes.

### Schritt 2: Firmendaten eingeben

Das Portal führt Sie durch einen einfachen Assistenten: Firmendaten, Adresse, Kontakt — fertig.

### Schritt 3: Logo hochladen

Optional, aber es macht einen riesen Unterschied. Einträge mit Logo bekommen deutlich mehr Klicks.

### Schritt 4: Veröffentlichen

Ein Klick, und Ihr Eintrag ist live.

## Kostenlos vs. Premium — was lohnt sich?

Der Basiseintrag ist dauerhaft kostenlos. Wer mehr möchte, kann auf Premium upgraden (9,90 Euro pro Monat): hervorgehobene Platzierung, Bildergalerie, auf Bewertungen antworten, detaillierte Statistiken.

Mein ehrlicher Rat: Starten Sie kostenlos. Wenn nach ein paar Wochen Anfragen reinkommen — dann ist Premium eine Überlegung wert.

## Typische Fehler beim Firmeneintrag

**Zu wenig Information.** Schreiben Sie mindestens 2–3 Sätze darüber, was Sie anbieten.

**Veraltete Kontaktdaten.** Prüfen Sie Ihren Eintrag mindestens einmal im Quartal.

**Kein Logo.** Einträge ohne Logo wirken lieblos.

**Falsche Kategorie.** Seien Sie spezifisch — Kunden suchen spezifisch.

## Fazit

Ein kostenloser Firmeneintrag ist die einfachste Möglichkeit, online sichtbar zu werden. Fünf Minuten Aufwand, null Euro Kosten, und ab sofort können Kunden Sie finden. Es gibt wirklich keinen Grund, es nicht zu tun.
BODY,
            ],

            // ARTIKEL 6
            [
                'title' => 'Handwerker-Rechnung prüfen: Darauf müssen Sie achten',
                'slug' => 'handwerker-rechnung-pruefen',
                'meta_title' => 'Handwerker-Rechnung prüfen: Darauf müssen Sie achten',
                'meta_description' => 'Ist die Handwerker-Rechnung korrekt? Was draufstehen muss, welche Posten verdächtig sind und wann Sie reklamieren sollten — verständlich erklärt.',
                'excerpt' => 'Die Rechnung vom Handwerker liegt im Briefkasten. Aber stimmt das alles so? Wir erklären, was auf einer korrekten Rechnung stehen muss und welche Warnsignale Sie kennen sollten.',
                'category' => 'Ratgeber',
                'tags' => ['Rechnung', 'Handwerker', 'Verbraucherschutz', 'Kosten'],
                'reading_time' => 7,
                'body' => <<<'BODY'
# Handwerker-Rechnung prüfen: Darauf müssen Sie achten

Die Arbeit ist erledigt, das Bad glänzt, der Handwerker ist weg. Dann kommt die Rechnung. Und mit ihr das ungute Gefühl: Stimmt das alles? Sind 12 Stunden für ein kleines Bad normal? Was sind "Kleinmaterial und Verbrauchsstoffe" für 180 Euro?

Diese Unsicherheit ist völlig normal. Die wenigsten von uns können eine Handwerker-Rechnung auf Anhieb einordnen. Aber mit ein paar Grundregeln wird es deutlich einfacher.

## Was auf jeder Rechnung stehen muss

Eine ordnungsgemäße Rechnung nach §14 UStG enthält:

- [ ] Vollständiger Name und Adresse des Betriebs
- [ ] Ihre vollständige Adresse als Auftraggeber
- [ ] Steuernummer oder USt-IdNr. des Betriebs
- [ ] Rechnungsdatum
- [ ] Fortlaufende Rechnungsnummer
- [ ] Art und Umfang der Leistung
- [ ] Zeitpunkt der Leistung
- [ ] Nettobetrag, Steuersatz, Steuerbetrag, Bruttobetrag
- [ ] Aufschlüsselung nach Arbeitszeit und Material

## Die häufigsten Auffälligkeiten

### 1. Stundenanzahl prüfen

Notieren Sie sich, wann der Handwerker angefangen und aufgehört hat. Ziehen Sie Pausen ab. Wenn auf der Rechnung 8 Stunden stehen, der Handwerker aber um 9 Uhr kam und um 16 Uhr ging (mit 30 Minuten Mittagspause), sind es 6,5 Stunden.

### 2. Anfahrtskosten

Anfahrtskosten sind legitim. Üblich sind Pauschalen (15–45 EUR) oder Kilometerberechnung (0,50–1,00 EUR pro km). Was nicht okay ist: Anfahrtskosten, die in der Arbeitszeit "versteckt" sind.

### 3. Material-Aufschläge

Ein Aufschlag von 10–15% auf den Einkaufspreis ist branchenüblich. Aufschläge von 50% oder mehr sind ungewöhnlich. Fragen Sie nach Einkaufsbelegen.

### 4. Pauschalposten ohne Erklärung

"Kleinmaterial und Verbrauchsstoffe: 180 EUR" — Sie haben das Recht, eine Aufschlüsselung zu verlangen. Kleinmaterial sollte maximal 3–5% der Gesamtrechnung ausmachen.

## Wann Sie reklamieren sollten

- Die Rechnung weicht deutlich vom Angebot ab
- Leistungen werden berechnet, die nicht erbracht wurden
- Die Stundenanzahl erscheint unrealistisch hoch
- Material wird offensichtlich zu teuer berechnet

**Wie reklamieren?** Immer schriftlich, per E-Mail. Sachlich bleiben, konkret benennen was unklar ist.

## Barzahlung: Bitte nicht

Handwerkerleistungen, die bar bezahlt werden, können Sie nicht steuerlich absetzen. Außerdem sind Barzahlungen ohne Rechnung ein Indiz für Schwarzarbeit. Bestehen Sie auf eine ordnungsgemäße Rechnung und bezahlen Sie per Überweisung.

## Fazit

Eine Handwerker-Rechnung zu prüfen ist kein Misstrauen — es ist Ihr gutes Recht als Auftraggeber. Nehmen Sie sich die fünf Minuten, gleichen Sie die Rechnung mit dem Angebot ab, und fragen Sie bei Unklarheiten nach.
BODY,
            ],

            // ARTIKEL 7
            [
                'title' => 'Lokales SEO für kleine Firmen: 8 Tipps die funktionieren',
                'slug' => 'lokales-seo-kleine-firmen-tipps',
                'meta_title' => 'Lokales SEO für kleine Firmen: 8 Tipps die funktionieren',
                'meta_description' => 'Lokale SEO muss nicht kompliziert sein. 8 praktische Tipps, mit denen Ihr Betrieb bei Google Maps und in der lokalen Suche besser gefunden wird.',
                'excerpt' => 'Sie wollen, dass Kunden aus Ihrer Region Ihren Betrieb bei Google finden? Diese 8 lokalen SEO-Tipps sind speziell für kleine und mittlere Unternehmen — ohne Fachchinesisch.',
                'category' => 'Online-Präsenz',
                'tags' => ['SEO', 'Lokale Suche', 'Google', 'Online-Marketing', 'Firmeninhaber'],
                'reading_time' => 8,
                'body' => <<<'BODY'
# Lokales SEO für kleine Firmen: 8 Tipps die funktionieren

"SEO" — drei Buchstaben, die bei den meisten Firmeninhabern entweder Augenrollen oder Panik auslösen. Zu technisch, zu abstrakt, zu teuer. Verstehe ich. Aber wenn es um lokales SEO geht — also darum, dass Kunden aus Ihrer Stadt Sie bei Google finden — dann ist es gar nicht so kompliziert.

## Was ist lokales SEO überhaupt?

Wenn jemand "Maler Berlin" oder "Elektriker in meiner Nähe" bei Google eingibt, sieht er drei Dinge:

1. **Google Ads** (Anzeigen oben) — kosten Geld
2. **Das Map Pack** (3 Ergebnisse mit Karte) — kostenlos, aber umkämpft
3. **Organische Ergebnisse** (normale Website-Links) — kostenlos

Lokales SEO zielt auf Platz 2 und 3. Und das Schöne: Google bevorzugt hier kleine lokale Betriebe gegenüber großen Ketten.

## Tipp 1: Google Business Profil vollständig ausfüllen

Das ist die wichtigste Einzelmaßnahme. Füllen Sie alles aus: Firmenname, Adresse, Telefonnummer, Öffnungszeiten, Kategorie, Beschreibung (250 Wörter), Fotos (mindestens 5).

## Tipp 2: NAP-Konsistenz

NAP steht für Name, Address, Phone. Google vergleicht Ihre Angaben über verschiedene Websites hinweg. Schreiben Sie Ihren Firmennamen, Ihre Adresse und Ihre Telefonnummer überall identisch.

## Tipp 3: Branchenportale nutzen

Jeder Eintrag auf einem seriösen Branchenportal ist ein "Citation" — ein Verweis auf Ihre Firma. Je mehr konsistente Citations, desto besser.

## Tipp 4: Bewertungen sammeln

Bewertungen sind einer der stärksten Ranking-Faktoren für lokales SEO. Bitten Sie zufriedene Kunden direkt nach Abschluss der Arbeit. Reagieren Sie auf jede Bewertung.

## Tipp 5: Lokale Keywords auf Ihrer Website

Bauen Sie lokale Keywords ein: Seitentitel, Überschrift, Über-uns-Text, Footer mit vollständiger Adresse.

## Tipp 6: Fotos, Fotos, Fotos

Betriebe mit Fotos werden 42% häufiger nach einer Wegbeschreibung gefragt. Smartphone-Qualität reicht.

## Tipp 7: Auf Bewertungen antworten

Jede Antwort zeigt Google: "Dieser Betrieb ist aktiv und kümmert sich um Kunden." Das wirkt sich positiv auf Ihr Ranking aus.

## Tipp 8: Regelmäßig aktualisieren

Google bevorzugt aktive Profile. 10 Minuten pro Woche reichen: Öffnungszeiten prüfen, Foto hochladen, Firmeneintrag aktualisieren.

## Fazit

Lokales SEO ist keine Raketenwissenschaft. Die acht Tipps kosten Sie einen Nachmittag — und die Ergebnisse sehen Sie oft schon nach 4–6 Wochen. Starten Sie mit Tipp 1 und arbeiten Sie sich vor.
BODY,
            ],

            // ARTIKEL 8
            [
                'title' => 'Elektriker finden: Worauf Sie wirklich achten müssen',
                'slug' => 'elektriker-finden-worauf-achten',
                'meta_title' => 'Elektriker finden: Worauf Sie wirklich achten müssen',
                'meta_description' => 'Elektriker gesucht? Meisterpflicht, Zertifikate, Preise — worauf Sie achten müssen und wie Sie einen seriösen Elektrofachbetrieb erkennen.',
                'excerpt' => 'Elektroarbeiten sind kein DIY-Projekt. Woran Sie einen qualifizierten Elektriker erkennen, was ein seriöser Betrieb kostet und welche Zertifikate wirklich zählen.',
                'category' => 'Ratgeber',
                'tags' => ['Elektriker', 'Handwerker', 'Elektroinstallation', 'Sicherheit'],
                'reading_time' => 7,
                'body' => <<<'BODY'
# Elektriker finden: Worauf Sie wirklich achten müssen

Bei Malerarbeiten können Sie im Notfall selbst zum Pinsel greifen. Bei Elektrik? Bitte nicht. Strom ist kein Bereich für Experimente — hier geht es um Ihre Sicherheit und um Versicherungsschutz.

## Meisterpflicht: Nicht verhandelbar

Elektroinstallation ist ein zulassungspflichtiges Handwerk. Nur ein Betrieb mit Meisterbrief darf Elektroarbeiten ausführen. Fehlerhafte Elektroinstallationen sind der häufigste technische Grund für Wohnungsbrände in Deutschland.

So prüfen Sie die Qualifikation:
- Fragen Sie nach dem Meisterbrief oder der Eintragung in die Handwerksrolle
- Seriöse Betriebe nennen ihre Qualifikation auf der Website oder im Firmeneintrag
- Die Handwerkskammer Ihrer Region gibt Auskunft

## Eingetragen in der Installateursliste

Nur Betriebe, die in der Installateursliste des lokalen Energieversorgers eingetragen sind, dürfen Arbeiten am Hausstromkasten durchführen.

## Preise: Was ein Elektriker kostet

| Leistung | Preisspanne |
|----------|------------|
| Stundensatz (Geselle) | 45–65 EUR netto |
| Stundensatz (Meister) | 55–80 EUR netto |
| Steckdose versetzen | 80–150 EUR |
| Lichtschalter austauschen | 50–100 EUR |
| Sicherungskasten erneuern | 1.200–3.000 EUR |
| Komplett-Elektrik Wohnung (80 qm) | 8.000–15.000 EUR |
| E-Check (Bestandsaufnahme) | 120–250 EUR |

## Woran Sie einen seriösen Betrieb erkennen

### Vor-Ort-Besichtigung vor dem Angebot

Ein seriöser Elektriker schickt Ihnen kein Angebot per E-Mail, ohne sich die Situation angesehen zu haben. Zumindest nicht für größere Projekte.

### Prüfprotokoll nach der Arbeit

Nach jeder Elektroinstallation muss der Betrieb ein Messprotokoll erstellen. Dieses Protokoll ist Ihr Nachweis, dass die Arbeit fachgerecht ausgeführt wurde — und die Versicherung fragt danach.

### Garantie auf die Arbeit

Gesetzlich haben Sie bei Handwerkerleistungen eine Gewährleistungsfrist von 2 Jahren (bei Arbeiten am Bauwerk: 5 Jahre).

## E-Check: Die Vorsorge für Ihre Elektrik

Empfehlung: Alle 4 Jahre einen E-Check machen lassen. Besonders wichtig bei Einzug in eine neue Wohnung, Häusern vor 1990, nach Blitzeinschlag oder wenn Sicherungen häufig rausspringen.

## Fazit

Einen Elektriker zu finden ist nicht schwer — einen guten zu finden schon eher. Achten Sie auf Meisterpflicht, Installateur-Status und eine Vor-Ort-Besichtigung vor dem Angebot. Bei Elektrik geht es um Sicherheit, nicht um den günstigsten Preis.
BODY,
            ],

            // ARTIKEL 9
            [
                'title' => 'Auf negative Bewertungen antworten: So machen Sie es richtig',
                'slug' => 'negative-bewertung-antworten-tipps',
                'meta_title' => 'Auf negative Bewertungen antworten: So machen Sie es richtig',
                'meta_description' => 'Negative Bewertung erhalten? Keine Panik. Mit der richtigen Antwort stärken Sie Ihr Image — wir zeigen wie, mit konkreten Textvorlagen.',
                'excerpt' => 'Eine negative Bewertung fühlt sich wie ein Schlag ins Gesicht an. Aber mit der richtigen Antwort können Sie daraus sogar einen Vorteil machen. Hier sind konkrete Vorlagen und Strategien.',
                'category' => 'Bewertungen',
                'tags' => ['Bewertungen', 'Negative Bewertung', 'Antwort', 'Reputation', 'Firmeninhaber'],
                'reading_time' => 8,
                'body' => <<<'BODY'
# Auf negative Bewertungen antworten: So machen Sie es richtig

Da steht sie. Zwei Sterne. "Nie wieder. Unfreundlich und teuer." Und Ihr Puls geht hoch. Verständlich. Eine negative Bewertung fühlt sich persönlich an — besonders wenn Sie Ihren Betrieb mit Herzblut führen.

Aber jetzt kommt die gute Nachricht: Eine negative Bewertung ist nicht das Ende der Welt. Im Gegenteil — Ihre Antwort darauf ist eine der besten Gelegenheiten, potenziellen Kunden zu zeigen, wie professionell Sie arbeiten.

Warum? Weil 89% der Verbraucher die Antworten von Unternehmen auf negative Bewertungen lesen.

## Regel Nr. 1: Nicht sofort antworten

Antworten Sie nicht in den ersten 30 Minuten. Ihr Adrenalinspiegel ist oben, Sie sind im Verteidigungsmodus. Bewertung lesen, Handy weglegen, eine Nacht drüber schlafen. Am nächsten Morgen mit klarem Kopf antworten.

## Regel Nr. 2: Immer antworten

Auch wenn die Bewertung unfair ist. Keine Antwort IST eine Antwort — und sie sagt: "Ist mir egal."

## Das LEAD-Framework für Antworten

### L — Listen (Zuhören zeigen)
"Vielen Dank für Ihr Feedback" oder "Es tut uns leid zu hören, dass Sie unzufrieden waren."

### E — Empathy (Verständnis zeigen)
"Wir verstehen, dass eine Verzögerung frustrierend ist." Keine Verteidigung, kein Aber.

### A — Address (Auf den Punkt eingehen)
Sachlich auf die Kritik eingehen. Wenn etwas schiefgelaufen ist: Sagen Sie es.

### D — Door open (Tür öffnen)
"Bitte kontaktieren Sie uns direkt unter [Telefon/E-Mail], damit wir eine Lösung finden können."

## Konkrete Textvorlagen

### Vorlage 1: Berechtigte Kritik

> Hallo [Name], vielen Dank für Ihre offene Rückmeldung. Sie haben recht — die Verzögerung bei Ihrem Projekt war nicht akzeptabel, und dafür möchten wir uns aufrichtig entschuldigen. Wir haben intern besprochen, wie wir die Terminplanung verbessern können. Bitte melden Sie sich gerne direkt bei uns unter [Telefon], falls Sie noch offene Punkte haben.

### Vorlage 2: Unfaire oder übertriebene Kritik

> Hallo [Name], danke dass Sie sich die Zeit für eine Bewertung genommen haben. Ihre Schilderung weicht leider von unserer Wahrnehmung ab. Aber natürlich nehmen wir Ihre Unzufriedenheit ernst. Bitte kontaktieren Sie uns unter [Telefon], damit wir das gemeinsam klären können.

### Vorlage 3: Einsilbige Negativbewertung

> Hallo [Name], es tut uns leid zu hören, dass Sie unzufrieden waren. Da Ihre Bewertung keine Details enthält, fällt es uns schwer einzuordnen, was schiefgelaufen ist. Bitte melden Sie sich gerne bei uns — wir möchten verstehen, was passiert ist.

## Was Sie auf keinen Fall tun sollten

1. **Persönlich werden.** Das mag stimmen, aber es liest sich furchtbar.
2. **Andere Kunden als Beweis anführen.** Klingt arrogant.
3. **Den Kunden als Lügner darstellen.** Beweisen Sie es im privaten Gespräch, nicht öffentlich.
4. **Standardtexte copy-pasten.** 10x der gleiche Text wirkt roboterhaft.

## Wann können Sie eine Bewertung löschen lassen?

- Beleidigungen oder Verleumdungen
- Unwahre Tatsachenbehauptungen (die Sie beweisen können)
- Fake-Bewertungen (Person war nie Kunde)
- Bewertungen die den falschen Betrieb betreffen

## Fazit

Eine negative Bewertung ist keine Katastrophe — Ihre Reaktion darauf ist, was zählt. Mit dem LEAD-Framework und ein wenig Abstand schreiben Sie Antworten, die aus einer Beschwerde einen Beweis für Ihre Professionalität machen.
BODY,
            ],

            // ARTIKEL 10
            [
                'title' => 'Renovierung planen: Die ultimative Checkliste',
                'slug' => 'renovierung-planen-checkliste',
                'meta_title' => 'Renovierung planen: Die ultimative Checkliste (2026)',
                'meta_description' => 'Renovierung steht an? Mit unserer Checkliste planen Sie alles von der Budgetplanung über die Handwerkersuche bis zur Endabnahme — ohne Stress.',
                'excerpt' => 'Eine Renovierung ohne Plan endet im Chaos. Diese Checkliste führt Sie Schritt für Schritt — von der ersten Idee bis zum fertigen Ergebnis. Inklusive Budget-Tipps und Zeitplanung.',
                'category' => 'Ratgeber',
                'tags' => ['Renovierung', 'Checkliste', 'Planung', 'Budget', 'Handwerker'],
                'reading_time' => 9,
                'body' => <<<'BODY'
# Renovierung planen: Die ultimative Checkliste

Sie sitzen in Ihrer Wohnung, schauen sich um und denken: "Hier muss was passieren." Die Tapete hängt, das Bad ist von 1994, und der Boden hat auch schon bessere Tage gesehen. Aber wo anfangen?

Eine Renovierung ist kein Hexenwerk. Aber sie braucht einen Plan. Ohne Plan wird aus "mal eben das Bad machen" schnell ein dreimonatiges Projekt mit Budgetüberschreitung.

## Phase 1: Bestandsaufnahme (4–6 Wochen vor Start)

### Was muss gemacht werden?

- [ ] Welche Räume sollen renoviert werden?
- [ ] Was genau soll gemacht werden? (Streichen, Boden, Bad, Küche, Elektrik...)
- [ ] Was davon können Sie selbst? Was braucht einen Handwerker?
- [ ] Gibt es bauliche Veränderungen? (Wände raus, Türen versetzen — genehmigungspflichtig?)

### Budget festlegen

| Maßnahme | Kosten (grobe Orientierung) |
|----------|----------------------------|
| Zimmer streichen (15 qm) | 300–600 EUR |
| Boden verlegen (15 qm) | 600–1.500 EUR |
| Bad komplett (6 qm) | 8.000–18.000 EUR |
| Küche (ohne Geräte) | 3.000–15.000 EUR |
| Komplettrenovierung Wohnung (70 qm) | 15.000–40.000 EUR |

**Goldene Regel:** Planen Sie immer 15–20% Puffer ein. Es kommt IMMER etwas dazu.

## Phase 2: Handwerker finden und beauftragen (3–4 Wochen vor Start)

- [ ] Gewerke identifizieren
- [ ] Pro Gewerk mindestens 3 Angebote einholen
- [ ] Bewertungen auf Branchenportalen lesen
- [ ] Reihenfolge der Gewerke festlegen

### Die richtige Reihenfolge

1. **Abriss und Rohbau** (Wände raus, Durchbrüche)
2. **Elektrik** (neue Leitungen, Steckdosen)
3. **Sanitär** (Wasserleitungen, Heizung)
4. **Estrich/Putz** (Trocknungszeit beachten!)
5. **Fliesen** (Bad, Küche)
6. **Trockenbau** (Decken, Wände verkleiden)
7. **Maler** (Wände, Decken)
8. **Boden** (Parkett, Laminat, Vinyl)
9. **Küche/Bad-Montage** (Möbel, Sanitärobjekte)
10. **Feinarbeiten** (Sockelleisten, Türen einstellen, Silikonfugen)

## Phase 3: Vorbereitung (1 Woche vor Start)

- [ ] Möbel rücken oder ausräumen
- [ ] Nachbarn informieren
- [ ] Zugangsmöglichkeiten für Handwerker geklärt
- [ ] Müllcontainer bestellt? (bei Abrissarbeiten)

## Phase 4: Während der Renovierung

- [ ] Täglicher kurzer Check: Läuft alles nach Plan?
- [ ] Fotos machen (Vorher-Nachher, Zwischenstände)
- [ ] Abweichungen sofort ansprechen
- [ ] Nachträge IMMER schriftlich vereinbaren

## Phase 5: Abnahme und Abschluss

- [ ] Jedes Gewerk einzeln abnehmen
- [ ] Abnahme bei Tageslicht
- [ ] Mängel sofort schriftlich dokumentieren
- [ ] Rechnungen mit Angeboten abgleichen
- [ ] Rechnungen aufbewahren (Steuererklärung + Gewährleistung)
- [ ] Bewertungen schreiben

## Budget-Spartipps

1. **Eigenleistung:** Abriss, Möbel rücken, Tapete entfernen — das können die meisten selbst.
2. **Saisonale Planung:** November bis Februar ist Nebensaison.
3. **Materialien selbst kaufen:** Vorher mit dem Handwerker absprechen!
4. **Steuervorteil nutzen:** 20% der Arbeitskosten (max. 1.200 EUR/Jahr) absetzbar. Nur per Überweisung zahlen!
5. **Bündelung:** Mehrere Gewerke gleichzeitig beauftragen spart Koordinationsaufwand.

## Fazit

Eine Renovierung ist ein Projekt — und jedes Projekt braucht einen Plan. Mit dieser Checkliste haben Sie einen roten Faden von der ersten Idee bis zum fertigen Ergebnis. Drucken Sie sie aus, hängen Sie sie an den Kühlschrank, und arbeiten Sie die Punkte ab.
BODY,
            ],
        ];
    }
}
