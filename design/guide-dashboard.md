# Ratgeber-Dashboard (themengetrieben) — Screen-Spezifikation

Ticket #4. Umsetzung in #14 (Gerüst, Tokens), #15 (Import, Themen, Kategorien, Gliederung), #16 (Heute, Prüfung, Verlauf, Einstellungen, Kosten).
Klammer: Dokument „UX-Leitbild Themengetriebenes Ratgebersystem (Epic #1)“ (im Folgenden *Leitbild*). Architektur: `docs/guide-system.md` (*Freeze*).
Tokens: `resources/css/content/theme.css`. Neue Werte dieses Dokuments stehen in §2.4 und werden von #14 dort eingetragen.

Dieses Dokument **ersetzt** `design/content-dashboard.md` für alle Screens des neuen Systems. Aus dem alten Dokument gelten weiter, ohne hier wiederholt zu werden:

- §0 *Grundgerüst* (Maße von Navigation, Kopfzeile, Filterbalken, Inhalt), *Pflichtzustände*, *Filament-Badge = Statuspille*, *Bewegung, Fokus, Tastatur*
- §4.1 / §4.1a *Fassungsvergleich* (Zeilentypen, Wortmarken, Umbruch unter 1024 px) — wird in §8 unverändert wiederverwendet
- §7, §7b *kein folgenloses Feld*, Herkunfts-Pille, Prompt-Editor
- Kontrastregel #64: auf `surface-sunken` nie `text-muted`, sondern `text-base`

Maße in px beziehen sich auf die Panel-Grundschrift 16 px. Alle Farben sind Token-Namen, keine Hex-Werte in Templates.

---

## 1. Rahmen

### 1.1 Abgrenzung zum Admin-Panel

Das Panel `content` behält sein eigenes Theme (dunkle Teal-Navigation `surface-nav`, warmer Seitengrund `surface-page`, Marke `content-600`). Das erfüllt den Hinweis „klar vom Admin-Panel unterschieden“ bereits; es wird **kein** zweites Theme gebaut. Der Seitentitel in der Kopfzeile lautet „Ratgeber“, das Panel-Logo bleibt.

### 1.2 Navigation — fünf Punkte, fest

| # | Punkt | Reiter (`?reiter=`) | Zählmarke |
|---|---|---|---|
| 1 | Heute | — | nein |
| 2 | Themen | Themen · Kategorien · Import · Altartikel | nein (Reiter „Altartikel“ trägt eine Zählmarke, §5.6) |
| 3 | Prüfung | — | **ja**, Zahl der Läufe in `review` |
| 4 | Verlauf | Läufe · Versionen · Kosten | nein |
| 5 | Einstellungen | Tageslauf · Budget · Prompts · Quellen | nein |

Der Gliederungs-Editor ist die Detailansicht eines Themas (§5), kein Navigationspunkt. Die Seiten `Production`, `SourceMonitor`, `Performance` des alten Systems erscheinen nicht mehr in der Navigation (Rückbau #19; `Performance` bleibt erreichbar, bis #16 entscheidet — dann als Reiter unter Verlauf, nicht als sechster Punkt).

Startseite des Panels ist **Heute**.

### 1.3 Filterbalken — einer, überall gleich

Reihenfolge fest: **Portal · Kategorie · Themenstatus · Laufergebnis · Zurücksetzen**. Nur unter *Verlauf* kommt links davor **Zeitraum** (Heute · 7 Tage · 30 Tage · Frei, Vorgabe 7 Tage).

| Filter | URL-Parameter | Auswahl | Anzeige im Balken |
|---|---|---|---|
| Portal | `portal` | Mehrfach, mit Suchfeld | „Alle Portale“ / „Sanitärfinder“ / „3 Portale“ |
| Kategorie | `kategorie` | Mehrfach, Slugs | „Alle Kategorien“ / Name / „2 Kategorien“ |
| Themenstatus | `thema` | Mehrfach über TopicStatus, Statuspunkt davor | „Alle“ / Beschriftung / „2 Status“ |
| Laufergebnis | `lauf` | Mehrfach über die sieben Anzeigewerte aus §2.2 (nicht die neun Enum-Werte) | wie oben |
| Zurücksetzen | — | Textverweis, nur sichtbar bei Abweichung vom Standard | — |

- Der Zustand steht in der URL, überlebt den Wechsel zwischen Bereichen und Reitern und ist teilbar.
- Gesetzte Filter erscheinen als entfernbare Marken unter dem Balken (24 px, `radius-content-sm`, `surface-sunken`, Text `text-base`, Schließen-Schaltfläche 24 × 24 px mit `aria-label="Filter Kategorie Förderung entfernen"`).
- Unter 1024 px: eine Schaltfläche „Filter (n)“, die ein Blatt von unten öffnet (max. 85 vh, eigener Scrollbereich, unten „Anwenden“ primär und „Zurücksetzen“ als Textverweis).
- Nicht anwendbare Filter werden nicht ausgeblendet, sondern deaktiviert mit Tooltip („In der Kostenansicht ohne Wirkung“) — sonst springt der Balken zwischen den Bereichen.

### 1.4 Sprache

Siezen. Keine Enum-Werte in der Oberfläche außer in der aufklappbaren Fehlerdiagnose eines Laufs. Verbindliche Wörter: *Thema*, *Lauf*, *Prüfung* (Tätigkeit des Systems: „geprüft“), *Freigabe* (Tätigkeit des Menschen), *Aktualisierung*, *Neuanlage*. Nie „Refresh“, „Update“, „Job“, „Draft“.

Geldbeträge in USD mit deutschem Format: `18,40 USD`. Unter 1 USD zwei Nachkommastellen, nie „0 USD“ für Beträge > 0 (dann `< 0,01 USD`).

---

## 2. Statussprache und Farben (normativ)

Zwei Achsen, zwei Pillen, nie in einer Spalte (Leitbild §3). Es kommen **keine neuen Farbrollen** hinzu; beide Enums werden auf die sieben vorhandenen `status-*`-Rollen abgebildet. Bauform ist die bestehende `.content-status` bzw. das Filament-Badge (24 px, Radius 6 px, Punkt 8 px).

### 2.1 Thema — `App\Guide\Enums\TopicStatus`

| Wert | Beschriftung | Farbrolle (Filament-Farbe) | Punkt | Symbol in Filtern/Auswahl |
|---|---|---|---|---|
| `draft` | Entwurf | `status-idea` | Punkt | `heroicon-o-document` |
| `outline_pending` | Gliederung offen | `status-review` | Punkt | `heroicon-o-list-bullet` |
| `active` | Aktiv | `status-published` | Punkt | `heroicon-o-check-circle` |
| `paused` | Pausiert | `status-scheduled` | **Pausenzeichen** statt Punkt (§2.3) | `heroicon-o-pause-circle` |
| `archived` | Archiviert | `status-archived` (Deckkraft 0,85) | Punkt | `heroicon-o-archive-box` |

### 2.2 Lauf von heute — `App\Guide\Enums\RunStatus` × `RunMode`

Die Oberfläche zeigt **sieben Anzeigewerte**. Die vier Arbeitsschritte teilen sich einen.

| Anzeigewert (`lauf=`) | Enum | Beschriftung in der Pille | Zeile unter der Pille (`.content-status-progress`) | Farbrolle |
|---|---|---|---|---|
| `wartet` | `queued` | Wartet | „eingeplant für 03:10“ | `status-idea` |
| `in-arbeit` | `probing`, `researching`, `writing`, `checking` | In Arbeit | Schritt: „Prüft Aktualität“ / „Recherchiert“ / „Schreibt“ / „Qualitätsprüfung“ + „seit 02:14“ | `status-generating`, Punkt pulsiert (nur ohne `prefers-reduced-motion`) |
| `pruefung` | `review` | Zur Prüfung | Anlass kurz: „Faktenabgleich unsicher“ / „Gliederung sperren“ | `status-review` |
| `neu` | `published` + Mode `create` | Neu erschienen | Uhrzeit | `status-published` |
| `aktualisiert` | `published` + Mode `update` | Aktualisiert | Zahl geänderter Abschnitte: „2 Abschnitte“ | `status-published` |
| `unveraendert` | `unchanged` | Geprüft, unverändert | Uhrzeit | **eigene Bauform** `.content-status--unchanged` (§2.3) |
| `fehlgeschlagen` | `failed` | Fehlgeschlagen | Grund im Klartext, max. 60 Zeichen, Rest im Tooltip | `status-failed` |

Themen ohne Lauf heute zeigen in der Laufspalte keinen Status, sondern Text in `text-muted`: „nicht fällig“ bzw. „ausgelassen (Budget)“ bzw. „pausiert“. Für die Zählung in §3 gelten sie als *Offen* (fällig, aber nicht gelaufen) oder gar nicht (nicht fällig).

Rangfolge beim Sortieren nach Laufergebnis (schlechtestes zuerst): Fehlgeschlagen · Zur Prüfung · In Arbeit · Wartet · Neu erschienen · Aktualisiert · Geprüft, unverändert · (kein Lauf).

### 2.3 Zwei Sonderbauformen

**`.content-status--unchanged` — „Geprüft, unverändert“.** Erfolg in abgeschwächter Form, ausdrücklich *nicht* grau wie „Wartet“:

| Teil | Token | Kontrast |
|---|---|---|
| Fläche | `status-idea-bg` (#f4f5f7) | — |
| Text | `text-base` (#3d4a48) | 8,4:1 |
| Punkt | `status-published-dot` (#047857) | 5,0:1 auf der Fläche |

Unterschied zu „Wartet“ (gleiche Fläche): Punkt grün statt schiefer, Text lautet anders, keine Zeitangabe „eingeplant“. Farbe trägt die Aussage nie allein.

**`.content-status--paused` — Pausenzeichen statt Punkt.** Fläche/Text `status-scheduled`; `::before` zeichnet zwei Balken je 2 × 8 px mit 2 px Abstand in `currentColor` (keine Animation). Grund: „Pausiert“ und „Wartet“ sind beide ruhende Zustände; das Zeichen macht den Unterschied auch für Farbfehlsichtige sichtbar.

### 2.4 Neue Tokens für `theme.css` (Eintrag durch #14)

Nur Füllfarben für den gestapelten Tagesbalken (§3.2) und die beiden Bauformen. Keine neue Statusrolle.

| Token | Wert | Verwendung | Kontrast |
|---|---|---|---|
| `--color-run-new-fill` | `var(--color-status-published-fill)` + Schraffur (s. u.) | Segment „Neu erschienen“ | 3,77:1 auf Weiß |
| `--color-run-updated-fill` | `var(--color-status-published-fill)` #059669 | Segment „Aktualisiert“ | 3,77:1 |
| `--color-run-unchanged-fill` | `#6fbf9f` | Segment „Geprüft, unverändert“ | 2,2:1 — zulässig nur, weil jedes Segment in der Legende mit Zahl steht (s. u.) |
| `--color-run-review-fill` | `var(--color-status-review-fill)` | Segment „Zur Prüfung“ | 3,19:1 |
| `--color-run-failed-fill` | `var(--color-status-failed-fill)` | Segment „Fehlgeschlagen“ | 4,83:1 |
| `--color-run-active-fill` | `var(--color-status-generating-fill)` | Segment „In Arbeit“ | 4,23:1 |
| `--color-run-open-fill` | `var(--color-line-strong)` #d3d3cc | Segment „Offen“ (Restspur) | — (Restfläche) |

Schraffur „Neu erschienen“: `repeating-linear-gradient(135deg, var(--color-status-published-fill) 0 4px, var(--color-status-published-dot) 4px 6px)`. Neu und Aktualisiert unterscheiden sich damit durch Muster, nicht nur durch Helligkeit.

Begründung `run-unchanged-fill`: „Unverändert“ ist das häufigste Segment (erwartet ~90 %). Ein voller Erfolgston würde den Balken grün fluten und Neu/Aktualisiert darin verschwinden lassen; ein heller Ton lässt die seltenen Ereignisse hervortreten. WCAG 1.4.11 ist erfüllt, weil der Balken nie allein steht: Legende mit Zahlen ist Pflicht, und der Balken trägt `role="img"` mit vollständigem `aria-label` (§3.2).

### 2.5 Abweichungen im Code, die #14 korrigieren muss

Die Enums unter `app/Guide/Enums/` liefern heute generische Filament-Rollen und teils abweichende Beschriftungen:

| Stelle | Ist | Soll |
|---|---|---|
| `TopicStatus::color()` | `gray/warning/success/info/danger` | `status-idea/review/published/scheduled/archived` |
| `TopicStatus::ARCHIVED` | `danger` (rot) | `status-archived` — Archivieren ist keine Störung |
| `TopicStatus::OUTLINE_PENDING->label()` | „Gliederung prüfen“ | „Gliederung offen“ |
| `RunStatus::color()` | generische Rollen, `UNCHANGED` = `gray` | Anzeigewert nach §2.2; `unchanged` nie grau |
| `RunStatus::QUEUED->label()` | „Eingeplant“ | „Wartet“ |
| `RunStatus::PUBLISHED->label()` | „Veröffentlicht“ | hängt vom Mode ab → Anzeige über eine Abbildung `(RunStatus, RunMode) → Anzeigewert`, nicht über `label()` allein |

Die Abbildung auf Anzeigewerte gehört an **eine** Stelle (z. B. ein `RunDisplay`-Wertobjekt); Tabellen, Heute, Prüfung, Mail-Tagesbericht rufen nur diese auf.

---

## 3. Heute — Tageslauf-Monitor (Desktop + Mobil)

Frage des Screens: **„Ist der Tag in Ordnung?“ — Antwort in unter 10 Sekunden.** Der Screen fordert keine Handlung, solange alles läuft.

### 3.1 Aufbau von oben nach unten (Desktop ≥ 1024 px, 12-Spalten-Raster)

1. **Kopf** (volle Breite): `h1` „Heute, 21. September 2026“. Darunter eine Zustandszeile, 15 px `text-base`, mit genau einem der Sätze:
   - „Tageslauf läuft seit 02:00 · voraussichtlich fertig gegen 04:40“
   - „Tageslauf abgeschlossen um 03:52“
   - „Nächster Lauf heute ab 02:00“ (vor dem Fenster; ersetzt den leeren Monitor, Leitbild §9)
   - „Tageslauf global pausiert seit 20.09., 18:12 (Enes)“ + Verweis „In den Einstellungen fortsetzen“
2. **Störungsbänder**, höchstens drei sichtbar, darunter „+ 2 weitere Hinweise“ als Aufklapper. Rangfolge: alle `failed`-Bänder vor allen `review`-Bändern; innerhalb *failed*: Watchdog/hängende Läufe · Budget erreicht · Fehlgeschlagen ≥ 5 % · Tenant-Datenbank nicht erreichbar; innerhalb *review*: Budget ≥ 80 % · Prüfung älter als 24 h · Gliederungen offen. Jedes Band: linke Kante 3 px `status-*-dot`, Fläche `status-*-bg`, ein Satz mit Zahl, **genau eine** Handlung als Textverweis in die gefilterte Liste.
   - Budget erreicht (Leitbild §9): „Tagesbudget erreicht um 03:31. 42 fällige Themen wurden ausgelassen und laufen morgen zuerst.“ Die 42 zählen als *Offen*, nie als *Fehlgeschlagen*.
3. **Kachel „Prüfstand heute“** (8 Spalten) und **Kachel „Kosten heute“** (4 Spalten), gleiche Höhe.
4. **Tabelle „Portale“** (12 Spalten).
5. **Zeile „Altartikel“** (12 Spalten, nur bei offenen Überschneidungen, §5.6.7): „7 Themen überschneiden sich mit Altartikeln — Entscheidung offen“ + Verweis „Entscheiden“ → *Themen › Altartikel*. Kein Störungsband: eine Überschneidung ist keine Störung des Tageslaufs (Stufe `info`).
6. **Zeile „Außerhalb des Plans“** (12 Spalten, nur wenn nicht leer): Themen, die seit mehr als ihrem Prüfabstand + 2 Tage nicht geprüft wurden. „19 Themen überfällig“ + Verweis.

### 3.2 Kachel „Prüfstand heute“

- Überschrift `content-h2` „Prüfstand heute“, rechts `content-asof` „Stand 03:41“ (Aktualisierung alle 60 s über `wire:poll.60s`, nur solange die Seite sichtbar ist).
- Kennzahl `content-metric` (44 px, tabellar): **„287 von 300“**, darunter 15 px `text-base`: „fälligen Themen geprüft“.
  - *Geprüft* = Neu erschienen + Aktualisiert + Geprüft, unverändert + Zur Prüfung. Fehlgeschlagen, In Arbeit, Wartet und Offen zählen nicht.
  - Nenner = heute fällige Läufe aller gefilterten Portale (inkl. wegen Budget ausgelassener).
- **Gestapelter Balken**, 16 px hoch, `radius-content-sm`, volle Kachelbreite, Segmente mit 2 px weißem Abstand, feste Reihenfolge links → rechts:
  **Neu erschienen · Aktualisiert · Geprüft, unverändert · Zur Prüfung · Fehlgeschlagen · In Arbeit · Offen** (Tokens §2.4).
  - Abweichung vom Leitbild §4 (dort ohne „In Arbeit“): Während des Laufzeitfensters ist ein Großteil der Läufe unterwegs; ohne eigenes Segment läse sich der Balken bis 04:00 wie „nichts passiert“. „In Arbeit“ steht deshalb vor „Offen“, getrennt durch Muster und Farbe.
  - Segmente unter 0,5 % werden auf 4 px Mindestbreite gesetzt, damit ein einzelner Fehlschlag sichtbar bleibt.
  - `role="img"`, `aria-label="287 von 300 fälligen Themen geprüft: 3 neu erschienen, 21 aktualisiert, 258 unverändert, 5 zur Prüfung, 4 fehlgeschlagen, 6 in Arbeit, 3 offen."`
- **Legende** unter dem Balken als Zeile von sieben Zählern (Umbruch erlaubt): Farbfeld 12 × 12 px (bei Neu mit Schraffur), Beschriftung 14 px `text-base`, Zahl 14 px fett tabellar. **Jeder Zähler ist ein Verweis** → *Themen* mit `lauf=<wert>` und dem aktuellen Portalfilter. Zähler mit 0 bleiben stehen (kein Springen), Zahl dann `text-muted`, kein Verweis.
- Zweite Zeile, 14 px `text-muted`: „Änderungsquote heute 8 % (Annahme im Kostenmodell: 10 %)“.

### 3.3 Kachel „Kosten heute“

- Kennzahl 28 px: „18,40 USD“, daneben 15 px „von 60,00 USD“.
- Fortschrittsbalken 8 px, Füllung `status-published-fill` unter 80 %, `status-review-fill` ab 80 %, `status-failed-fill` ab 100 %; Schwelle 80 % als 1-px-Marke im Balken.
- Drei Zeilen darunter, 14 px, Zahlen rechtsbündig tabellar: „Neuanlagen 3 × ⌀ 0,79 USD“, „Aktualisierungen 21 × ⌀ 0,58 USD“, „Prüfungen ohne Änderung 258 × ⌀ 0,09 USD“. Aktualisierungen stehen getrennt, nie herausgerechnet.
- Verweis „Kostenverlauf“ → *Verlauf › Kosten*.

### 3.4 Tabelle „Portale“

| Spalte | Breite | Inhalt |
|---|---|---|
| Portal | 22 % | Name, darunter 12 px `text-muted` Domain |
| Tagesbalken | 26 % | derselbe Balken wie §3.2 in 8 px Höhe, ohne Legende; `aria-label` wie oben je Portal |
| Geprüft | 10 % | „96 / 100“ tabellar, rechtsbündig |
| Neu · Aktualisiert · Zur Prüfung · Fehlgeschlagen | je 8 % | Zahlen; 0 als „–“ in `text-muted`; jede Zahl > 0 ist Verweis in *Themen* gefiltert auf Portal + Laufergebnis |
| Kosten | 10 % | „0,84 USD“; ab 80 % des Portalbudgets Text `status-review-fg` + Symbol |

- Sortierung Vorgabe: Portale mit Fehlgeschlagen > 0 oder Prüfung > 0 zuerst, dann alphabetisch. Spaltenköpfe sortierbar (`aria-sort`).
- Zeile hat Höhe 56 px; Klick auf die Zeile setzt den Portalfilter (gleiches Verhalten wie der Filter, keine eigene Detailseite).
- Portale mit `is_active = false` stehen unter einer Trennzeile „Nicht freigeschaltet (3)“, eingeklappt.

### 3.5 Zustände

| Zustand | Darstellung |
|---|---|
| Leer und gut (alles geprüft, nichts offen) | Band ohne Farbe entfällt; über der Kennzahl ein Satz mit Häkchen in `status-published-dot`: „Alle fälligen Themen sind geprüft.“ |
| Vor dem ersten Lauf (Themen importiert, nichts aktiv) | Kacheln entfallen. Karte mit `h2` „Themen importiert, noch nicht aktiviert“, Satz „100 Themen in 3 Portalen warten auf die Aktivierung. Erst dann entstehen Kosten.“ und **einer** primären Schaltfläche „Themen aktivieren …“ (öffnet §4.4). |
| Außerhalb des Fensters, noch kein Lauf heute | Zustandszeile „Nächster Lauf heute ab 02:00“; Kacheln zeigen den gestrigen Lauf mit Überschrift „Gestern, 20. September“ und `content-asof`. |
| Lädt | `content-skeleton` in Zielgeometrie (Kennzahl 44 px, Balken 16 px, 8 Tabellenzeilen). Nie von 0 hochzählen. |
| Teilweise fehlgeschlagen | Normalfall: Band `review` bzw. `failed` nach §3.1; Rest bleibt bedienbar. |
| Veraltet (Poll schlägt fehl) | `content-asof` wird zu „Stand 03:41 — Verbindung unterbrochen“ in `status-review-fg`; keine Werte löschen. |

### 3.6 Mobil (< 1024 px) — Mobilvariante 1 von 3

Reihenfolge: Kopf (Zustandszeile zweizeilig) → Störungsbänder (max. 2 sichtbar) → Kachel „Prüfstand“ volle Breite (Kennzahl 36 px, Legende zweispaltig) → Kachel „Kosten“ → Portalliste als Karten.

Portalkarte: Innenabstand 16 px, Name 16 px fett, rechts „96 / 100“; darunter Balken 8 px; darunter eine Zeile nur mit den von 0 verschiedenen Problemzählern („2 zur Prüfung · 1 fehlgeschlagen“, als Verweise). Ganze Karte 44 px Mindest-Klickfläche, Tippen setzt den Portalfilter. Kein waagerechtes Scrollen.

---

## 4. Themen › Import — Wizard (Desktop)

Vier Schritte (Leitbild §8). Stepper oben: Schrittnummer im Kreis 28 px + Beschriftung, aktiver Schritt `content-600`, erledigte mit Häkchen, künftige `text-muted`; `<ol>` mit `aria-current="step"`. Schritte sind nur rückwärts anklickbar.

Der Wizard ist eine eigene Seite im Reiter *Import* (URL `?reiter=import&schritt=2`), kein Dialog — Schritt 3 braucht die volle Breite. Verlassen mit ungesicherten Angaben fragt nach („Import verwerfen?“). Der Zwischenstand wird serverseitig gehalten, ein Neuladen verliert nichts.

Fußleiste jedes Schritts, klebend, 72 px: links „Zurück“ (grau, sekundär), rechts genau eine primäre Schaltfläche.

### 4.1 Schritt 1 — Hochladen

- Feld „Name der Themenliste“ (Pflicht, max. 80 Zeichen), Feld „Branche“ (Auswahl, Pflicht; steuert die Platzhalter `{branche}`, `{leistung}`).
- Drei Eingabewege als Segmentschalter: **Datei** · **Einfügen** · *(kein dritter)*.
  - Datei: Ablagefläche 100 % × 200 px, gestrichelter Rand 2 px `line-strong`, `radius-content-lg`; Text „CSV- oder Excel-Datei hierher ziehen oder“ + Schaltfläche „Datei auswählen“ (sekundär). Die Schaltfläche ist der eigentliche Bedienweg, Ziehen ist Zugabe. Erlaubt: `.csv`, `.xlsx`, max. 5 MB, max. 1.000 Zeilen. Nach Auswahl: Dateiname, Zeilenzahl, „Entfernen“.
  - Einfügen: Textfeld 12 Zeilen, Hinweis darunter „Eine Zeile je Thema. Spalten mit Tabulator trennen — so kommt es aus Excel oder Google Tabellen.“
- Primär: „Weiter zu Spalten“. Fehler (falsches Format, leere Datei, > 1.000 Zeilen) inline unter der Ablagefläche in `status-failed-fg` mit Handlung: „Die Datei hat 1.240 Zeilen. Teilen Sie sie in zwei Listen auf.“

### 4.2 Schritt 2 — Spalten zuordnen

Tabelle „Erkannte Spalten“: je Zeile eine Spalte der Datei.

| Spaltenkopf der Datei | Beispielwerte (3, grau, abgeschnitten) | Zuordnung (Auswahl) |
|---|---|---|

Zuordnungsziele: **Thema** (Pflicht, genau einmal) · Kategorie · Überschriften H2 · Überschriften H3 · Suchbegriff · Notiz · *Nicht übernehmen*.

- Vorbelegung aus dem Spaltenkopf (Thema/Titel/Topic → Thema, Kategorie/Rubrik → Kategorie, H2/Gliederung → Überschriften H2). Vorbelegte Werte tragen die Herkunfts-Pille „erkannt“.
- Überschriften in einer Zelle werden mit `|` getrennt; H3 werden der jeweils vorausgehenden H2 zugeordnet über das Präfix `-` („- Unterpunkt“). Diese Regel steht als Hilfetext direkt unter der Auswahl, nicht in einem Tooltip.
- Platzhalter in Beispielwerten (`{branche}`) werden als Marke mit `placeholder-known` hervorgehoben, unbekannte mit `placeholder-missing` und dem Hinweis „Unbekannter Platzhalter {gewerk} — wird nicht ersetzt“.
- Primär „Weiter zur Vorschau“ ist deaktiviert, solange *Thema* nicht zugeordnet ist; Grund steht daneben im Klartext.

### 4.3 Schritt 3 — Vorschau

Oben vier Zählerkarten in einer Reihe (je 3 Spalten), jede ein Filter auf die Tabelle darunter:

| Zähler | Marke in der Tabelle | Farbe der Marke |
|---|---|---|
| Neu | „neu“ | `status-published` |
| Dublette | „Dublette“ + Verweis auf das vorhandene Thema | `status-review` |
| Kategorie wird neu angelegt | „neue Kategorie“ | `status-scheduled` |
| Überschriften vorgegeben | „Gliederung vorgegeben“ | `status-idea` |

Eine Zeile kann mehrere Marken tragen (20-px-Pillen, Bauform `.content-origin-pill` mit Statusfläche). Fehlerzeilen (leeres Thema, > 120 Zeichen) oben, Marke „wird übersprungen“ in `status-failed`.

**Kategorienverteilung** (rechte Spalte, 4 von 12, klebend): Liste Kategorie · Zahl der Themen als waagerechter Balken (`status-scheduled-fill` auf Weiß) + Zahl. Hinweise darüber als `review`-Band, je Regel ein Satz (Leitbild §7):

- ab 15 Kategorien: „17 Kategorien — empfohlen sind 6 bis 12. Kleine Kategorien zusammenlegen?“
- Kategorien mit 1–2 Themen: „3 Kategorien haben weniger als 3 Themen und bekommen keine eigene Seite.“
- ähnliche Namen: „‚Förderung‘ und ‚Förderungen‘ zusammenlegen?“ mit Schaltfläche „Zusammenlegen“ (sekundär, wirkt nur im Import).

Tabelle (Hauptspalte, 8 von 12): Thema (mit aufgelösten Platzhaltern für das im Feld „Vorschau für Portal“ gewählte Portal), Kategorie, Überschriften (Zahl, aufklappbar), Marken. Dubletten: Auswahl je Zeile „Überspringen“ (Vorgabe) / „Trotzdem anlegen“.

Primär: „Weiter zur Zuweisung“.

### 4.4 Schritt 4 — Zuweisen und abschließen

- Portalauswahl als Liste mit Kontrollkästchen, gruppiert nach Branche; Portale der gewählten Branche vorausgewählt. Je Portal rechts: „hat bereits 12 Themen“.
- Zusammenfassung in einer Karte, 15 px:
  „Es werden **96 Themen** als **Entwurf** in **3 Portalen** angelegt (288 Themen). 4 neue Kategorien. **Es entstehen keine Kosten** — geschrieben wird erst nach ‚Themen aktivieren‘.“
- Primär: „Import abschließen“. Danach Erfolgsseite: Häkchen, Satz „96 Themen in 3 Portalen angelegt“, eine primäre Schaltfläche **„Themen aktivieren …“** und ein Textverweis „Später, zur Themenliste“.

### 4.5 Dialog „Themen aktivieren“ — Kostenvorschau (Pflicht, Leitbild §2.4)

Filament-Modal, 560 px, `radius-content-xl`. Wird aus Import-Erfolg, Themenliste (Sammelaktion) und „Heute“ (Vor-dem-ersten-Lauf) gleich aufgerufen.

Inhalt, von oben:

1. Satz: „Sie aktivieren **96 Themen** in **3 Portalen** (288 Artikel).“
2. Aufschlüsselung als Definitionsliste, Zahlen rechtsbündig tabellar:
   - Ersterstellung einmalig: 288 × 0,78 USD = **≈ 225 USD**
   - Dauer der Ersterstellung: **≈ 20 Tage** (höchstens 5 Neuanlagen je Portal und Tag)
   - Laufend danach: **≈ 5,84 USD pro Tag** (≈ 175 USD im Monat) bei Prüfabstand 7 Tage
   - Tagesbudget: 60,00 USD, davon heute verbraucht 18,40 USD
   Werte kommen aus `config('guide.*')` und dem Kostenmodell des Freeze; die Formel steht als 12-px-Zeile darunter („Schätzung nach Kostenmodell #2, Stand 21.09.2026“).
3. Gliederung: „62 Themen haben vorgegebene Überschriften — deren Gliederung wird beim Aktivieren gesperrt. 34 Themen bekommen einen Gliederungsvorschlag und warten danach unter ‚Gliederung offen‘ auf Ihre Freigabe.“
4. Kontrollkästchen (Pflicht bei > 50 USD Ersterstellung): „Ich habe die geschätzten Kosten gesehen.“
5. Schaltflächen: „Abbrechen“ (sekundär) · **„96 Themen aktivieren“** (primär, Zahl in der Beschriftung).

Liegt die Schätzung über dem Monatsrest des Budgets, erscheint über den Schaltflächen ein `failed`-Band: „Die Ersterstellung übersteigt das Monatsbudget. Sie wird über das Tagesbudget gestreckt und dauert ≈ 34 Tage.“ Die Aktivierung bleibt möglich.

---

## 5. Themen › Themen — Themenliste und Thema-Detail

### 5.1 Themenliste (Desktop)

Filament-Tabelle, 50 Zeilen je Seite, Zeilenhöhe 56 px.

| Spalte | Breite | Inhalt | Sortierbar |
|---|---|---|---|
| Auswahl | 40 px | Kontrollkästchen | — |
| Thema | 30 % | Titel (15 px, 2 Zeilen max.), darunter 12 px `text-muted` Kategorie | ja (A–Z) |
| Portal | 12 % | Name; bei „Alle Portale“ siehe unten | ja |
| Thema-Status | 12 % | TopicStatus-Pille §2.1 | ja |
| Heute | 14 % | Laufergebnis §2.2 + Fortschrittszeile | ja (Rangfolge §2.2) |
| Aktualisiert | 10 % | Datum `dd.mm.yyyy`, „–“ wenn nie | ja |
| Geprüft | 10 % | Datum | ja |
| Nächste Prüfung | 10 % | relativ: „in 3 Tagen“, „heute“; überfällig in `status-failed-fg` mit Symbol: „seit 2 Tagen fällig“ | ja, Vorgabe aufsteigend |
| Aktionen | 48 px | ⋯-Menü | — |

- **Eine Zeile = ein Thema in einem Portal** (so liegen die Daten, Freeze §4). Bei „Alle Portale“ gibt es eine Gruppierung nach Thema (Filament-Tabellengruppe), Gruppenkopf: Titel + „in 3 Portalen“ + die schlechteste Laufpille der Gruppe; eingeklappt als Vorgabe. So bleibt die Liste bei 23 Portalen × 100 Themen lesbar.
- Aktionen je Zeile: Öffnen · Jetzt prüfen … · Pausieren / Fortsetzen · Kategorie ändern … · Archivieren …
- **Sammelaktionen** (erscheinen als Leiste über der Tabelle bei Auswahl, „12 ausgewählt“): Aktivieren … (Dialog §4.5) · Gliederungen sperren (nur `outline_pending`) · Pausieren · Fortsetzen · Kategorie ändern … · Jetzt prüfen … · Archivieren …
- Kostenwirksam und daher mit Kostenzeile im Bestätigungsdialog: *Aktivieren*, *Jetzt prüfen* („12 Prüfungen ≈ 1,70 USD, bei Änderung bis ≈ 7,26 USD“), *Neu schreiben* (nur Detail, §5.3).
- Leer und gut (Filter liefert nichts, weil nichts klemmt, z. B. `lauf=fehlgeschlagen`): „Heute ist kein Lauf fehlgeschlagen.“ + Häkchen. Leer, weil nichts importiert: „Noch keine Themen“ + primär „Themen importieren“.

### 5.2 Themenliste — Mobil (< 1024 px) — Mobilvariante 2 von 3

Kartenliste, keine Tabelle. Karte: Innenabstand 16 px, `radius-content-lg`, Abstand 8 px.

- Zeile 1: Titel 16 px fett, max. 2 Zeilen.
- Zeile 2: TopicStatus-Pille und Laufpille nebeneinander (Umbruch erlaubt), **nie verschmolzen**.
- Zeile 3, 13 px `text-muted`: „Portal · Kategorie · nächste Prüfung in 3 Tagen“.
- Ganze Karte öffnet das Thema. Auswahl über Schaltfläche „Auswählen“ in der Kopfzeile, danach Kontrollkästchen 24 px links an jeder Karte und Aktionsleiste unten (klebend, 64 px, max. zwei Schaltflächen + „Mehr“).
- Sortierung als Auswahlfeld über der Liste („Sortiert nach: nächste Prüfung“).
- Nachladen per „Weitere 50 laden“-Schaltfläche, kein endloses Scrollen.

### 5.3 Thema-Detail

Seite, nicht Slide-over (der Gliederungs-Editor braucht die Breite). URL `/themen/{id}`.

Kopf: Brotkrume „Themen › Förderung › Titel“, `h1` Titel, darunter Pillen TopicStatus + letzter Lauf, Metazeile „Sanitärfinder · angelegt aus Liste ‚SHK Herbst 2026‘ · Prüfabstand 7 Tage“. Rechts Aktionen: „Im Portal ansehen“ (sekundär, nur wenn veröffentlicht) · ⋯ (Jetzt prüfen …, Neu schreiben …, Pausieren, Archivieren …).

Reiter: **Gliederung** · **Versionen** · **Läufe**.

*Neu schreiben …* (force_rewrite): Dialog mit Kostenzeile „≈ 0,78 USD je Portal“ und dem Hinweis „Alle Abschnitte werden neu geschrieben. Überschriften und Sprungziele bleiben.“

### 5.4 Gliederungs-Editor (Reiter Gliederung)

Zwei Zustände: **gesperrt** (Normalfall) und **in Bearbeitung**.

**Gesperrt**

- Band oben, neutral (`surface-sunken`, Text `text-base`), mit Schloss-Symbol: „Gesperrt am 21.09.2026 von Enes. Überschriften und Sprungziele sind fest.“ Rechts Schaltfläche „Entsperren …“ (sekundär).
- Liste der Überschriften, lesbar: H2 15 px fett, H3 eingerückt 24 px, 15 px normal; rechts jeweils das Sprungziel in 12 px Monospace `text-muted`: `#foerderung-beantragen`. Alles in der Tabulatorfolge (Überschriften als `<li tabindex="-1">` nicht nötig — Liste ist reiner Text, die Schaltfläche „Entsperren“ ist das einzige Bedienelement).

**Entsperren …** — Dialog: „Beim Entsperren bleiben alle Sprungziele erhalten. Abschnitte, deren Überschrift Sie ändern oder neu anlegen, werden beim nächsten Lauf neu geschrieben — ≈ 0,61 USD je betroffenem Portal.“ Schaltflächen „Abbrechen“ · „Entsperren“.

**In Bearbeitung** (auch Zustand `outline_pending` mit Modellvorschlag)

Zeile je Überschrift, 48 px hoch, auf `surface-card`, Trennlinie `line-soft`:

| Teil | Maß | Verhalten |
|---|---|---|
| Ziehgriff | 24 × 48 px, Symbol ⋮⋮ `text-muted` | Maus/Touch: ziehen. Zeigt beim Ziehen eine 2-px-Einfügelinie `content-600`. |
| Ebene | Marke „H2“/„H3“ 20 px, `.content-origin-pill` | H3 eingerückt um 24 px |
| Text | Eingabefeld, volle Restbreite, max. 70 Zeichen, Zähler ab 60 | Enter speichert die Zeile, Esc verwirft |
| Sprungziel | 12 px Monospace | nur bei bereits gesperrten Überschriften; neue zeigen „neu“ |
| Aktionen | 4 Symbolschaltflächen je 36 × 36 px im 44-px-Ziel: ↑ ↓ ← → | „nach oben“, „nach unten“, „ausrücken (H2)“, „einrücken (H3)“, jeweils mit `aria-label`, deaktiviert wenn nicht möglich |
| Löschen | Symbolschaltfläche im ⋯-Menü | mit Rückfrage, siehe unten |

- **Tastatur (Pflicht, WCAG 2.1 AA):** Fokus auf der Zeile → `Alt+↑`/`Alt+↓` verschieben, `Alt+→` einrücken, `Alt+←` ausrücken. Jede Verschiebung wird über eine höfliche Live-Region angesagt: „‚Antrag stellen‘ ist jetzt Überschrift 3 von 7, Ebene H3.“ Ziehen ist Zugabe, nicht der einzige Weg.
- Unter der Liste: „+ Überschrift H2“ und „+ Überschrift H3“ (Textschaltflächen).
- **Regeln, inline geprüft** (Hinweis rechts neben der Zeile, `status-failed-fg`, Sperren deaktiviert solange verletzt): erste Überschrift ist H2 · 3–8 H2 · höchstens 4 H3 je H2 · keine leere Überschrift · keine doppelte Überschrift.
- **Umbenennen behält das Sprungziel** (Leitbild §6). Neben einer umbenannten Überschrift steht 12 px `text-muted`: „Sprungziel bleibt #foerderung-beantragen“.
- **Löschen** einer gesperrten Überschrift fragt: „Das Sprungziel #kosten entfällt. Verweise darauf landen danach am Artikelanfang. Der Abschnitt wird aus dem Artikel entfernt.“
- Fußleiste klebend: links „Änderungen verwerfen“ (Textverweis), rechts Kostenzeile „3 Abschnitte werden neu geschrieben · ≈ 1,83 USD in 3 Portalen“ und **„Gliederung sperren“** (primär).
- Vorschlag des Modells (`outline_pending`): Band `status-review-bg` „Vorschlag des Systems vom 21.09. — prüfen, anpassen und sperren.“

**Sammelfreigabe** (aus der Themenliste mit `thema=outline_pending`): Liste gruppiert nach Kategorie; jede Zeile aufklappbar (`<details>`-Verhalten, Tastatur Enter/Leertaste) zeigt H2/H3 lesend; Kontrollkästchen je Thema und je Kategorie („alle 8 in Förderung“); Sammelaktion „Gliederungen sperren (14)“. Keine Kosten (die Ersterstellung ist mit der Aktivierung bereits bestätigt) — der Dialog sagt das ausdrücklich.

### 5.5 Reiter Versionen und Läufe

- **Versionen:** Liste, neueste oben: Datum, Art („Neuanlage“, „Aktualisierung: 2 Abschnitte“, „Zurückgenommen“), Changelog-Satz, Freigabe durch („automatisch“ / Name). Aktion je Zeile „Vergleichen“ (öffnet den Diff §8.3 zwischen zwei Versionen) und „Diese Fassung wiederherstellen …“.
  - Wiederherstellen-Dialog: Kurzfassung der Unterschiede („2 Abschnitte, 1 FAQ“), Changelog-Vorschau in Leserform „Angabe zu Förderhöchstbetrag zurückgenommen“ (editierbar, 1 Satz, max. 160 Zeichen), Hinweis „Keine Kosten. Aktualisiert-Datum und Sitemap werden gesetzt.“ Primär „Fassung wiederherstellen“.
- **Läufe:** Tabelle je Lauf: Datum/Uhrzeit, Art (RunMode), Ergebnis (Pille §2.2), Dauer, Kosten, Suchen. Zeile aufklappbar: Zeitstempel je Schritt als senkrechte Zeitleiste, Fehlerdiagnose (hier dürfen Enum-Werte stehen, in Monospace).

### 5.6 Themen › Altartikel — Überschneidungen entscheiden (#24)

Quelle: offene Alarme `guide_alerts` mit `key = legacy_overlap` (geschrieben von `guide:legacy:overlaps`, #19). Jeder Alarm ist ein Paar *(Thema, Altartikel)* in einem Portal. Handlungen rufen **ausschließlich** `App\Guide\Legacy\LegacyOverlapResolver` auf; der Resolver erledigt den Alarm selbst.

**Warum ein Reiter unter *Themen* und nicht im Tageslauf-Monitor:** Die Entscheidung betrifft Adresse und Bestand eines Themas, nicht den Zustand des Tages. *Heute* beantwortet „Ist der Tag in Ordnung?“ (§3) und zeigt höchstens drei Störungsbänder — dutzende Paare nach einem Import würden ihn fluten. *Heute* bekommt deshalb nur die Hinweiszeile aus §3.1 Punkt 5; entschieden wird hier.

#### 5.6.1 Rechte

- Sichtbar und bedienbar für **jede** `GuideRole` (Inhaber und Redaktion — beide pflegen Themen) sowie Administratoren ohne Rolle (gelten als Inhaber). Ohne Rolle: Reiter nicht in der Reiterleiste, direkter Aufruf → 403.
- Die Prüfung sitzt in der Aktion selbst (serverseitig, bei jedem Klick), nicht nur in der Sichtbarkeit des Knopfs.

#### 5.6.2 Aufbau (Desktop ≥ 1024 px)

- Filterbalken §1.3; wirksam ist nur **Portal**. Kategorie und Themenstatus filtern über das Thema, Laufergebnis ist deaktiviert mit Tooltip „Bei Altartikeln ohne Wirkung“.
- Einleitung, ein Satz, 15 px `text-base`: „Diese Themen behandeln dieselbe Frage wie ein bereits veröffentlichter Beitrag. Entscheiden Sie je Paar, damit es nicht zwei Seiten zum selben Thema gibt.“
- **Gruppiert nach Thema** (Filament-Tabellengruppe, aufgeklappt als Vorgabe). Grund: ein Thema hat oft mehrere Altartikel-Kandidaten, und nach „Adresse übernehmen“ für einen werden die übrigen zu Weiterleitungs-Kandidaten (§5.6.5). Gruppenkopf: Themenfrage (15 px fett, Verweis aufs Thema-Detail §5.3) · Portalname 12 px `text-muted` · rechts der **Themenzustand** als Satz, 14 px `text-base`:
  - „Noch kein veröffentlichter Artikel“ oder
  - „Veröffentlicht unter /ratgeber/…/…“ (Pfad in 13 px Monospace, Verweis, neues Fenster).
- Zeile je Altartikel, Höhe 56 px:

| Spalte | Breite | Inhalt |
|---|---|---|
| Altartikel | 44 % | `post_title` 15 px, max. 2 Zeilen; darunter `post_url` 12 px Monospace `text-muted` als Verweis (neues Fenster, Symbol `heroicon-m-arrow-top-right-on-square` 16 px, `aria-label="Altartikel ‚…‘ in neuem Fenster öffnen"`) |
| Ähnlichkeit | 12 % | „87 %“ tabellar rechtsbündig, darunter 12 px `text-muted` „Titel“; unter 70 % zusätzlich „schwach“ in `text-base` (kein Warnton — keine Störung, nur Lesehilfe) |
| Gemeldet | 12 % | Datum `last_seen_at` `dd.mm.yyyy`; bei `occurrences > 1` darunter 12 px `text-muted` „3 × gemeldet“ |
| Entscheidung | 32 % | genau **eine** primäre Aktion nach §5.6.3 + „Keine Überschneidung“ als Textschaltfläche, rechtsbündig |

- Sortierung Vorgabe: Gruppen nach höchster Ähnlichkeit absteigend, Zeilen darin ebenso. Spalte Ähnlichkeit und Gemeldet sortierbar.
- **Keine Sammelaktionen** für Übernehmen und Weiterleiten: jede Entscheidung verändert eine öffentliche Adresse und muss am einzelnen Paar geprüft werden. Einzige Sammelaktion: „Als keine Überschneidung markieren (n)“ mit Rückfrage.

#### 5.6.3 Welche Aktion erscheint — immer am **aktuellen** Zustand

`context_json.can_adopt_slug` / `can_redirect` sind ein Schnappschuss vom Meldezeitpunkt und dürfen **nicht** über die Knöpfe entscheiden. Maßgeblich ist beim Rendern der Zeile `LegacyOverlapResolver::publishedArticle($topic)` im Tenant des Alarms (bzw. dessen gebündelte Abfrage je Seite).

| Zustand jetzt | Primäre Aktion (Filament-Schaltfläche, Größe `sm`, Farbe `content`) | Zweite Aktion |
|---|---|---|
| Thema **ohne** veröffentlichten Artikel | **„Adresse übernehmen …“** | „Keine Überschneidung“ |
| Thema **mit** veröffentlichtem Artikel | **„Weiterleiten (301) …“** | „Keine Überschneidung“ |
| Altartikel nicht mehr veröffentlicht oder schon einem Thema zugeordnet | keine; statt Knopf ein Satz 14 px `text-base`: „Überholt — der Beitrag ist nicht mehr veröffentlicht.“ bzw. „Überholt — gehört inzwischen zum Thema ‚…‘.“ | „Erledigen“ (wie „Keine Überschneidung“, ohne Rückfrage) |

- Die jeweils nicht passende Aktion wird **ausgeblendet, nicht deaktiviert**: die beiden schließen sich fachlich aus, ein grauer zweiter Knopf lädt nur zum Rätseln ein. Warum die eine Aktion angeboten wird, sagt der Gruppenkopf (§5.6.2).
- Nie beide primären Aktionen gleichzeitig.

#### 5.6.4 Bestätigungsdialoge (Filament-Modal, Breite `lg`)

Beide Handlungen verändern öffentliche Adressen und sind im Panel nicht rückgängig zu machen → Bestätigung Pflicht, Fokus beim Öffnen auf „Abbrechen“.

**„Adresse übernehmen …“** — Überschrift „Adresse des Altartikels übernehmen?“

- Vorher/Nachher als zweizeilige Liste (13 px Monospace für Pfade):
  „Der Beitrag *‚Titel‘* unter /ratgeber/… wird zum Artikel des Themas *‚Frage‘*.“
- Folgen, drei Punkte, 15 px `text-base`:
  1. „Adresse und Erstveröffentlichungsdatum bleiben — Suchmaschinen-Rang und Verweise gehen nicht verloren.“
  2. „Beim nächsten Lauf schreibt das System den Inhalt nach der Gliederung des Themas neu. Der bisherige Text wird dabei ersetzt.“
  3. Nur wenn das Thema schon einen unveröffentlichten Entwurf hat: „Der bisherige Entwurf des Themas wird vom Thema gelöst.“
- Hinweis, 14 px `text-muted`: „Andere Altartikel zu diesem Thema können Sie danach per 301 hierher weiterleiten.“
- Schaltflächen: „Abbrechen“ · **„Adresse übernehmen“** (primär `content`).

**„Weiterleiten (301) …“** — Überschrift „Altartikel dauerhaft weiterleiten?“

- „/alt/pfad → /ratgeber/kategorie/thema“ als eine Zeile, 13 px Monospace, Pfeil als Text „→“ mit `aria-label="leitet weiter auf"`.
- Folgen: „Der Altartikel wird archiviert und ist nicht mehr erreichbar. Wer seine Adresse aufruft, landet dauerhaft (301) auf dem Artikel des Themas.“
- Schaltflächen: „Abbrechen“ · **„Weiterleiten“** (Farbe `status-failed` — archiviert eine öffentliche Seite; keine generische `danger`-Rolle, §2).

**„Keine Überschneidung“** — kurze Rückfrage, keine Folgenliste: „Das Paar verschwindet aus der Liste und wird bei künftigen Abgleichen nicht erneut gemeldet. Beide Seiten bleiben unverändert.“ Schaltflächen „Abbrechen“ · „Bestätigen“ (sekundär/grau).

#### 5.6.5 Rückmeldung und Fehler

| Ausgang | Darstellung |
|---|---|
| Erfolg Übernehmen | Filament-Benachrichtigung `success`: „Adresse übernommen. ‚Frage‘ erscheint künftig unter /ratgeber/….“ Zeile verschwindet; Geschwisterzeilen derselben Gruppe rendern neu und zeigen jetzt „Weiterleiten (301) …“ (Gruppenkopf wechselt auf „Veröffentlicht unter …“). |
| Erfolg Weiterleiten | `success`: „Weitergeleitet: /alt → /neu.“ Zeile verschwindet. |
| Erfolg Keine Überschneidung | `success`: „Als keine Überschneidung vermerkt.“ |
| `LogicException` des Resolvers | Benachrichtigung `danger`, Titel „Nicht möglich“, Text = **Meldung der Exception unverändert** (sie ist bereits deutsch und nennt den Ausweg). Dialog schließt, Zeile rendert neu und zeigt danach die jetzt passende Aktion bzw. „Überholt“. Kein Alarmstatus ändern. |
| Tenant-Datenbank nicht erreichbar | `danger`: „Portal ‚…‘ ist gerade nicht erreichbar. Bitte später erneut versuchen.“ Zeile bleibt. |
| Doppelklick | Aktionsknopf während der Anfrage deaktiviert mit Ladezeichen (`wire:loading`), zweite Anfrage findet den Alarm erledigt und meldet nichts Doppeltes. |

Nach jeder Entscheidung sinkt die Zählmarke des Reiters sofort.

#### 5.6.6 „Keine Überschneidung“ muss dauerhaft wirken — Bedingung an die Umsetzung

Heute öffnet `GuideAlert::raise()` einen erledigten Alarm mit gleichem `dedupe_key` wieder. Ein „Ignorieren“, das beim nächsten `guide:legacy:overlaps` zurückkommt, wäre ein folgenloser Knopf (Grundsatz *kein folgenloses Feld*). Deshalb:

- „Keine Überschneidung“ setzt `status = resolved`, `resolved_at` und `context_json.decision = "dismissed"` (gleiche Stelle wie der Resolver seine Entscheidungen `adopt_slug` / `redirect` ablegt). Empfehlung: als dritte Methode `dismiss()` in `LegacyOverlapResolver`, damit alle drei Entscheidungen an einer Stelle liegen.
- `guide:legacy:overlaps` meldet ein Paar mit `decision = dismissed` **nicht erneut**.
- Wer sich geirrt hat: kein Rückgängig im Panel in dieser Ausbaustufe; das Paar lässt sich per Befehl erneut melden (Option dafür ist Sache der Umsetzung). Die Rückfrage in §5.6.4 sagt deshalb klar „wird nicht erneut gemeldet“.

#### 5.6.7 Zählmarke, Hinweis auf *Heute*, Zustände

- Reiter „Altartikel“ trägt die Zahl offener `legacy_overlap`-Alarme im aktuellen Portalfilter (Filament-Badge, Farbe `status-review`). Bei 0 bleibt der Reiter stehen (kein Springen der Reiterleiste), ohne Marke.
- *Heute*, Zeile „Altartikel“ (§3.1): zählt **Themen** mit mindestens einem offenen Paar, nicht Paare. Nur Inhaber und Redaktion sehen sie.
- Leer und gut: Häkchen `status-published-dot` + „Keine offenen Überschneidungen mit Altartikeln.“ darunter 14 px `text-muted`: „Neue Überschneidungen erscheinen hier nach dem nächsten Abgleich.“ (Keine Befehlsnamen in der Oberfläche, §1.4.)
- Lädt: `content-skeleton`, 3 Gruppen à 2 Zeilen.

#### 5.6.8 Mobil (< 1024 px)

Karten statt Tabelle, gruppiert nach Thema (Gruppenkopf wie Desktop, Themenzustand als eigene Zeile darunter). Karte: Innenabstand 16 px, Titel 16 px fett, Pfad 12 px Monospace mit Umbruch an `/` (`overflow-wrap: anywhere`), Zeile „87 % Titelähnlichkeit · 21.09.2026“, darunter primäre Aktion **volle Breite**, 44 px hoch, und „Keine Überschneidung“ als Textschaltfläche darunter (44 px Zielhöhe). Dialoge als Blatt von unten (max. 85 vh), Pfade umbrechend.

#### 5.6.9 Barrierefreiheit

- Aktionsbeschriftungen tragen den Kontext für Screenreader: `aria-label="Adresse von ‚Titel‘ für Thema ‚Frage‘ übernehmen"`; sichtbar bleibt der Kurztext.
- Gruppenköpfe als echte Überschriften (`h3`), damit die Liste per Überschriftensprung navigierbar ist.
- Nach Verschwinden einer Zeile Fokus auf die nächste Zeile der Gruppe, sonst auf den nächsten Gruppenkopf, sonst auf die Leer-Meldung — nie auf `body`.
- Benachrichtigungen landen in der höflichen Live-Region von Filament; Fehlertexte werden nicht nur farblich unterschieden (Titel „Nicht möglich“).

---

### 5.7 Nicht erreichbare Quellen (#26)

Ziel: Eine kaputte Quelle wird **ohne Zutun** der Redaktion ersetzt. Das Dashboard zeigt den Zustand, verlangt aber keine Handlung, solange die Automatik arbeitet. Handlungsbedarf entsteht erst, wenn die Recherche keinen Ersatz findet.

#### 5.7.1 Verhalten des Tageslaufs (Vorgabe an Recherche/Fälligkeit, nicht sichtbar, aber Voraussetzung für alles Folgende)

1. **Fälligkeit:** Belegt eine Quelle mit `broken_at` einen aktuellen Fakt des Themas, ist das Thema beim nächsten Tageslauf fällig — unabhängig vom Prüfabstand — und geht **direkt in die Tiefenrecherche**. Die Freshness-Probe wird übersprungen: Sie kann „unverändert“ melden, dann gäbe es nie eine Tiefenrecherche und die Quelle würde nie ersetzt. Kosten: eine Tiefenrecherche statt Probe + Tiefenrecherche; in der Kostenvorschau von *Heute* zählt das Thema als „Tiefenrecherche“.
2. **Auftrag an die Recherche:** Der Prompt `guide.deep_research` bekommt einen eigenen Block „Zu ersetzende Belege“ — je betroffenem Fakt `key`, bisheriger Wert und die kaputte URL — mit der Anweisung: *„Diese Seiten sind nicht mehr erreichbar. Belege die genannten Fakten mit einer anderen, erreichbaren Quelle neu. Verwende die genannten URLs nicht. Findest du keinen Beleg, nenne den Fakt in open_points.“* Ist die Liste leer, steht dort „keine“ (Prompt bleibt unverändert lesbar). Neue Vorlagenversion mit Changelog-Vermerk, Pflege im Prompt-Editor §9.3.
3. **Die kaputte URL ist als Beleg gesperrt:** Liefert die Recherche dennoch dieselbe URL (Vergleich nach der bestehenden URL-Normalisierung, also auch mit/ohne `www`, Schrägstrich am Ende, Tracking-Parameter), wird der Kandidat verworfen und im Laufprotokoll als „abgelehnt: Quelle nicht erreichbar“ vermerkt — gleiche Bauform wie Blacklist-Ablehnungen. Eine *andere* Seite derselben Domain ist erlaubt.
4. **Ersatz gefunden:** Der Fakt wird mit der neuen Quelle als aktueller Fakt gespeichert. Bleibt der Wert gleich, ist das **keine inhaltliche Änderung** (nur „Geprüft am“, kein Changelog, kein `lastmod`, Datumsvertrag Leitbild §5). Ändert sich der Wert, gilt der normale Weg mit Changelog. Danach hängt kein aktueller Fakt mehr an der kaputten Quelle, die Sperre im Gate entfällt, der Lauf kann automatisch freigegeben werden.
5. **Kein Ersatz gefunden:** Der bisherige Fakt bleibt aktuell (ein Wert wird nicht gelöscht, nur weil seine Quelle offline ist), der Lauf geht wie heute in die Prüfung, Anlass siehe §5.7.3.

#### 5.7.2 Themenliste (§5.1)

- Filter im Filterbalken §1.3: **„Quelle nicht erreichbar“** (Ja/Nein). Kein neues Spaltenelement in der Tabelle — die Liste hat bereits neun Spalten.
- In der Spalte *Thema* unter der Kategorie, nur wenn zutreffend: 12 px `status-review-fg` mit Symbol `heroicon-m-link-slash` 14 px: „1 Quelle nicht erreichbar“. Das Symbol ist `aria-hidden`, der Text trägt die Aussage.
- Auf *Heute* §3.2 **keine** eigene Kachel. Themen, die heute wegen einer nicht erreichbaren Quelle laufen, erscheinen in der Fortschrittszeile mit dem Grund „Quelle ersetzen“. Gemeint ist die Fortschrittszeile unter der Laufpille (`.content-status-progress`, §2.2) in der Spalte *Heute* dieser Liste und im Thema-Detail — *Heute* selbst hat keine Themenzeilen, seine Zähler verweisen hierher. Bauvorgabe §5.7.5 C.

#### 5.7.3 Thema-Detail (§5.3) und Prüfblatt (§8.2)

- **Thema-Detail:** Unter der Metazeile ein Band in Bauform `review`, nur solange `broken_at` gesetzt ist und die Quelle noch einen aktuellen Fakt belegt:
  „1 Quelle ist nicht erreichbar (404 seit 18.09.). Die nächste Tiefenrecherche sucht Ersatz — am 22.09. im Tageslauf.“ Ohne Schaltfläche; „Jetzt prüfen …“ im ⋯-Menü genügt (mit Kostenzeile wie §5.1). Mehrere Quellen: „3 Quellen sind nicht erreichbar.“ + `<details>` mit Liste Herausgeber · URL (als Text, nicht als Verweis) · Statuscode · seit.
- **Prüfblatt, Anlass:** Klartext aus dem Gate, z. B. „Das Qualitätsgate hat nicht freigegeben: Die Quelle ‚KfW – Merkblatt 262‘ ist nicht erreichbar (404), und die Recherche hat keinen Ersatz gefunden. Betroffen: Förderhöchstbetrag.“ Keine Regelnummer, kein HTTP-Fachjargon außer dem Statuscode in Klammern.
- **Prüfblatt, Quellen zu diesem Abschnitt:** Kaputte Quelle mit Marke **„nicht erreichbar“** (Pille `status-review`, Symbol `heroicon-m-link-slash`), URL als Text, nicht verlinkt; darunter 13 px `text-muted` „Statuscode 404 · geprüft am 21.09., 03:14“. Wurde im Lauf ersetzt: die neue Quelle trägt die bestehende Marke „neu in diesem Lauf“ und die Zeile „ersetzt KfW – Merkblatt 262 (nicht erreichbar)“; die alte erscheint nicht mehr.
- **Handlung bei fehlendem Ersatz** — zwei Wege, beide vorhanden, keine neue Aktion: „Zurück zum Schreiben …“ mit Freitext (z. B. Ersatzquelle nennen), oder „Freigeben“. Freigeben ist **erlaubt**; der Leser sieht die Quelle dann nach Frontend §4.3a unverlinkt. Bestätigungsdialog nur in diesem Fall: „Die Quelle bleibt unverlinkt stehen, bis eine Recherche Ersatz findet.“
- Mobil (§8.5): Marke und Statuszeile wandern mit in die `<details>` „Quellen (n)“ des Abschnitts, der Zähler bleibt die Anzahl sichtbarer Quellen.

#### 5.7.4 Wortlaut

„nicht erreichbar“ — nie „kaputt“, „broken“, „tot“ oder „Fehler 404“ allein. Statuscode nur als Zusatz in Klammern oder in der Metazeile.

#### 5.7.5 Bauvorgabe Prüfblatt und Fortschrittszeile (#29)

Setzt §5.7.2 (letzter Punkt) und §5.7.3 (Prüfblatt) in Bauteile um. Alle Texte kommen aus `App\Guide\Support\UnreachableSources`, Marke und Statuszeile aus dem Partial `content.guide.partials.unreachable-source` — **keine eigenen Formulierungen im View**. Teil A und B entstehen mit dem Prüfblatt aus #16, Teil C ist sofort umsetzbar.

**A — Prüfblatt §8.2**

1. **Anlass (Punkt 1).** Gibt `forTopic($topic)` Zeilen, ist das Anlass-Band `reviewReason($rows)`. Hat das Gate *zusätzlich* andere Gründe, steht der Quellen-Satz **zuerst**, die übrigen Gründe folgen als zweiter Satz im selben Band — kein zweites Band. Bauform bleibt `review`, auch wenn nur die Quelle der Grund ist.
2. **Quellen zu diesem Abschnitt (Punkt 4, rechte Spalte).** Welche Quellen ein Abschnitt zeigt, bestimmen wie bisher die Fakten des Abschnitts. Je Quelle gilt genau einer von drei Fällen:

   | Fall | Erkennung | Darstellung |
   |---|---|---|
   | Nicht erreichbar, kein Ersatz | `id` steht in `forTopic()` | Herausgeber, Titel wie üblich; **URL als Text** (`text-muted`, `break-all`, kein `<a>`, kein Symbol für externe Verweise); darunter Partial mit `$code` und `$checkedAt`. Bewertung und belegter Satz bleiben stehen. Diese Quelle steht im Abschnitt **oben**, damit die Pille nicht erst nach dem Scrollen auftaucht. |
   | Im Lauf ersetzt, neue Quelle | Laufdaten der Recherche: Eintrag in `replace_sources` (Fakt-`key` + alte URL), und der aktuelle Fakt mit diesem `key` hängt jetzt an einer anderen URL. Deckt beide Fälle ab: Wert gleich (`source_replaced`) und Wert geändert (`changed`). | Normale Quellenzeile mit Verweis, bestehende Marke „neu in diesem Lauf“ (`status-scheduled`); direkt unter dem Titel 13 px `text-muted`: „ersetzt ‚<Titel oder Herausgeber der alten Quelle>‘ (nicht erreichbar)“. Label-Regel wie in `forTopic()`: Titel, sonst Herausgeber, sonst URL. |
   | Im Lauf ersetzt, alte Quelle | alte URL aus demselben `replace_sources`-Eintrag | **erscheint nicht.** Belegt die alte Quelle noch einen *anderen* aktuellen Fakt dieses Abschnitts, fällt sie in Fall 1 und bleibt stehen. |

   Ersetzt eine neue Quelle mehrere alte, stehen die Zeilen „ersetzt …“ untereinander (höchstens drei, dann „und 2 weitere“). Die Zuordnung läuft ausschließlich über den Fakt-`key` — nie über Domain- oder Titelähnlichkeit.
3. **Freigeben (Punkt 6).** Nur wenn `forTopic()` nicht leer ist, öffnet „Freigeben“ (auch per `F`) einen Bestätigungsdialog; sonst gibt es keinen Dialog, der Ablauf bleibt unverändert.
   - Filament-Bestätigung, Überschrift „Trotzdem freigeben?“, Text `approveConfirmation()`, Schaltflächen „Freigeben“ (primär, `content-600`) und „Abbrechen“.
   - Fokus beim Öffnen auf „Abbrechen“ (Schutz gegen doppeltes `F`), `Esc` schließt, Fokus zurück auf „Freigeben“ in der Aktionsleiste.
   - Keine Farbe `failed`, kein Warnsymbol: Das ist eine zulässige Entscheidung, keine Gefahr.
   - Nach Bestätigung: Erfolgsmeldung und Sprung zum nächsten Eintrag wie in §8.2.
   - „Zurück zum Schreiben …“ bleibt unverändert; der Platzhalter im Freitextfeld lautet in diesem Fall „z. B. Ersatzquelle nennen“.

**B — Prüfblatt mobil §8.5**

- Marke und Statuszeile stehen *in* der `<details>` „Quellen (n)“ des Abschnitts, an derselben Stelle wie auf dem Desktop. Nichts davon im `<summary>`.
- `n` = Anzahl der angezeigten Quellen: Die nicht erreichbare Quelle zählt mit, die ersetzte alte nicht.
- Enthält der Abschnitt eine nicht erreichbare Quelle, ist die `<details>` **offen**, alle anderen bleiben geschlossen. So ist der Anlass ohne Suchen belegt.
- Die URL als Text bricht um (`break-all`), kein waagerechtes Scrollen bei 320 px.

**C — Fortschrittszeile „Quelle ersetzen“ (§2.2, Themenliste und Thema-Detail)**

- Bedingung: Laufanzeige `wartet` oder `in-arbeit` **und** das Thema hat mindestens eine Quelle in `countsByTopic()`. Nur dann wird an die bestehende Fortschrittszeile „ · Quelle ersetzen“ angehängt:
  - `wartet`: „angelegt 21.09., 02:00 · Quelle ersetzen“
  - `in-arbeit`: „Recherchiert · Quelle ersetzen · seit 02:14“ (der Grund steht vor der Zeit, damit er bei Kürzung sichtbar bleibt)
- Kein Symbol, keine eigene Farbe: Die Zeile bleibt `.content-status-progress`. Die Pille bleibt die Laufpille. Der Hinweis „1 Quelle nicht erreichbar“ in der Spalte *Thema* (§5.7.2) zeigt den Zustand bereits.
- Nach dem Lauf entfällt der Grund. Ersatz gefunden → normale Anzeige nach §2.2. Kein Ersatz → `pruefung` mit Anlass aus A.1.
- Keine neue Zählgröße in *Heute*: Die Themen zählen wie jeder Lauf unter „In Arbeit“ bzw. „Wartet“/„Offen“.

**Abnahme (#29)**

1. Thema mit nicht erreichbarer Quelle ohne Ersatz im Prüfblatt: Anlass-Band mit `reviewReason()`, Quelle mit Pille, URL nicht anklickbar, Zeile „Statuscode 404 · geprüft am …“.
2. Thema, dessen Quelle im Lauf ersetzt wurde (Wert gleich *und* Wert geändert): neue Quelle mit „neu in diesem Lauf“ + „ersetzt ‚…‘ (nicht erreichbar)“, alte fehlt.
3. „Freigeben“ mit leerem `forTopic()` → kein Dialog; mit Einträgen → Dialog, Fokus auf „Abbrechen“, `Esc` schließt.
4. Mobil 360 px: Quelle mit Marke in der geöffneten `<details>`, Zähler ohne ersetzte alte Quelle, kein waagerechtes Scrollen.
5. Themenliste während des Laufs: „Recherchiert · Quelle ersetzen · seit …“ nur bei Themen mit nicht erreichbarer Quelle.
6. Volltextsuche in den geänderten Views: kein „kaputt“, „broken“ oder „Fehler 404“ in sichtbarem Text.

## 6. Themen › Kategorien

Tabelle, gefiltert über den Portalfilter. Bei „Alle Portale“ eine Zeile je Kategorie-Slug, Spalte „Portale“ zeigt „3 / 3“.

| Spalte | Inhalt |
|---|---|
| Kategorie | Name 15 px, darunter Slug 12 px Monospace mit Schloss-Symbol, sobald veröffentlicht („Adresse fest seit 21.09.“) |
| Themen | „12 aktiv · 2 Entwurf“ |
| Eigene Seite | „ja“ oder „nein — weniger als 3 aktive Themen“ (`text-base`, kein Warnton; Regel aus #18) |
| Jüngste Aktualisierung | Datum |
| Aktionen | Umbenennen … · Zusammenlegen … · Löschen (nur ohne Themen, sonst deaktiviert mit Grund) |

- Band über der Tabelle nach denselben Regeln wie im Import (§4.3): ab 15 Kategorien, Kategorien mit 1–2 Themen, ähnliche Namen.
- **Umbenennen …**: Name ändert sich, Slug nicht (Hinweis im Dialog: „Die Adresse /ratgeber/foerderung bleibt.“). Wirkt auf alle Portale der Auswahl; betroffene Portale werden im Dialog aufgezählt.
- **Zusammenlegen …**: Zielkategorie wählen; Dialog nennt die Folgen: „14 Themen wandern nach ‚Förderung‘. /ratgeber/zuschuesse leitet dauerhaft (301) auf /ratgeber/foerderung um. Die Artikeladressen ändern sich mit — ihre alten Adressen leiten ebenfalls um.“ (Folge der URL-Struktur `/ratgeber/{kategorie}/{thema}` im Freeze §5.)
- Reihenfolge der Kategorien im Frontend: Spalte „Position“ per Ziehgriff **und** ↑/↓-Schaltflächen, wie im Gliederungs-Editor.
- Keine Unterkategorien, kein Feld dafür (Leitbild §7).

---

## 7. Verlauf

### 7.1 Reiter Läufe

Tabelle aller Läufe im Zeitraum (Filter *Zeitraum* aktiv), 50 je Seite: Zeit, Portal, Thema, Art, Ergebnis, Dauer, Kosten. Oberhalb eine Zeile je Tag (letzte 14 Tage) als Kleinbalken nach §3.2 (8 px), anklickbar → setzt den Zeitraum auf den Tag.

### 7.2 Reiter Versionen

Portal- und themenübergreifende Liste aller Versionen mit Changelog-Satz. Dient der Frage „Was hat sich diese Woche auf den Portalen geändert?“. Aktionen wie §5.5.

### 7.3 Reiter Kosten

- Kennzahlenreihe (4 Kacheln): *Heute* · *Dieser Monat* („412 USD von 1.800 USD“) · *Prognose Monatsende* (lineare Hochrechnung, als solche beschriftet) · *⌀ je Prüfung* („0,13 USD, Modell 0,14 USD“).
- Säulendiagramm je Tag (30 Tage), gestapelt nach Art: Neuanlage (`run-new-fill` mit Schraffur) · Aktualisierung (`run-updated-fill`) · Prüfung ohne Änderung (`run-unchanged-fill`). Waagerechte Linie Tagesbudget 60 USD in `status-failed-fg`, 1 px gestrichelt, beschriftet. Legende und Tabelle darunter sind Pflicht (Werte je Tag als `<table>`, per „Als Tabelle zeigen“ aufklappbar) — das Diagramm ist nie die einzige Quelle.
- Tabelle je Portal: Kosten Monat, Zahl der Läufe je Art, Anteil am Budget, Suchen (Web-Search-Anzahl). Sortierung nach Kosten absteigend.
- Alle Beträge aus `llm_usage_logs` mit Präfix `guide.`; Datenstand `content-asof`.

---

## 8. Prüfung (Desktop + Mobil)

Der einzige Screen, der Arbeit einfordert. Ziel: **unter 90 Sekunden je Entscheidung, vollständig per Tastatur.**

### 8.1 Layout (≥ 1280 px)

Zweispaltig ohne Seitenwechsel:

- **Warteschlange** links, 360 px, eigener Scrollbereich: Einträge 72 px hoch — Thema (2 Zeilen), darunter 12 px „Portal · Aktualisierung · 2 Abschnitte“, rechts Wartezeit („seit 5 Std.“, ab 24 h in `status-review-fg`). Aktiver Eintrag: linke Kante 3 px `content-600`, Fläche `content-50`. Sortierung: älteste zuerst. Anlass „Gliederung sperren“ springt direkt in den Editor §5.4 statt in den Diff.
- **Prüfblatt** rechts, Restbreite.

1024–1279 px: Warteschlange klappt auf 64 px ein (nur Zähler + Pfeile „vorheriger/nächster“), per Schaltfläche als Überlagerung aufklappbar.

### 8.2 Prüfblatt, von oben

1. **Kopf:** `h2` Thema, Pillen (Laufpille „Zur Prüfung“ mit Symbol `heroicon-o-eye`), Zeile „Sanitärfinder · Aktualisierung · Lauf vom 21.09., 03:12 · 0,58 USD“. **Anlass** als `review`-Band: „Das Qualitätsgate hat nicht freigegeben: Zahl ‚30.000 €‘ ist nur durch eine Quelle belegt.“ — Klartext aus dem Gate (#11), keine Regelnummern.
2. **Stand-Zeile-Vorschau:** die echte Frontend-Komponente (Frontend-Spezifikation §3.1) mit den Daten, die nach Freigabe gelten würden: „Aktualisiert am 21. September 2026 · Was ist neu?“. Damit sieht der Prüfer, ob die Freigabe das Datum bewegt.
3. **Changelog-Vorschlag:** Komponente §3.2 der Frontend-Spezifikation, der neue Eintrag hervorgehoben (linke Kante 3 px `status-review-dot`). Darunter Schaltfläche „Formulierung ändern“ → Eingabefeld, 1 Satz, max. 160 Zeichen. Fehlt ein Eintrag, obwohl ein Fakt geändert wurde, blockiert ein `failed`-Band die Freigabe: „Ohne Changelog-Eintrag darf sich das Aktualisiert-Datum nicht ändern.“
4. **Abschnitte:** nur geänderte Abschnitte offen; unveränderte als eine eingeklappte Zeile „4 Abschnitte unverändert“ (`surface-sunken`, Text `text-base`), aufklappbar. Je geändertem Abschnitt:
   - Kopf: Überschrift (H2/H3), rechts Grund in 13 px: „Fakt geändert: Förderhöchstbetrag 25.000 € → 30.000 €“.
   - Links (8 von 12 Spalten): Diff nach `design/content-dashboard.md` §4.1/§4.1a (Bisher | Neu, Wortmarken `diff-del`/`diff-ins`, neutrale Zeile). Ab < 1440 px volle Breite.
   - Rechts (4 von 12): **Quellen zu diesem Abschnitt** — Komponente *Quellenliste* (Frontend-Spezifikation §3.3) in Panel-Variante: je Quelle Herausgeber, Titel, Datum, Bewertung (Whitelist-Gewicht als „vertrauenswürdig / geprüft / neu“), Marke „neu in diesem Lauf“ (`status-scheduled`), und der **belegte Satz** als Zitat (13 px, linke Kante 2 px `line-strong`). Unter 1440 px wandern die Quellen unter den Diff des Abschnitts.
5. **FAQ und Kurzantwort**, falls geändert, im selben Aufbau.
6. **Aktionsleiste**, klebend unten, 72 px, `surface-card`, obere Linie `line-soft`:
   - **„Freigeben“** (primär, `content-600`), Kurzbefehl `F`
   - „Zurück zum Schreiben …“ (sekundär; `review → writing`), Kurzbefehl `S`: Dialog mit Freitext „Was soll anders werden?“ und Kostenzeile „≈ 0,35 USD“
   - „Verwerfen …“ (sekundär, Text `status-failed-fg`, keine rote Fläche), Kurzbefehl `V`: Pflichtgrund aus Liste + Freitext; Ergebnis „Fehlgeschlagen — verworfen“, der Artikel bleibt in der bisherigen Fassung.
   - rechts: „Überspringen“ (Textverweis, `J`) — nächster Eintrag ohne Entscheidung.

Nach jeder Entscheidung: Erfolgsmeldung („Freigegeben. Aktualisiert am 21.09. gesetzt.“) und automatischer Sprung zum nächsten Eintrag; Fokus liegt danach auf dessen Überschrift.

**Kurzbefehle:** `J`/`K` nächster/vorheriger Eintrag, `F`, `S`, `V` wie oben, `?` zeigt die Übersicht. Einzeltasten wirken nur bei Fokus im Prüfblatt und nie in Eingabefeldern; im Nutzermenü abschaltbar (WCAG 2.1.4).

### 8.3 Diff zwischen zwei Versionen (aus Verlauf/Thema)

Gleiches Prüfblatt ohne Aktionsleiste und ohne Anlass-Band; Kopf „Version vom 12.09. ↔ Version vom 21.09.“ mit zwei Auswahlfeldern.

### 8.4 Zustände

| Zustand | Darstellung |
|---|---|
| Leer und gut | „Nichts zu prüfen. Die letzte Freigabe war heute um 08:12.“ + Häkchen in `status-published-dot`. |
| Lädt | Skelett der Warteschlange (6 Einträge) und des Prüfblatts (Kopf, 3 Abschnittsblöcke). |
| Eintrag inzwischen erledigt (paralleler Prüfer) | `review`-Band „Dieser Lauf wurde bereits von Uwe freigegeben.“ + „Zum nächsten“. |
| Gate-Bericht fehlt | Freigabe bleibt möglich, Band „Kein Prüfbericht vorhanden — bitte Quellen selbst sichten.“ |

### 8.5 Mobil (< 1024 px) — Mobilvariante 3 von 3

- Warteschlange als eigene Seite (Liste wie oben, 72-px-Einträge). Tippen öffnet das Prüfblatt als Vollbild mit Zurück-Pfeil „Warteschlange (7)“.
- Prüfblatt einspaltig: Kopf → Anlass → Stand-Zeile → Changelog → Abschnitte (Diff „Bisher“ über „Neu“ nach §4.1a, Quellen als `<details>` „Quellen (3)“ unter jedem Abschnitt).
- Aktionsleiste unten klebend, 64 px + sichere Zone: „Freigeben“ (primär, 50 % Breite) · „Mehr“ (öffnet Blatt mit Zurück zum Schreiben, Verwerfen, Überspringen). Keine Kurzbefehle.

---

## 9. Einstellungen

Vier Reiter. Werte aus `config/guide.php`/`.env` sind schreibgeschützt und tragen die Herkunfts-Pille „aus der Serverkonfiguration“ (bestehendes Prinzip *kein folgenloses Feld*). Schlüssel und Zugangsdaten erscheinen nirgends, auch nicht maskiert.

### 9.1 Tageslauf

- **Globaler Schalter** oben in eigener Karte: „Tageslauf aktiv“ (Umschalter 44 × 24 px). Ausschalten fragt: „Ab sofort startet kein neuer Lauf. Laufende Läufe werden beendet. Veröffentlichte Artikel bleiben online.“ Zustand erscheint danach in der Kopfzeile von *Heute*.
- Laufzeitfenster: „02:00 bis 06:00“ (Herkunft Konfiguration, schreibgeschützt).
- Prüfabstand: Standard „7 Tage“ + Tabelle je Kategorie mit Auswahl 1 / 3 / 7 / 14 Tage; rechts je Zeile die Kostenfolge „+ 0,31 USD/Tag“ (Leitbild Regel 4). Speichern zeigt die Summe der Mehrkosten.
- Pausen: Liste aller aktiven Pausen (global, Portal, Kategorie, Thema) mit Wer/Wann und „Fortsetzen“.

### 9.2 Budget

Tagesbudget gesamt, je Portal, je Lauf, Warnschwelle, max. Neuanlagen je Portal und Tag — alle schreibgeschützt aus der Konfiguration, jeweils mit Verbrauch heute daneben. Satz darunter: „Änderungen an diesen Werten erfolgen in der Serverkonfiguration.“

### 9.3 Prompts

Bestehender Prompt-Editor (`design/content-dashboard.md` §7b / §7b.1), gefiltert auf Vorlagen mit Präfix `guide.`. Keine Änderung der Bauform.

### 9.4 Quellen

Whitelists je Branche (`guide_source_policies`): Tabelle Domain · Herausgeber · Gewicht (vertrauenswürdig / geprüft / zulässig) · zuletzt verwendet · Aktionen. „Domain hinzufügen“ als Dialog mit Prüfung auf gültige Domain. Gesperrte Domains (Blacklist) im selben Reiter als zweite Tabelle mit Grund.

---

## 10. Abnahmefragen

1. Beantwortet *Heute* in unter 10 Sekunden, ob alle fälligen Themen aller Portale geprüft sind — auch mobil?
2. Sieht „Geprüft, unverändert“ in Pille, Balken und Legende nach Erfolg aus und nirgends grau wie „Wartet“?
3. Stehen TopicStatus und Laufergebnis in jeder Liste in getrennten Spalten und getrennten Pillen?
4. Zeigt jeder kostenwirksame Dialog (Aktivieren, Jetzt prüfen, Neu schreiben, Entsperren, Prüfabstand, Zurück zum Schreiben) eine Kostenzeile vor der Bestätigung?
5. Ist der Gliederungs-Editor ohne Maus vollständig bedienbar, und wird jede Verschiebung angesagt?
6. Bleibt das Sprungziel einer umbenannten Überschrift unverändert sichtbar?
7. Lässt sich eine Prüfentscheidung ohne Maus in unter 90 Sekunden treffen, und sind die Einzeltasten abschaltbar?
8. Ist jede Zahl auf *Heute* ein Verweis in die passend gefilterte Themenliste?
9. Verwenden alle Pillen ausschließlich die registrierten `status-*`-Farben — keine generischen Filament-Rollen?
10. Unterscheidet sich das Panel auf einem Screenshot ohne Kontext eindeutig vom Admin-Panel?
11. Entscheidet sich die angebotene Altartikel-Aktion am aktuellen Themenzustand statt am gemeldeten Schnappschuss, und bleibt „Keine Überschneidung“ auch nach dem nächsten Abgleich erledigt?
12. Zeigt jeder Resolver-Fehler seine Meldung im Klartext, und rendert die Zeile danach mit der jetzt gültigen Aktion?
13. Läuft ein Thema, dessen aktueller Fakt an einer nicht erreichbaren Quelle hängt, am nächsten Tag ohne Probe direkt in die Tiefenrecherche, und kommt es mit Ersatzquelle ohne manuellen Eingriff durch das Gate (#26)?

---

## 11. Go-Live-Vorbedingungen (#38)

Gestaltungsvorgaben zu den sichtbaren Teilen von #38. Die Regeln im Code (Schwellenrechnung, Budgetprüfung, Worker, Backup) sind hier nur so weit beschrieben, wie die Oberfläche von ihnen abhängt. Leitlinie für alles Folgende: **Das Panel zeigt den Wert, der tatsächlich wirkt** — nie nur den eingetragenen (*kein folgenloses Feld*, §9).

### 11.1 Einstellungen › Portal, Abschnitt „Budget und Freigabe“ (G3, G4, G5)

**Reihenfolge ändern.** Der Umschalter „YMYL-Portal“ steht heute unter der Schwelle, beeinflusst sie aber. Neue Reihenfolge: (1) YMYL-Umschalter, volle Breite · (2) Tagesbudget | Schwelle, zweispaltig; unter 1024 px untereinander. Ursache vor Wirkung.

**Feld „Schwelle für die automatische Freigabe“** — Eingabe bleibt 50–100, Pflicht, gespeichert wird immer der eingetragene Wert (nie still auf 90 umschreiben, sonst wäre nach dem Abschalten von YMYL der alte Wert verloren).

Hilfetext unter dem Feld, ersetzt den heutigen Satz („Vorgabe 80, bei YMYL-Themen 90“ ist falsch, weil das Feld nie leer ist). Aktualisiert sich bei Änderung von Feld **oder** Umschalter (Verlassen des Felds bzw. sofort beim Umschalten), ohne Speichern:

| Zustand | Zeile 1 (14 px, `text-muted`) | Zeile 2 (14 px, `text-base`, mit Symbol `heroicon-m-information-circle` 16 px in `status-scheduled-fg`) |
|---|---|---|
| Wert < 100, kein YMYL | „50 bis 100. Liegt die Bewertung einer Fassung darunter, geht sie in die Prüfung.“ | — |
| YMYL an, Wert < 90 | wie oben | „Wirksam: 90 (YMYL-Untergrenze)“ |
| YMYL an, Wert 90–99 | wie oben | — (der eingetragene Wert wirkt) |
| Wert 100 (mit oder ohne YMYL) | wie oben | „100 = jede Fassung geht in die Prüfung.“ |

- Zeile 2 ist **Information, kein Fehler**: keine rote Farbe, kein Rahmenwechsel am Feld, Speichern bleibt möglich.
- Hilfetext hängt über `aria-describedby` am Feld (Filament-Standard); der Container der Zeile 2 trägt `aria-live="polite"`, damit die Änderung beim Umschalten von YMYL angesagt wird.
- Regel dahinter (für die Umsetzung, eine Stelle): wirksame Schwelle = `max(eingetragen, 90)` bei YMYL, sonst eingetragen; bei wirksamer Schwelle 100 gibt es **keine** Auto-Freigabe, auch nicht bei Bewertung exakt 100. Panel-Hinweis, `guide:rollout` und Go-Live-Check lesen dieselbe Methode.

**Feld „Tagesbudget dieses Portals (USD)“** — wirkt nach G5 beim Modellaufruf; der Hilfetext muss das sagen:

- „Leer: Vorgabe 3,00 USD. Ist das Budget erreicht, startet heute kein weiterer Lauf für dieses Portal. Heute verbraucht: 0,84 USD.“
- **0 ist nicht erlaubt** (untere Grenze 0,01). Grund: Die Budgetprüfung wertet 0 als „keine Grenze“ — ein Nutzer, der 0 einträgt, um ein Portal anzuhalten, würde das Gegenteil bewirken. Validierungsmeldung am Feld: „0 würde die Grenze abschalten. Zum Anhalten den Schalter ‚Portal ist für den Tageslauf freigeschaltet‘ ausschalten.“
- §9.2 („alle schreibgeschützt aus der Konfiguration“) gilt weiter für die globalen Werte; das Portalbudget ist die einzige beschreibbare Ausnahme. Die Zeile „Vorgabe je Portal“ in der Konfigurationsübersicht bekommt den Zusatz „— gilt, wenn im Portal kein Wert eingetragen ist“.
- *Heute* (§3.4, Spalte Kosten) misst die 80-%-Marke am **wirksamen** Portalbudget (Panelwert, sonst Vorgabe).

### 11.2 Quote „geprüft / fällig“ (G7)

Ziel aus der Abnahme: ≥ 95 % der fälligen Themen täglich geprüft. Begriffe wie §3.2: *Geprüft* = Neu erschienen + Aktualisiert + Geprüft, unverändert + Zur Prüfung. *Fällig* = Zahl, die die Fälligkeitsauswahl am Tag ermittelt hat, **einschließlich** wegen Budget verschobener. Die heutige Nennersumme aus den Laufzählern ersetzt *Heute* durch dieses `due`.

**Darstellung der Quote**, überall gleich:

- Format „96 %“ (ganzzahlig, abgerundet — 94,6 % darf nie als „95 %“ das Ziel vortäuschen), tabellarische Ziffern.
- `due = 0` → „–“ in `text-muted`, keine Warnung (nichts fällig ist kein Fehler, und kein „100 %“ vortäuschen).
- Unter 95 %: Text `status-review-fg` (#b45309, 5,0:1 auf Weiß) + Symbol `heroicon-m-exclamation-triangle` 16 px + sichtbarer Zusatz „unter Ziel 95 %“ (Kachel, Mail) bzw. nur für Screenreader (Tabellenzelle). Farbe trägt die Aussage nie allein.
- **Rolle:** Das Ticket nennt `status-warning` — diese Rolle gibt es im Panel nicht (sieben `status-*`-Rollen, §2). Richtig ist `status-review`, die „Achtung“-Rolle; keine neue Farbe.
- **Nicht während des Laufs warnen:** Solange der Tageslauf noch läuft (Zustandszeile „läuft seit …“), bleibt die Quote in `text-base` ohne Symbol — sonst wäre sie jeden Morgen bis ca. 04:00 „rot“. Warnfarbe erst ab „Tageslauf abgeschlossen“ oder nach Fensterende.

**Heute, Kachel „Prüfstand heute“ (§3.2):** Kennzahl bleibt „287 von 300“; Unterzeile wird „fälligen Themen geprüft · 96 %“. `aria-label` des Balkens beginnt mit „287 von 300 fälligen Themen geprüft, 96 Prozent: …“.

**Heute, Tabelle „Portale“ (§3.4):** Spalte „Geprüft“ zeigt „96 / 100“ und darunter 12 px die Quote („96 %“), gleiche Regeln. Keine zusätzliche Spalte (Tabelle ist bei 1024 px ausgereizt). Mobil (§3.6): Quote rechts neben „96 / 100“.

**Tagesbericht-Mail:**

- Neuer erster Absatz direkt unter der Überschrift, 16 px fett: „Geprüft: 287 von 300 fälligen Themen (96 %)“; unter Ziel mit Zusatz „ — unter Ziel 95 %“ in #b45309. Der bestehende Aufzählungssatz folgt darunter unverändert.
- Vorschauzeile (`preview`): „Ratgeber-Tageslauf 21.09.: 96 % der fälligen Themen geprüft, 4 fehlgeschlagen“.
- Portaltabelle: Spalte „Geprüft“ zeigt „96 / 100“, neue Spalte „Quote“ direkt dahinter, gleiche Farbregel. Die Tabelle hat dann 10 Spalten — Mail-Clients unter 600 px scrollen sonst quer: Spalte „Unverändert“ entfällt in der Mail (steht im Satz darüber und im Panel).
- Portale mit `due = 0`: „0 / 0“, Quote „–“.

### 11.3 Alarm-Mail (G1, G2)

**Empfänger** (G1 und Tagesbericht G2, eine Regel an einer Stelle): alle nicht gesperrten Nutzer mit Mailadresse, deren Panel-Rolle *Inhaber* ist — also auch Admins ohne eigene Guide-Rolle, genau wie im Panel.

**Welche Alarme:** nur `provider_down`, `budget_exceeded`, `failure_rate`, `run_stuck` (erst nach Ausschöpfen der Neustarts). Höchstens eine Mail je Alarmcode, Portal (bzw. „global“) und Kalendertag; ein am selben Tag wieder geöffneter Alarm löst keine zweite Mail aus. Alles Übrige nur im Tagesbericht.

**Aufbau** (sun-Mail-Layout wie Tagesbericht, eine Mail je Alarm):

- Betreff: „Ratgeber-Alarm: {Portal oder ‚alle Portale‘} — {Kurztext}“, z. B. „Ratgeber-Alarm: sanitaerfinder.com — Tagesbudget erreicht“. Kurztexte: *Modellanbieter nicht erreichbar* · *Tagesbudget erreicht* · *Fehlerquote über 5 %* · *Lauf hängt*.
- `h1` = Kurztext. Darunter ein Absatz mit der Alarmmeldung im Klartext (derselbe Text wie im Störungsband auf *Heute*, §3.1) und der Uhrzeit „seit 03:31“.
- Ein Absatz „Was jetzt passiert:“ mit der Folge ohne Eingriff, je Code ein Satz, z. B. Budget: „Verschobene Themen laufen morgen zuerst. Veröffentlichte Artikel bleiben online.“; Anbieter: „Läufe werden angehalten und nach … erneut versucht.“ Die Formulierung liefert die Umsetzung aus dem tatsächlichen Verhalten — nichts versprechen, was der Code nicht tut.
- **Genau eine** Schaltfläche „Im Content-Panel ansehen“ → *Heute*, Portalfilter gesetzt. Kein Link auf Einstellungen (Handlung entscheidet der Mensch im Panel).
- Fußzeile 14 px grau: „Diese Nachricht kommt höchstens einmal am Tag je Alarm. Weitere Vorkommen stehen im Tagesbericht.“
- Keine Farbe als einziger Träger; kein Rot für die ganze Mail — nur die Überschrift in #b91c1c.

### 11.4 Konsolenausgaben (G8, G9)

**`guide:rollout`** — Ausgabe immer (auch ohne Option) als Tabelle, sortiert nach Domain:

| Domain | Aktiv | YMYL | Schwelle | Tagesbudget |
|---|---|---|---|---|
| sanitaerfinder.com | ja | nein | 100 | 3,00 USD (Vorgabe) |
| tierarztportal.com | nein | ja | 80 → 90 (YMYL) | 5,00 USD |

- „Schwelle“ zeigt den wirksamen Wert; weicht er vom eingetragenen ab, „eingetragen → wirksam (YMYL)“; 100 mit Zusatz „(alles in Prüfung)“.
- Geänderte Zeilen am Zeilenanfang mit „*“, darunter „2 Portale geändert.“
- `--activate-all` / `--deactivate-all` fragen vorher „12 Portale freischalten? (ja/nein)“, Vorgabe **nein**; mit `--force` ohne Rückfrage (Deploy-Skripte).
- Unbekannte Domain: Fehlermeldung „Kein Portal mit der Domain … gefunden.“, Exit 1, nichts geändert.
- `content:rollout` bleibt als Verweis stehen: „Dieser Befehl wirkt nicht mehr. Verwende guide:rollout.“, Exit 1 (verhindert, dass alte Notizen/Runbooks still ins Leere schalten).

**`guide:golive:check`** — eine Zeile je Prüfung: Status fest breit (`OK  ` grün, `WARN` gelb, `FAIL` rot, Wort immer ausgeschrieben), Prüfpunkt, Ergebnis im Klartext. Schlüssel nur als „gesetzt“/„fehlt“, nie Wert, Länge oder Anfang. Stufen:

| Prüfpunkt | FAIL wenn | WARN wenn |
|---|---|---|
| Schlüssel (Anthropic, fal.ai, IndexNow) | fehlt | — |
| `guide:llm:ping` | nicht ok | — |
| IndexNow | aus, oder `/<key>.txt` einer aktiven Domain ≠ 200 (je Domain eine Zeile) | — |
| Horizon-Supervisor | eine Queue aus `guide.queues` ohne Supervisor (Queue nennen) | — |
| Budgets | ein Wert ≤ 0 (Zusatz: „0 schaltet die Grenze ab“) | — |
| Inhaber mit Mailadresse | weniger als 2 | — |
| Backup `storage/app/backups/content/<heute>` | fehlt (Ergebnis nennt das jüngste vorhandene Datum, falls es eins gibt) | — |

Schlusszeile: „Go-Live-Check: 2 FAIL, 1 WARN — nicht bereit.“ bzw. „… 0 FAIL — bereit.“ Exit 1 nur bei FAIL.

### 11.5 Ohne Oberfläche (G6, G10)

- G6: Queue `guide-assets` nachrangig am Supervisor `guide-write` (Entscheidung und Begründung im Kommentar zu #36/#38), nicht an `guide-publish`.
- G10: Backup ohne sichtbaren Anteil; der Go-Live-Check (§11.4) ist die einzige Anzeige.

### 11.6 Abnahmefragen #38

1. Zeigt das Schwellenfeld bei YMYL und Wert 80 sofort — ohne Speichern — „Wirksam: 90 (YMYL-Untergrenze)“, und wird das beim Umschalten angesagt?
2. Geht bei Schwelle 100 eine Fassung mit Bewertung 100 in die Prüfung?
3. Wird 0 als Portalbudget abgelehnt, mit Hinweis auf den Freischalt-Schalter?
4. Zeigen *Heute*, Portaltabelle und Mail dieselbe Quote aus demselben `due`, abgerundet, und ist sie während des Laufs nicht gelb?
5. Kommt je kritischem Alarm, Portal und Tag genau eine Mail an alle Inhaber einschließlich Admins ohne Guide-Rolle?
6. Gibt `guide:golive:check` an keiner Stelle einen Schlüsselwert oder Teile davon aus?
