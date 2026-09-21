# Musterartikel mit simuliertem Update — Referenz für `update_section` und `change_summary`

> **Hinweis:** Alle Zahlen und Quellen sind **erfundene Platzhalter** wie in
> `guide-musterartikel.md`, von dem dieses Dokument die Fassung 2 ableitet.

**Frage:** Was kostet eine Photovoltaikanlage für ein Einfamilienhaus?
**Lauf:** `mode = update`, Datum 2026-09-21, Fassung 1 (Stand 2026-08-03) → Fassung 2

## 1. Auslöser (`guide.freshness_probe`)

```json
{
  "changed": true,
  "reason": "Die Muster-Netzagentur hat neue Vergütungssätze zum 01.09.2026 veröffentlicht.",
  "candidate_changes": [
    {"key": "pv_einspeiseverguetung_10kwp", "old_value": "7,9", "new_value": "7,6",
     "source_url": "https://example.org/muster-netzagentur/verguetung-2026-09", "published_at": "2026-08-28"}
  ],
  "confidence": 0.9
}
```

Die Tiefenrecherche bestätigt den Wert; `guide_facts` bekommt `pv_einspeiseverguetung_10kwp`
= 7,6 ct/kWh, `valid_from` = 2026-09-01. Der Fakt ist in Abschnitt `s4` und im FAQ
verwendet. `facts_hash` ändert sich, `AffectedSectionsResolver` (#9) meldet `s4`
(Gliederung, `fact_keys`) sowie den FAQ-Block und die Key-Facts. `s1`, `s2`, `s2-1`,
`s3` und `s5` bleiben unberührt und werden nicht an das Modell gegeben.

## 2. Aufruf `guide.update_section` für `s4`

`{{changed_facts}}`:

```json
[{"key": "pv_einspeiseverguetung_10kwp", "label": "Einspeisevergütung Teileinspeisung bis 10 kWp",
  "old_value": "7,9", "new_value": "7,6", "unit": "ct/kWh", "valid_from": "2026-09-01",
  "source_url": "https://example.org/muster-netzagentur/verguetung-2026-09"}]
```

`{{existing_section_html}}` (Fassung 1):

```html
<p>Für Strom, den du bei Teileinspeisung ins Netz gibst, zahlt der Netzbetreiber laut
Muster-Netzagentur (seit 02/2026) 7,9 Cent je Kilowattstunde für Anlagen bis 10 kWp.</p>
<p>Die Vergütung gilt ab Inbetriebnahme für einen festen Zeitraum. Später gebaute Anlagen
bekommen den dann gültigen Satz.</p>
```

Ausgabe:

```json
{
  "section_id": "s4",
  "changed": true,
  "html": "<p>Für Strom, den du bei Teileinspeisung ins Netz gibst, zahlt der Netzbetreiber laut Muster-Netzagentur (seit 09/2026) 7,6 Cent je Kilowattstunde für Anlagen bis 10 kWp.</p>\n<p>Die Vergütung gilt ab Inbetriebnahme für einen festen Zeitraum. Später gebaute Anlagen bekommen den dann gültigen Satz.</p>",
  "edited_sentences": [
    {"old": "Für Strom, den du bei Teileinspeisung ins Netz gibst, zahlt der Netzbetreiber laut Muster-Netzagentur (seit 02/2026) 7,9 Cent je Kilowattstunde für Anlagen bis 10 kWp.",
     "new": "Für Strom, den du bei Teileinspeisung ins Netz gibst, zahlt der Netzbetreiber laut Muster-Netzagentur (seit 09/2026) 7,6 Cent je Kilowattstunde für Anlagen bis 10 kWp.",
     "fact_key": "pv_einspeiseverguetung_10kwp"}
  ]
}
```

Wortgleichheits-Prüfung: Der zweite Absatz ist zeichengleich zur Fassung 1; im ersten
Absatz unterscheiden sich nur „02/2026" → „09/2026" und „7,9" → „7,6". Weicht ein Satz ohne
Bezug zu `changed_facts` ab, lehnt der Qualitätsgate (#11) die Änderung ab.

## 3. Angepasste Bausteine außerhalb von `s4`

FAQ-Antwort 3 (`faq_json`, nur diese Antwort ändert sich):

```json
{"question": "Wie viel bekomme ich für eingespeisten Strom?",
 "answer": "Bei Teileinspeisung bis 10 kWp zahlt der Netzbetreiber laut Muster-Netzagentur seit 09/2026 7,6 Cent je kWh."}
```

Key-Facts (Zeile 3): „Einspeisevergütung Teileinspeisung bis 10 kWp: 7,6 ct/kWh (seit 09/2026)".
Die Kurzantwort nennt den Wert nicht und bleibt unverändert. Meta-Description
(„Stand August 2026") wird auf „Stand September 2026" gesetzt, weil sich der Inhalt geändert hat.

## 4. Changelog-Eintrag (`guide.change_summary`)

```json
{
  "summary": "Die Einspeisevergütung für Teileinspeisung bis 10 kWp sinkt laut Muster-Netzagentur ab dem 1. September 2026 von 7,9 auf 7,6 Cent je Kilowattstunde.",
  "changes": [
    {"key": "pv_einspeiseverguetung_10kwp", "label": "Einspeisevergütung Teileinspeisung bis 10 kWp",
     "old_value": "7,9", "new_value": "7,6"}
  ],
  "section_ids": ["s4"]
}
```

Wird als Eintrag in `guide_article_details.changelog_json` (neuester zuerst) abgelegt,
ergänzt um das Datum des Laufs:

```json
[
  {"date": "2026-09-21",
   "summary": "Die Einspeisevergütung für Teileinspeisung bis 10 kWp sinkt laut Muster-Netzagentur ab dem 1. September 2026 von 7,9 auf 7,6 Cent je Kilowattstunde.",
   "changes": [{"key": "pv_einspeiseverguetung_10kwp", "old_value": "7,9", "new_value": "7,6"}],
   "section_ids": ["s4"],
   "version": 2}
]
```

`guide_article_versions.change_summary` = `summary`, `version` = 2.

## 5. Stand-Zeile nach dem Update

- Vorher: „Geprüft am 3. August 2026 · Inhalt zuletzt geändert am 3. August 2026"
- Nachher: „Geprüft am 21. September 2026 · Inhalt zuletzt geändert am 21. September 2026"
- `last_checked_at` und `content_changed_at` = 2026-09-21; nur `content_changed_at`
  wird Sitemap-`lastmod`. Hätte der Lauf keine Änderung ergeben, wäre nur
  `last_checked_at` gesetzt und kein Changelog-Eintrag angelegt worden.

## 6. Rubrik-Ergebnis Fassung 2 (`guide.quality_rubric`, gekürzt)

```json
{"score": 92,
 "per_criterion": [
   {"key": "belege", "points": 25, "max_points": 25, "comment": "Alle Zahlen mit Quelle und Stand."},
   {"key": "faktentreue", "points": 20, "max_points": 20, "comment": "Werte entsprechen dem Fakten-Set."},
   {"key": "aktualitaet", "points": 10, "max_points": 10, "comment": "Vergütung mit neuem Stand 09/2026."},
   {"key": "suchintention", "points": 9, "max_points": 10, "comment": "Kosten früh genannt."},
   {"key": "sprache", "points": 9, "max_points": 10, "comment": "Du-Ansprache, kurze Absätze."},
   {"key": "neutralitaet", "points": 10, "max_points": 10, "comment": "Keine Werbesprache."},
   {"key": "ymyl", "points": 5, "max_points": 10, "comment": "Pflicht-Hinweis vorhanden, aber nur am Artikelende."},
   {"key": "struktur", "points": 4, "max_points": 5, "comment": "FAQ und Kurzantwort vorhanden."}],
 "blocking_issues": [],
 "fix_instructions": [{"section_id": "artikel", "instruction": "Pflicht-Hinweis zusätzlich unter der Kurzantwort platzieren."}]}
```
