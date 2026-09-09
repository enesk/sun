# Ratgeber-Seite — Template-Spezifikation (Desktop und Mobil)

Ticket #5. Umsetzung in #14 (Generator füllt die Blöcke), #17 (Template und Markup), #16 (Bilder), #27 (Autorschaft und Transparenz).
Bestand: `resources/views/themes/{default,starter}/views/pages/blog/show.blade.php`. Das ist ein **Ausbau**, kein Neubau. Vorhandene Blöcke (Hero, Fortschrittsbalken, Inhaltsverzeichnis, Prose, CTA, Tags, Sharing, Autorenbox, Pager, Related, Sidebar, Article-JSON-LD) bleiben und werden ergänzt.

Farben ausschließlich über `--portal-*` und die `portal-`-Utilities. Keine Hex-Werte. Typografie-Tokens `read-*` in `tailwind.content.config.cjs`.

---

## 1. `article_blueprint` — verbindliche Blockfolge

Diese Reihenfolge ist normativ für Generator (#14) und Template (#17). Ein Artikel ohne Block 2, 5, 8 oder 9 darf nicht veröffentlicht werden.

| # | Block | Pflicht | Vorhanden im Bestand |
|---|---|---|---|
| 1 | Titel und Hero | ja | ja |
| 2 | **Kurzantwort** | ja | **neu** |
| 3 | Meta- und Transparenzzeile | ja | teilweise |
| 4 | Inhaltsverzeichnis | ab 3 Überschriften | ja |
| 5 | **Key-Facts-Tabelle** | ja | **neu** |
| 6 | Hauptteil, 3–6 Abschnitte je `h2` | ja | ja (Prose) |
| 7 | **Regionalblock** | ja | **neu** |
| 8 | **FAQ-Akkordeon** | ja, 3–6 Fragen | **neu** |
| 9 | **Quellen- und Aktualitätszeile** | ja | **neu** |
| 10 | CTA-Box zur Firmensuche | ja | ja, wird verschoben |
| 11 | Autorenbox | ja | ja, wird umgeschrieben (#27) |
| 12 | Tags und Sharing | nein | ja |
| 13 | Pager und Related | nein | ja |

Werbung nur in bestehenden Positionen aus `app/View/Components/AdSlot.php`: `content_after_intro` **frühestens nach Block 5**, `sidebar_sticky` ab 1024 px, `footer_above`, `mobile_sticky_bottom`. Keine Anzeige zwischen Kurzantwort und erstem Absatz, **keine im Regionalblock** — dort entsteht die Konversion, die diese Portale trägt.

---

## 2. Raster und Lesbarkeit

| | Desktop ab 1024 px | Mobil unter 768 px |
|---|---|---|
| Layout | Textspalte 720 px + Sidebar 320 px, Rinne 48 px, Gesamt max. 1160 px, zentriert | eine Spalte, Seitenrand 20 px |
| Fließtext | 18 px, Zeilenhöhe 1,7 | 17 px, Zeilenhöhe 1,7 |
| Zeilenlänge | 68–75 Zeichen (ergibt sich aus 720 px bei 18 px) | 38–45 Zeichen |
| `h1` | 36 px, Zeilenhöhe 1,2 | 28 px |
| `h2` | 26 px, Abstand oben 40 px, unten 12 px | 22 px, oben 32 px |
| `h3` | 20 px, oben 28 px | 18 px |
| Absatzabstand | 20 px | 18 px |

Die Textspalte wird nicht breiter gemacht, auch nicht auf großen Bildschirmen. Über 75 Zeichen je Zeile fällt die Lesegeschwindigkeit messbar, und der Leser verliert beim Zeilenwechsel den Anschluss. Der gewonnene Platz geht an den Rand, nicht in die Spalte.

Ausrichtung linksbündig, kein Blocksatz — Blocksatz erzeugt ohne Silbentrennung Löcher im Text.

---

## 3. Blöcke im Einzelnen

### 1. Titel und Hero

Bestand bleibt. Zwei Ergänzungen:

- Das Titelbild bekommt **fest reservierte Geometrie**: `aspect-ratio: 16/9` auf dem Container, `width`/`height` am `img`, `fetchpriority="high"`, kein `loading="lazy"` (es ist das LCP-Element). Format WebP, Breiten 640/960/1440 über `srcset`. Ohne reservierte Geometrie kippt CLS beim Nachladen.
- Ohne Titelbild bleibt die Hero-Höhe konstant bei 280 px (Desktop) / 200 px (Mobil), damit Artikel mit und ohne Bild denselben Aufbau haben.

Breadcrumb über dem Titel: Startseite › Ratgeber › Kategorie › Titel. 14 px, `text-muted`, letzter Eintrag ohne Verweis.

### 2. Kurzantwort — der wichtigste neue Block

Direkt unter dem Titel, **vor** dem Inhaltsverzeichnis und vor jeder Anzeige.

- Kasten über die volle Textspalte, Innenabstand 24 px, `--portal-radius-lg`, Hintergrund `portal-primary-light`, linke Kante 4 px `portal-primary`.
- Überschrift „Kurz gesagt" als `h2` in 16 px, Versalabstand 0,04em, `portal-primary-dark`.
- Ein Absatz, 40–60 Wörter, 19 px (Desktop) / 18 px (Mobil), Zeilenhöhe 1,6, `font-weight: 500`. Genau eine Aussage, kein Aufwärmen, keine Aufzählung.
- Mindesthöhe 120 px reservieren.

Dieser Block bedient den Leser mit Eile und ist der Absatz, den Antwortmaschinen zitieren. Er wird als `speakable` und als erster Absatz im `articleBody` ausgezeichnet.

### 3. Meta- und Transparenzzeile

Unter der Kurzantwort, einzeilig auf Desktop, zweizeilig mobil. 14 px, `text-muted`, Trennung durch Mittelpunkt:

`Zuletzt geprüft am 8. September 2025 · Lesezeit 6 Minuten · Redaktion Ratgeber · Maschinell erstellt, redaktionell geprüft`

Der Transparenzhinweis ist ein Verweis auf eine Erklärseite, nicht ein Hinweisfeld — ruhig, nicht entschuldigend (#27). Datumsangaben immer als `<time datetime>` plus Klartext.

### 4. Inhaltsverzeichnis

Bestand bleibt (Alpine, ab 3 Überschriften). Eine Änderung: Es muss **auch ohne JavaScript** dastehen. Umsetzung als `<details open>` mit serverseitig gerenderter Liste; Alpine übernimmt nur das Hervorheben des aktiven Abschnitts. Höhe im geöffneten Zustand serverseitig festgelegt, damit nichts nachrutscht.

### 5. Key-Facts-Tabelle

Nach dem Inhaltsverzeichnis, vor dem ersten `h2`.

- Zwei Spalten: Merkmal (40 %) und Wert (60 %). 3–7 Zeilen, nicht mehr.
- Zeilenhöhe mindestens 48 px, senkrechter Innenabstand 12 px, Trennlinie 1 px zwischen den Zeilen, kein Gitter.
- Merkmal 15 px `font-weight: 600`, Wert 16 px. Zahlen tabellar und rechtsbündig, wenn die Spalte durchgehend numerisch ist.
- Rahmen `--portal-radius-lg`, Kopfzeile „Das Wichtigste in Zahlen" 16 px auf `portal-primary-light`.
- Enthält die Tabelle Preise oder Zeiträume, steht darunter eine Herkunftszeile in 13 px mit Quelle und Stand.

**Mobil**: die Tabelle bleibt eine Tabelle, sie wird nicht zu Karten umgebaut. Zwei kurze Spalten passen auf 350 px; ein Kartenumbau würde den Vergleich zerstören, der der Zweck der Tabelle ist. Merkmalspalte auf 45 % erhöhen, Schrift 15 px, waagerechtes Scrollen ist verboten.

Auszeichnung: normale `<table>` mit `<caption>`, nicht als Bild. Die Werte gehen zusätzlich in das Schema.org-Markup (#17).

### 6. Hauptteil

3–6 Abschnitte, je `h2` mit `id` für Anker. Innerhalb: Absätze, höchstens eine Aufzählung je Abschnitt, gelegentlich ein Hinweiskasten.

Hinweiskasten: linke Kante 3 px `portal-accent`, Hintergrund `portal-accent-light`, Innenabstand 16 px, `--portal-radius`, Überschrift 15 px fett. Höchstens zwei pro Artikel — mehr, und der Fließtext wirkt wie eine Restmenge.

Inline-Bilder und Infografiken (#16): Container mit fester `aspect-ratio` (`read-inline` 3:2, `read-infographic` 4:5), `loading="lazy"`, `decoding="async"`, Bildunterschrift 14 px `text-muted` mit 8 px Abstand. Alt-Text beschreibt den Inhalt, nicht das Keyword.

### 7. Regionalblock

Nach dem zweiten oder drittletzten `h2`, immer im Mittelteil, nie am Ende. Er ist der einzige Grund, warum diese Artikel besser sind als generische Ratgeber, und muss als eigenständige Einheit erkennbar sein.

- Volle Textspaltenbreite, Innenabstand 24 px (Desktop) / 20 px (Mobil), `--portal-radius-lg`, Hintergrund `portal-secondary-light`, kein Schlagschatten.
- `h2` mit Ortsbezug im Klartext: „Was in Regensburg gilt".
- Aufbau: ein einleitender Absatz, danach 2–4 regionale Fakten als Definitionsliste (Merkmal links fett, Wert rechts), darunter ein Absatz zur Einordnung.
- Abschluss: Verweisleiste zur Firmensuche der Region — Schaltfläche `bg-portal-primary`, Höhe 48 px, Text „Anbieter in Regensburg vergleichen", daneben in 14 px die Anzahl gelisteter Betriebe.
- **Keine Anzeige in diesem Block und keine unmittelbar davor oder danach.**

### 8. FAQ-Akkordeon

3–6 Fragen. Umsetzung als `<details>`/`<summary>`, **ohne JavaScript vollständig bedienbar**.

- Jede Frage ein `<details>`, Rahmen 1 px `line`, `--portal-radius`, Abstand 8 px zwischen den Einträgen.
- `<summary>`: Höhe mindestens 56 px, Innenabstand 16 px, Frage 17 px `font-weight: 600`, rechts ein Chevron, das sich bei `[open]` um 90° dreht. `list-style: none` und `::-webkit-details-marker { display: none }`, damit der Standardpfeil nicht doppelt erscheint. Cursor Zeiger, Hover `portal-primary-light`.
- Antwort: 16 px, Zeilenhöhe 1,7, Innenabstand 0/16/16 px, 40–80 Wörter.
- **Der erste Eintrag ist `open`.** So ist sofort erkennbar, dass es sich aufklappen lässt, und der Block ist nicht nur eine Liste fetter Zeilen.
- Die Antworten stehen vollständig im ausgelieferten HTML, auch im geschlossenen Zustand. Sonst geht das FAQPage-Markup verloren — genau deshalb kein JavaScript-Akkordeon.
- Überschrift des Blocks: `h2` „Häufige Fragen".

Bewegung der Chevron-Drehung bei `prefers-reduced-motion: reduce` abschalten.

### 9. Quellen- und Aktualitätszeile

Am Textende, vor der CTA-Box.

- Trennlinie oben, Abstand 32 px.
- Zeile 1: `Erstellt am 3. September 2025 · Zuletzt geprüft am 8. September 2025`, 14 px `text-muted`, beide als `<time datetime>`.
- Zeile 2: „Verwendete Quellen" als 15-px-Überschrift, darunter eine nummerierte Liste. Je Eintrag: Herausgeber, Titel, Datum, Verweis mit `rel="nofollow noopener"` und externem Symbol. Höchstens acht Einträge sichtbar, weitere hinter `<details>` „Alle Quellen anzeigen" — wieder ohne JavaScript.
- Quellen älter als 24 Monate tragen den Zusatz „Stand 2023" statt einer Warnfarbe. Eine rote Markierung an einer Quelle liest der Nutzer als Fehler des Artikels.

Trägt E-E-A-T und ist Voraussetzung für den Aktualisierungs-Kreislauf (#24).

### 10. CTA-Box zur Firmensuche

Bestand, aber **nach** der Quellenzeile statt mitten im Text — der Regionalblock hat die Konversion bereits im Mittelteil abgeholt, eine zweite Aufforderung davor konkurriert mit ihr.

Volle Textspaltenbreite, Innenabstand 32 px, `--portal-radius-lg`, Hintergrund `portal-primary`, Text weiß, Kontrast zur Schaltfläche geprüft. Überschrift 22 px, ein Satz, eine Schaltfläche 48 px hoch in Weiß mit `portal-primary-dark` als Text.

### 11. Autorenbox

Bestand, Inhalt neu (#27). Keine Person, kein Foto, kein erfundener Lebenslauf. Stattdessen: Name der Redaktionseinheit, ein Satz zum Verfahren („Recherche und Erstentwurf maschinell, Prüfung und Freigabe redaktionell"), Datum der letzten Prüfung, Verweis auf die Erklärseite. Bildplatz durch ein Signet des Portals gefüllt, 56 × 56 px, feste Maße.

### 12–13. Tags, Sharing, Pager, Related

Bestand unverändert. Related-Karten bekommen feste Bildgeometrie (`aspect-ratio: 16/9`), sonst rutscht der Seitenfuß beim Nachladen.

---

## 4. Sidebar (ab 1024 px)

320 px, links Abstand 48 px. Reihenfolge von oben:

1. `sidebar_top` — Anzeige, reservierte Mindesthöhe aus `AdSlot.php`
2. Inhaltsverzeichnis, klebend ab dem Scrollen, `top: 88px`
3. Firmensuche-Kasten der Region, kompakt
4. `sidebar_sticky` — klebende Anzeige, erst ab 1024 px

Unter 1024 px entfällt die Sidebar. Das Inhaltsverzeichnis wandert dann als `<details>` an Position 4 des Blueprints, die Anzeigen in `content_after_intro` und `mobile_sticky_bottom`. Sidebar-Inhalte werden mobil **nicht** unter den Artikel gehängt — das verlängert nur den Weg zum Seitenfuß.

---

## 5. Kernmetriken und Layoutstabilität

Verbindlich für die Umsetzung:

- Jedes `img` trägt `width` und `height` oder liegt in einem Container mit `aspect-ratio`. Ohne Ausnahme.
- Jeder Anzeigenplatz behält die in `AdSlot.php` reservierte Mindesthöhe, auch wenn keine Anzeige geliefert wird.
- Titelbild: WebP, `fetchpriority="high"`, vorab geladen, nicht verzögert. Alles unterhalb des ersten Bildschirms verzögert.
- Schriften mit `font-display: swap` und vorab geladener Hauptschnitt; kein Nachladen einer zweiten Familie für Überschriften.
- Der Lesefortschrittsbalken wird über `transform` bewegt, nicht über `width`, und bei `prefers-reduced-motion` weggelassen.
- Zielwerte mobil: LCP unter 2,5 s, CLS unter 0,1, INP unter 200 ms.

## 6. Barrierefreiheit

- Kontrast 4,5:1 für Text, 3:1 für Bedienelemente. Die Kurzantwort auf `portal-primary-light` und der Regionalblock auf `portal-secondary-light` sind je Portal-Branding zu prüfen, weil die Portalfarbe variabel ist — bei zu geringem Kontrast wird die Fläche aufgehellt, nicht der Text.
- Eine `h1` je Seite, danach keine Ebene überspringen.
- `<summary>` ist von Haus aus fokussierbar; Fokusring 2 px in `portal-primary` mit 2 px Abstand nicht entfernen.
- Sprungverweis „Zum Inhalt" vor dem Hero, sichtbar bei Fokus.
- Sharing-Schaltflächen mit `aria-label` im Klartext, nicht nur Symbol.
- Klickfläche mindestens 44 × 44 px, im FAQ 56 px Zeilenhöhe.
- Ohne JavaScript ist die Seite vollständig lesbar: Inhaltsverzeichnis, FAQ und die Quellenliste laufen über `<details>`, Fortschrittsbalken und aktive TOC-Hervorhebung sind Zugaben.

## 7. Abnahmefragen

1. Sind alle 13 Blöcke des Blueprints in der vorgegebenen Reihenfolge vorhanden?
2. Steht die Kurzantwort vor jeder Anzeige und vor dem Inhaltsverzeichnis?
3. Ist die Seite mit abgeschaltetem JavaScript vollständig lesbar, FAQ und Quellen eingeschlossen?
4. Bleibt die Zeilenlänge in der Textspalte unter 75 Zeichen?
5. Erzeugt kein Bild und kein Anzeigenplatz eine Layoutverschiebung?
6. Ist der Regionalblock frei von Anzeigen und führt er in die Firmensuche der Region?
7. Tragen Erstellungs-, Prüfdatum und Quellen sichtbar am Textende?
8. Behauptet die Autorenbox keine Person?
