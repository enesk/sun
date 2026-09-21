# Musterartikel Ratgebersystem (Erstfassung) — Referenz für Few-Shot-Prompting

> **Hinweis:** Alle Zahlen, Quellen-URLs und Stände sind **erfundene Platzhalter** zur
> Illustration von Format und Belegpflicht. Sie dürfen weder veröffentlicht noch als
> echtes Fakten-Set behandelt werden. Prompts zitieren aus diesem Dokument höchstens
> einzelne Abschnitte. Struktur = `article_blueprint` (Kurzantwort, Key-Facts, Gliederung,
> Abschnitte, FAQ, Quellen, Stand-Zeile, Changelog), Felder = `guide-system.md` §4.
> Die zweite Fassung mit simuliertem Update steht in `guide-musterartikel-update.md`.

**Portal:** Solar-Portal (Branche `solar-pv`) · **Kategorie:** Kosten und Förderung
**Frage (`guide_topics.question`):** Was kostet eine Photovoltaikanlage für ein Einfamilienhaus?
**Slug:** `was-kostet-eine-photovoltaikanlage-einfamilienhaus`
**Stand der Fassung:** 2026-08-03 · **Version:** 1

## Meta (`guide.meta`)

```json
{
  "meta_title": "Photovoltaikanlage: Was kostet sie fürs Einfamilienhaus?",
  "meta_description": "Kosten einer Photovoltaikanlage für das Einfamilienhaus: Preisspanne je kWp, Nebenkosten und Einspeisevergütung. Stand August 2026.",
  "primary_keyword": "Photovoltaikanlage Kosten"
}
```

## Fakten-Set (`guide.deep_research`, Auszug, alle Werte Platzhalter)

| key | label | value | unit | valid_from | source |
|---|---|---|---|---|---|
| pv_preis_je_kwp_min | Anlagenpreis je kWp, untere Spanne | 1.300 | EUR/kWp | 2026-06-01 | Muster-Marktübersicht |
| pv_preis_je_kwp_max | Anlagenpreis je kWp, obere Spanne | 1.900 | EUR/kWp | 2026-06-01 | Muster-Marktübersicht |
| pv_einspeiseverguetung_10kwp | Einspeisevergütung Teileinspeisung bis 10 kWp | 7,9 | ct/kWh | 2026-02-01 | Muster-Netzagentur |
| pv_speicher_preis_je_kwh | Batteriespeicher je kWh nutzbarer Kapazität | 600 bis 1.000 | EUR/kWh | 2026-06-01 | Muster-Marktübersicht |
| pv_mwst_satz | Umsatzsteuer auf Anlagen bis 30 kWp | 0 | % | 2023-01-01 | Muster-Bundesregierung |

Quellen (`guide_sources`): Muster-Netzagentur (`official`), Muster-Bundesregierung (`official`),
Muster-Marktübersicht des Branchenverbands (`trade`).

## Kurzantwort (`guide.short_answer`)

Eine Photovoltaikanlage für ein Einfamilienhaus kostet nach der Muster-Marktübersicht
(Stand 06/2026) zwischen 1.300 und 1.900 Euro je Kilowatt Peak. Bei 10 kWp sind das
13.000 bis 19.000 Euro, ein Batteriespeicher kommt on top. Was deine Anlage tatsächlich
kostet, hängt von Dach, Größe und Speicher ab.

## Key-Facts (`guide_article_details.key_facts_json`)

- Anlagenpreis: 1.300 bis 1.900 EUR je kWp (Stand 06/2026)
- Umsatzsteuer: 0 % bei Anlagen bis 30 kWp
- Einspeisevergütung Teileinspeisung bis 10 kWp: 7,9 ct/kWh (seit 02/2026)
- Batteriespeicher: 600 bis 1.000 EUR je kWh (Stand 06/2026)

## Gliederung (`guide_topics.outline_json`)

1. `s1` Was kostet eine Photovoltaikanlage je Kilowatt Peak?
2. `s2` Welche Nebenkosten kommen dazu?
   - `s2-1` Batteriespeicher
3. `s3` Was zahlst du für Steuer und Anmeldung?
4. `s4` Was bekommst du für den eingespeisten Strom?
5. `s5` Wovon hängt dein Preis ab?

