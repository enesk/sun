# Rich Results Test auf Staging — Durchfuehrung (Ticket #84)

Rest aus #34. Der Schema-Markup-Validator ist bereits durch (0 Fehler,
0 Warnungen, siehe `ratgeber-template-messung-2026-09-09.md`, Abschnitt 4).
Offen ist nur der Lauf im Google-eigenen Werkzeug.

Es gibt zwei Wege. **Weg A** ist der Abnahmelauf und braucht den Staging-Host
aus #90. **Weg B** geht heute schon und beantwortet alles ausser der
Auslieferung. Wer beides macht, faengt mit B an: findet B einen Fehler im
Markup, ist er ohne Staging behebbar.

| | Weg A (URL) | Weg B (Code) |
|---|---|---|
| Voraussetzung | oeffentliche Staging-URL | keine |
| prueft Markup | ja | ja |
| prueft Bildabruf | ja | nein |
| prueft `noindex` / robots.txt | ja | nein |
| Abnahme fuer #84 | ja | nein |

---

## Weg A — URL-Lauf auf Staging

### Voraussetzungen

- Staging erreichbar unter einer oeffentlichen Domain, kein Basic-Auth, kein
  `noindex` auf der Seite (die Redaktionsvorschau setzt `noindex` — deshalb den
  veroeffentlichten Artikel pruefen, nicht die Vorschau).
- Mindestens ein veroeffentlichter Ratgeber-Artikel mit Titelbild aus #16.
- `APP_URL` auf Staging auf die Staging-Domain gesetzt.

### Schritte

1. `https://<staging>/ratgeber/<slug>` in
   https://search.google.com/test/rich-results eingeben (Reiter „URL").
   Ergebnis fuer **Article**, **FAQPage** und **BreadcrumbList** als Screenshot
   und Textauszug hier im Ordner ablegen
   (`rich-results-<datum>.png` / `.txt`).
2. **HowTo** nur, wenn ein Artikel `outline_json.howto` mitbringt. Google zeigt
   HowTo seit September 2023 nicht mehr als Rich Result; der Test meldet den Typ
   hoechstens als gueltiges Markup. Kein Artikel im Bestand hat derzeit Schritte.
3. Gegenprobe absolute Bild-URLs — siehe unten.
4. Ergebnis in die Tabelle „Protokoll" am Ende dieser Datei eintragen.

---

## Weg B — Code-Reiter, ohne Staging

Der Rich Results Test nimmt im Reiter „Code" eingefuegtes HTML entgegen und
prueft daran dieselben Typen. Damit laesst sich das Markup heute abnehmen; nur
die Auslieferung (Bildabruf, `noindex`, robots.txt) bleibt Weg A vorbehalten.

Einfuegefertig liegt `rich-results-code-tab.html` in diesem Ordner. Sie stammt
aus dem am 09.09.2026 ausgelieferten Graphen
(`ratgeber-jsonld-2026-09-09.json`), mit Platzhalter-Host und der absoluten
Bild-URL, wie `ArticleSeoService::meta()` sie seit #84 baut.

```bash
sed 's|STAGING-HOST|staging.example.de|g' \
  docs/messungen/rich-results-code-tab.html | pbcopy
```

Danach den Inhalt in den Reiter „Code" einfuegen und pruefen lassen.

Erwartung: **Article**, **FAQPage** und **BreadcrumbList** je einmal gueltig,
kein Fehler. `Organization` ist Beiwerk und wird nicht als Rich Result
gemeldet. HowTo taucht nicht auf — der Graph enthaelt keine Schritte.

Zwei Befunde sind kein Fehler und gehoeren nicht in ein neues Ticket:

- Meldungen zum Bild („Bild konnte nicht abgerufen werden"). Der
  Platzhalter-Host loest nicht auf. Der Bildabruf ist Sache von Weg A.
- Fehlende `logo`/`sameAs` an `Organization`. Datenluecke aus #78, kein
  Template-Fehler.

Steht Staging spaeter, ersetzt eine frische Fassung die abgelegte:

```bash
curl -s https://<staging>/ratgeber/<slug> > /tmp/ratgeber.html
```

Eine frische Fassung lokal zu erzeugen geht derzeit nicht: die Mandanten-
Datenbanken dieser Arbeitskopie haben weder `blog_posts` noch `article_drafts`,
es existiert also kein veroeffentlichter Artikel zum Rendern.

---

## Gegenprobe absolute Bild-URLs

```bash
curl -s https://<staging>/ratgeber/<slug> \
  | grep -Eo '<meta (property="og:image"|name="twitter:image") content="[^"]+"'
curl -s https://<staging>/ratgeber/<slug> \
  | sed -n 's/.*"image": *"\([^"]*\)".*/\1/p'
```

Alle drei Werte muessen mit `https://<staging>/` beginnen.

`ArticleSeoService::meta()` loest seit #84 relative Bildpfade gegen den Host der
laufenden Anfrage auf, also gegen die Portal-Domain des Mandanten. Der Wert in
`assets_json.hero.variants` kann relativ sein: `AssetStorage::url()` gibt zurueck,
was die Platte liefert, und die Platte `public` baut ihre URL aus `APP_URL`
— ohne gesetzten Wert entsteht ein Pfad. Vor der Messung stand deshalb
`"image": "/t34/hero-1440.webp"` im ausgelieferten Graphen.

Bleibt eine Bild-URL nach dem Lauf absolut, aber auf einer anderen Domain als der
Portal-Domain (naemlich auf `APP_URL` der Zentrale), ist das kein Fehler fuer
Google, solange die Datei dort oeffentlich abrufbar ist. Der `curl`-Abruf der
Bild-URL muss 200 liefern.

---

## Protokoll

| Datum | Weg | Article | FAQPage | BreadcrumbList | Bild absolut | Auszug |
|---|---|---|---|---|---|---|
| offen | B | | | | entfaellt | |
| offen | A | | | | | |
