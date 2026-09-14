# Content-Dashboard — Screen-Spezifikation

Ticket #5. Umsetzung in #4 (Panel/Layout), #19 (Übersicht, Produktion), #20 (Prüfung, Einstellungen, Quellen-Monitor), #25 (Leistung).
Klammer: Dokument „UX-Leitbild Ratgeber-Pipeline (Epic #1)". Tokens: `resources/css/content/theme.css`.

Alle Farb-, Abstands- und Schriftwerte unten sind Token-Namen. Keine Hex-Werte in Templates.

---

## 0. Rahmen für alle sieben Screens

### Grundgerüst

| Zone | Maß | Verhalten |
|---|---|---|
| Seitennavigation links | 240 px, `surface-nav` (#103f3c), fix | ab 1280 px offen; 1024–1279 px auf 64 px Symbolleiste eingeklappt; unter 1024 px als Schublade über Kopfzeile |
| Kopfzeile | 64 px, `surface-card`, untere Linie `line-soft` | Seitentitel `content-h1`, rechts Datum/Uhrzeit der letzten Pipeline-Runde und Nutzermenü |
| Filterbalken | 56 px, `surface-page`, klebend unter der Kopfzeile | siehe unten |
| Inhalt | max. 1440 px, Innenabstand `content-8` (32 px) | 12-Spalten-Raster, Rinne 16 px |

Die dunkle Navigation ist der stärkste Unterschied zum Admin-Panel (dort helle Navigation, Violett). Zusammen mit dem Teal und dem warmen Seitenhintergrund `surface-page` ist ein Screenshot ohne Kontext eindeutig zuordenbar.

### Navigation

Fünf Punkte, in dieser Reihenfolge, keine weiteren:

1. Übersicht
2. Produktion (Reiter: Board · Kalender · Artikel)
3. Prüfung — einziger Punkt mit Zählmarke
4. Leistung (Reiter: Artikel · Cluster · Regionen · Kosten)
5. Einstellungen (Reiter: Portale · Quellen · Prompts · Budget)

Der Quellen-Monitor ist ein Reiter unter Einstellungen, kein sechster Navigationspunkt. Er wird selten und anlassbezogen aufgerufen; ein eigener Punkt würde die Navigation ohne Nutzen verlängern.

Zählmarke: Kreis 20 px, `status-review-dot` auf `surface-nav`, Zahl in Weiß, 11 px, fett. Ab 100 „99+". Bei 0 verschwindet sie ganz, sie wird nicht als graue Null gezeigt.

Aktiver Punkt: linke Kante 3 px `surface-nav-active`, Hintergrund `content-900`, Text Weiß.

### Filterbalken — identisch in allen fünf Bereichen

Reihenfolge fest: **Zeitraum · Portal · Status · Branche · Zurücksetzen**.

- Zeitraum: Segmentschalter Heute · 7 Tage · 30 Tage · Frei. Vorgabe „Heute" in Übersicht/Produktion/Prüfung, „30 Tage" in Leistung.
- Portal: Mehrfachauswahl mit Suchfeld, 24 Einträge. Anzeige „Alle Portale" oder „3 Portale".
- Status: Mehrfachauswahl über die sieben DisplayStatus, Einträge mit Statuspunkt davor.
- Branche: Mehrfachauswahl.
- Zurücksetzen: Textverweis, nur sichtbar wenn ein Filter vom Standard abweicht.

Jeder Filter schreibt sich in die URL (`?zeitraum=7t&portal=…`), überlebt den Ansichtswechsel und ist teilbar. Gesetzte Filter erscheinen zusätzlich als entfernbare Marken unter dem Balken, Höhe 24 px, `radius-content-sm`.

Unter 1024 px klappt der Balken auf eine Schaltfläche „Filter (3)" zusammen, die ein Blatt von unten öffnet.

### Die fünf Pflichtzustände

Jeder Screen unten benennt sie einzeln. Grundmuster:

- **Leer und gut** — Symbol in `content-300`, Überschrift bejahend, darunter der letzte Erfolg mit Zeitstempel. Kein graues Trauerbild.
- **Leer und schlecht** — Band in `status-failed-bg`, linke Kante 3 px `status-failed-dot`, Ursache im Klartext, genau eine Handlung.
- **Lädt** — `content-skeleton` in Zielgeometrie. Kennzahlen bleiben leer, bis sie echt sind. Nie von 0 hochzählen.
- **Teilweise fehlgeschlagen** — der Normalfall. Erfolgreicher Teil bleibt bedienbar, darüber ein geschlossenes Band in `status-review-bg` mit Zahl und Verweis in die gefilterte Liste.
- **Veraltet** — jede Kennzahl aus Search Console oder AdSense trägt `content-asof` („Stand: 07.09."). Fehlt der Stand, gilt der Screen als nicht fertig.

### Statusfarben und ihre Rollen (Ticket #30)

Ein Status, eine Farbe, überall gleich. Je Status vier Token-Rollen, keine weiteren:

| Rolle | Verwendung | Anforderung |
|---|---|---|
| `status-*-bg` | Fläche der Statuspille und des Filament-Badges, Bänder | — |
| `status-*-fg` | Text und Rand der Pille | ≥ 4,5:1 auf `bg` und auf Weiß |
| `status-*-dot` | 8-px-Punkt, 20-px-Zählmarke, Portalkachel-Punkte, linke Kante von Bändern | identisch mit `fg`, ≥ 4,5:1 |
| `status-*-fill` | große Flächen: Fortschrittsbalken, Kalenderbalken, Diagrammreihen | ≥ 3:1 auf `surface-card`; nur auf Weiß, nie auf `surface-sunken`, nie ohne beschriftete Legende |

**Textfarben auf gesenkter Fläche (Ticket #64).** `text-muted` (`#64716f`) ist für `surface-card` gerechnet (4,9:1). Auf `surface-sunken` (`#f0f0ed`) trägt es nur 4,45:1 und verfehlt die 4,5:1 — auch im Hover-Zustand einer Tabellenzeile. Auf `surface-sunken` gilt deshalb `text-base` (`#3d4a48`, 8,1:1) als kleinste zulässige Textfarbe; `text-muted` bleibt der weißen Kartenfläche vorbehalten.

Punkt und Text sind bewusst derselbe Wert. Die früher helleren Punkttöne erreichten bei „Zur Prüfung" nur 2,1:1 auf ihrer eigenen Fläche und trugen die weiße Zahl der Navigations-Zählmarke nicht.

| Status | bg | fg / dot | fill | fg auf bg | fg auf Weiß | fill auf Weiß |
|---|---|---|---|---|---|---|
| Themenvorschlag | `#f4f5f7` | `#475569` | `#64748b` | 6,95 | 7,58 | 4,76 |
| Eingeplant | `#eff6ff` | `#1d4ed8` | `#3b82f6` | 6,16 | 6,70 | 3,68 |
| In Erstellung | `#f5f3ff` | `#6d28d9` | `#8b5cf6` | 6,48 | 7,10 | 4,23 |
| Zur Prüfung | `#fffbeb` | `#b45309` | `#d97706` | 4,84 | 5,02 | 3,19 |
| Veröffentlicht | `#ecfdf5` | `#047857` | `#059669` | 5,21 | 5,48 | 3,77 |
| Fehlgeschlagen | `#fef2f2` | `#b91c1c` | `#dc2626` | 5,91 | 6,47 | 4,83 |
| Zurückgezogen | `#f8fafc` | `#64716f` | `#7c8a88` | 4,86 | 5,08 | 3,59 |

### Filament-Badge = Statuspille

Im Panel darf **kein** Status eine generische Filament-Rolle tragen. `primary` ist hier die Teal-Markenfarbe; ein Status in Markenfarbe verschmilzt mit Schaltflächen, aktiver Navigation und Fokusring. `info`, `warning`, `success`, `danger` sind frei belegbar und decken sich nicht mit den Tokens.

Stattdessen sind sieben benannte Farben registriert — `status-idea`, `status-scheduled`, `status-generating`, `status-review`, `status-published`, `status-failed`, `status-archived` — plus die Marke als `content`. Tabelle in `config/content.php` unter `colors`, Registrierung im `ContentPanelProvider`:

```php
FilamentColor::register(config('content.colors'));
```

`DisplayStatus::color()` liefert genau diese Namen, `DisplayStatus::cssClass()` die Klasse der Blade-Pille.

Aufbau jeder Skala: Schattierung 50 = `bg`, Schattierung 600 = `fg`. Filament wählt die Textfarbe eines Badges selbst — die erste Schattierung aufsteigend, die auf Schattierung 50 mindestens 4,5:1 erreicht. Die Skalen sind so gesetzt, dass 100 bis 500 diese Schwelle verfehlen und die Wahl deterministisch auf 600 fällt. Wer eine Schattierung ≤ 500 abdunkelt, bricht die Deckungsgleichheit.

Damit sind beide Bauformen identisch:

| Merkmal | `.content-status` | `.fi-badge` |
|---|---|---|
| Höhe | 24 px gesetzt | 24 px aus `py-1` + 12-px-Zeile |
| Radius | `radius-content-sm` = 6 px | `rounded-md` = 6 px |
| Fläche / Text | `bg` / `fg` | Schattierung 50 / 600 = dieselben Werte |
| Punkt 8 px davor | `::before` | `::before`, in `theme.css` ergänzt |
| Abstand Punkt–Text | 8 px | 8 px |
| Schriftschnitt | 12 px, 600 | 12 px, 600 |

Regeln für Umsetzende:

- Status-Badges bekommen **kein** `->icon()`. Sonst stehen Punkt und Heroicon nebeneinander. `DisplayStatus::icon()` gehört in Auswahllisten, Filter und den Kopf des Prüfblatts.
- Statusfarben nie für Schaltflächen. Sie kennzeichnen Zustand, nicht Handlung; Handlungen tragen `content` oder Grau.
- Der Quellen-Monitor darf die Statusfarben mit eigenen Beschriftungen weiterverwenden (§ 7), aber keine achte Farbe einführen.

### Bewegung, Fokus, Tastatur

Fokusring 2 px `content-600`, Abstand 2 px. Klickfläche mindestens 44 × 44 px, auch bei Symbolschaltflächen. Bewegung nur bei erlaubtem `prefers-reduced-motion`. Statusänderungen und Zählerstände über eine höfliche Live-Region ansagen.

---

## 1. Übersicht (Desktop)

Beantwortet in unter zehn Sekunden: Ist der Tag in Ordnung?

### Aufbau von oben nach unten

**A. Störungsstapel** (nur wenn nötig, sonst nicht vorhanden)
Volle Breite, `radius-content-lg`, Statusfläche des Bandes, linke Kante 3 px. Text: „Für 3 Portale wurde heute nichts erzeugt." Rechts eine Schaltfläche „Betroffene Portale ansehen". Höchstens **drei** Bänder untereinander, 8 px Abstand, in der Rangfolge aus §1a; ab dem vierten eine Zählzeile. Nur das oberste Band trägt die Schaltfläche. Bauvorgabe in §1a (Ticket #73).

**B. Tagesziel** — die wichtigste Fläche des Panels
Karte über 12 Spalten, Innenabstand `content-6`, Höhe 168 px fest (kein Springen beim Nachladen).

- Links: Zahl im Format **41 / 48** in `content-metric` (44 px), `text-strong`. Darunter „veröffentlichte Artikel heute" in `content-label`.
- Mitte: waagerechter Fortschrittsbalken, Höhe 12 px, `radius-content-sm`, gestapelt in `status-*-fill` in Board-Reihenfolge (eingeplant, in Erstellung, zur Prüfung, veröffentlicht, fehlgeschlagen). Jeder Abschnitt anklickbar, führt gefiltert in die Artikelliste. Beschriftung unter dem Balken als Legende mit Punkt und Zahl, nie nur Farbe.
- Rechts: Ampelurteil in einem Satz — „Auf Kurs", „Rückstand von 7 Artikeln", „Produktion gestoppt (Budget)". Diese Zeile ist die eigentliche Antwort; die Zahl belegt sie nur.

Vor 12 Uhr ist ein Rückstand normal. Deshalb zeigt der Balken zusätzlich eine dünne senkrechte Marke am Sollwert der aktuellen Tageszeit, damit „22 von 48 um 10 Uhr" nicht als Störung gelesen wird.

**C. Portalraster** — 24 Kacheln, 6 Spalten ab 1280 px, 4 ab 1024 px
Kachel 1 Portal: Name (14 px, `text-strong`, einzeilig gekürzt), darunter zwei Punkte für die zwei Tagesartikel. Ist für das Portal das Tagesziel gefährdet, steht links **vor** dem Namen ein 6-px-Punkt in `status-failed-dot` (§1a). Punkt gefüllt `status-published-dot` = erschienen, `status-generating-dot` = läuft, `status-review-dot` = wartet auf Prüfung, `status-failed-dot` = gescheitert, leer mit Rand = noch offen. Kachelhöhe 72 px, `radius-content-lg`, `shadow-content-card`.

Sortierung: Portale mit Problemen zuerst, dann alphabetisch. Wer alles erledigt hat, rutscht nach unten — das Raster erzählt von selbst, wo es klemmt.

Klick auf eine Kachel öffnet die Produktion, gefiltert auf dieses Portal und heute.

**D. Zwei schmale Karten nebeneinander** (je 6 Spalten)

- **Kosten heute**: Betrag groß, darunter Balken gegen das Tagesbudget aus `config/content.php`, Beschriftung „24,10 $ von 35 $". Ab 80 % wechselt der Balken auf `status-review-dot`, ab 100 % auf `status-failed-dot` und die Karte bekommt die Zeile „Produktion angehalten".
- **Quellenlage**: Zeile je Konnektorgruppe (Trends, Search Console, SERP, Förderung/Recht, Statistik, News) mit Punkt und Klartext „aktuell" / „verzögert" / „ausgefallen seit 06:20". Nur eine Zeile pro Gruppe, Details im Quellen-Monitor.

**E. Letzte Ereignisse** — 8 Zeilen, kompakt
Zeit, Portal, Klartextsatz. Nur Ausnahmen und Meilensteine, kein Job-Protokoll. Verweis „Alle Ereignisse" am Ende.

### Zustände

- Leer und gut: vor dem ersten Lauf des Tages — „Der heutige Lauf startet um 05:00. Zuletzt erschienen gestern 48 von 48 Artikeln."
- Leer und schlecht: Orchestrator nicht gelaufen — Band A mit „Der Tageslauf ist heute nicht gestartet" plus Handlung „Lauf jetzt starten".
- Lädt: Tagesziel-Karte, Portalraster und beide schmalen Karten als Skelette in exakt diesen Maßen.
- Teilweise: Band A plus vollständig bedienbares Portalraster.
- Veraltet: Kostenzahl und Quellenlage tragen ihren Stand.
- Mehrere Störungen gleichzeitig: bis zu drei Bänder gestapelt, darunter die Zählzeile. Kein Band verdeckt ein anderes.

---

## 1a. Bauvorgabe Störungsstapel und Portalmarke (Ticket #73)

### Der Befund

`ContentOverviewService::alarms()` baut je Ursache höchstens ein Band und liefert sie sortiert
zurück. Die Übersicht rendert davon nur `alarms[0]`; alle weiteren schrumpfen auf „und :count
weitere Ursachen" ohne Text. „Tagesziel gefährdet" steht in dieser Reihe hinter Budget und
ausgefallenen Quellen — es ist also genau an den Tagen unsichtbar, an denen es auftritt. Die
Portalkachel führt `at_risk` im Datensatz, zeigt es aber nicht; sie sortiert betroffene Portale
nur nach vorn, und eine Position ohne Marke ist keine Aussage.

### Entscheidung 1: Rangfolge nach Schweregrad, dann nach Ursache

Ein Band mit `level = review` darf nie ein Band mit `level = failed` aus den ersten drei
Plätzen drängen. Die 80-Prozent-Budgetwarnung ist ein Hinweis, ein gerissenes Tagesziel ein
Verlust. Verbindliche Reihenfolge in `alarms()`:

| Rang | Ursache | `level` |
| --- | --- | --- |
| 1 | Festgehaltene Alarme des Orchestrators (`persistedAlarms()`) | failed |
| 2 | Tagesbudget erschöpft (`today_share >= 1.0`) | failed |
| 3 | **Tagesziel gefährdet** (`at_risk > 0`) | failed |
| 4 | Quelle(n) ausgefallen | failed |
| 5 | Für :count Portale läuft heute nichts nach Plan | failed |
| 6 | :percent % des Tagesbudgets verbraucht | review |
| 7 | Rückstand gegenüber dem Sollwert der Uhrzeit | review |
| 8 | Für :count Portale sind die Pipeline-Tabellen nicht erreichbar | review |

Gegenüber heute wandern zwei Blöcke: `at_risk` vor die ausgefallenen Quellen (eine ausgefallene
Quelle kostet Themen von morgen, ein gerissenes Tagesziel einen Artikel von heute), und die
80-Prozent-Warnung hinter alle `failed`-Bänder. Die Wortlaute bleiben unverändert.

### Entscheidung 2: Drei Bänder, eine Schaltfläche

- **Stapel.** Container um alle Bänder, `display: flex`, `flex-direction: column`,
  `gap: var(--spacing-content-2)` (8 px). Der Container trägt `role="status"` **einmal**;
  die einzelnen Bänder tragen keine eigene Rolle mehr. Drei getrennte Live-Bereiche würden
  vom Screenreader als drei Unterbrechungen gelesen.
- **Bandaufbau unverändert.** Je Band: `radius-content-lg`, `border-inline-start: 3px solid
  var(--color-status-{level}-dot)`, Fläche `--color-status-{level}-bg`, Innenabstand
  `content-4`, Text `content-body`, `font-medium`, Farbe `--color-status-{level}-fg`.
- **Menge.** Gerendert werden `array_slice($alarms, 0, 3)`.
- **Schaltfläche.** „Betroffene Portale ansehen" trägt **nur das erste Band** (`$index === 0`).
  Drei gleich aussehende Knöpfe untereinander sehen aus wie drei verschiedene Ziele, führen aber
  alle auf dieselbe Produktionsansicht. Bei den Bändern 2 und 3 füllt der Text die Breite.
- **Zählzeile.** Ab vier Ursachen folgt unter dem dritten Band eine eigene Zeile, kein Band:
  kein Rahmen, keine Fläche, `content-label`, `--color-text-base` (nicht `text-muted`, die Zeile
  steht auf dem Seitengrund), `margin-block-start: var(--spacing-content-2)`,
  `padding-inline-start: calc(3px + var(--spacing-content-4))` — damit sie unter dem Bandtext
  bündig steht, nicht unter der Rahmenkante. Wortlaut unverändert
  `{1}und eine weitere Ursache|[2,*]und :count weitere Ursachen` mit
  `count($alarms) - 3`. Die Zeile verschwindet bei drei oder weniger Ursachen; die heutige
  Einbettung in das erste Band entfällt ersatzlos.

### Entscheidung 3: Marke auf der Portalkachel

Die Kachel bleibt 72 px hoch und bekommt keinen zusätzlichen sichtbaren Text — dafür ist kein
Platz, und die Kachel muss im Raster überflogen werden können.

- **Punkt.** Bei `at_risk > 0` steht links vor dem Namen ein Punkt: 6 × 6 px, `border-radius:
  9999px`, `background: var(--color-status-failed-dot)`, `flex-shrink: 0`,
  `aria-hidden="true"`. Abstand zum Namen `content-2` (8 px).
- **Namenszeile.** Wird zur Flex-Zeile: `display: flex`, `align-items: center`,
  `gap: var(--spacing-content-2)`, `min-width: 0`. Der Name behält `truncate` und braucht
  dafür selbst `min-width: 0`, sonst schiebt er den Punkt aus der Kachel. Nicht betroffene
  Kacheln rendern keinen Punkt und keinen Platzhalter — der Name beginnt dort weiter links,
  das ist gewollt und macht die Marke im Raster erst auffällig.
- **Zugängliche Beschriftung.** Reine Farbe genügt nicht. Bei `at_risk > 0` bekommt der
  Kachel-Link `aria-label="{Name}, Tagesziel gefährdet, {published} von {target}
  veröffentlicht"` und `title="{Name} — Tagesziel gefährdet"`. Das `title` des Namensspans
  trägt denselben Text, sonst zeigt der Browser beim Zeigen auf den Namen nur den Namen.
  Nicht betroffene Kacheln bleiben unverändert ohne `aria-label`.
- **Graustufen.** Der Punkt ist eine An-/Abwesenheit, keine Farbunterscheidung, und bleibt
  deshalb ohne Farbe lesbar. Er darf nicht durch eine eingefärbte Kachelfläche oder einen
  farbigen Namen ersetzt werden — beides bricht bei fünf betroffenen Portalen das Raster.

### Abnahmefälle

1. Budgetwarnung (80 %) und `at_risk` gleichzeitig: beide Texte stehen ohne Interaktion
   untereinander, `at_risk` oben.
2. Fünf Ursachen: drei Bänder plus die Zeile „und 2 weitere Ursachen".
3. Genau drei Ursachen: keine Zählzeile.
4. Eine Ursache: Aussehen wie heute, ein Band mit Schaltfläche.
5. Nur das oberste Band trägt „Betroffene Portale ansehen".
6. Screenshot des Portalrasters in Graustufen: betroffene Kacheln sind erkennbar.
7. Screenreader auf einer betroffenen Kachel liest Name, „Tagesziel gefährdet" und den
   Tagesstand; der Punkt selbst wird nicht angesagt.
8. Portalname länger als die Kachel: Punkt bleibt sichtbar, Name wird gekürzt, Kachel bleibt
   72 px hoch.

---

## 1b. Bauvorgabe: Aktualisierungen zählen nicht als neuer Artikel (Ticket #103)

### Der Befund

`DailyReportBuilder::portal()` zählt jeden Entwurf des Tages mit Status `published` einzeln.
Eine Kindfassung aus dem Refresh-Lauf (#24/#86) trägt dieselbe `article_id`, denselben Titel,
denselben Slug und als `published_at` bewusst das Erstveröffentlichungsdatum. Der Bericht meldet
sie deshalb als zweiten Artikel: ein Portal, das an einem Tag nur aktualisiert hat, erscheint
als „Ziel erfüllt". Die Übersicht rechnet mit `ArticleDraft::originals()` bereits richtig —
Bericht und Panel widersprechen sich also. Umgekehrt ist die Aktualisierung im Panel heute
vollständig unsichtbar: geleistete und bezahlte Arbeit ohne jede Spur.

### Entscheidung 1: Eine Zählregel für alle drei Flächen

**Verbindlich:** Ein Entwurf mit gesetztem `parent_draft_id` ist **kein** Artikel des Tages.
Er zählt nicht in `published`, nicht gegen `target`, nicht in den Fortschrittsbalken, nicht in
die Punkte der Portalkachel und in keinen Statuszähler, der gegen das Tagesziel gelesen wird.
Einziger Zugriffsweg bleibt der bestehende Scope `ArticleDraft::originals()`; es entsteht keine
zweite Abfragelogik.

Er zählt dagegen **immer** in die Kosten. Das Geld ist ausgegeben, und Tages- wie Monatsbudget
müssen weiter aufgehen. Kosten werden nur getrennt **ausgewiesen**, nie herausgerechnet.

Eine Aktualisierung unterdrückt außerdem **keine** Störung: `at_risk`, „Für :count Portale läuft
heute nichts nach Plan" und der Wachhund bleiben unverändert. Ein Portal mit null neuen Artikeln
und drei Aktualisierungen hat sein Tagesziel gerissen und muss das auch sagen.

### Entscheidung 2: Zweite Spur „aktualisiert" im Datensatz

Der Bericht bekommt je Portal neben `articles` eine gleichwertige Liste `refreshes` und den
Zähler `refreshed`; `totals` bekommt `refreshed` als Summe. Felder je Zeile:

| Feld | Inhalt | Herkunft |
| --- | --- | --- |
| `title` | Titel der Kindfassung (identisch mit der Elternfassung) | `title` |
| `url` | Adresse des Artikels, unverändert seit der Erstveröffentlichung | `publication_json.url`, sonst die der Elternfassung |
| `cost` | Kosten des Aktualisierungslaufs inklusive Qualitätsgate (#94) | `generation_cost_usd` |
| `at` | Uhrzeit des Laufs, `HH:MM` | `updated_at`, sonst `created_at` der Kindfassung |
| `first_published` | Erstveröffentlichung, `TT.MM.JJJJ` | `published_at` |

`published_at` der Kindfassung darf **nicht** als Uhrzeit des Tages ausgegeben werden — es ist
das Datum der Erstveröffentlichung und läge in der Zeitspalte falsch. Es steht stattdessen als
`first_published` in eigener Beschriftung. Das ist zugleich die Erklärung, warum die Zeile nicht
mitzählt, und macht den Punkt ohne Fußnote klar.

### Entscheidung 3: Tagesbericht (Mail)

- **Betreff und Vorschauzeile** bleiben `:published von :target Artikeln`. Die Zahl ist nach
  Entscheidung 1 jetzt richtig; ein zweiter Wert im Betreff macht ihn unlesbar.
- **Einleitungssatz.** Der Aktualisierungsteil erscheint nur bei `totals.refreshed > 0`, als
  eigener Satz hinter dem bestehenden: „Zusätzlich wurden :count Artikel aktualisiert."
  (`trans_choice`, Einzahl „ein Artikel aktualisiert").
- **Portalzeile.** Hinter „:published/:target veröffentlicht" folgt bei `refreshed > 0`
  ein weiterer, mit `·` getrennter Abschnitt „:count aktualisiert", danach wie bisher der
  Betrag. Bei `refreshed = 0` ändert sich nichts.
- **Block „Aktualisiert".** Unter den Artikelzeilen, vor den fehlgeschlagenen Slots. Vorlauf
  eine Beschriftungszeile „Aktualisiert" in `font-weight: 600`, `color: #64748b`, 13 px,
  `margin: 12px 0 4px`. Darunter je Zeile im Aufbau der Artikelzeile: Uhrzeit, Titel als Link,
  danach in `#64748b` „erschienen am {first_published}, {Betrag} USD". Kein Score — eine
  Aktualisierung wird gegen dieselbe Schwelle geprüft, aber der Wert gehört zum Artikel, nicht
  zum Tag.
- **Leerer Zustand.** „Heute ist nichts erschienen." erscheint nur, wenn Artikel, Aktualisierungen
  und fehlgeschlagene Slots **alle** leer sind. Bei null Artikeln und mindestens einer
  Aktualisierung lautet der Satz über dem Block: „Heute ist kein neuer Artikel erschienen."
  Der Satz bleibt in `#64748b`, nicht in Rot: es ist eine Feststellung, die Bewertung leistet
  die Zahl `0/2` in der Portalzeile.
- **Sortierung der Portale** bleibt nach `published - target`, dann Name. Aktualisierungen
  gehen nicht in die Sortierung ein.

### Entscheidung 4: Übersicht

- Die Zählung in `ContentOverviewService::portalSnapshot()` ist bereits richtig und bleibt
  unverändert. Der Kommentar dort ist die verbindliche Begründung und wird nicht entfernt.
- Der Portaldatensatz bekommt zusätzlich `refreshed` (Anzahl der heute erzeugten Kindfassungen
  mit Status `published`), der Schnappschuss `target.refreshed` als Summe.
- **Tagesziel-Karte.** Unter „veröffentlichte Artikel heute" folgt bei `refreshed > 0` eine
  dritte Zeile in `content-label`, `--color-text-muted`:
  „und :count Aktualisierungen" (`trans_choice`, Einzahl „und eine Aktualisierung"). Die Karte
  behält ihre feste Höhe von 168 px; die Zeile passt in den vorhandenen Raum unter der
  Beschriftung. Kein eigener Zähler, keine zweite große Zahl — die Aktualisierung ist eine
  Nebenaussage und darf die Antwort „Ist der Tag in Ordnung?" nicht verwässern.
- Das Ampelurteil rechts bleibt unverändert und kennt keine Aktualisierungen.
- Der Fortschrittsbalken und seine Legende bleiben unverändert. Ein sechster Abschnitt
  „aktualisiert" wäre falsch: der Balken zerlegt das Tagesziel, und dazu gehört die
  Aktualisierung nicht.

### Entscheidung 5: Portalkachel

Die Kachel bleibt 72 px hoch und bekommt keine zusätzliche Zeile.

- **Ort.** Die untere Zeile trägt rechts heute „:published/:target". Bei `refreshed > 0` wird
  daraus `2/2 · ↻ 1`. Trenner ist ein `·` mit je 4 px Abstand, das Zeichen `↻` steht als
  `aria-hidden="true"` und trägt keine Bedeutung allein.
- **Form.** Ganze Zeile `white-space: nowrap`, damit Zähler und Marke nie umbrechen; sie behält
  `ms-auto`, `content-label`, `--color-text-muted`. Die Punkte links behalten Vorrang beim
  Platz, weil sie den Tagesstand tragen; reicht der Platz nicht, wird die Punktreihe gekürzt,
  nie der Zähler.
- **Farbe.** Kein Statuston. Die Aktualisierung ist weder gut noch schlecht, sie ist geleistete
  Arbeit. `--color-text-muted` auf der Kartenfläche, wie der Tagesstand daneben.
- **Zugängliche Beschriftung.** Der Kachel-Link bekommt bei `refreshed > 0` ein `title` und ein
  `aria-label`, das den Zähler ausspricht: „{Name}, :published von :target veröffentlicht,
  :count Artikel aktualisiert". Trifft zusätzlich `at_risk` zu, steht „Tagesziel gefährdet"
  wie in §1a **vor** dem Tagesstand, die Aktualisierung als letzter Teil. Der Punkt aus §1a
  bleibt unverändert.
- **Graustufen.** Zähler und Trenner sind Text und bleiben ohne Farbe lesbar.

### Wortwahl (verbindlich)

„Aktualisierung" / „aktualisiert", nie „Refresh", „Update" oder „Kindfassung" in einer Fläche,
die ein Betreiber liest. „Kindfassung" bleibt der Prüffläche vorbehalten (§4.3), wo die beiden
Fassungen tatsächlich nebeneinander stehen.

### Abnahmefälle

1. Portal mit einem neuen Artikel und einer Aktualisierung, Ziel 2: Bericht zeigt „1/2", nicht
   „2/2"; die Aktualisierung steht im eigenen Block; das Portal gilt als Ziel verfehlt.
2. Portal mit null neuen Artikeln und einer Aktualisierung: Bericht zeigt „0/2", darüber
   „Heute ist kein neuer Artikel erschienen.", der Block „Aktualisiert" ist da; die Störung
   bleibt bestehen.
3. Der genaue Fall aus #103 (Entwürfe 13 und 28, `article_id` 19): der Tagesbericht zeigt eine
   Artikelzeile und eine Aktualisierungszeile mit derselben Adresse, nicht zwei Artikel.
4. Summe der Portalkosten im Bericht entspricht weiterhin `cost.today`; die Kosten der
   Aktualisierung fehlen nirgends.
5. Zeitangabe der Aktualisierungszeile ist die Uhrzeit des heutigen Laufs, das Datum daneben
   die Erstveröffentlichung.
6. Übersicht und Bericht desselben Tages nennen für jedes Portal dieselbe Zahl veröffentlichter
   Artikel.
7. Portal ohne Aktualisierung: Bericht, Tagesziel-Karte und Kachel sehen unverändert aus, kein
   Platzhalter, kein „0 aktualisiert".
8. Screenreader auf einer Kachel mit Aktualisierung liest Name, Tagesstand und die Zahl der
   Aktualisierungen; das Zeichen `↻` wird nicht angesagt.

---

## 2. Produktion — Pipeline-Board (Desktop)

Reiter 1 unter Produktion. Kanban über die DisplayStatus, nicht über DraftStatus/TopicStatus.

### Spalten

Feste Reihenfolge aus `DisplayStatus::board()`: **Themenvorschlag · Eingeplant · In Erstellung · Zur Prüfung · Veröffentlicht · Fehlgeschlagen**. „Zurückgezogen" ist keine Spalte, sondern über den Statusfilter erreichbar — sonst steht dauerhaft eine Spalte im Blick, die niemand bearbeitet.

Spaltenbreite 280 px fest, waagerechtes Scrollen erlaubt. Spaltenhintergrund `surface-sunken`, `radius-content-lg`, Innenabstand `content-3`.

Spaltenkopf, Höhe 44 px, klebend: Beschriftung + Anzahl in einer Pille. Bei mehr als 50 Karten zeigt die Spalte die 50 dringendsten und darunter den Verweis „+ 84 weitere in der Liste ansehen" — ein Board mit 300 Karten ist keine Übersicht. Ziel und Parameter des Verweises stehen in §3a.

### Karte

Breite 100 %, Innenabstand `content-3`, `radius-content-lg`, `surface-card`, `shadow-content-card`, Abstand 8 px. Aufbau:

1. Portalname, 12 px, `text-muted`, mit 8-px-Farbpunkt des Portals
2. Artikeltitel, 14 px, `text-strong`, maximal zwei Zeilen
3. Statuspille (`content-status`, 24 px) und rechts daneben, wenn vorhanden, der Qualitätsscore als Zahl in `score-*`
4. Fortschrittszeile `content-status-progress` — hier und nur hier erscheint der interne Feinschritt („Qualitätsprüfung läuft"), nie als eigene Pille
5. Fußzeile: geplante Veröffentlichung als Zeit, bei `failed` stattdessen der Fehlergrund in einem Satz

Hover: `shadow-content-raise`, keine Verschiebung. Klick öffnet die Detailansicht als Blatt von rechts, 640 px breit, nicht als eigene Seite — der Board-Kontext bleibt erhalten.

### Kein Ziehen und Ablegen

Die Karten werden von der Pipeline bewegt, nicht von Hand. Ziehen würde Übergänge suggerieren, die `DraftStatus::allowedTransitions()` gar nicht erlaubt. Erlaubte Handlungen stehen als benannte Schaltflächen im Detailblatt: Prüfen, Freigeben, Neu erzeugen, Verwerfen, Anhalten.

### Zustände

- Leer und gut: „Alles durch. 48 Artikel heute veröffentlicht." mit Verweis auf die Kalenderansicht.
- Leer und schlecht: leere Spalte „In Erstellung" bei laufendem Tag — Hinweis in der Spalte selbst, nicht seitenweit.
- Lädt: drei Kartenskelette je Spalte, exakte Kartenhöhe.
- Teilweise: Band über dem Board.
- Veraltet: nicht relevant, Board-Daten sind live. Trotzdem Zeitstempel „aktualisiert vor 30 Sekunden" in der Kopfzeile.

### Mobil (< 1024 px) — Mobilvariante 1 von 3

Spalten werden zu einer Auswahlliste: waagerecht scrollende Statusreiter oben (Beschriftung + Anzahl, aktiver Reiter mit 2-px-Unterstrich in `content-600`), darunter eine einspaltige Kartenliste des gewählten Status. Karte identisch, volle Breite, Innenabstand `content-4`. Detailblatt öffnet sich von unten auf 90 % Höhe.

---

## 3. Produktion — Redaktionskalender (Desktop)

Reiter 2. Beantwortet: Was erscheint wann, und wo klaffen Lücken?

**Ansicht**: Monat als Standard, Woche als Umschaltung. Monatsraster 7 Spalten, Zellhöhe 132 px fest.

**Tageszelle**: Datum oben links; oben rechts der Zählerstand **„44/48"** in 12 px, eingefärbt nach Erfüllung (`score-good` bei Soll erreicht, `score-mid` bei Rückstand, `score-poor` bei unter der Hälfte). Darunter höchstens drei Statusbalken, je 6 px hoch, volle Zellbreite, gestapelt nach Status in `status-*-fill` — nicht die Artikeltitel. Bei 48 Artikeln pro Tag ist eine Titelliste in der Zelle unlesbar.

Heute: Zelle mit 2-px-Rand `content-600`. Vergangene Tage mit Soll erreicht: dezenter Haken. Zukünftige Tage: nur „geplant: 48".

Klick auf eine Zelle öffnet ein Blatt von rechts mit der Artikelliste dieses Tages, gruppiert nach Portal.

**Wochenansicht**: 7 Spalten, senkrechte Zeitachse 05:00–20:00 in Stundenschritten, Artikel als Blöcke zur geplanten Veröffentlichungszeit. Das ist die Ansicht, in der die gestaffelte Veröffentlichung aus #21 sichtbar wird — Blöcke dürfen sich nicht zur selben Minute stapeln.

**Zustände**: Leer und gut = Monat ohne Lücke, mit Zusammenfassung oben („September: 1.392 von 1.440"). Leer und schlecht = Tag mit 0 von 48 bekommt `status-failed-bg` und einen Klartextgrund im Blatt. Lädt = Zellskelette. Teilweise = Band über dem Kalender. Veraltet = Zeitstempel in der Kopfzeile.


---

## 3a. Produktion — Artikelliste (Desktop)

Reiter 3, Wert `?reiter=liste`. Beantwortet: Zeig mir alles, was das Board nicht mehr zeigen kann — sortierbar, durchblätterbar, teilbar. Das Board ist die Übersicht des Tages, der Kalender die Zeitachse, die Liste der vollständige Bestand.

### Filterbalken

Unverändert `content.partials.filter-bar` mit `App\Content\Livewire\Concerns\HasPipelineFilters` — Portal · Status · Region · Branche · Zurücksetzen, jede Auswahl in der URL. Kein zusätzlicher Filter, keine eigene Suche in diesem Ticket: wer sucht, filtert erst Portal und Status. Rechts im Balken (`$meta`) steht statt des Board-Zeitstempels die Trefferzeile, siehe unten.

Anders als Board und Kalender zeigt die Liste zurückgezogene Artikel weiterhin nur über den ausdrücklichen Statusfilter. Das Verhalten von `filterByStatus()` bleibt unangetastet.

### Tabelle

Sieben Spalten, feste Reihenfolge, keine achte:

| Spalte | Breite | Inhalt | Ausrichtung |
|---|---|---|---|
| Titel | flexibel, min. 320 px | Artikeltitel, 14 px, `text-strong`, eine Zeile, `truncate`, voller Text als `title` | links |
| Portal | 180 px | Portalname, `truncate`, voller Text als `title` — kein Statuspunkt davor, die Statusspalte steht daneben | links |
| Status | 160 px | `content-status`-Pille, unverändert 24 px | links |
| Region | 160 px | Klartext aus `regionLabel()`, nie der Code | links |
| Aktualisierung | 152 px | Statuspille nach §4.3, leer wenn kein Zustand zutrifft, kein Umbruch, nicht sortierbar | links |
| Score | 88 px | ganze Zahl in `score-good/mid/poor`, ohne Wert ein `—` in `text-muted` | rechts, tabellar |
| Termin | 132 px | `TT.MM. HH:MM`, ohne Zeit nur Datum, ohne Termin `ohne Termin` in `text-muted` | rechts, tabellar |

Zeilenhöhe 44 px fest (`h-11`), Schrift `content-table` (14 px), Kopfzeile 40 px, klebend unter dem Filterbalken, `surface-sunken`, Beschriftung `content-label` in `text-base` (auf `surface-sunken` verfehlt `text-muted` die 4,5:1, #64), Großbuchstaben nein. Zeilentrenner `line-soft`, kein Zebrastreifen — die Statuspille trägt die Farbe, ein zweites Rasterelement macht die Zeile unruhig. Hover: Zeilenfläche `surface-sunken`, keine Verschiebung, kein Schatten; die Platzhalter „—" und „ohne Termin" wechseln dabei auf `text-base`, weil `text-muted` auf `surface-sunken` unter 4,5:1 liegt (#64).

Bei Portalfilter auf genau ein Portal bleibt die Spalte Portal stehen. Sie zu verstecken spart 180 px und kostet die Gewissheit, worauf man gerade schaut.

### Sortierung

Sortierbar sind Titel, Portal, Status, Score, Termin. Region ist nicht sortierbar — eine alphabetische Bundesland­reihe beantwortet keine Frage, dafür ist der Regionsfilter da.

Kopfzelle ist ein `<button>` über die volle Zellbreite mit `aria-sort` (`ascending`/`descending`/`none`) an der `<th>`. Pfeilsymbol 12 px nur an der aktiven Spalte, in `text-strong`; inaktive Spalten zeigen kein blasses Pfeilpaar. Klick wechselt die Richtung, Klick auf eine andere Spalte startet mit deren sinnvoller Erstrichtung: Score und Termin absteigend, Titel und Portal aufsteigend.

Sortierung steht in der URL: `?sortieren=termin&richtung=ab`. Erlaubte Werte `titel`, `portal`, `status`, `score`, `termin`; Richtung `auf`/`ab`. Unbekannte Werte fallen still auf die Vorgabe zurück.

Vorgabe ist **Termin aufsteigend**, Karten ohne Termin immer am Ende — unabhängig von der Richtung. Ein Nullwert ist keine kleine Zahl, sondern eine offene Frage; er gehört ans Ende beider Richtungen. Zweitschlüssel bei Gleichstand: Score absteigend, dann Titel aufsteigend, damit die Reihenfolge über Seitengrenzen hinweg stabil bleibt. Status sortiert nach der Board-Reihenfolge aus `DisplayStatus::board()`, nicht alphabetisch — „Eingeplant" vor „In Erstellung" ist die Reihenfolge, in der die Pipeline arbeitet.

Sortiert wird über die zusammengeführten Karten aller Portale, nicht je Portal. Ein Score von 82 aus Portal B gehört zwischen 85 und 79 aus Portal A.

### Paginierung

50 Zeilen je Seite, keine wählbare Seitengröße. Seite in der URL als `?seite=3`. Jede Filter- oder Sortieränderung setzt auf Seite 1 zurück.

Fuß der Tabelle, Höhe 56 px: links die Trefferzeile „51–100 von 312", rechts Zurück/Weiter als benannte Schaltflächen plus Seitenzahlen ab zwei Seiten. Ist alles auf einer Seite, entfällt der ganze Fuß statt einer toten Leiste. Die Trefferzeile erscheint zusätzlich im Filterbalken rechts, damit die Gesamtzahl ohne Scrollen sichtbar ist.

Die Zusammenführung geschieht im Speicher (`ContentPipelineService::cards()` liest je Tenant). Ab spürbarer Wartezeit wird nicht die Seitengröße erhöht, sondern die Ergebnismenge über den Filterbalken verengt — die Liste bleibt eine Arbeits-, keine Exportansicht.

### Zeile öffnen

Klick auf die Zeile öffnet dasselbe Detailblatt wie im Board: `cardDetailsAction()` mit `content.partials.card-details`, Blatt von rechts, 640 px. Kein Sprung auf eine eigene Seite, die Listenposition bleibt erhalten.

Bedienung ohne Maus: der Titel ist der fokussierbare `<button>` der Zeile mit `aria-haspopup="dialog"`; die Zeile selbst reagiert zusätzlich auf den Mausklick, bekommt aber weder `tabindex` noch eine Rolle. So gibt es je Zeile genau einen Tabstopp. Fokusring 2 px `content-600` mit 2 px Abstand, an der Zelle sichtbar, nicht abgeschnitten.

### Sprung aus dem Board

Der Hinweis in einer übervollen Board-Spalte ändert Text und Funktion. Aus dem Satz „+ 84 weitere — bitte Filter enger setzen." wird ein Verweis: **„+ 84 weitere in der Liste ansehen"**, `content-700`, unterstrichen, Klickfläche mindestens 44 px hoch.

Ziel ist die Liste mit den Filtern des Boards, ergänzt um den Status der Spalte und die Sortierung der Spalte, damit die Fortsetzung genau dort weitergeht, wo die Spalte abbricht:

`?reiter=liste&portal[]=…&region[]=…&branche[]=…&status[]=<Spaltenstatus>&sortieren=score&richtung=ab`

Der Spaltenstatus **ersetzt** den Statusfilter des Boards, er ergänzt ihn nicht. Der Sinn des Verweises ist genau diese eine Spalte.

Dieselbe Zielform bekommen die Abschnitte des Fortschrittsbalkens der Übersicht (§1) und, sobald der Kalender einen Tag mit mehr Artikeln zeigt als sein Blatt fasst, die Fußzeile des Tagesblatts.

### Zustände

- **Leer und gut** — kein Filter gesetzt und nichts vorhanden: „Noch keine Artikel. Der heutige Lauf startet um 05:00." mit Verweis auf die Übersicht.
- **Leer und schlecht** — Filter gesetzt, kein Treffer: „Kein Artikel passt zu diesen Filtern." und genau eine Handlung: „Filter zurücksetzen". Kein Vorschlagsgenerator.
- **Lädt** — zehn Zeilenskelette in exakt 44 px Höhe, Kopfzeile und Filterbalken bleiben bedienbar. Die Trefferzahl bleibt leer, bis sie echt ist.
- **Teilweise fehlgeschlagen** — ein Portal antwortet nicht: geschlossenes Band in `status-review-bg` über der Tabelle, „2 von 24 Portalen konnten nicht gelesen werden.", der Rest bleibt sortier- und blätterbar.
- **Veraltet** — nicht relevant, die Liste liest live. Kein Polling: eine Liste, die unter der Hand die Zeilen tauscht, verliert die Zeile, die man gerade lesen wollte. Stattdessen im Filterbalken „Stand: 09:14" und daneben „Aktualisieren".

### Mobil (< 1024 px) — Kartenliste wie Mobilvariante 3 von 3 (§5)

Keine waagerecht scrollende Tabelle. Jede Zeile wird eine Karte über die volle Breite, Innenabstand `content-4`, Trennlinie `line-soft`, Mindesthöhe 72 px:

- Zeile 1: Portalname (`content-label`, `text-muted`), rechts der Score.
- Zeile 2: Titel, `content-table`, `text-strong`, höchstens zwei Zeilen.
- Zeile 3: Statuspille, rechts Termin in `content-label`.

Die Karte ist der Tabstopp und öffnet das Detailblatt von unten auf 90 % Höhe. Sortierung wird zu einer Klappliste „Sortieren: Termin ↑" über der Liste; der Filterbalken klappt wie überall auf „Filter (3)".

### Abgrenzung

Keine Massenauswahl, keine Kontrollkästchen, kein Export in diesem Ticket. Handlungen an einem Artikel geschehen im Detailblatt, so wie im Board. Wer mehrere Artikel gleichzeitig anfassen will, braucht vorher ein Rechtekonzept dafür.

---

## 4. Prüfung (Desktop) — der einzige Screen, der Arbeit einfordert

Ziel: Entscheidung in unter 90 Sekunden, vollständig mit der Tastatur.

### Zweispaltiges Layout, kein Listen-Detail-Sprung

| Spalte | Breite | Inhalt |
|---|---|---|
| Warteschlange | 320 px, fix, `surface-card` | Liste der wartenden Artikel |
| Prüffläche | Rest, max. 1120 px | Vorschau, Qualitätsreport, Quellen |

**Warteschlange**: Zeilen 72 px, Portalname klein, Titel zweizeilig, Score-Zahl rechts in `score-*`, Wartezeit („seit 3 h") in `content-label`. Aktive Zeile mit linker Kante 3 px `content-600`. Sortierung: längste Wartezeit zuerst.

**Prüffläche**, drei Abschnitte untereinander, alle offen — kein Reiterwechsel, denn jeder Wechsel kostet Sekunden:

1. **Artikelvorschau** — der Artikel in der echten Lesertypografie (18 px, Zeilenhöhe 1,7, Spalte 680 px), auf 640 px Höhe begrenzt mit eigenem Scrollbereich und Umschaltung „Ganze Seite ansehen" (öffnet die Portalvorschau in neuem Tab). Ohne echte Typografie beurteilt der Prüfer einen anderen Text als der Leser.
2. **Qualitätsreport** — Kopfzeile mit Gesamtscore als große Zahl und der Auto-Freigabegrenze daneben („74 von 100, Grenze 80"). Darunter je Rubrik eine Zeile: Rubrikname, Balken 6 px in `score-*`, Punktzahl, und **die Begründung des Modells im Klartext**. Rubriken unter der Grenze zuerst. Darunter der SEO-Lint und der Faktencheck als Liste von Befunden; jeder Befund verweist per Anker in die Vorschau und hebt die betroffene Stelle dort in `status-review-bg` hervor. Das ist die Verbindung, die die Prüfung schnell macht.
3. **Quellen** — Tabelle: Quelle, Datum, Verwendung im Artikel, Verweis. Quellen älter als 12 Monate mit Warnmarke. Eine Zeile je Quelle, höchstens sieben Spalten.

**Entscheidungsleiste**, klebend am unteren Rand der Prüffläche, Höhe 72 px, `surface-card`, obere Linie `line-strong`:

`Freigeben und veröffentlichen` (primär, `content-600`) · `Freigeben, Termin behalten` · `Neu erzeugen lassen` · `Verwerfen` (Textschaltfläche, `status-failed-fg`).

Verwerfen und Neu erzeugen verlangen einen Grund aus einer kurzen Auswahlliste plus optionalem Freitext. Der Grund fließt in die Lernschleife (#23) — eine Ablehnung ohne Grund ist verlorene Information.

**Tastatur**: `J`/`K` durch die Warteschlange, `F` freigeben, `N` neu erzeugen, `V` verwerfen (mit Rückfrage), `Enter` bestätigt, `Esc` bricht ab. Die Belegung steht dauerhaft klein in der Entscheidungsleiste. Nach jeder Entscheidung rückt die nächste Zeile automatisch nach; die getroffene Entscheidung wird 6 Sekunden lang als rücknehmbare Meldung eingeblendet.

**Zustände**: Leer und gut — „Nichts zu prüfen. Zuletzt geprüft: heute 09:14, 3 Artikel." Leer und schlecht — Gate ausgefallen: „Seit 04:00 kam kein Artikel in die Prüfung. Das Qualitätsgate meldet einen Fehler." Lädt — Vorschau- und Reportskelett. Teilweise — Artikel ohne Quellenbelege bekommen im Quellenabschnitt ein eigenes Band statt eines leeren Blocks. Veraltet — nicht relevant.

### 4.1 Vergleich mit der stehenden Fassung (Aktualisierungen, #24 / #44)

Der Abschnitt erscheint nur, wenn eine stehende Fassung existiert. Er beantwortet zwei Fragen in dieser Reihenfolge: *Lohnt die Aktualisierung überhaupt?* und *Was genau ist jetzt anders?* Absatzebene allein beantwortet nur die erste. Deshalb kommt eine zweite Auflösungsstufe dazu: Wortmarken innerhalb geänderter Absätze.

**Zerlegung bleibt zweistufig.** Der Fließtext ist HTML. Stufe 1 zerlegt beide Fassungen weiter in Klartextblöcke und gleicht sie über die längste gemeinsame Teilfolge ab — daran ändert sich nichts. Stufe 2 vergleicht *nur innerhalb eines gepaarten Blocks* auf Wortebene. Ein Zeichenvergleich über rohes Markup ist ausgeschlossen; die Marken werden auf bereits entschärften Klartext gesetzt und als `<del>`/`<ins>` ausgezeichnet.

**Neuer Zeilentyp `changed`.** Heute kennt die Tabelle nur `unchanged`, `added`, `removed`. Ein umformulierter Absatz erscheint dadurch als zwei Zeilen — einmal ganz rot, einmal ganz grün — obwohl sich eine Jahreszahl geändert hat. Ein Paar aus je einem `removed` und einem `added` Block wird zu einer `changed`-Zeile zusammengezogen, wenn die Blöcke einander ähnlich genug sind (Ähnlichkeitsschwelle 0,5 auf Wortebene; darunter bleiben es zwei getrennte Zeilen, denn dann ist der Absatz wirklich ersetzt und keine Wortmarke hilft).

**Zeilentypen und Darstellung**

| Typ | Linke Spalte „Bisher" | Rechte Spalte „Neu" | Flächenfarbe |
|---|---|---|---|
| `unchanged` | Klartext | Klartext | keine |
| `removed` | Klartext | leer | links `status-failed-bg` |
| `added` | leer | Klartext | rechts `status-published-bg` |
| `changed` | Klartext mit `<del>`-Marken | Klartext mit `<ins>`-Marken | beide Spalten `surface-sunken` |
| `collapsed` | Trennzeile über beide Spalten | — | `surface-sunken` |

Eine `changed`-Zeile bekommt **nicht** die volle Statusfläche. Sonst ist bei einer Aktualisierung die halbe Tabelle rot und grün und die Wortmarken gehen darin unter. Die Zeile bleibt neutral, nur die geänderten Wörter tragen Farbe.

**Wortmarken**

Zwei neue Tokens in `resources/css/content/theme.css`, direkt unter den Statusfarben, mit eigenem Namensraum, weil sie keine Statusbedeutung haben:

```
--color-diff-del-bg: #fecaca;  --color-diff-del-fg: #7f1d1d;   /* 8,1:1 */
--color-diff-ins-bg: #a7f3d0;  --color-diff-ins-fg: #064e3b;   /* 9,4:1 */
```

Bauform: Innenabstand 0 / 2 px, Radius 2 px, kein eigener Zeilenabstand — die Marke darf die Zeilenhöhe des Absatzes nicht verändern, sonst springt die Gegenspalte aus dem Takt. Farbe ist nie das einzige Merkmal: `del` zusätzlich durchgestrichen, `ins` unterstrichen (`text-decoration-thickness: 1px`, `underline-offset: 2px`). Bei `prefers-contrast: more` entfällt die Fläche, Durchstreichung und Unterstreichung bleiben.

**Gutter statt Farbe als einziger Träger.** Jede Spalte bekommt links eine 20 px breite Rinne mit dem Zeichen `−` (removed), `+` (added), `~` (changed), `content-label`, `text-base` (bei `changed` liegt sie auf `surface-sunken`, #64), `aria-hidden`. Die Zeile selbst trägt für Screenreader einen unsichtbaren Präfix: „Entfernt:", „Neu:", „Geändert:".

**Zusammengefasste Strecken.** Heute steht „12 unveränderte Absätze" als Text in beiden Spalten und liest sich wie Artikelinhalt. Stattdessen eine echte Trennzeile über beide Spalten: `surface-sunken`, Höhe 32 px, mittig `content-label` in `text-base` (#64), Text „12 unveränderte Absätze — einblenden". Der Text ist eine Schaltfläche und klappt die Strecke an Ort und Stelle auf (`aria-expanded`); ohne JavaScript bleibt die Zeile reine Beschriftung. Kontext bleibt bei einem Absatz vor und nach jeder Änderung.

**Kopfzeile des Abschnitts.** Zählung auf Absatzebene ist zu grob, wenn nichts als drei Wörter getauscht wurden. Zeile lautet: „3 geänderte, 1 neuer, 0 entfallene Absätze". Ist `identical` wahr, bleibt der heutige Satz stehen. Zusätzlich rechts in `content-asof` der Vergleichsbezug: „gegen die Fassung vom 14.03.2026".

**Kürzung.** Die 600-Zeichen-Kürzung je Block bleibt für `unchanged`, entfällt aber für `changed`: ein gekürzter Absatz kann die geänderte Stelle abschneiden, und dann zeigt die Ansicht eine Änderung an, die niemand sieht. Lange geänderte Absätze laufen vollständig.

**Zustände.** Lädt — drei Skelettzeilen in `surface-sunken`. Identisch — der heutige Satz, Tabelle entfällt. Nur Streichungen — Hinweisband in `status-review-bg`: „Die neue Fassung ist kürzer und bringt keinen neuen Inhalt." Keine stehende Fassung — Abschnitt erscheint nicht.

**Responsiv.** Ab 1024 px zwei Spalten nebeneinander. Darunter kippt die Zeile auf Einspaltigkeit: „Bisher" über „Neu", zwischen beiden eine 1 px `line-soft`, die Blockpaare bleiben als Karte zusammen. Kein waagerechtes Scrollen.

**Zur Umsetzung.** `jfcherng/php-diff` liefert die Wortebene, sein `SideBySide`-Renderer aber eigenes Markup mit eigenen Klassennamen. Dieses HTML direkt auszugeben, hängt eine zweite, fremde Farbwelt neben die Tokens des Panels. Vorgabe daher: Rückgabestruktur (`rows`, `added`, `removed`, `unchanged`, `identical`) beibehalten, um `changed` als vierten Typ und je Zeile `before_html`/`after_html` erweitern, in denen ausschließlich `<del>` und `<ins>` als Markup vorkommen. Das Template bleibt Herr über Farbe und Abstand.

### 4.1a Bauvorgabe zum Vergleich (Zuarbeit zu #53)

§4.1 legt Verhalten und Farbe fest. Dieser Abschnitt schließt die Lücken, die beim Bauen sonst
jeder anders schließt. Er ist verbindlich, wo er §4.1 ergänzt; wo er ihr zu widersprechen
scheint, gilt §4.1.

**Was das Template je Zeile erwartet**

| Typ | `before` | `after` | `before_html` | `after_html` | `count` |
|---|---|---|---|---|---|
| `unchanged` | Klartext, auf 600 Zeichen gekürzt | dito | — | — | — |
| `removed` | Klartext, ungekürzt | `null` | — | — | — |
| `added` | `null` | Klartext, ungekürzt | — | — | — |
| `changed` | Klartext, ungekürzt | Klartext, ungekürzt | Klartext mit `<del>` | Klartext mit `<ins>` | — |
| `collapsed` | `null` | `null` | — | — | Zahl der übersprungenen Absätze |

`before`/`after` bleiben auf allen Zeilen gesetzt und entschärft, damit die Ansicht auch dann
lesbar ist, wenn `before_html` fehlt. Bei `changed` gibt das Template `before_html`/`after_html`
aus und fällt auf `before`/`after` zurück, wenn beide leer sind. Die Zählzeile „12 unveränderte
Absätze" steht **nicht** mehr in `before`/`after` — die Zahl steht in `count`.

**Aufbau einer Zeile**

Jede Tabellenzelle besteht aus Rinne und Text nebeneinander (`display: flex; gap: 8px`):

1. Rinne: `<span class="content-diff-gutter" aria-hidden="true">` mit `−`, `+`, `~` oder leer,
   feste Breite 20 px, `content-label`, `text-base`, oben ausgerichtet, Zeilenhöhe wie der Text.
2. Screenreader-Präfix: `<span class="sr-only">` mit „Entfernt: ", „Neu: ", „Geändert: ".
   `unchanged` bekommt keinen Präfix, sonst liest sich jeder Absatz doppelt an.
3. Der Text selbst.

Zellabstände: `padding: 8px 12px 8px 8px`, Zeilenhöhe 1,55, Mindesthöhe der Zeile 40 px.
Leere Gegenzellen bei `removed`/`added` bleiben leer, ohne Rinne und ohne Platzhalterzeichen.

**Die `collapsed`-Zeile** ist ein `<tr>` mit einem `<td colspan="2">`: Höhe 32 px, Fläche
`surface-sunken`, Inhalt mittig, `content-label` in `text-base` (#64), keine Rinne. Der Text ist eine
`<button type="button">` mit `aria-expanded` und `aria-controls` auf die aufklappbare Strecke.
Ohne JavaScript (`x-cloak`-freier Grundzustand) bleibt sie sichtbarer Text ohne Schaltflächen-
Optik: dann `<span>` statt `<button>`. Fokusring wie im Panel üblich, 2 px `content-600`,
2 px Abstand.

**CSS in `resources/css/content/theme.css`**

Tokens direkt unter dem Statusblock, danach die Bauform:

```
.content-diff-mark--del,
.content-diff-mark--ins {
    padding: 0 2px;
    border-radius: 2px;
    line-height: inherit;
    text-decoration-thickness: 1px;
    text-underline-offset: 2px;
}
.content-diff-mark--del { background: var(--color-diff-del-bg); color: var(--color-diff-del-fg); text-decoration-line: line-through; }
.content-diff-mark--ins { background: var(--color-diff-ins-bg); color: var(--color-diff-ins-fg); text-decoration-line: underline; }

@media (prefers-contrast: more) {
    .content-diff-mark--del,
    .content-diff-mark--ins { background: transparent; color: inherit; }
}
```

`del` und `ins` werden über einen Elementselektor innerhalb der Diff-Tabelle gestylt, nicht über
eine Klasse im gelieferten HTML — im `before_html`/`after_html` steht laut §4.1 ausschließlich
`<del>`/`<ins>` ohne Attribute.

**Responsiv.** Umbruch bei 1024 px. Darunter wird jede Zeile zu einer Karte: `<td>` auf
`display: block`, „Bisher" über „Neu", dazwischen 1 px `line-soft`, zwischen den Karten 12 px
Abstand. Die Spaltenüberschriften werden unter 1024 px zu Zeilenbeschriftungen in `content-label`
über der jeweiligen Hälfte. Das heutige `overflow-x-auto` um die Tabelle entfällt — waagerechtes
Scrollen ist ausgeschlossen.

**Kopfzeile.** Der heutige Satz „:added neue, :removed entfallene Absätze" wird ersetzt durch
„:changed geänderte, :added neue, :removed entfallene Absätze". Rechts daneben in `content-asof`
der Vergleichsbezug „gegen die Fassung vom TT.MM.JJJJ"; fehlt das Datum der stehenden Fassung,
entfällt nur dieser rechte Teil, nicht die ganze Zeile.

**Bänder und Zustände.** Nur Streichungen (`added === 0 && removed > 0 && changed === 0`):
Band über der Tabelle, `status-review-bg`, Text „Die neue Fassung ist kürzer und bringt keinen
neuen Inhalt." Identisch: heutiger Satz, keine Tabelle. Lädt: drei Zeilen `surface-sunken`,
Höhe 40 px, 8 px Abstand, ohne Pulsieren bei `prefers-reduced-motion`.

**Abnahme.** Geprüft wird an einem Entwurf mit (a) einer getauschten Jahreszahl in einem langen
Absatz, (b) einem vollständig ersetzten Absatz, (c) einer Strecke von mehr als 5 unveränderten
Absätzen. Erwartet: (a) eine `changed`-Zeile, Absatz vollständig, nur die Zahl markiert;
(b) zwei getrennte Zeilen; (c) eine `collapsed`-Trennzeile mit Zähler.

### 4.2 Längenkorridor: eine Wahrheit, und was der Prüfer davon sieht (Ticket #62)

**Der Befund.** Es gibt heute zwei Korridore, die sich widersprechen. Der Generator zielt je
Suchintention auf 900–1.800 Wörter (`content.generation.target_words`), das Qualitätsgate trägt
`content.quality.min_words = 1200` / `max_words = 2600`. Ein `transactional`-Artikel mit 1.000
Wörtern entsteht regelkonform und fiele im Gate durch; ein Artikel mit 2.400 Wörtern bestünde,
kann aus dem Generator aber nie kommen.

Erschwerend: die beiden Zahlen aus `content.quality` liest **kein** Codepfad. Gemessen wird
heute gegen die flachen 900/1.800 aus `content.generation` — der `word_count`-Regel in
`config/content_seo_rules.php` sind `min`/`max` auf `null` gesetzt und der `SeoLinter` fällt
auf die Generatorgrenzen zurück. Das ist der zweite, stillere Fehler: ein `transactional`-Artikel
mit 1.780 Wörtern liegt 480 Wörter über seinem Ziel und wird trotzdem als „ok" gemeldet, ein
`informational`-Artikel mit 950 Wörtern ebenso. Der Korridor ist so weit, dass er nichts mehr
misst.

#### Entscheidung 1: Der Zielkorridor der Suchintention ist die einzige Wahrheit

`content.generation.target_words` gilt. Alle anderen Längenzahlen werden entweder gestrichen
oder zur Reißleine.

| Schlüssel | heute | künftig |
|---|---|---|
| `content.generation.target_words` | vier Korridore je Intention | **unverändert, Quelle der Wahrheit** |
| `content.generation.min_words` / `max_words` | 900 / 1.800, klemmen den Zielkorridor zusätzlich ein | **entfallen.** Der Zielkorridor braucht keine zweite Klammer um sich herum |
| `content.quality.min_words` / `max_words` | 1.200 / 2.600, von niemandem gelesen | **entfallen** |
| `content.quality.word_tolerance` | — | **neu**, `['under' => 0.10, 'over' => 0.25]` |
| `content.quality.hard_word_limits` | — | **neu**, `['min' => 600, 'max' => 3000]` — die äußere Reißleine |

Die Umgebungsvariablen `CONTENT_GENERATION_MIN_WORDS` und `CONTENT_GENERATION_MAX_WORDS`
entfallen mit. Sie stehen in keiner `.env` und in keiner Beispieldatei.

**Warum asymmetrische Toleranz.** Die beiden gemessenen Läufe liegen bei 1.340 und 1.474
Wörtern — bei `transactional` (Ziel 900–1.300) also über dem Ziel. Das Modell schreibt
systematisch länger als beauftragt. Ein Text, der 20 % über dem Ziel liegt, ist für den Leser
kein Mangel; ein Text, der 20 % darunter liegt, ist fast immer dünn. Darum 10 % nach unten und
25 % nach oben. Wer das ändern will, ändert eine Konfigurationszahl, keinen Code.

Daraus ergeben sich die wirksamen Bänder:

| Intention | Zielkorridor | „ok" mit Toleranz |
|---|---|---|
| `informational` | 1.300–1.800 | 1.170–2.250 |
| `commercial` | 1.100–1.600 | 990–2.000 |
| `transactional` | 900–1.300 | 810–1.625 |
| `navigational` | 900–1.200 | 810–1.500 |

Beide beobachteten Läufe liegen damit im grünen Bereich, auch der `transactional`-Fall.

#### Entscheidung 2: Drei Schweregrade statt bestanden/durchgefallen

Die Regel `word_count` bleibt in `config/content_seo_rules.php` aktiviert und behält ihr
Gewicht 1,5. Sie wird jedoch auf `blocking: true` gestellt — den Unterschied macht der Status,
den der Linter zurückgibt, denn blockierend wird eine Regel nur bei `fail`:

| Wortzahl | Status | Wirkung |
|---|---|---|
| innerhalb Zielkorridor ± Toleranz | `ok` | volle Punktzahl |
| außerhalb Toleranz, innerhalb Reißleine | `warn` | halbe Punktzahl, **nicht** blockierend |
| außerhalb 600–3.000 | `fail` | blockierend, keine automatische Freigabe |

Der Grund für die Trennung: eine Abweichung von 300 Wörtern ist eine redaktionelle
Geschmacksfrage, die ein Mensch in der Prüfung in zehn Sekunden entscheidet. Ein Artikel mit 410
Wörtern ist kein kurzer Ratgeber, sondern ein abgebrochener Generierungslauf — den darf niemand
versehentlich freigeben.

Die Regel bekommt ihren Korridor als Eingabe, nicht aus der Konfiguration:
`QualityCheckJob` reicht `'intent' => $context?->intent` und
`'target_words' => $context?->targetWords` an `SeoLinter::lint()` durch. Sind `min`/`max` in der
Regelkonfiguration ausdrücklich gesetzt, schlagen sie die Eingabe — das bleibt der Notgriff für
den Betrieb.

**Fehlender Kontext.** Ist `$context` null (Nachbewertung ohne Themenkandidat), wird nicht
übersprungen. Gemessen wird gegen die Hülle aller vier Korridore, 900–1.800, plus Toleranz, und
der Meldungstext nennt die Intention nicht. Eine übersprungene Prüfung sieht in der Liste aus
wie eine bestandene und ist damit schlimmer als eine grobe.

#### Entscheidung 3: `warnOnLength()` misst am selben Korridor

`GenerateDraftJob::warnOnLength()` protokolliert weiterhin nur und bricht weiterhin nicht ab —
die Entscheidung gehört ins Gate. Es misst aber gegen `$context->targetWords` statt gegen die
gestrichenen Flachwerte, sonst meldet das Protokoll etwas anderes als der Prüfbericht und
niemand traut mehr beiden. Geloggt wird mit `warning`, sobald die Toleranz gerissen ist, nicht
schon beim ersten Wort außerhalb des Ziels.

`ContextAssembler::targetWords()` verliert die Klammerung auf `$hardMin`/`$hardMax` und gibt den
Korridor der Intention unverändert zurück. Fehlt die Intention, gilt `informational`; das ist
heute schon so.

#### Wortlaut der Meldung im Prüfbericht

Der Prüfer entscheidet in unter 90 Sekunden (§4). Er braucht drei Angaben in einem Satz: was
gemessen wurde, wogegen, und um wie viel es abweicht. Zahlen mit Tausenderpunkt, Intention auf
Deutsch, kein Codewort.

| Fall | Text |
|---|---|
| `ok` | „1.340 Wörter, Zielkorridor 1.100–1.600 (vergleichend)." |
| `ok`, ohne Kontext | „1.340 Wörter, Zielkorridor 900–1.800." |
| `warn`, zu kurz | „980 Wörter, 10 unter dem Zielkorridor 1.100–1.600 (vergleichend)." |
| `warn`, zu lang | „2.150 Wörter, 150 über dem Zielkorridor 1.100–1.600 (vergleichend)." |
| `fail`, zu kurz | „410 Wörter. Unter der harten Untergrenze von 600 Wörtern — der Lauf ist abgebrochen, nicht kurz." |
| `fail`, zu lang | „3.480 Wörter. Über der harten Obergrenze von 3.000 Wörtern." |

Die Abweichung wird gegen die **Toleranzgrenze** gerechnet, nicht gegen die Zielgrenze — sonst
liest der Prüfer eine Zahl, die nichts erklärt („20 unter dem Ziel" bei grüner Bewertung).

Deutsche Namen der Suchintention, verbindlich für jede Fläche des Panels:

| Wert | Anzeige |
|---|---|
| `informational` | informierend |
| `commercial` | vergleichend |
| `transactional` | abschlussnah |
| `navigational` | navigierend |

#### Die SEO-Prüfliste im Qualitätsreport (§4, Abschnitt 2)

Die heutige Liste in `review-detail.blade.php` gibt zwei Dinge falsch wieder und macht damit
gerade diese Regel unlesbar:

1. **Sie zeigt den Regelschlüssel statt der Beschriftung.** In der Liste steht `word_count`,
   `keyword_in_short_answer`, `ymyl_disclaimer`. Der `SeoLinter` liefert zu jeder Zeile bereits
   ein `label` („Wortzahl", „Keyword in der Kurzantwort", „Pflichthinweis (YMYL)"), der
   `QualityReportPresenter` bevorzugt aber `check`. Er muss `label` bevorzugen und erst dann auf
   `check` und den Schlüssel zurückfallen. Englische snake_case-Bezeichner haben in einer
   deutschen Prüffläche nichts zu suchen.
2. **Sie kennt nur zwei Farben.** Der Punkt ist grün bei `ok` und rot bei allem anderen. Ein
   `warn` sieht damit aus wie ein Fehler, und genau als `warn` erscheint der Längenkorridor im
   Regelfall. Der Prüfer sucht dann nach einem Problem, das keines ist.

Vorgabe für die Zeile:

| Status | Punkt (8 px) | Text |
|---|---|---|
| `ok` | `--color-status-published-dot` | `text-text-base` |
| `warn` | `--color-status-review-dot` | `text-text-base` |
| `fail` | `--color-status-failed-dot` | `text-text-base` |
| `skipped` | `--color-line-strong` | `text-text-muted`, Zusatz „nicht geprüft" |

Blockierende Befunde tragen zusätzlich vor der Beschriftung die Marke **„Blockiert"** in
`status-failed-fg`, `content-label`, fett. Ein roter Punkt allein unterscheidet nicht zwischen
„kostet Punkte" und „verhindert die Freigabe" — das ist aber die einzige Unterscheidung, die für
die Entscheidung des Prüfers zählt. Dafür muss der Presenter `blocking` und `label` bis in die
View durchreichen; der Linter liefert beide bereits.

Sortierung der Liste: `fail` zuerst, dann `warn`, dann `ok`, dann `skipped`; innerhalb einer
Gruppe die Reihenfolge der Konfiguration. Wie bei den Rubriken (§4) steht oben, was Arbeit macht.

**Barrierefreiheit.** Der Punkt ist `aria-hidden` und bleibt es. Der Status muss im Text stehen,
sonst ist die Liste für Screenreader eine Aufzählung ohne Bewertung. Jede Zeile beginnt mit einem
`sr-only`-Präfix: „Bestanden: ", „Hinweis: ", „Fehler: ", „Nicht geprüft: ". Farbe ist nie der
alleinige Träger einer Aussage.

#### Abnahme

1. Ein `transactional`-Entwurf mit 1.000 Wörtern läuft durch das Gate. Erwartet: `word_count`
   ist `ok`, nicht blockierend, Text nennt 900–1.300 und „abschlussnah".
2. Ein `informational`-Entwurf mit 1.000 Wörtern läuft durch. Erwartet: `warn`, „170 unter dem
   Zielkorridor 1.300–1.800 (informierend)", halbe Punktzahl, keine Blockade der Freigabe.
3. Ein Entwurf mit 420 Wörtern. Erwartet: `fail`, blockierend, keine automatische Freigabe,
   unabhängig vom Gesamtscore.
4. Ein Entwurf ohne Themenkandidat. Erwartet: geprüft, nicht `skipped`, Korridor 900–1.800 ohne
   Intentionsangabe.
5. Volltextsuche über `app/` und `config/` nach `quality.min_words`, `quality.max_words`,
   `generation.min_words`, `generation.max_words`: kein Treffer.
6. Die SEO-Prüfliste eines beliebigen Entwurfs zeigt keine snake_case-Bezeichner, hat mindestens
   drei unterscheidbare Punktfarben und markiert blockierende Befunde im Text.

### 4.3 Aktualisierungsstand einer Fassung (Ticket #99)

Ohne diese Angabe ist die Frage „warum wurde dieser abrutschende Artikel nicht aktualisiert?"
nur über die Datenbank zu beantworten. Seit der Sperrfrist aus #93 tatsächlich 30 Tage greift,
fällt sie häufiger.

Ein einzeiliger Zustandsstreifen über dem Inhalt, genau ein Zustand, in dieser Rangfolge:

| Zustand | Text | Ton |
|---|---|---|
| Aktualisierung vorgemerkt | „Für Aktualisierung vorgemerkt: {Grund}" | `status-review` |
| In Sperrfrist | „Zuletzt aktualisiert am {Datum}, wieder wählbar ab {Datum}" | neutral |
| Aktualisierung läuft | „Eine Aktualisierung ist in Arbeit → {Link auf Kindfassung}" | `status-generating` |
| Letzter Lauf ohne Änderung | „Lauf am {Datum} hat nichts geändert: {Grund}" | neutral |
| Nichts davon | Streifen entfällt ersatzlos | — |

Datum immer `TT.MM.JJJJ`, nie relativ: die Sperrfrist ist eine harte Frist, der Leser muss
rechnen können. Kein Countdown, keine Prozentanzeige. Der Streifen ist Text; die Farbe stützt
die Aussage, trägt sie nicht. Kein `title`-Attribut als einziger Träger.

Zur Rangfolge: die Sperrfrist steht vor der laufenden Aktualisierung. Damit beide Zustände
erreichbar bleiben, zählt für die Sperrfrist nur eine Fassung, die nicht mehr läuft — sonst
fände eine gerade erzeugte Kindfassung immer zuerst die Sperrfrist. Die eigene Fassung zählt
dabei mit, wenn sie selbst eine Aktualisierung ist: nach einem Lauf ist sie die
veröffentlichte, und genau sie hält den Artikel gesperrt (#93).

Ist `parent_draft_id` gesetzt, steht über dem Titel „Aktualisierung von {Titel der
Elternfassung}" mit Link auf die Gegenüberstellung (§4.1). Titel und Slug sind identisch;
ohne diese Zeile sind Eltern- und Kindfassung in Listen nicht zu unterscheiden.

Farben ausschließlich aus `config('content.colors')`, keine neuen Token: Fläche Schattierung 50,
Text Schattierung 600. Neutral ist der warme Grauton von „Zurückgezogen". Klassen:
`.content-refresh--marked|running|neutral` für die Pille, `.content-refresh-strip--*` für den
Streifen.

Quelle des Zustands ist `App\Content\Services\RefreshStatus`; sie liest nur und greift nicht
in die Auswahl des `RefreshSelector` ein.

### Mobil — Mobilvariante 2 von 3

Zwei Spalten werden zwei Schritte. Zuerst die Warteschlange als volle Liste, Tippen öffnet die Prüffläche als volle Seite mit Zurück-Pfeil. In der Prüffläche werden die drei Abschnitte zu einem waagerechten Reiter (Vorschau · Report · Quellen), weil untereinander auf 390 px zu viel Scrollweg entsteht. Die Entscheidungsleiste bleibt klebend, zwei Schaltflächen sichtbar (Freigeben, Verwerfen), der Rest im Überlaufmenü. Klickfläche 48 px.

---

## 5. Leistung (Desktop)

Vier Reiter: Artikel · Cluster · Regionen · Kosten. Standardzeitraum 30 Tage.

**Kopfbereich**: vier Kennzahlkacheln über 12 Spalten, je 3 Spalten, Höhe 112 px — Impressionen, Klicks, mittlere Position, AdSense-Ertrag. Je Kachel: Zahl in `content-metric`, Veränderung gegen die Vorperiode als Pfeil mit Prozentzahl (Pfeil plus Vorzeichen, nicht nur Farbe), darunter **verpflichtend** `content-asof`. Klick führt gefiltert in die jeweilige Tabelle.

**Verlaufsdiagramm**: eine Linie je Kennzahl, umschaltbar, Höhe 280 px, Rasterlinien in `line-soft`, Achsen mit Klartextdatum. Die letzten drei Tage werden gestrichelt dargestellt und tragen die Erklärung „Werte der letzten Tage sind noch unvollständig" — Search-Console-Daten hinken nach, und ein abfallendes Linienende ist sonst ein Fehlalarm, der Entscheidungen verdirbt.

**Tabelle**, kompakt: Zeilenhöhe 44 px, Schrift 14 px, höchstens sieben Spalten, Zahlen rechtsbündig und tabellar. Artikel-Reiter: Titel · Portal · Status · Impressionen · Klicks · Position · Ertrag. Sortierbar, Standardsortierung nach Impressionen absteigend.

**Kosten-Reiter**: Kosten gegen Ertrag je Portal als waagerechtes Balkenpaar, darunter die Deckungszeile („Ertrag deckt Kosten seit Tag 34"). Artikel mit negativer Bilanz nach 60 Tagen bekommen eine Marke „Kandidat für Aktualisierung" mit Verweis in den Refresh-Loop (#24).

**Zustände**: Leer und gut — Zeitraum ohne Daten, weil das Portal jünger als 3 Tage ist: „Search Console liefert erste Zahlen ab dem 12.09." Leer und schlecht — Konnektor nicht verbunden, mit Handlung „Search Console verbinden". Lädt — Kachel-, Diagramm- und Tabellenskelette. Teilweise — 20 von 24 Portalen geliefert: Band mit „Für 4 Portale fehlen die Zahlen von gestern", die übrigen bleiben auswertbar. Veraltet — der Regelfall, Datenstand an jeder Kachel und am Diagramm.

### Mobil — Mobilvariante 3 von 3

Kennzahlkacheln zu 2 × 2, Diagramm auf volle Breite und 200 px Höhe mit waagerechtem Scrollen über den Zeitraum. Die Tabelle fällt auf eine Kartenliste zurück: Titel, Portal, Statuspille, darunter zwei Kennzahlen als Paar. Kein waagerechtes Scrollen einer Tabelle auf dem Telefon.

---

## 6. Quellen-Monitor (Desktop)

Reiter unter Einstellungen. Beantwortet: Welche Quelle liefert nicht, seit wann, und was fehlt dadurch?

**Kachelraster**, 3 Spalten ab 1280 px, je Konnektor eine Karte, Höhe 148 px:

- Kopf: Konnektorname, rechts Zustandspille — „aktuell" (`status-published-*`), „verzögert" (`status-review-*`), „ausgefallen" (`status-failed-*`), „nicht verfügbar" (`status-failed-*`, toter Zugang: Guthaben aufgebraucht oder Schlüssel abgelehnt, #104), „abgeschaltet" (`status-archived-*`). Die Statusfarben werden hier wiederverwendet, aber mit eigenen Beschriftungen; ein Konnektor hat keinen DisplayStatus.
- Mitte: letzter erfolgreicher Abruf als Klartext („vor 12 Minuten"), Anzahl gelieferter Datensätze, Fehlerquote der letzten 24 Stunden.
- Fuß: Verfügbarkeitsstreifen der letzten 30 Tage — 30 Segmente à 4 px Breite, Lücke 2 px, Höhe 20 px. Beim Überfahren Datum und Zustand. Der Streifen zeigt Muster, die eine Zahl verschweigt.
- Bei Ausfall zusätzlich die Auswirkung im Klartext: „Regionale News fehlen für 6 Portale" plus Schaltfläche „Jetzt erneut abrufen".

Darunter eine Tabelle der letzten 50 Abrufe: Zeit · Konnektor · Ergebnis · Dauer · Datensätze · Meldung.

**Rate-Limit und Kosten** je Konnektor als schmale Zeile über dem Raster, falls ein Kontingent zu über 80 % verbraucht ist.

**Zustände**: Leer und gut — alle Konnektoren grün, Zusammenfassungszeile „Alle 9 Quellen aktuell, letzter Abruf 06:20". Leer und schlecht — kein Abruf seit über 24 Stunden: Scheduler-Hinweis oben. Lädt — Kachelskelette. Teilweise — der Normalfall, Karten sind je Konnektor unabhängig. Veraltet — je Karte der eigene Zeitstempel.

---

## 7. Einstellungen (Desktop)

Vier Reiter. Zweispaltig: links Bereichsnavigation 220 px, rechts Formular, Feldbreite maximal 560 px.

**Portale**: Tabelle der 24 Tenants — Name · Branche · Artikel pro Tag · Auto-Freigabe ab Score · Zustand (aktiv/pausiert) · letzte Veröffentlichung. Der Schalter „Produktion pausieren" ist je Portal vorhanden und zusätzlich einmal global als deutlich abgesetzte Schaltfläche am Ende der Seite, mit Rückfrage und Pflichtgrund.

**Quellen**: je Konnektor Aktivierung, Abrufrhythmus, Gewicht im Scoring. Zugangsdaten werden **nicht** angezeigt und nicht eingegeben — sie kommen aus `.env` (siehe `config/content.php`). Statt eines Feldes steht „Zugang aus der Serverkonfiguration, gültig" oder „fehlt". Ein Eingabefeld hier wäre eine Einladung, Schlüssel in die Datenbank zu schreiben. Die vollständige Vorgabe dazu steht in §7a; der Monitor desselben Reiters in §6.

**Prompts**: Liste der Vorlagen mit Version und Änderungsdatum. Editor zweispaltig — links Text mit Platzhalterhervorhebung, rechts Vorschau der eingesetzten Werte an einem Beispielthema. Über dem Editor ein Band: „Änderungen wirken auf alle 24 Portale ab dem nächsten Lauf." Speichern erzeugt eine neue Version; die vorherige bleibt mit Datum und Bearbeiter abrufbar und wiederherstellbar. Was die Redaktion am Ausgabeschema darf und was nicht, steht in §7b.

**Budget**: Tagesbudget, Monatsbudget, Verhalten bei Überschreitung. Die aktuellen Werte aus `config/content.php` werden als Herkunft benannt, wenn sie aus der Umgebung stammen und im Panel nicht änderbar sind — ein Feld, das aussieht wie änderbar und beim Speichern nichts tut, ist schlimmer als ein gesperrtes Feld. Die Herkunft wird an gesperrten Feldern immer mit derselben Pille genannt: `.content-origin-pill` (20 px, `surface-sunken`, `text-base`, kein Statusfarbton), Bauform und Begründung in §7b.1.

Ein Budgetwert von **0 schaltet die betroffene Prüfung ab** (`BudgetGuard::assertBelow()` kehrt bei `$limit <= 0` ohne Prüfung zurück). Die Zeile zeigt dann keinen Geldbetrag, sondern den Klartext „Ohne Grenze" in der Warnfarbe (`--color-status-review-fg`), dahinter in Textfarbe „(0 schaltet die Prüfung ab)". „0,00 $" wäre die gefährlichste Fehldeutung dieser Fläche: es liest sich als „es wird nichts ausgegeben", gemeint ist das Gegenteil. Steht mindestens ein Wert auf 0, erscheint über der Tabelle ein Warnkasten (`role="status"`, Symbol 20 px in `--color-status-review-fill`, Fließtext in Textfarbe) mit den Namen der betroffenen Umgebungsvariablen. Stehen alle Werte, erscheint kein Kasten — keine Dauerwarnung im Normalzustand.

**Zustände**: Leer und gut — nicht anwendbar, Einstellungen sind nie leer. Leer und schlecht — kein Portal aktiv: Band „Für kein Portal ist die Produktion aktiv." Lädt — Formularskelette. Teilweise — ein Speichern schlägt fehl: Fehler am Feld, nicht als seitenweite Meldung. Veraltet — nicht relevant.

### 7.1 Messung (Search Console) — Property, Zustand, Zugriff (Ticket #116)

Herkunft: Vorgabe zu #107. `gsc_property` stand als nacktes Textfeld in der Sektion *Autor und Organisation*, nahm jede Zeichenkette an und zeigte keinen Zustand. Der teuerste Fehlerfall — Property gepflegt, Dienstkonto aber nicht als Nutzer eingetragen — sah damit genauso aus wie der Gutfall, weil die Search-Console-API dann leere Listen statt eines Fehlers liefert.

**Ort**: eigene Sektion „Messung (Search Console)" als **letzte** Formularsektion des Reiters *Portal*, nach *Kategorie-Zuordnung*. Die Angabe wird einmal gesetzt und danach selten angefasst; sie darf *Produktion* und *Veröffentlichungsfenster* nicht nach unten drücken.

**Formprüfung** (`App\Content\Services\SearchConsoleProperty::validate()`, live beim Verlassen des Feldes und beim Speichern):

- `sc-domain:` + Hostname, klein geschrieben, ohne Schema, Pfad oder Port.
- `https://` + Host + optional Pfad + abschließender Schrägstrich.
- Reservierte Endungen `.test`, `.local`, `.invalid`, `.example` und `localhost` **blockieren das Speichern**: ein solcher Wert liefert garantiert nie Daten, stellt den Go-Live-Check aber trotzdem auf Grün.
- Weicht die Domain der Property von der Portal-Domain ab, erscheint nur eine Warnung (`role="status"`, Warnfarbe). Eine übergeordnete Domain-Property ist ein legitimer Sonderfall und wird nicht blockiert.

**Sechs Zustände**, jeder als `.content-status`-Pille mit Text *und* Farbe, in einem `aria-live="polite"`-Bereich:

| Zustand | Pille | Klasse | Bedeutung |
| --- | --- | --- | --- |
| `missing` | Nicht eingerichtet | `--idea` | Kein Wert gepflegt, das Portal liefert keine Metriken. |
| `unchecked` | Ungeprüft | `--scheduled` | Wert gepflegt, Zugriff noch nie geprüft. |
| `connected` | Verbunden | `--published` | Property sichtbar, Zeilen vorhanden. |
| `no_data` | Ohne Daten | `--review` | Leserecht bestätigt, 0 Zeilen im Fenster des Collectors. |
| `no_access` | Kein Zugriff | `--failed` | Property für das Dienstkonto nicht sichtbar. |
| `no_service_account` | Dienstkonto fehlt | `--failed` | `GOOGLE_SERVICE_ACCOUNT_JSON` fehlt, es lässt sich nichts prüfen. |

`missing` und `no_service_account` werden immer aus dem aktuellen Zustand abgeleitet, die übrigen drei stehen in `tenant_content_settings.gsc_check_status` mit `gsc_checked_at` und `gsc_check_detail`. Ein geänderter Property-Wert löscht den Befund beim Speichern — sonst stünde „Verbunden" an einer nie geprüften Property.

**Schaltfläche „Zugriff prüfen"** rechts neben der Zustandszeile ruft dieselbe Prüfung, die `content:metrics:preflight` je Portal macht (sites.list plus Stichprobe über das Fenster des Gap-Connectors), und schreibt Befund und Zeitstempel. Während des Laufs „Prüfe …" mit Ladeindikator und gesperrter Schaltfläche. Gesperrt ist sie ohne Portal, ohne Wert, bei ungespeichertem Wert und ohne Dienstkonto — der Grund steht als Hilfetext unter der Schaltfläche, nicht nur im Tooltip. Fehler des Aufrufs erscheinen mit dem Klartext von Google.

**Dienstkonto** steht darunter als statischer Text mit sichtbar beschrifteter Schaltfläche „Adresse kopieren" und Textbestätigung „Kopiert" — kein deaktiviertes Formularfeld, das fiele aus der Tabulatorfolge. Ohne hinterlegtes Konto steht dort „Noch kein Dienstkonto hinterlegt (`GOOGLE_SERVICE_ACCOUNT_JSON`)."

**Sammelansicht**: unter dem Budget-Abschnitt die Tabelle „Search Console je Portal" mit Name, Zustandspille und Property, sortiert nach Schweregrad — `no_access`, `no_service_account`, `missing`, `unchecked`, `no_data`, `connected`. `php artisan content:rollout` trägt dieselben Zustände als Spalte „Search Console", behält dort aber die Sortierung nach Portal-ID, weil die Tabelle zum Schalten und nicht zum Suchen dient.

---

## 7a. Einstellungen — Quellen: Konfiguration (Desktop)

Herkunft: #45. §6 beschreibt den Quellen-**Monitor** (Zustand, Läufe, Fehler, manueller Abruf), §7 verlangt unter demselben Reiter zusätzlich die **Konfiguration** je Konnektor: Aktivierung, Abrufrhythmus, Gewicht im Scoring. Dieser Abschnitt ist die verbindliche Vorgabe dafür. Alles aus §6 bleibt unverändert bestehen; hier kommt genau eine Zeile auf der Karte und ein Formular dahinter dazu.

### Ablage: zentral je Konnektor, nicht je Portal

Die Einstellung liegt **zentral je Konnektor** in einer neuen Central-Tabelle `content_source_settings`, direkt neben `provider_states`. Keine Tenant-Tabelle, kein Portalbezug.

Begründung:

- Der Reiter „Quellen" ist der einzige portalübergreifende Bereich der Einstellungen. Der Reiter „Portal" ist der portalbezogene. Diese Trennung ist gelernt und darf nicht aufgeweicht werden.
- Ratenlimit, Kosten und Provider-Zustand eines Konnektors sind bereits zentral (`provider_states`). Ein Rhythmus je Portal würde denselben Anbieter bis zu 24-fach unterschiedlich takten und die Frage „warum lief die Quelle nicht?" unbeantwortbar machen.
- 9 Konnektoren × 24 Portale = 216 Einstellungen, die niemand pflegt. Zentral sind es 9.
- Die Form bleibt erweiterbar: Braucht ein Portal später eine Ausnahme, bekommt die Tabelle eine nullable Spalte `tenant_id` und einen zweiten Datensatz mit Vorrang. Bis dahin wird nichts erfunden.

**Spalten** (verbindlich, Namen frei nur in der Schreibweise): `source_key` (eindeutig) · `is_enabled` (bool, Vorgabe `true`) · `frequency_override` (nullable, Werte aus `SourceFrequency`, `null` = Deklaration des Konnektors gilt) · `weight` (0–200, Vorgabe 100, Prozent) · `disabled_reason` (nullable, 200 Zeichen) · `updated_by_content_user_id` (nullable) · `updated_at`.

Datensätze entstehen erst beim ersten Speichern. Ohne Datensatz gilt die Vorgabe aus Code und `config/content.php` — die Tabelle beschreibt ausschließlich **Abweichungen**.

### Wirkung im Code (verbindlich)

- `SourceRegistry::frequencyOf()` liefert die **wirksame** Frequenz: Override, sonst `SourceConnector::schedule()`. `dueFor()` und damit die vier Scheduler-Sammelläufe folgen automatisch; `SourceRunner::isDue()` rechnet mit derselben Frequenz.
- `SourceRunner::run()` prüft die Aktivierung **vor** `ProviderState::isAvailable()` und gibt bei „aus" ein `SourceRunResult::skipped()` mit dem Klartext „In den Einstellungen abgeschaltet" zurück. Kein Fehler, kein Fehlerzähler, kein Alarm.
- Das Gewicht wirkt an genau **einer** Stelle: beim Laden der Rohsignale im Themen-Scoring wird `signal_strength` mit `weight / 100` multipliziert und auf 0…1 begrenzt. Damit sehen Trend-, Nachfrage-, Lücken- und Saison-Teilscore denselben gewichteten Wert; es gibt keine zweite Rechenregel, die man später suchen muss. Gewicht 0 heißt: Das Signal wird weiter gesammelt und angezeigt, zählt aber nicht — auch nicht als eigenständige Quelle im Mehrquellen-Zuschlag des Trendscores.
- `config('content.sources.*.enabled')` bleibt das **Tor**: Was dort aus ist, wird im `ContentServiceProvider` gar nicht erst registriert. Das Panel schaltet nur, was registriert ist. Ein Panel-Schalter, der eine kostenpflichtige Quelle ohne hinterlegten Zugang anwerfen kann, wäre die teurere Variante.
- Der Rhythmus ist im Panel nur **gleich oder seltener** wählbar als die Deklaration des Konnektors. Die Deklaration ist die Obergrenze; sie bildet Quellentakt, Ratenlimit und Kosten ab. Häufiger geht nur im Code.
- **Keine Zugangsdaten.** Kein Feld, kein maskiertes Feld, kein „nur überschreiben". Angezeigt wird ausschließlich ein Zustandssatz, siehe unten.

### Karte im Raster (Ergänzung zu §6)

Unter der Kopfzeile der Konnektorkarte, oberhalb der Kennzahlen, eine einzelne Konfigurationszeile in `content-label`, `text-muted`, ein Zeilenumbruch erlaubt:

`täglich · Gewicht 100 %` — Werte, die von der Vorgabe abweichen, stehen in `text-strong` und tragen dahinter die Marke **„abweichend"** als 20-px-Pille in der Klasse `.content-origin-pill` (`surface-sunken`, `text-base`, kein Statusfarbton — es ist kein Zustand, sondern eine Herkunftsangabe). `text-base`, nicht `text-muted`: `--color-text-muted` erreicht auf `surface-sunken` nur 4,45:1 und verfehlt die unten geforderten 4,5:1 (#64).

Rechts in der Fußzeile, neben „Jetzt ausführen", die zweite Schaltfläche **„Einstellen"** (gleiche Höhe 36 px, gleiche Klickfläche ≥ 44 px inklusive Abstand, sekundär: Rahmen `line-soft`, Text `text-base`). „Jetzt ausführen" bleibt die primäre Handlung der Karte.

**Abgeschaltete Konnektoren**: Zustandspille „abgeschaltet" in `status-archived-*` (bereits vorgesehen, keine achte Farbe), Karte auf 70 % Deckkraft mit Ausnahme der Pille und der Schaltfläche „Einstellen". „Jetzt ausführen" **entfällt** dann ersatzlos und wird durch den Satz ersetzt: „Abgeschaltet am 09.09.2026 von Kim — Grund: liefert seit dem Umbau nur Duplikate." Ein manueller Abruf, der den Schalter aushebelt, macht den Schalter wertlos.

**Sortierung** (§6: gestörte zuerst): Abgeschaltete rutschen hinter „aktuell" ans Ende. Sie sind kein Problem, sondern eine Entscheidung.

**Zusammenfassungszeile** über dem Raster zählt Abgeschaltete nicht als „braucht Aufmerksamkeit", sondern nennt sie getrennt: „Alle 7 aktiven Quellen aktuell. 2 Quellen sind abgeschaltet."

### Formular „Einstellen"

Als Filament-Aktion mit Modal, Breite `lg`, gleiche Mechanik wie „Jetzt ausführen" auf derselben Seite — kein neues Muster für ein Formular mit drei Feldern. Überschrift: **„Quelle einstellen: Regionale News"**. Direkt darunter ein Band in `surface-sunken`: „Gilt für alle Portale ab dem nächsten Lauf."

Feldbreite maximal 560 px (§7), Reihenfolge:

1. **Zustand** — Umschalter, Beschriftung „Quelle aktiv". Hilfetext an: „Wird im deklarierten Rhythmus abgerufen." Hilfetext aus: „Kein Abruf mehr, auch nicht von Hand. Bereits gesammelte Signale bleiben erhalten und laufen normal aus."
2. **Grund** — einzeilig, 200 Zeichen, **Pflicht, sobald der Umschalter auf aus steht**, sonst ausgeblendet. Beschriftung „Warum wird die Quelle abgeschaltet?". Der Grund erscheint auf der Karte und beantwortet in drei Wochen die Frage, die sonst niemand mehr beantworten kann. Beim Wiedereinschalten wird er gelöscht.
3. **Abrufrhythmus** — Auswahlliste, nicht nativ. Optionen: die Deklaration des Konnektors und alle selteneren Werte, aufsteigend. Die Deklaration trägt den Zusatz „(Vorgabe)". Hilfetext: „Häufiger als die Vorgabe ist nicht wählbar — der Takt bildet Ratenlimit und Kosten der Quelle ab."
4. **Gewicht im Scoring** — Zahlenfeld, Suffix „%", 0 bis 200, Schrittweite 10, Vorgabe 100. Hilfetext: „100 % = unverändert. 0 % = Signale werden weiter gesammelt, zählen im Themen-Scoring aber nicht."
5. **Zugang** — gesperrter Textbaustein, kein Eingabefeld, Beschriftung „Zugang". Drei Fälle:
   - „Kein Zugang nötig — die Quelle ist frei abrufbar." (RSS, Saisonkalender, Portal-Eigendaten)
   - „Zugang aus der Serverkonfiguration hinterlegt." (Werte vorhanden)
   - „Zugang fehlt: `DATAFORSEO_LOGIN`, `DATAFORSEO_PASSWORD`." in `status-failed-fg`, mit dem Zusatz „Zugangsdaten werden auf dem Server gepflegt, nicht hier."

   Bewusste Abweichung von §7: Dort steht „gültig". Geprüft wird ausschließlich das Vorhandensein der Werte, nicht ihre Gültigkeit. „Hinterlegt" ist das, was wir belegen können; „gültig" wäre eine Behauptung, die der erste 401 widerlegt. Ob der Zugang trägt, sagt ohnehin die Zustandspille der Karte.

**Fußzeile des Modals**: rechts „Speichern" (primär, `content-600`), links davon „Abbrechen". Ganz links, nur wenn mindestens ein Wert abweicht, der Textverweis **„Auf Vorgabe zurücksetzen"** — löscht den Datensatz und schließt das Modal.

**Rückmeldung**: Filament-Meldung in Grün, ein Satz mit der Folge, nicht mit dem Vorgang: „Regionale News laufen ab jetzt wöchentlich." bzw. „Regionale News sind abgeschaltet." Keine Meldung „gespeichert".

**Validierung**: am Feld, nie seitenweit (§7). Fälle: Grund fehlt beim Abschalten; Gewicht außerhalb 0–200; Rhythmus häufiger als die Vorgabe (kann nur über eine manipulierte Anfrage auftreten, wird trotzdem abgewiesen).

### Konnektoren, die in der Serverkonfiguration aus sind

Sie erscheinen im Raster, in einem eigenen Block **unter** dem Raster mit der Überschrift „Nicht eingerichtet" — nicht dazwischen, sonst sucht man morgen im aktiven Raster nach etwas, das nie läuft. Je Zeile: Name, der Satz „In der Serverkonfiguration abgeschaltet (`CONTENT_SOURCES_DATAFORSEO_TRENDS_ENABLED`)" und der Zugangs-Zustandssatz. Keine Schaltfläche. Grundlage ist ein Katalog aller bekannten Konnektoren in `config/content.php` (Schlüssel, Klartextname, Name der Umgebungsvariable, benötigte Zugangsschlüssel) — die Registry kennt nur die eingeschalteten.

### Zustände

- **Leer und gut**: Kein Datensatz in `content_source_settings` — alle Karten zeigen ihre Vorgabewerte ohne „abweichend"-Marke. Das ist der Normalfall und braucht keinen Hinweis.
- **Leer und schlecht**: Alle Konnektoren abgeschaltet — Band über dem Raster in `status-failed-bg`: „Keine Quelle ist aktiv. Ohne Rohsignale findet die Pipeline keine Themen."
- **Lädt**: Kartenskelette wie in §6, die Konfigurationszeile als 12-px-Balken über 60 % der Kartenbreite.
- **Teilweise**: Speichern schlägt fehl — Fehler am Feld, Modal bleibt offen, bereits eingegebene Werte bleiben stehen.
- **Veraltet**: Nicht relevant, die Einstellung ist der gespeicherte Wert.

### Rechte, Tastatur, Barrierefreiheit

- Nur Rolle `owner` (`canManageSettings()`), wie die übrige Seite. Für alle anderen ist der Reiter ohnehin nicht erreichbar.
- Der Zustand einer Quelle wird nie allein über Farbe transportiert: Pille mit Text, Konfigurationszeile im Klartext.
- Nach dem Speichern wird die geänderte Karte über eine höfliche Live-Region angesagt (§0), Fokus kehrt auf die auslösende Schaltfläche „Einstellen" zurück.
- Kontrast der „abweichend"-Pille mindestens 4,5:1 gegen `surface-sunken`.
- Mobil (< 1024 px): Raster einspaltig, Modal von unten auf 90 % Höhe, Felder volle Breite. Die Konfigurationszeile bricht auf zwei Zeilen um, statt zu kürzen.

### Abnahmefragen

1. Beantwortet die Karte ohne Klick, in welchem Rhythmus und mit welchem Gewicht die Quelle läuft?
2. Ist an jeder abweichenden Einstellung erkennbar, dass sie abweicht — und lässt sie sich in einem Schritt zurücksetzen?
3. Steht bei jeder abgeschalteten Quelle, wer sie wann und warum abgeschaltet hat?
4. Gibt es auf der ganzen Seite kein einziges Eingabefeld für Zugangsdaten?
5. Kann ein Rhythmus gewählt werden, der häufiger ist als die Deklaration des Konnektors? (Muss „nein" sein.)
6. Wirkt eine geänderte Einstellung ohne Neustart auf den nächsten Sammellauf, und sagt das Modal das auch?

---

## 7b. Einstellungen — Prompts: Ausgabeschema und Vertrag mit der Pipeline (Desktop)

Herkunft: #47. §7 beschreibt den Prompt-Editor als zweispaltigen Text-Editor mit Vorschau. Dieser Abschnitt ergänzt genau den unteren Abschnitt „Variablen und Ausgabeschema" und die Frage, was die Redaktion dort ändern darf. Alles aus §7 bleibt bestehen.

### Der Befund

Das geseedete Template `topic_discover` beschreibt in seinem Ausgabeschema fünf Felder. Der Job, der das Template benutzt, verlangt sieben und ignoriert das Schema des Templates vollständig. Wer im Editor am Schema arbeitet, arbeitet an einem Feld, das beim Speichern nichts bewirkt. Fünf Variablen des Templates werden zur Laufzeit mit dem Platzhaltertext „siehe Signalliste" gefüllt, weil die Signale gebündelt an den fertigen Prompt gehängt werden. Die Vorschau im Editor zeigt damit einen Prompt, den es so nie gibt.

Beides ist derselbe Fehler: Die Oberfläche behauptet Wirkung, die es nicht gibt. §7 hat diesen Fall für das Budget schon entschieden — ein Feld, das aussieht wie änderbar und beim Speichern nichts tut, ist schlimmer als ein gesperrtes Feld. Dieselbe Regel gilt hier.

### Entscheidung 1: Das Ausgabeschema ist schreibgeschützt, nicht validiert

Das Ticket lässt beides offen: schreibgeschützt zeigen **oder** validieren. Die Vorgabe ist **schreibgeschützt**, und zwar für jede Vorlage, deren Schlüssel einer Pipeline-Stufe entspricht.

Begründung:

- Jedes Feld des Schemas wird im Job namentlich ausgelesen (`primary_keyword`, `source_item_ids`, `region_scope` …). Ein umbenanntes oder entferntes Feld ergibt kein kaputtes JSON, sondern gültiges JSON mit stillem Datenverlust. Kein Validator kann das abfangen, weil es syntaktisch fehlerfrei ist.
- Eine Validierung, die nur „hat `type` oder `properties`" prüft, gibt der Redaktion ein Sicherheitsgefühl ohne Deckung. Sie ist schlechter als gar keine Freigabe.
- Der ganze Wert des Feldes für die Redaktion ist Lesen: „Was kommt aus dieser Stufe zurück?" Genau das bleibt erhalten.

Der Prompttext bleibt uneingeschränkt editierbar. Das ist die redaktionelle Stellschraube und bleibt es.

### Aufbau des Abschnitts „Variablen und Ausgabeschema"

Zwei Felder untereinander, Feldbreite wie in §7 maximal 560 px, hier ausnahmsweise volle Spaltenbreite bis 720 px, weil JSON umbricht.

1. **Erwartete Variablen** — unverändert editierbar, Code-Editor, Sprache JSON. Beschriftung, Hilfetext und Validierung bleiben wie umgesetzt.
2. **Ausgabeschema (JSON Schema)** — schreibgeschützte Leseansicht statt Code-Editor (Begründung und Bauform in §7b.1, Abschnitt 4), Sprache JSON, Hintergrund `surface-sunken`, Text `text-base` (auf `surface-sunken` bleibt `text-muted` unter 4,5:1, #64), keine Zeilennummern-Hervorhebung, kein Cursor. Höhe 12 Zeilen mit eigener Scrollfläche; das Schema von `topic_discover` ist 60 Zeilen lang und darf das Formular nicht aufblähen.
   - Beschriftung: „Ausgabeschema (JSON Schema)".
   - Darüber, statt des bisherigen Hilfetexts, eine Zeile in `content-label`, `text-muted`: **„Vom Code vorgegeben — `App\Content\Jobs\DiscoverTopicsJob`."** Der Klassenname wird als Klartext gezeigt, nicht verlinkt. Wer das Schema ändern will, weiß danach, wo.
   - Rechts neben der Beschriftung eine 20-px-Pille **„nicht änderbar"** in derselben Klasse `.content-origin-pill` wie die Pille „abweichend" in §7a (`surface-sunken`, `text-base`, kein Statusfarbton — es ist eine Herkunftsangabe, kein Zustand). `text-base`, nicht `text-muted`: auf `surface-sunken` trägt `text-muted` nur 4,45:1 (#64).
   - Rechts über dem Feld der Textverweis **„Schema kopieren"** (kopiert das JSON in die Zwischenablage, Rückmeldung als höfliche Live-Region „Schema kopiert"). Das ist die einzige Handlung am Feld.

**Vorlagen ohne Code-Vertrag** (Styleguides, das Referenzdokument des System-Prompts): Das Feld ist leer und bleibt es. Statt des gesperrten Editors steht dort der Satz „Diese Vorlage liefert kein strukturiertes Ergebnis." in `text-muted`. Kein leerer Editor, den man versehentlich befüllt.

### Band über dem Editor

§7 verlangt bereits ein Band: „Änderungen wirken auf alle 24 Portale ab dem nächsten Lauf." Es bekommt bei Vorlagen mit Code-Vertrag einen zweiten Satz:

> Änderungen wirken auf alle 24 Portale ab dem nächsten Lauf. Der Prompttext ist frei; Variablen und Ausgabeschema sind mit der Pipeline verdrahtet und nur im Code änderbar.

### Abweichendes Schema — was die Themenfindung tun muss

Der Schreibschutz gilt in der Oberfläche. Er gilt nicht für Datensätze, die vor #47 entstanden sind, für portalspezifische Vorlagen aus der Datenbank und für alles, was per Seeder oder Konsole hineinkommt. Deshalb zusätzlich, verbindlich:

- Der Job vergleicht das Schema der aufgelösten Vorlage mit seinem eigenen Vertrag: Sind alle Pflichtfelder unter `topics.items.required` enthalten? Wenn ja, wird das Schema der Vorlage verwendet.
- Wenn nein, läuft der Aufruf mit dem Schema aus dem Code weiter und protokolliert eine Warnung mit Schlüssel und Version der Vorlage. **Die Themenfindung hält nie an, weil ein Schema nicht passt.** Ein Tag ohne Themen ist teurer als ein Tag mit dem Schema von gestern.
- In der Vorlagenliste trägt eine solche Zeile in der Spalte „Zustand" zusätzlich die Pille **„Schema veraltet"** in `status-failed-*`, mit Titelattribut „Wird beim Lauf durch das Schema aus dem Code ersetzt." Auf der Bearbeitungsseite steht derselbe Satz als Band in `status-failed-bg` über dem Editor. Ohne diese Anzeige merkt niemand, dass die Vorlage nur halb wirkt — genau der Zustand, den #47 beschreibt.

### Entscheidung 2: Versionen je Vorlage, nicht global

Der Seeder führt eine gemeinsame Konstante `VERSION` für alle 26 Zeilen. Für #47 ändert sich genau eine Vorlage. Alle auf Version 2 zu heben, legt 25 inhaltsgleiche Zeilen an und nimmt der Spalte „Version" im Editor ihre einzige Aussage: dass sich an dieser Vorlage etwas geändert hat.

Vorgabe: Die Version wird je Vorlage geführt (Vorgabewert bleibt 1, `topic_discover` bekommt 2). Die Auflösung nimmt ohnehin die höchste aktive Version je Schlüssel; Version 1 bleibt als abgelöste Zeile lesbar, wie §7 es verlangt.

### Vorgabe für `topic_discover`, Version 2

**System-Prompt**: eigener Text, **nicht** der Redakteurs-System-Prompt der Schreib-Stufen. Die Themenfindung schreibt keinen Artikel; der Redakteurstext verlangt `{{styleguide}}` und `{{fact_snippets}}`, die es an dieser Stelle nicht gibt. Übernommen wird wörtlich der Planer-Text, der heute im Job steht.

**User-Prompt**: der bestehende redaktionelle Text, ergänzt um die Signalliste und die Ausgaberegeln als Platzhalter statt als angehängte Blöcke. Die fünf Variablen, die heute mit „siehe Signalliste" gefüllt werden, entfallen ersatzlos — sie beschreiben denselben Inhalt ein zweites Mal.

**Variablen** (`variables_json`, genau diese sechs):

| Variable | Inhalt |
|---|---|
| `{{tenant_name}}` | Name des Portals |
| `{{branch}}` | Anzeigename der Branche |
| `{{signals}}` | Nummerierte Rohsignale des Fensters; die Nummer ist die `source_items`-ID |
| `{{internal_link_targets}}` | Titel vorhandener Ratgeber des Portals, sonst „keine" |
| `{{max_candidates}}` | Obergrenze aus `config('content.topics.discover.max_candidates')` |
| `{{output_rules}}` | Ausgaberegeln, heute `rulesBlock()` |

`{{region_scope}}` und `{{region_name}}` entfallen: Über den Regionszuschnitt entscheidet der `RegionScopeResolver` nach dem Modellaufruf. Eine Region im Prompt vorzugeben, die anschließend überschrieben wird, erzeugt Themen, die zur späteren Entscheidung nicht passen.

**Ausgabeschema**: wörtlich das Schema aus `DiscoverTopicsJob::schema()`, mit `maxItems` auf dem Vorgabewert 30. Der Job setzt die tatsächliche Obergrenze weiterhin selbst, wenn die Konfiguration abweicht.

**Aufrufweg**: `LlmClient::structured()` mit der Vorlage statt `emit()` mit selbst zusammengebautem Prompt. Fehlt die Vorlage oder ist sie nicht renderbar, bleibt der eingebaute Ersatztext samt Code-Schema die Rückfallebene — unverändert und weiterhin ohne Abbruch.

### Zustände

- **Leer und gut**: Vorlage ohne Ausgabeschema — der Satz „Diese Vorlage liefert kein strukturiertes Ergebnis.", kein Editor.
- **Leer und schlecht**: Vorlage mit Code-Vertrag, aber ohne Schema in der Datenbank. Band in `status-failed-bg`: „Diese Vorlage hat kein Ausgabeschema. Der Lauf verwendet das Schema aus dem Code." Gleiche Behandlung wie ein abweichendes Schema.
- **Lädt**: Feldskelett über 12 Zeilen, wie die übrigen Formularskelette in §7.
- **Teilweise**: Speichern schlägt am Variablenfeld fehl — Fehler am Feld, das gesperrte Schemafeld bleibt unberührt sichtbar.
- **Veraltet**: Die Pille „Schema veraltet" ist genau dieser Fall.

### Rechte, Tastatur, Barrierefreiheit

- Wie die übrige Seite nur Rolle `owner` (`canManageSettings()`).
- Das gesperrte Feld bleibt mit der Tastatur erreichbar und vorlesbar (`readonly`, **nicht** `aria-hidden`, nicht aus der Tabulatorfolge genommen). Lesen ist der Zweck des Feldes; ein übersprungenes Feld wäre für Screenreader gar nicht vorhanden.
- Die Pille „nicht änderbar" trägt ihren Text, nicht nur eine Farbe, Kontrast mindestens 4,5:1 gegen `surface-sunken`.
- „Schema kopieren" ist eine Schaltfläche, Klickfläche mindestens 44 px, Rückmeldung über eine höfliche Live-Region.
- Mobil (< 1024 px): Der Editor wird einspaltig, Vorschau unter dem Text; das Schemafeld behält seine eigene Scrollfläche und wird nicht auf volle Länge ausgeklappt.

### Abnahmefragen

1. Gibt es im Prompt-Editor noch ein Feld, dessen Änderung beim nächsten Lauf folgenlos bleibt? (Muss „nein" sein.)
2. Steht am Ausgabeschema, woher es kommt und wo es geändert wird?
3. Hält die Themenfindung an, wenn eine Vorlage ein unpassendes Schema trägt? (Muss „nein" sein — und es muss im Editor sichtbar sein.)
4. Zeigt die Vorschau denselben Prompt, den der Lauf tatsächlich abschickt, einschließlich Signalliste und Ausgaberegeln?
5. Legt ein erneuter Seeder-Lauf 25 inhaltsgleiche Vorlagenzeilen an? (Muss „nein" sein.)

---

## 7b.1 Bauvorgabe zum Prompt-Editor (Umsetzung von §7b)

Herkunft: #58. §7b entscheidet, **was** gilt; dieser Abschnitt legt fest, **woran** die Oberfläche das erkennt und **wie** sie es baut. Alles aus §7 und §7b bleibt unverändert.

### 1. Eine Stelle für den Vertrag: `PromptSchemaContract`

Die Prüfung „passt das gespeicherte Schema zum Code?" darf nicht zweimal existieren. Heute steht sie als private Methode `DiscoverTopicsJob::schemaFor()` im Job; die Oberfläche braucht dieselbe Antwort, ohne einen Job zu instanziieren.

Vorgabe: eine reine Nachschlageklasse `App\Content\Llm\PromptSchemaContract` ohne Abhängigkeiten (kein Container, kein Modell, kein `GenerationContext`), damit sie aus Job **und** Formular aufrufbar ist.

Schnittstelle (verbindlich in Bedeutung, frei in der Schreibweise):

| Methode | Rückgabe |
|---|---|
| `for(string $key): ?self` | Eintrag der Zuordnungstabelle, `null` = kein Code-Vertrag |
| `className(): string` | vollständiger Klassenname als Klartext, für die Herkunftszeile |
| `missingFields(?array $schema): array<int,string>` | fehlende Pflichtfelder; leer = Schema passt |
| `isSatisfiedBy(?array $schema): bool` | `missingFields() === [] && $schema !== null && $schema !== []` |

`missingFields()` arbeitet über eine Liste von Paaren *Pfad → Pflichtfelder*. Ein Pfad ist ein punktgetrennter Zeiger in das Schema (`properties.topics.items.required`). Fehlt der Pfad oder ist er kein Array, gelten **alle** dort deklarierten Felder als fehlend. Ein leeres oder fehlendes Schema gilt als „passt nicht" — das ist der Zustand „Leer und schlecht" aus §7b.

### 2. Zuordnungstabelle Vorlagenschlüssel → Code-Vertrag

Verbindlicher Stand. Die Pflichtfelder werden, wo eine Konstante existiert, aus dieser gelesen statt abgeschrieben.

| Schlüssel | Klasse für die Herkunftszeile | Pfad → Pflichtfelder |
|---|---|---|
| `topic_discover` | `App\Content\Jobs\DiscoverTopicsJob` | `properties.topics.items.required` → `DiscoverTopicsJob::REQUIRED_TOPIC_FIELDS` |
| `outline` | `App\Content\Generation\OutlineStep` | `required` → `title`, `outline`; `properties.outline.items.required` → `heading`, `level`, `key_points` |
| `section_write` | `App\Content\Generation\SectionStep` | `required` → `heading`, `summary_sentence`, `html`, `word_count`, `used_fact_ids`, `used_link_urls` |
| `short_answer` | `App\Content\Generation\ShortAnswerStep` | `required` → `short_answer` |
| `faq` | `App\Content\Generation\FaqStep` | `required` → `faq`; `properties.faq.items.required` → `question`, `answer` |
| `meta` | `App\Content\Generation\MetaStep` | `required` → `meta_title`, `meta_description` |
| `regional_block` | `App\Content\Generation\RegionalBlockStep` | `required` → `heading`, `intro`, `html`, `outro` |
| `quality_rubric` | `App\Content\Quality\RubricEvaluator` | `required` → `score`, `per_criterion`, `blocking_issues`, `fix_instructions` |
| `lead_question_cluster` | `App\Content\Jobs\ClusterLeadQuestionsJob` | `required` → `questions`; `properties.questions.items.required` → `ClusterLeadQuestionsJob::REQUIRED_QUESTION_FIELDS` (`question`, `share`) |

**Kein Eintrag** haben: `system_ratgeber_redakteur`, alle `styleguide_*` und `refresh_update`.

### 3. Drei Fälle am Feld „Ausgabeschema", nicht zwei

§7b nennt zwei. Der Bestand kennt einen dritten: `refresh_update` trägt ein geseedetes Schema, aber der Refresh-Lauf (#24) ist nicht gebaut — niemand liest es. Es als „vom Code vorgegeben" auszuzeichnen wäre falsch, es als editierbar zu zeigen wäre der Fehler, den §7b abstellt.

| Fall | Erkennung | Darstellung |
|---|---|---|
| **A — Vertrag vorhanden** | Eintrag in der Tabelle | Gesperrte Ansicht, Herkunftszeile „Vom Code vorgegeben — `<Klasse>`.", Pille „nicht änderbar", Verweis „Schema kopieren" |
| **B — kein Vertrag, kein Schema** | kein Eintrag **und** `output_schema_json` leer | Kein Editor, kein Feld. Nur der Satz „Diese Vorlage liefert kein strukturiertes Ergebnis." in `text-muted` |
| **C — kein Vertrag, aber Schema** | kein Eintrag **und** Schema vorhanden (`refresh_update`) | Wie A, aber Herkunftszeile: „Noch nicht mit der Pipeline verdrahtet — dieses Schema wird derzeit von keiner Stufe gelesen." Pille „nicht änderbar" bleibt. Kein Band „Schema veraltet" |

Fall C beantwortet die erste Abnahmefrage von §7b sauber: Auch hier bleibt eine Änderung folgenlos, also darf das Feld nicht änderbar aussehen.

### 4. Warum kein `disabled()` am Filament-Code-Editor

§7b schreibt `disabled` und gleichzeitig „nicht aus der Tabulatorfolge genommen". Beides zusammen geht mit dem vorhandenen Bauteil nicht: `Filament\Forms\Components\CodeEditor` reicht `isDisabled` an CodeMirror durch und setzt dort `EditorView.editable.of(false)`. Der Inhalt verliert damit `contenteditable`, CodeMirror setzt `tabindex="-1"` und das Feld ist für Tastatur und Screenreader nicht mehr erreichbar — genau der Zustand, den §7b ausschließt.

Vorgabe: Für die gesperrte Ansicht wird **kein Code-Editor** verwendet, sondern eine eigene Leseansicht als Blade-Partial `content.partials.prompt-schema`. Syntaxfärbung ist für 60 Zeilen JSON kein Gewinn; Einrückung und der Kopierverweis tragen die Lesbarkeit.

Aufbau:

- Äußeres Element: `<div role="group">` mit `aria-labelledby` auf die Beschriftung.
- Scrollfläche: `<div tabindex="0" role="region" aria-label="Ausgabeschema, schreibgeschützt">`, Höhe **12 Zeilen** (`max-height: 18rem`), `overflow: auto`, `background: var(--color-surface-sunken)`, Radius `rounded-content-sm`, Innenabstand `content-2`, Breite bis **720 px** (`max-w-[720px]`), volle Breite darunter.
- Inhalt: `<pre>` mit `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, Schriftgröße `content-label`, Farbe `--color-text-base`. Kein Cursor, keine Zeilennummern, keine Auswahlhervorhebung über das Übliche hinaus.
- Fokus: sichtbarer Fokusring am Scrollcontainer wie an den übrigen Feldern des Panels. Das Feld ist fokussierbar, weil es scrollt — ein scrollbarer Bereich ohne Tastaturfokus ist mit der Tastatur nicht lesbar.

**Kein Formularzustand.** Das Schema wird nicht als Formularfeld geführt: kein `dehydrate`, kein Eintrag in `$data`. `EditPromptTemplate::storeVersion()` liest `output_schema_json` deshalb ausschließlich vom Datensatz und übernimmt es unverändert in die neue Version. Ein gesperrtes Feld, dessen Wert trotzdem mitgeschickt wird, ist nur eine Sperre bis zum ersten manipulierten Formular.

### 5. Beschriftungszeile, Pille, Kopierverweis

Eine Kopfzeile über der Leseansicht, `flex`, `items-center`, `justify-between`, `gap-content-2`:

- Links: Beschriftung „Ausgabeschema (JSON Schema)" in der Feldbeschriftungs-Typografie des Panels, direkt daneben die Pille.
- **Pille „nicht änderbar"**: exakt die Bauform der Pille „abweichend" aus §7a, seit #64 als eine Klasse im Theme: `<span class="content-origin-pill">` (Höhe 20 px, Radius `--radius-content-sm`, Innenabstand `--spacing-content-2`, `surface-sunken`, `text-base`) — nicht per Utility-Klassen nachbauen. **`text-base`, nicht `text-muted`**: `--color-text-muted` liegt auf `surface-sunken` bei 4,45:1 und verfehlt die geforderten 4,5:1 (#64). Kein Statusfarbton, kein Icon, der Text trägt die Aussage allein.
- Rechts: Schaltfläche **„Schema kopieren"** als `<button type="button">` in Textverweis-Optik, Klickfläche mindestens 44 × 44 px (`min-h-11`, `px-content-2`, negativer Randausgleich, damit die Zeile optisch nicht wächst). Alpine-Handler auf `navigator.clipboard.writeText`. Rückmeldung: eine `<p role="status" aria-live="polite">` unter der Kopfzeile mit dem Text „Schema kopiert." für 4 Sekunden, danach leer. Kein Toast — die Rückmeldung gehört an den Ort der Handlung. Schlägt das Kopieren fehl, steht dort „Kopieren nicht möglich. Text markieren und kopieren." in `--color-status-failed-fg`.
- Darunter, vor der Leseansicht: die **Herkunftszeile** in `text-content-label`, `text-text-muted` (auf weißer Kartenfläche zulässig), Klassenname als Klartext in Schreibmaschinenschrift, nicht verlinkt, umbruchfähig (`break-all`).

Der bisherige Hilfetext des Feldes entfällt ersatzlos.

### 6. Bänder über dem Editor

Reihenfolge von oben nach unten, höchstens zwei sichtbar:

1. **Schema veraltet / Schema fehlt** — nur bei Fall A und `isSatisfiedBy() === false`. Hintergrund `--color-status-failed-bg`, Text `--color-status-failed-fg`, Rand `1px solid` in derselben Farbe wie der Text bei 20 % Deckung, Radius `rounded-content-sm`, Innenabstand `content-3`. Text bei vorhandenem, aber unpassendem Schema: „Wird beim Lauf durch das Schema aus dem Code ersetzt." Bei ganz fehlendem Schema: „Diese Vorlage hat kein Ausgabeschema. Der Lauf verwendet das Schema aus dem Code." Kein Icon-only, der Satz steht ausgeschrieben.
2. **Wirkungsband** — das vorhandene Band aus §7 (`ListPromptTemplates`, „Änderungen wirken auf alle Portale ab dem nächsten Lauf.") bekommt bei Fall A und C den zweiten Satz: „Der Prompttext ist frei; Variablen und Ausgabeschema sind mit der Pipeline verdrahtet und nur im Code änderbar." Bei Fall B bleibt es einsätzig.

### 7. Spalte „Zustand" in der Liste

Die Spalte trägt heute genau ein Abzeichen (`aktiv` / `abgelöst`). Sie bekommt ein zweites, **rechts daneben in derselben Zelle**, nicht statt des ersten:

- Text „Schema veraltet", Farbe `status-failed`, gleiche Abzeichenbauform wie das vorhandene.
- Bedingung: Fall A **und** `isSatisfiedBy() === false`. Fall B und C tragen nie ein Abzeichen.
- `title`-Attribut: „Wird beim Lauf durch das Schema aus dem Code ersetzt."
- Das `title`-Attribut ist Zugabe, keine Information — der Text „Schema veraltet" steht sichtbar in der Zelle und beantwortet die dritte Abnahmefrage von #58 ohne Zeigergerät.
- Die Berechnung läuft je Zeile über `PromptSchemaContract`, ohne Abfrage. Bei zwei Abzeichen umbricht die Zelle (`flex-wrap`, `gap-content-1`).

### 8. Mobil unter 1024 px

Der Editor ist bereits einspaltig (`Grid` mit `default: 1, lg: 2`). Die Leseansicht behält ihre 12 Zeilen und ihre eigene Scrollfläche, sie wird nicht ausgeklappt. Die Kopfzeile bricht um: Beschriftung mit Pille in die erste Zeile, „Schema kopieren" linksbündig in die zweite, Klickfläche bleibt 44 px.

### 9. Was bleibt

`jsonSchemaRule()` bleibt bestehen und bleibt am Feld registriert, wenn eines existiert — sie greift für Vorlagen ohne Code-Vertrag und für alles, was per Seeder oder Konsole in die Tabelle kommt. Sie wird nicht verschärft: Was sie nicht prüfen kann, prüft der Vertrag zur Laufzeit.

`DiscoverTopicsJob::schemaFor()` behält sein Verhalten und seine Warnung wörtlich, bezieht die Pflichtfelder aber aus `PromptSchemaContract`. Es bleibt bei einer Prüflogik.

---

## 8. Abnahmefragen für die Umsetzung

1. Beantwortet die Übersicht in unter zehn Sekunden, ob der Tag in Ordnung ist?
2. Sehen alle sieben Status in Board, Kalender, Tabellen, Leistung und E-Mail identisch aus und heißen gleich? Trägt kein Status die Teal-Markenfarbe des Panels?
3. Verhält sich der Filterbalken in allen fünf Bereichen gleich und überlebt er den Ansichtswechsel?
4. Hat jeder Screen alle fünf Zustände umgesetzt?
5. Ist eine Prüfung mit der Tastatur allein in unter 90 Sekunden abschließbar?
6. Trägt jede Kennzahl aus Search Console oder AdSense ihren Datenstand?
7. Bestehen Kontrast- und Tastaturprüfung, respektiert jede Bewegung `prefers-reduced-motion`?
8. Ist das Panel ohne Kontext vom Admin-Panel unterscheidbar?
9. Führt jede Stelle, die eine gekürzte Menge zeigt — volle Board-Spalte, Balkenabschnitt der Übersicht, Tagesblatt — gefiltert und passend sortiert in die Artikelliste?
10. Gibt es in den Einstellungen ein Feld, dessen Änderung folgenlos bleibt — Budget, Quellen-Zugänge, Ausgabeschema? (Muss „nein" sein.)
11. Sind bei mehreren gleichzeitigen Störungen alle wichtigen Ursachen ohne Interaktion lesbar, und trägt eine betroffene Portalkachel eine Marke, die auch in Graustufen wirkt? (§1a)
12. Wird die Artikellänge an genau einer Zahlenquelle gemessen — dem Zielkorridor der Suchintention — und unterscheidet die Prüffläche sichtbar zwischen Hinweis und Blockade? (§4.2)
