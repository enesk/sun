# Sichtprüfung Go-Live — Leitfaden und Abnahmeprotokoll

Status: **verbindlich für die manuelle Abnahme**
Tickets: #89 (Ausführung), #26 (Checkliste `docs/content-golive.md`)
Letzte Änderung: 2026-09-09
Verfasst von: Rathana (UI/UX-Konzept)

Dieser Leitfaden operationalisiert die mit **(manuell)** markierten Zeilen der
Abschnitte 5 und 6 von `docs/content-golive.md`. Er ersetzt keine Zeile dort,
sondern sagt, woran ein Mensch „bestanden" erkennt. Ohne diese Festlegung
entscheidet jede Sichtung anders, und die Abnahme ist nicht wiederholbar.

Alle Zahlen und Schwellen sind Vorgaben, keine Vorschläge.

---

## 1. Wer sichtet was, wann

| Prüfung | Umfang | Zeitpunkt | Aufwand |
| --- | --- | --- | --- |
| Tagessichtung | alle Artikel des Tages, Kurzform (§3) | täglich nach dem Tagesbericht (20:00) | ca. 3 min je Artikel |
| Stichprobe | 10 Artikel, Langform (§4 und §5) | einmal am Ende der Woche 1 | ca. 12 min je Artikel |
| Abnahme | Zählwerte aus dem Tagesbericht (§6) | Ende Woche 1, Ende erster voller Tag Woche 2 | ca. 20 min |

Die Tagessichtung findet in der Prüf-Queue des Content-Panels statt, auch für
bereits automatisch veröffentlichte Artikel. Die Stichprobe findet auf der
**öffentlichen Portalseite** statt, nicht in der Vorschau — nur dort sieht man
Ladeverhalten, Werbeplätze und Regionalblock so, wie ein Leser sie sieht.

### Auswahl der 10 Stichprobenartikel

Nicht frei greifen. Zusammensetzung:

- 3 Artikel des YMYL-Portals, davon mindestens 1 mit Punktzahl zwischen 90 und 93
  (knapp über der YMYL-Schwelle — dort steht die Qualität am dünnsten).
- 4 Artikel der beiden Handwerksportale mit Punktzahl zwischen 85 und 88.
- 2 Artikel mit `quality.fix_runs = 1` (der Korrekturlauf hat eingegriffen).
- 1 Artikel mit Regionalblock auf Stadtebene.

Fehlt eine Kategorie in der Woche, wird sie durch den nächstniedrigeren
Punktwert ersetzt und das im Protokoll vermerkt.

---

## 2. Bewertung je Prüfpunkt

Drei Stufen, keine Zwischenwerte:

- **ok** — trifft zu, kein Handlungsbedarf.
- **anmerkung** — Mangel ohne Leserisiko. Artikel bleibt online, Befund wird
  gesammelt und am Wochenende zu einem Ticket gebündelt.
- **stopp** — Artikel muss zurückgezogen werden (Panel, `Publisher::unpublish()`).
  Zwei Stopps im selben Portal innerhalb einer Woche heißen:
  `content:rollout --deactivate=<portal>`, bevor weiter erzeugt wird.

---

## 3. Tagessichtung — Kurzform, 8 Punkte

Reihenfolge ist die Lesereihenfolge der Seite, damit die Sichtung ohne Springen
läuft.

1. **Titel und H1 tragen dasselbe Thema.** Der Titel verspricht nichts, was der
   Artikel nicht liefert. → *stopp* bei Themenbruch.
2. **Kurzantwort beantwortet die Titelfrage in den ersten zwei Sätzen.** Keine
   Einleitung vor der Antwort. → *stopp*, wenn die Antwort erst im Hauptteil steht.
3. **Meta-/Transparenzzeile** trägt Datum, Autor und den Aktualisierungshinweis.
   → *anmerkung* bei fehlendem Autor.
4. **Key-Facts-Tabelle** hat mindestens drei Zeilen und keine leere Zelle.
   → *anmerkung*.
5. **Erste zwei Absätze des Hauptteils** ohne Floskelanfang („In der heutigen
   Zeit", „Immer mehr Menschen"). → *anmerkung*.
6. **Regionalblock** nennt den Ort und mindestens eine ortsspezifische Tatsache
   (Förderprogramm, Zuständigkeit, Preisniveau, Ansprechstelle). Ein Block, der
   nur den Ortsnamen einsetzt, ist ein Doorway-Signal. → *stopp*.
7. **FAQ**: mindestens drei Fragen, keine wiederholt die Kurzantwort wörtlich.
   → *anmerkung*.
8. **Quellen-/Aktualitätszeile** vorhanden und jede genannte Quelle erreichbar
   (eine Stichprobe je Artikel genügt). → *stopp* bei toter oder erfundener Quelle.

Blockfolge und Pflichtblöcke nach `design/ratgeber-template.md`. Fehlt ein
Pflichtblock (Kurzantwort, Key-Facts, FAQ, Quellenzeile) vollständig, ist das
immer *stopp*.

---

## 4. Stichprobe A — „keine Zahl ohne Beleg"