(„Häufige Fragen" folgt als FAQ-Block, nicht als Teil von `outline_json`.)

## Abschnitte (`guide.write_section`)

### Was kostet eine Photovoltaikanlage je Kilowatt Peak? {#s1}

```html
<p>Für eine Aufdachanlage auf dem Einfamilienhaus zahlst du nach der Muster-Marktübersicht
(Stand 06/2026) zwischen 1.300 und 1.900 Euro je Kilowatt Peak (kWp) inklusive Montage.</p>
<p>Ein Einfamilienhaus braucht häufig eine Anlage von etwa 8 bis 10 kWp. Bei 10 kWp
ergibt das eine Spanne von 13.000 bis 19.000 Euro. Nimm diese Werte als Richtwert: Ein
Angebot vom Fachbetrieb nach Aufmaß ersetzt die Spanne.</p>
```

### Welche Nebenkosten kommen dazu? {#s2}

```html
<p>Zum Anlagenpreis kommen je nach Haus Gerüst, Zählerschrankumbau und Netzanmeldung. Frag
im Angebot nach, welche dieser Posten enthalten sind, damit du Angebote vergleichen kannst.</p>
<h3 id="s2-1">Batteriespeicher</h3>
<p>Ein Batteriespeicher kostet nach der Muster-Marktübersicht (Stand 06/2026) 600 bis
1.000 Euro je Kilowattstunde nutzbarer Kapazität. Ob sich ein Speicher lohnt, hängt von
deinem Verbrauch am Abend und in der Nacht ab.</p>
```

### Was zahlst du für Steuer und Anmeldung? {#s3}

```html
<p>Für Anlagen bis 30 kWp gilt nach der Muster-Bundesregierung (seit 01/2023) ein
Umsatzsteuersatz von 0 Prozent auf Lieferung und Montage.</p>
<p>Melde die Anlage beim Netzbetreiber und im Marktstammdatenregister an. Dein Installateur
übernimmt das meist für dich.</p>
```

### Was bekommst du für den eingespeisten Strom? {#s4}

```html
<p>Für Strom, den du bei Teileinspeisung ins Netz gibst, zahlt der Netzbetreiber laut
Muster-Netzagentur (seit 02/2026) 7,9 Cent je Kilowattstunde für Anlagen bis 10 kWp.</p>
<p>Die Vergütung gilt ab Inbetriebnahme für einen festen Zeitraum. Später gebaute Anlagen
bekommen den dann gültigen Satz.</p>
```

### Wovon hängt dein Preis ab? {#s5}

```html
<ul>
  <li>Dachform und Zugänglichkeit: Ein Gerüst und ein aufwendiger Dachanschluss erhöhen den Preis.</li>
  <li>Anlagengröße: Größere Anlagen sind je kWp oft günstiger.</li>
  <li>Speicher und Wallbox: Beides erhöht die Gesamtsumme deutlich.</li>
</ul>
<p>Hol dir mehrere Angebote von Fachbetrieben ein und vergleiche sie nach Preis je kWp
und Leistungsumfang.</p>
```

## Häufige Fragen (`guide.faq` → `faq_json`)

```json
[
  {"question": "Muss ich auf eine Photovoltaikanlage Umsatzsteuer zahlen?",
   "answer": "Für Anlagen bis 30 kWp gilt nach der Muster-Bundesregierung (seit 01/2023) ein Steuersatz von 0 Prozent auf Lieferung und Montage."},
  {"question": "Lohnt sich ein Batteriespeicher?",
   "answer": "Das hängt von deinem Stromverbrauch am Abend ab. Ein Speicher kostet laut Muster-Marktübersicht (Stand 06/2026) 600 bis 1.000 Euro je kWh. Lass dir die Wirtschaftlichkeit für dein Haus vom Fachbetrieb rechnen."},
  {"question": "Wie viel bekomme ich für eingespeisten Strom?",
   "answer": "Bei Teileinspeisung bis 10 kWp zahlt der Netzbetreiber laut Muster-Netzagentur seit 02/2026 7,9 Cent je kWh."},
  {"question": "Wer meldet die Anlage an?",
   "answer": "Die Anmeldung beim Netzbetreiber und im Marktstammdatenregister übernimmt meist der Installateur. Du bleibst als Betreiber verantwortlich."}
]
```

## Pflicht-Hinweis (YMYL, Styleguide `solar-pv`)

Förderbedingungen und Vergütungssätze können sich ändern. Die Angaben ersetzen keine
individuelle Wirtschaftlichkeitsberechnung durch einen Fachbetrieb oder Steuerberater.
Renditeversprechen geben wir nicht.

## Quellen (`guide_sources`, sichtbar am Artikelende)

1. Muster-Netzagentur: Vergütungssätze (`official`), veröffentlicht 2026-01-15
2. Muster-Bundesregierung: Umsatzsteuer auf Photovoltaikanlagen (`official`), veröffentlicht 2022-12-20
3. Muster-Branchenverband: Marktübersicht Anlagenpreise (`trade`), veröffentlicht 2026-06-10

## Stand-Zeile und Changelog (`guide_article_details`)

- Stand-Zeile: „Geprüft am 3. August 2026 · Inhalt zuletzt geändert am 3. August 2026"
- `changelog_json`: `[]` (Erstfassung, noch kein Eintrag)
- `last_checked_at` = `content_changed_at` = 2026-08-03
