# Abnahme Refresh-Loop (#24) — Durchlauf mit echtem Modellaufruf, Ticket #86

Datum: 2026-09-09. Durchgefuehrt lokal auf Mandant 1 („Sanitär",
`sanitaer.test`), weil kein Staging-Host existiert (`deploy.php` traegt noch
`1.2.3.4`; Einrichtung liegt in #90, Abschnitt 1). Die Modellaufrufe waren
echt (Anthropic, Vorlage `refresh_update`), die Ausloeser von Hand gesetzt.

## Ausgangslage

Entwurf 13, Artikel 19, Slug `heizungstausch-forderung-in-bayern-was-gilt-2026`,
acht Abschnitte s1–s8. Von Hand gesetzt: `published_at` 45 Tage zurueck sowie
`needs_refresh` mit einem Leistungsabfall (Position 6,28 → 12,72; CTR 8 % →
3 %), also genau das Bild, das der Metrik-Collector (#23) schreiben wuerde.
Der Weg ueber abgelaufene Belege war nicht moeglich: die beiden Belege des
Artikels (117, 118) tragen kein `valid_until`.

## Ablauf und Befund

| Schritt | Befehl | Ergebnis |
| --- | --- | --- |
| Auswahl | `content:refresh:run --tenant=1 --draft=13 --sync` | ein Kandidat, ein Zielabschnitt (s1), Anweisung aus dem Leistungsabfall |
| Aktualisierung | Vorlage `refresh_update`, ein Aufruf | 0,0185 USD, 8,0 s, neue Fassung 28 mit `parent_draft_id` 13 |
| Qualitaetsgate | `content:quality:check --tenant=1 --draft=28 --sync` | 76,2 von 100, Schwelle 85 → manuelle Pruefung, ein automatischer Korrekturlauf |
| Freigabe/Livegang | Freigabe von Hand, `ScheduleAndPublishJob`, `PublishDraftJob` | Fassung 28 live unter derselben URL |

Geprueft und bestanden:

- Titel und Slug identisch, `article_id` 19 unveraendert, URL unveraendert.
- Der Refresh-Lauf selbst aendert nur s1 (123 → 111 Woerter), s2–s8 Byte fuer
  Byte gleich. Die zwei Quellen sind an die neue Fassung mitkopiert.
- `changelog_json`: ein Eintrag, `at` beim Erzeugen leer, vom Publisher auf den
  Livegang gesetzt, Anlass „Sichtbarkeit rückläufig", Abschnitt s1.
- Elternfassung: `needs_refresh` zurueckgesetzt, `refreshed_by_draft_id` 28.
- Oeffentliche Seite: „Aktualisiert am 9. September 2026" steht unter der
  Autorenbox; JSON-LD `Article` traegt `datePublished` 06:38:37 und
  `dateModified` 11:50:25; `sitemap-ratgeber.xml` `lastmod` 11:50:24;
  IndexNow HTTP 202 fuer Artikel- und Uebersichts-URL; Fingerprint 9 neu
  registriert.

## Kosten: die Annahme „rund ein Viertel" traegt nicht

| Posten | Neuer Artikel (Entwurf 13) | Aktualisierung (Fassung 28) |
| --- | --- | --- |
| Erzeugung | 0,2135 USD (16 Aufrufe) | 0,0185 USD (1 Aufruf) |
| Qualitaetsgate inkl. Korrekturlauf | 0,1574 USD | 0,1418 USD |
| Summe | 0,3709 USD | 0,1602 USD |

Die Aktualisierung kostet 43 Prozent eines neuen Artikels, nicht 25. Der
Schreibschritt ist mit 5 Prozent der Artikelkosten sogar guenstiger als
erwartet; teuer ist das Qualitaetsgate, das die volle Bewertung plus einen
Korrekturlauf ueber vier Abschnitte faehrt — darunter drei, die der Refresh
bewusst nicht angefasst hatte. Nach dem Gate unterscheiden sich s1, s5, s6 und
s8 von der Elternfassung.

## Was nur auf Staging zu pruefen bleibt

Alles, was eine oeffentlich erreichbare Domain braucht: der IndexNow-Ping ging
hier mit `sanitaer.test`-URLs raus, die Sitemap zeigt auf dieselbe Testdomain,
und eine Indexierungspruefung bei Google ist damit nicht moeglich. Diese Reste
haengen an #90, Abschnitt 1 (Staging-Host benennen).

## Nachtrag (#86, zweiter Anlauf): Weg ueber abgelaufene Belege

Der erste Anlauf konnte nur den Leistungsabfall pruefen. Nachgeholt am
2026-09-09, ohne Modellaufruf: Beleg 117 („Zuschuss Prozent", 15 %) fuer die
Dauer der Probe auf `valid_until` = heute minus drei Tage gesetzt und
`published_at` der Fassung 28 um 45 Tage zurueckdatiert, danach beides
zurueckgesetzt.

| Probe | Ergebnis |
| --- | --- |
| `RefreshSelector::candidateFor(28)` | Grund `stale_fact` mit Beleg 117, `valid_until` 2026-09-06 |
| `sectionsToRefresh()` | genau ein Zielabschnitt s1 — der Abschnitt, in dem die 15 % stehen; Anweisung nennt Beleg, Wert, Stand und Ablaufdatum |
| `RefreshSelector::candidates(3)` | ein Kandidat |
| `remainingToday()` | 2 von 3 (Fassung 28 des Tages ist verbraucht) |

## Befund: der Tageslauf brach beim Belegweg mit einem SQL-Fehler ab

`RefreshSelector::pool()` filterte die Kandidaten mit
`orWhereJsonOverlaps('outline_json->fact_snippet_ids', ...)`. Laravel haengt
den JSON-Pfad als dritten Parameter an `JSON_OVERLAPS`, die Funktion nimmt
aber genau zwei — MySQL antwortet mit Fehler 1582 („Incorrect parameter count
in the call to native function 'json_overlaps'"). Wirkung: sobald irgendein
Beleg des Mandanten abgelaufen war, brach `content:refresh:run` ohne `--draft`
ab, noch vor dem ersten Kandidaten. Der Handbetrieb mit `--draft` lief, weil
`candidateFor()` die Vorauswahl nicht braucht — genau deshalb blieb der Fehler
im ersten Anlauf unsichtbar.

Behoben in `app/Content/Services/RefreshSelector.php`: der Pfad steht jetzt in
`JSON_EXTRACT`, die Ueberdeckung prueft `json_overlaps(json_extract(...), ?)`
mit der Beleg-Liste als gebundener JSON-Parameter. Danach liefert
`candidates(3)` den Kandidaten. Sonst nichts geaendert; `vendor/bin/pint` und
`vendor/bin/phpstan analyse` auf der Datei sind sauber.