Prüfgegenstand ist jede **konkrete Zahl** im Fließtext, in der Key-Facts-Tabelle
und in der Kurzantwort: Beträge, Prozentsätze, Fristen, Mengen, Jahreszahlen mit
Aussagewert. Nicht zu prüfen sind Ordnungszahlen („drei Schritte"), das
Veröffentlichungsdatum und Zahlen in Eigennamen.

Je Zahl gilt sie als belegt, wenn **alle vier** Angaben da sind:

1. Ein Beleg ist zugeordnet (Belegmarke im Entwurf, in der Quellenzeile
   aufgelöst).
2. Die Quelle ist benannt und öffentlich erreichbar.
3. Ein Zeitbezug steht dabei — Stand, Jahr oder Gültigkeitszeitraum.
4. Der Wert in der Quelle stimmt mit dem Wert im Artikel überein (bei
   Rundung: gleiche Größenordnung, Rundungsrichtung nachvollziehbar).

Bewertung je Artikel:

| Befund | Stufe |
| --- | --- |
| alle Zahlen belegt | ok |
| eine Zahl ohne Zeitbezug, Quelle stimmt | anmerkung |
| eine Zahl ohne Quelle | stopp |
| Zahl weicht von der Quelle ab | stopp |

Belegmarken der Form `[F117]` sind **kein** Mangel — sie sind die interne
Zuordnung und in der ausgelieferten Seite aufgelöst (Ticket #76). Wer sie im
Fließtext der öffentlichen Seite sieht, meldet *stopp* wegen Darstellung, nicht
wegen fehlendem Beleg.

Bestanden ist die Stichprobe A, wenn **höchstens 1 von 10** Artikeln eine
Anmerkung trägt und **kein** Artikel ein Stopp.

---

## 5. Stichprobe B — „kein Doorway-Muster"

Doorway heißt: zwei Seiten, die dieselbe Frage beantworten und sich nur im
Ortsnamen unterscheiden. Prüfweg für die 10 Artikel:

1. Artikel nach Themencluster gruppieren. Nur Artikel desselben Clusters können
   ein Doorway-Paar bilden.
2. Je Paar im selben Cluster die **drei ersten Absätze des Hauptteils** und den
   **Regionalblock** nebeneinander lesen.
3. Ein Paar ist ein Doorway, wenn zwei der drei Merkmale zutreffen:
   - Die Absatzfolge ist gleich und unterscheidet sich nur in Orts- oder
     Firmennamen.
   - Der Regionalblock trägt keine ortsspezifische Tatsache (siehe §3 Punkt 6).
   - Key-Facts-Tabelle und FAQ sind inhaltlich identisch.

Ein erkanntes Doorway-Paar ist *stopp* für **beide** Artikel, weil sich nicht
sagen lässt, welcher der ursprüngliche ist.

Bestanden ist die Stichprobe B, wenn **kein** Doorway-Paar gefunden wird. Hier
gibt es keine Toleranz: ein Doorway-Paar in einer Zehnerstichprobe heißt, das
Muster ist im Bestand systematisch, und das Portal wird bis zur Klärung
herausgenommen.

---

## 6. Abnahme Woche 1 und Woche 2

Zahlen kommen aus dem Tagesbericht, nicht aus dem Panel — der Bericht ist das
Dokument, das auch später noch nachweist, was an dem Tag galt.

**Woche 1 bestanden, wenn alle fünf Zeilen zutreffen:**

| Kriterium | Sollwert |
| --- | --- |
| automatisch veröffentlichte Artikel | ≥ 40 von 42 |
| Tagessichtung durchgeführt | an 7 von 7 Tagen |
| Stichprobe A | ≤ 1 Anmerkung, 0 Stopp |
| Stichprobe B | 0 Doorway-Paare |
| Wochenkosten | innerhalb Budget, Panel *Leistung* |

**Woche 2 bestanden, wenn:**

| Kriterium | Sollwert |
| --- | --- |
| aktive Portale | alle |
| voller Tag mit 2 Artikeln je Portal | im Tagesbericht belegt |
| Tagesbericht 20:00 an Enes und Uwe | an 2 aufeinanderfolgenden Tagen zugestellt |
| Tageskosten | innerhalb `CONTENT_BUDGET_DAILY_USD` |

Zwei fehlende Artikel in Woche 1 sind zugelassen, aber zu benennen: Portal, Tag,
Grund aus dem Alarmband der Übersicht.

---

## 7. Protokollvorlage

Je Sichtungstag eine Zeile, je Stichprobenartikel ein Block. Ablage:
`docs/messungen/golive-sichtpruefung-<datum>.md`.

```
## Tagessichtung <Datum>
Portal | Artikel | Punktzahl | fix_runs | Befund (ok/anmerkung/stopp) | Notiz

## Stichprobe <Artikel-URL>
Punktzahl:            <n>       fix_runs: <n>      Portal: <name>
Zahlen im Artikel:    <n> geprüft, <n> ohne Beleg, <n> ohne Zeitbezug
Doorway-Vergleich:    Cluster <name>, verglichen mit <URL>, Ergebnis <kein Doorway / Doorway>
Blockfolge komplett:  ja/nein  (fehlend: ...)
Gesamt:               ok / anmerkung / stopp
Maßnahme:             keine / Ticket / zurückgezogen
```

## 8. Barrierefreiheit — nicht Teil der Artikelabnahme

Kontrast, Fokusreihenfolge und Tastaturbedienung der Ratgeber-Seite sind
Template-Eigenschaften und in #82, #83 und #91 abgehandelt. Sie werden **nicht**
je Artikel geprüft — das würde die Tagessichtung unbrauchbar aufblähen. Einmal
je Portal, bevor das Portal freigeschaltet wird, genügt.
