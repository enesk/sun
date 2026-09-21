# Ratgeber-Frontend (themengetrieben) — Übersicht, Kategorie, Artikel

Ticket #4. Umsetzung in #17 (Templates, Komponenten), #12 (Datumswerte, `dateModified`), #18 (Sitemap, Indexierung), #20 (Titelbild).
Klammer: Dokument „UX-Leitbild Themengetriebenes Ratgebersystem (Epic #1)“ (*Leitbild*), §5–§7. Architektur: `docs/guide-system.md` (*Freeze*), §5 URL-Struktur.
Artikelseite: `design/ratgeber-template.md` bleibt gültig (Raster, Typografie, Blöcke 1–13); dieses Dokument belegt die Blöcke für das neue System und ergänzt Übersicht, Kategorieseite und drei Komponenten.

---

## 0. Designgrundlage und Tokens

- **Gestaltungssystem:** sun-portal-design. Ein Look für alle Portale; je Portal ändern sich **nur Markenfarbe und Logo** (plus Branchenbegriffe aus der Tenant-Konfiguration).
- **Tokens:** ausschließlich `--portal-*` aus `resources/css/portal.css` (injiziert von `TenantStyleInjector`) und die `portal-`-Utilities. Keine Hex-Werte in Templates.
- **Markenfarbe in neuen Bausteinen nur als `portal-primary`-Familie:** `portal-primary-light` (Fläche), `portal-primary-text` (Verweise, ≥ 4,5:1 auf Weiß), `portal-primary` (reine Fläche ohne Text, Schaltfläche). `portal-secondary`/`portal-accent` werden in den hier spezifizierten Bausteinen **nicht** verwendet — sonst unterscheiden sich Portale über mehr als eine Farbe.
- **Neutrale:** Seitengrund Zinc 50, Karten Weiß mit Rand Zinc 200, Fließtext Zinc 700, Überschriften Zinc 900, Nebentext Zinc 600 (nicht Zinc 500: dieses erreicht auf Zinc 50 nur 4,3:1).
- **Klassenbenennung:** neue Klassen im bestehenden Namensraum `ratgeber-*` in `portal.css` (vorhanden: `ratgeber-breadcrumb`, `-short-answer`, `-transparency`, `-toc`, `-facts`, `-sources`, `-changelog`).
- **Ansprache: Sie.** Abweichung vom Skill (dort Du) zugunsten der Repo-Konvention: alle Ratgeber-Partials siezen (`cta.blade.php`). Ein Wechsel mitten im Artikel wäre schlimmer als jede der beiden Formen.
- **Maße** in px bei 16 px Grundschrift, wie in `ratgeber-template.md`. Umsetzung in `rem` (px ÷ 16), außer Anzeigenplätze und Bildmaße.

Breakpoints (mobile first): Basis 375 px · `sm` 640 px · `md` 768 px · `lg` 1024 px (Sidebar ab hier).

---

## 1. Ratgeber-Übersicht `/ratgeber`

### 1.1 Mobil (Basis, 375 px)

```
┌─────────────────────────────────────┐
│ header                              │
├─────────────────────────────────────┤
│ breadcrumb: Startseite › Ratgeber   │
│ H1 Ratgeber                         │
│ Intro, 1 Satz                       │
│ [ Ratgeber durchsuchen      ] [🔍]  │  GET /ratgeber/suche, ohne JS
├─────────────────────────────────────┤
│ H2 Themen                           │
│ ┌─────────────────────────────────┐ │
│ │ Förderung                     › │ │  Kategorie-Kachel §4.4
│ │ 14 Ratgeber                     │ │
│ │ Aktualisiert am 12.09.2026      │ │
│ └─────────────────────────────────┘ │
│ … je Kategorie eine Kachel          │
├─────────────────────────────────────┤
│ H2 Zuletzt aktualisiert             │  nur wenn Einträge ≤ 30 Tage
│ Themen-Karte §4.5 × max. 5          │
├─────────────────────────────────────┤
│ ad-slot footer_above                │
│ footer                              │
└─────────────────────────────────────┘
```

- **H1** „Ratgeber“ (bestehender Titel der Blog-Übersicht bleibt, wenn er einen Branchenbezug trägt). Intro 17 px Zinc 700, max. 2 Zeilen, Branchenbegriff aus der Tenant-Konfiguration.
- **Suche:** Eingabe 48 px hoch, `radius-xl`, Schaltfläche 48 × 48 px mit `aria-label="Suchen"`. Reines Formular, kein Livewire.
- **Kategorie-Kacheln:** eine Spalte, Abstand 12 px. Reihenfolge = Position aus der Kategorienverwaltung (Dashboard §6), nicht alphabetisch und nicht nach Aktualität (sonst springt die Anordnung täglich).
- Kategorien ohne veröffentlichten Artikel erscheinen nicht.
- **Zuletzt aktualisiert:** Themen mit einem Changelog-Eintrag in den letzten 30 Tagen, neuester zuerst, höchstens 5. Keine Einträge → Abschnitt entfällt ganz (kein Leerzustand für Leser). Neu erschienene Artikel zählen mit („Neu am …“ statt „Aktualisiert am …“).
- Anzeige: nur `footer_above`. Kein Platz zwischen Suche und Kacheln.

### 1.2 Tablet und Desktop

- `sm` (≥ 640 px): Kacheln zweispaltig, Rinne 16 px.
- `lg` (≥ 1024 px): Container max. 1160 px; Kacheln dreispaltig, Rinne 24 px; „Zuletzt aktualisiert“ als zweispaltige Liste der Themen-Karten. Keine Sidebar auf dieser Seite — sie hätte hier keinen Inhalt außer Werbung.

### 1.3 Zustände

- Keine einzige veröffentlichte Kategorie: Route liefert die bestehende leere Blog-Übersicht mit `empty-state` „Hier erscheinen bald die ersten Ratgeber.“ und Verweis auf die Firmensuche; `noindex` (Regel #18).
- Nur eine Kategorie: Kacheln entfallen, stattdessen direkt die Themenliste dieser Kategorie (§2.1) unter der Überschrift der Kategorie. Eine einzelne Kachel ist ein Klick ohne Nutzen.

---

## 2. Kategorieseite `/ratgeber/{kategorie}`

### 2.1 Mobil (Basis)

```
┌─────────────────────────────────────┐
│ breadcrumb: Startseite › Ratgeber › │
│             Förderung               │
│ H1 Förderung                        │
│ Beschreibung, max. 2 Sätze (optional)│
│ 14 Ratgeber · aktualisiert 12.09.   │  Metazeile 14 px Zinc 600
├─────────────────────────────────────┤
│ Themen-Karte §4.5                   │
│ Themen-Karte                        │
│ Themen-Karte                        │
│ Themen-Karte                        │
│ Themen-Karte                        │
│ ad-slot content_after_intro         │  nach der 5. Karte
│ Themen-Karte …                      │
│ pagination (erst ab 31 Themen)      │
├─────────────────────────────────────┤
│ H2 Weitere Themen                   │  Chip-Gruppe der übrigen Kategorien
│ [Kosten] [Recht] [Technik] …        │
│ ad-slot footer_above                │
└─────────────────────────────────────┘
```

- **H1** = Kategoriename. Beschreibung nur, wenn gepflegt; kein generierter Fülltext.
- **Liste, kein Raster** — auch auf Desktop. Die Karten sind Text; zweispaltiger Text mit ungleicher Länge liest sich schlechter als eine Spalte.
- **Reihenfolge** = Position aus der Themenliste (Enes’ Reihenfolge). Nicht nach Datum sortieren: ein täglich geprüftes System würde die Liste sonst ständig umwerfen.
- **Pagination** erst ab 31 Themen, dann 24 je Seite, `?seite=2`, bestehende `pagination`-Komponente. Bei erwarteten 6–15 Themen je Kategorie ist das die Ausnahme.
- Anzeige `content_after_intro` nach der 5. Karte, mit der in `AdSlot.php` reservierten Mindesthöhe; bei weniger als 6 Karten entfällt sie.
- **Weitere Themen:** Chips (`pill`, 40 px hoch, Abstand 8 px, Umbruch), alle übrigen Kategorien in Positionsreihenfolge.

### 2.2 Desktop (≥ 1024 px)

Raster wie Artikelseite: Textspalte 720 px + Sidebar 320 px, Rinne 48 px, max. 1160 px.
Sidebar: `sidebar_top`, darunter Karte „Weitere Themen“ als senkrechte Linkliste (statt der Chips unter der Liste — auf Desktop entfällt die Chip-Gruppe), darunter `sidebar_sticky`.

### 2.3 Zustände

- Kategorie ohne veröffentlichten Artikel → 404 (erscheint nirgends verlinkt).
- Weniger als 3 aktive Themen → Seite wird normal ausgeliefert, `noindex` nach Regel #18; gestalterisch kein Unterschied.
- Alter Pfad `/ratgeber/kategorie/{slug}` → 301 (Freeze §5), keine eigene Gestaltung.

---

## 3. Artikelseite `/ratgeber/{kategorie}/{thema}`

Blueprint und Raster aus `design/ratgeber-template.md` gelten. Hier nur, was sich im neuen System ändert oder ausdrücklich festgelegt wird.

> **URL-Hinweis:** Das Leitbild (§7) empfiehlt eine flache Artikel-URL. Der Freeze (§5) hat `/ratgeber/{kategorie}/{thema}` festgelegt; der Freeze ist maßgeblich. Gestalterische Folge: Zusammenlegen oder Verschieben von Kategorien ändert Artikeladressen. Das Dashboard kündigt die 301-Folge deshalb im Dialog an (Dashboard §6).

### 3.0 Belegung der Blöcke

| # | Block | Im neuen System |
|---|---|---|
| 1 | Titel und Hero | Brotkrume **Startseite › Ratgeber › Kategorie › Titel** über dem `h1`, keine Kategoriepille. Titelbild aus #20 mit fester Geometrie (s. §5). |
| 2 | Kurzantwort | unverändert (Pflicht) |
| 3 | Meta-/Transparenzzeile | **Zeile 1: Stand-Zeile, Variante kompakt (§4.1).** Zeile 2: „Lesezeit 6 Minuten · So entsteht dieser Ratgeber“ (bestehende `ratgeber-transparency`). |
| 4 | Inhaltsverzeichnis | Einträge aus der **gesperrten Gliederung** (H2, H3 eingerückt) plus „Häufige Fragen“ am Ende. Anker = gespeicherte, feste IDs der Gliederung — nie aus dem Überschriftentext neu erzeugt. `<details open>`, serverseitig. |
| 5 | Key-Facts | Werte aus `guide_facts`; Herkunftszeile mit Quelle und Stand bleibt Pflicht. |
| 6 | Hauptteil | Abschnitte je gesperrter H2/H3, `id` fest. Keine hochgestellten Quellennummern im Fließtext. |
| 7 | Regionalblock | **entfällt** (Freeze §9: `regional-block` wird gelöscht; Themen sind nicht regional). An seiner Stelle, nach dem vorletzten H2: **Verweisleiste Firmensuche** — eine Zeile Text „{Branche_Plural} in Ihrer Nähe vergleichen“ und eine **sekundäre** Schaltfläche (48 px, Rand `portal-primary-text`) zur Suche. Sekundär, weil Block 10 der primäre Aufruf bleibt. Keine Anzeige unmittelbar davor oder danach. |
| 8 | FAQ | unverändert, `<details>`, erster Eintrag offen (Pflicht) |
| 9 | Quellen und Aktualität | **Stand-Zeile, Variante vollständig (§4.1) → Changelog „Was ist neu?“ (§4.2) → Quellenliste (§4.3)**, in dieser Reihenfolge, als ein `<section>` mit Trennlinie oben und 32 px Abstand. |
| 10 | CTA-Box | unverändert, einziger primärer Aufruf im Artikel |
| 11 | Autorenbox | unverändert; nennt „zuletzt geprüft am“ mit dem Prüfdatum. Der Changelog zieht von hier nach Block 9 um. |
| 12–13 | Tags, Sharing, Pager, Related | unverändert. Related = Themen derselben Kategorie (Freeze §9, `InternalLinkResolver`). |

Werbung: wie `ratgeber-template.md` §1 (`content_after_intro` frühestens nach Block 5, nie zwischen Kurzantwort und erstem Absatz, nie neben der Verweisleiste).

### 3.1 Mobil (Basis) — Reihenfolge am Bildschirm

Brotkrume → H1 → Titelbild 16:9 → Kurzantwort → Stand-Zeile + Transparenzzeile → Inhaltsverzeichnis (`<details>`, auf Mobil **geschlossen**, Beschriftung „Inhalt (7 Abschnitte)“) → Key-Facts → `content_after_intro` → Hauptteil → Verweisleiste → FAQ → Block 9 → CTA → Autorenbox → Tags/Sharing → Related → `footer_above` → `mobile_sticky_bottom`.

Seitenrand 20 px, Fließtext 17/1,7 (Template §2).

### 3.2 Desktop (≥ 1024 px)

Textspalte 720 px + Sidebar 320 px (Template §4). Das Inhaltsverzeichnis steht als `<details open>` an Blockposition 4 **und** klebend in der Sidebar (`top: 88px`). Damit es nicht doppelt vorgelesen wird, ist ab 1024 px die Blockfassung per `display: none` ausgeblendet; die Sidebar-Fassung trägt `<nav aria-label="Inhalt">`. Unter 1024 px entfällt die Sidebar-Fassung. Beide Fassungen kommen aus demselben Partial.

---

## 4. Komponenten

Je Block eine Komponente, verwendet in Artikel, Vorschau (`/ratgeber/vorschau/{draft}`) und Prüf-Ansicht des Dashboards (Leitbild §6), damit geprüft wird, was später erscheint.

| Komponente | Partial | Eingaben |
|---|---|---|
| Stand-Zeile | `ratgeber/partials/stand.blade.php` (neu) | `publishedAt`, `updatedAt` (letzte **inhaltliche** Änderung, null wenn nie), `checkedAt`, `hasChangelog`, `variant` = `kompakt`/`vollstaendig` |
| Changelog | `ratgeber/partials/changelog.blade.php` (umbauen) | Liste `{at, text, source: {label, url}?}`, neuester zuerst; `highlight` (Index, nur Prüf-Ansicht) |
| Quellenliste | `ratgeber/partials/sources.blade.php` (umbauen) | Quellen `{publisher, title, url?, published_at?, stale_year?}`; **keine Datumsangaben mehr** (die übernimmt die Stand-Zeile) |
| Kategorie-Kachel | `ratgeber/partials/category-tile.blade.php` (neu) | `name`, `url`, `count`, `lastUpdatedAt` |
| Themen-Karte | `ratgeber/partials/topic-card.blade.php` (neu) | `title`, `url`, `teaser`, `dateLabel`, `date` |

### 4.1 Stand-Zeile

Setzt den Datumsvertrag aus Leitbild §5 um. **Die wichtigste Komponente des Vorhabens.**

**Welche Daten erscheinen** (Tage kalendarisch in Europe/Berlin verglichen):

| Lage | Variante kompakt (Block 3) | Variante vollständig (Block 9) |
|---|---|---|
| nie inhaltlich geändert, heute veröffentlicht | Veröffentlicht am 21. September 2026 | Veröffentlicht am 21. September 2026 |
| nie geändert, später geprüft | Veröffentlicht am 3. September 2026 · Geprüft am 21. September 2026 | dasselbe |
| geändert, Prüfung am selben Tag | Aktualisiert am 21. September 2026 · Was ist neu? | Veröffentlicht am 3. September 2026 · Aktualisiert am 21. September 2026 |
| geändert, später geprüft | Aktualisiert am 12. September 2026 · Geprüft am 21. September 2026 · Was ist neu? | Veröffentlicht am 3. September 2026 · Aktualisiert am 12. September 2026 · Geprüft am 21. September 2026 |

- „Was ist neu?“ ist ein Sprungverweis auf `#was-ist-neu` und erscheint **nur**, wenn der Changelog mindestens einen Eintrag hat. In der vollständigen Variante entfällt er (der Changelog folgt direkt).
- Jedes Datum als `<time datetime="YYYY-MM-DD">` + Klartext `j. F Y`.
- Kein Warnton, kein Symbol für „lange nicht geprüft“. Ein veraltetes Prüfdatum ist ein Betriebsproblem, das im Dashboard gemeldet wird, nicht beim Leser.
- **Darstellung:** 14 px, Zinc 600, Zeilenhöhe 1,5, `<p>`; Trenner „·“ als `<span aria-hidden="true">` mit 8 px Abstand beidseitig. Der Mittelpunkt folgt dem bestehenden Muster der Transparenzzeile (Repo-Konvention vor Skill-Regel).
- **Umbruch mobil:** Bauform als Inline-Liste (`<ul role="list">`, `display: flex; flex-wrap: wrap; column-gap: 0; row-gap: 4px`), jede Angabe ein `<li>` mit `white-space: nowrap`. Der Trenner „·“ ist `::before` jedes `<li>` außer dem ersten (Innenabstand 8 px beidseitig). Unter 480 px bekommt jedes `<li>` volle Breite und der Trenner entfällt (`::before { content: none }`) — die Angaben stehen dann untereinander. Es gibt damit nie einen Trenner am Zeilenanfang oder -ende.
- Verweis „Was ist neu?“: `portal-primary-text`, unterstrichen, Klickfläche per senkrechtem Innenabstand 10 px auf mindestens 44 px Höhe erweitert.
- Reservierte Höhe: keine nötig, die Zeile wird serverseitig vollständig ausgeliefert.
- **Strukturdaten** (für #12/#17): `datePublished` = `publishedAt`, `dateModified` = `updatedAt ?? publishedAt`. `checkedAt` geht **nie** in `dateModified`, Sitemap-`lastmod` oder den Feed.

### 4.2 Changelog „Was ist neu?“

```
────────────────────────────────────── (Trennlinie Block 9)
Veröffentlicht am … · Aktualisiert am … · Geprüft am …

Was ist neu?                                  ← h2, id="was-ist-neu"
┌ 12. September 2026
│ Förderhöchstbetrag auf 30.000 € angepasst (Quelle: KfW, 12.09.2026).
├ 2. August 2026
│ Neuer Abschnitt zu Fristen beim Antrag.
├ 14. Juni 2026
│ Angabe zur Bearbeitungszeit zurückgenommen.
▸ Frühere Änderungen (4)                      ← <details>, geschlossen
```

- `<section aria-labelledby>` mit `h2` „Was ist neu?“, `id="was-ist-neu"` (fest, Sprungziel der Stand-Zeile). Überschrift 20 px (mobil 18 px), 600, Zinc 900, oben 24 px Abstand.
- `<ol reversed>` nicht verwenden (Nummern haben keine Bedeutung) → `<ul role="list">`, jüngster Eintrag zuerst.
- **Die drei jüngsten Einträge offen**, alle älteren in `<details class="ratgeber-changelog__more">` mit `<summary>` „Frühere Änderungen (n)“ (Summary 44 px hoch, Chevron wie FAQ). Ohne JavaScript bedienbar. Höchstens 20 Einträge insgesamt; ältere werden nicht ausgeliefert.
- **Eintrag:** Datum als `<time>`, 14 px, 600, Zinc 700; darunter der Satz, 16 px, Zeilenhöhe 1,6, Zinc 700. Quelle am Satzende in Klammern, der Herausgeber als Verweis (`portal-primary-text`, `rel="nofollow noopener"`). Abstand zwischen Einträgen 16 px.
- **Zeitleiste:** linke Linie 2 px Zinc 200, je Eintrag ein Punkt 8 px Zinc 400 auf Höhe der Datumszeile. Keine Markenfarbe — der Block ist Information, keine Handlung.
- **Wortlaut** (Vorgaben aus Leitbild §5, Vorlage #7): ein Satz, für Leser, mit Sache und Richtung. Rollback: „Angabe zu … zurückgenommen.“ Nie „Abschnitt 3 überarbeitet“.
- **Leer:** Komponente rendert nichts (auch keine Überschrift); die Stand-Zeile zeigt dann kein „Was ist neu?“.
- **Variante Prüf-Ansicht** (`highlight`): der vorgeschlagene Eintrag trägt linke Kante 3 px in der Prüffarbe des Panels und die Marke „Vorschlag“; sonst identisch. Im Panel werden die Panel-Tokens statt `--portal-*` gesetzt, Aufbau und Abstände bleiben gleich.

### 4.3 Quellenliste

- `h2` „Verwendete Quellen“, 20 px (mobil 18 px), `<ol>` (Reihenfolge = Verwendung im Text).
- Eintrag: **Herausgeber** (600) · Titel als Verweis · Stand. 16 px, Zeilenhöhe 1,5, Abstand 12 px. Verweis `portal-primary-text`, externes Symbol 12 px, `rel="nofollow noopener"`, `target="_blank"` mit unsichtbarem Zusatz „(öffnet in neuem Tab)“.
- Höchstens 8 sichtbar, weitere in `<details>` „Alle Quellen anzeigen (n)“ — wie Bestand.
- Quellen älter als 24 Monate: Zusatz „Stand 2023“ in Zinc 600, keine Warnfarbe (Template §9).
- **Änderung am Bestand:** Die Datumszeile „Erstellt am … · Zuletzt geprüft am …“ wandert aus diesem Partial in die Stand-Zeile. Der heutige Code befüllt „Zuletzt geprüft am“ mit `$modifiedAt` — das widerspricht dem Datumsvertrag und muss in #17 entfallen.
- **Keine hochgestellten Nummern** im Fließtext. Die Quelle der jüngsten Änderung ist im Changelog-Eintrag verlinkt.
- **Variante Prüf-Ansicht:** zusätzlich Bewertung und belegter Satz je Quelle (Dashboard §8.2), Marke „neu in diesem Lauf“.

### 4.3a Nicht erreichbare Quellen (`guide_sources.broken_at`, #26)

Grundsatz: **Der Leser bekommt nie einen toten Verweis und nie eine Betriebsmeldung.** Eine kaputte Quelle ist ein Redaktionsproblem (Dashboard §8.2/§5.3), kein Leserhinweis — wie beim Prüfdatum (§4.1). Maßgeblich ist allein `broken_at !== null` zum Zeitpunkt der Auslieferung; ein erfolgreicher Link-Check leert das Feld, dann gilt wieder der Normalfall.

| Lage der Quelle | Quellenliste (§4.3) | Changelog (§4.2) | Fließtext (Block 6, FAQ, Kurzantwort) |
|---|---|---|---|
| erreichbar | wie §4.3 | Herausgeber als Verweis | Verweis bleibt |
| kaputt, belegt noch einen aktuellen Fakt (`is_current`) oder steht noch im Text | **bleibt stehen, ohne Verweis:** Herausgeber (600) · Titel als reiner Text, kein Symbol „extern“, kein Zusatz, keine Warnfarbe. „Stand 2023“ bleibt, falls zutreffend. | Herausgeber als reiner Text in derselben Klammer: „(Quelle: KfW, 12.09.2026)“ | `<a>` entfällt beim Rendern, der Ankertext bleibt als normaler Text (keine Unterstreichung, keine Markenfarbe) |
| kaputt, belegt keinen aktuellen Fakt mehr und steht nicht im Text (ersetzt) | **entfällt vollständig** | wie Zeile darüber — der Eintrag ist Historie und bleibt, nur ohne Verweis | — |

Begründung der Zwischenstufe: Solange ein Wert auf der Seite noch aus dieser Quelle stammt, muss die Herkunft benannt bleiben (Transparenz, E-E-A-T). Streichen würde einen Wert ohne Beleg zurücklassen; ein toter Verweis kostet Vertrauen und Crawl-Qualität. Sobald die Tiefenrecherche ersetzt hat, verschwindet die Quelle ohne Zutun.

- **Zählung und Reihenfolge:** Entfallene Quellen zählen nicht in „Alle Quellen anzeigen (n)“ und nicht in die 8 sichtbaren. Die `<ol>`-Nummerierung schließt ohne Lücke.
- **Leer:** Bleibt nach dem Filtern keine Quelle übrig, rendert die Quellenliste nichts (auch keine Überschrift) — wie der leere Changelog.
- **Barrierefreiheit:** Die unverlinkte Quelle ist ein normales `<li>` ohne `aria-disabled`, ohne „nicht verfügbar“-Text; es gibt nichts zu bedienen, also nichts anzusagen.
- **Strukturdaten:** Kaputte URLs erscheinen in keiner Ausgabe — weder `citation`/`isBasedOn` im JSON-LD noch im Feed oder in `llms.txt`.
- **Vorschau** (`/ratgeber/vorschau/{draft}`) und **Prüf-Ansicht** verwenden dieselbe Regel, damit geprüft wird, was erscheint. Nur die Panel-Variante der Quellenliste zeigt zusätzlich die Marke aus Dashboard §8.2 („nicht erreichbar“).
- **Keine eigene Sperre im Frontend:** Die Seite wird nicht zurückgezogen, nur weil eine Quelle kaputt ist. Die zuletzt freigegebene Fassung bleibt online; das Gate hält lediglich die *nächste* Fassung an, bis Ersatz da ist.

### 4.4 Kategorie-Kachel

Karte (`card-interactive`): Weiß, Rand 1 px Zinc 200, `radius-2xl` (16 px), Innenabstand 20 px, kein Schatten; Hover: Rand `portal-primary-text`, 150 ms (ohne Übergang bei `prefers-reduced-motion`).

- Inhalt: Name als `h3` 18 px 600 Zinc 900 (max. 2 Zeilen, dann Kürzung), darunter 14 px Zinc 600: „14 Ratgeber“ und in neuer Zeile „Aktualisiert am 12.09.2026“ (jüngstes `updatedAt ?? publishedAt` der Kategorie). Rechts ein Chevron 20 px Zinc 400, senkrecht zentriert.
- **Kein Symbol, kein Bild.** Kategorien entstehen frei aus dem Import; eine Symbolzuordnung müsste jemand pflegen und würde bei neuen Kategorien fehlen. Das hält die Kachel außerdem bildfrei (kein Layout-Shift).
- **Feste Mindesthöhe** 112 px (mobil) / 128 px (≥ 640 px); die ganze Kachel ist der Verweis (`<a>` umschließt den Inhalt), Fokusring 2 px `portal-primary-text` mit 2 px Abstand.
- Zahl „1 Ratgeber“ im Singular korrekt.

### 4.5 Themen-Karte

Gleiche Kartenbauform wie §4.4, Innenabstand 20 px, Abstand zwischen Karten 12 px.

- Titel als `h2` (Kategorieseite) bzw. `h3` (Übersicht), 18 px, 600, Zinc 900, **max. 2 Zeilen** (`line-clamp: 2`).
- Anriss: die **Kurzantwort**, serverseitig auf 160 Zeichen an Wortgrenze gekürzt mit „…“, 15 px Zinc 700, Zeilenhöhe 1,5, `line-clamp: 2` (mobil 3).
- Datumszeile 14 px Zinc 600: „Aktualisiert am 12.09.2026“ — oder „Neu am 21.09.2026“, wenn nie geändert und jünger als 30 Tage — sonst „Veröffentlicht am …“. **Nie das Prüfdatum** auf Listenkarten: ein täglich wechselndes Datum auf 15 Karten ist die Kosmetik, die das Leitbild ausschließt.
- **Kein Bild** (Leitbild §7). Feste Mindesthöhe: 136 px ab 640 px, 160 px mobil (reserviert Titel 2 Zeilen + Anriss + Datum); dadurch gleiche Höhe aller Karten und kein Verschieben beim Laden der Schrift.
- Die ganze Karte ist klickbar über den Titelverweis mit gestrecktem `::after`; der Anriss bleibt markierbar.

---

## 5. Layoutstabilität, Performance, ohne JavaScript

- Übersicht und Kategorieseite enthalten **keine Bilder** außer dem Logo im Header (mit `width`/`height`). CLS-Quellen dort sind nur Anzeigenplätze → reservierte Mindesthöhen aus `AdSlot.php`.
- Titelbild (#20): Container `aspect-ratio: 16/9`, `img` mit `width`/`height`, WebP mit `srcset` 640/960/1440, `fetchpriority="high"`, kein `loading="lazy"`. Ohne Titelbild konstante Hero-Höhe (Template §3.1). Branchen-Fallbackbild hat dieselbe Geometrie.
- Alles in §4 wird serverseitig vollständig gerendert. Aufklappbares ausschließlich über `<details>`: Inhaltsverzeichnis, FAQ, „Frühere Änderungen“, „Alle Quellen anzeigen“. JavaScript ist nur Zugabe (aktive TOC-Markierung, Lesefortschritt).
- Zielwerte mobil: LCP < 2,5 s, CLS < 0,1, INP < 200 ms. Messaufbau wie bisher (lokal, Lighthouse, siehe Messprotokolle #34).

## 6. Barrierefreiheit

- Ein `h1` je Seite; Kategorieseite: Karten-Titel `h2`; Übersicht: Abschnittstitel `h2`, Karten-Titel `h3`.
- Kontrast Text ≥ 4,5:1, Bedienelemente ≥ 3:1. Zinc 600 auf Weiß 7,6:1, auf Zinc 50 7,2:1. Verweise immer `portal-primary-text`, nie `portal-primary`.
- Klickflächen ≥ 44 × 44 px (Kacheln, Karten, Chips 40 px hoch + 4 px Abstand ⇒ Zielfläche ≥ 44 px, Summaries 44 px).
- Fokusring sichtbar, 2 px mit 2 px Abstand; `<summary>` nicht umgestylt ohne Fokusring.
- Zeitangaben immer mit `datetime`. Chevron- und Trennzeichen `aria-hidden`.

## 7. Abnahmefragen

> Ergänzung #26: Enthält eine Artikelseite, deren Thema eine Quelle mit `broken_at` hat, irgendwo (Quellenliste, Changelog, Fließtext, JSON-LD) noch einen Verweis auf diese URL? Erwartung: nein. Steht die Quelle noch als Text da, solange sie einen aktuellen Fakt belegt, und ist sie nach der Ersetzung ganz verschwunden?

1. Ist jede der drei Seiten auf 375 px ohne waagerechtes Scrollen vollständig nutzbar, und entspricht die Desktop-Fassung §1.2 / §2.2 / §3.2?
2. Unterscheiden sich zwei Portale nebeneinander nur in Markenfarbe, Logo und Branchenbegriffen?
3. Ändert sich „Aktualisiert am“ nur zusammen mit einem neuen Changelog-Eintrag, und erscheint „Geprüft am“ nie in `dateModified`, `lastmod` oder auf Listenkarten?
4. Führt „Was ist neu?“ zu `#was-ist-neu`, und fehlt der Verweis, wenn es keinen Eintrag gibt?
5. Sind Artikel, FAQ, Changelog (inkl. „Frühere Änderungen“) und alle Quellen mit abgeschaltetem JavaScript lesbar?
6. Bleiben die Sprungziele des Inhaltsverzeichnisses nach einer Umformulierung der Überschrift gleich?
7. Liegt CLS auf allen drei Seiten mobil unter 0,1, und hat keine Listenkarte ein Bild?
8. Werden Stand-Zeile, Changelog und Quellenliste in Artikel, Vorschau und Prüf-Ansicht aus denselben Partials gerendert?
