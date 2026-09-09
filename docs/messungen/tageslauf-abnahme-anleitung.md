# Tageslauf-Abnahme über einen Kalendertag — Durchführungsanleitung und Protokoll

Status: **verbindlich für die Ausführung**
Ticket: #105 (Rest aus #81), gehört in die Go-Live-Woche #89
Vorbefund: `tageslauf-abnahme-2026-09-09.md`
Letzte Änderung: 2026-09-09

Kein Schritt dieser Anleitung ist aus dem Repository heraus ausführbar. Sie braucht
das Anthropic-Konto, einen Staging- oder Produktionshost mit Cron und Horizon und
das Postfach des Owner-Accounts. Die Anleitung sagt, in welcher Reihenfolge gearbeitet
wird, welche Probe jeden Schritt abschließt und wie das Ergebnis festgehalten wird.

---

## 0. Voraussetzungen vor dem Abnahmetag

| Punkt | Prüfung | Muss |
| --- | --- | --- |
| Modellguthaben | `CONTENT_PIPELINE_ENABLED=true php artisan content:llm:ping` | antwortet ohne 400 |
| Freigeschaltete Portale | `php artisan content:rollout` | genau drei, davon ein YMYL-Portal |
| Reservethemen je Portal | siehe Abschnitt 6 | mindestens 8 je Portal |
| Owner-Account | Rolle `owner`, echtes erreichbares Postfach | Berichts- und Alarmmail kommen an |
| Zählregeln | #102, #103/#113, #104 erledigt | sonst Auswertung von Hand bereinigen |
| Zeitzone des Hosts | `php artisan tinker --execute="echo config('app.timezone');"` | passt zu den Uhrzeiten der Kette |

Sind #102, #103/#113 oder #104 am Abnahmetag noch offen, ist die Tabelle aus
Abschnitt 4 trotzdem gültig — dann aber von Hand bereinigen: nicht freigeschaltete
Portale aus der Berichtszeile streichen und jede Kindfassung (`parent_draft_id`
gesetzt) aus `veröffentlicht` herausnehmen, ihre Kosten aber stehen lassen.

## 1. Guthaben aufladen

20 USD genügen für einen Abnahmetag plus Reserve. Rechengrundlage aus der Messung
vom 09.09.2026: 0,20 bis 0,45 USD je Entwurf, rund 4,32 USD für drei Portale mit
zwölf Entwürfen. Probe: `content:llm:ping` antwortet, danach zeigt die Übersicht des
Content-Panels für Anthropic keinen Störungshinweis mehr.

## 2. Host vorbereiten

- `CONTENT_PIPELINE_ENABLED=true` in der `.env` des Hosts, danach `config:cache` neu bauen.
- Cron ruft `schedule:run` minütlich. Probe: `php artisan schedule:list` zeigt
  02:00 discover, 03:00 select, 03:30 generate, alle 30 Minuten watchdog, 20:00 Bericht.
- Horizon läuft mit dem `staging`-Block aus `config/horizon.php`:
  `supervisor-content-sources` (2), `supervisor-content-generate` (4),
  `supervisor-content-publish` (1). Probe: `php artisan horizon:status` meldet
  „running", und kein Worker steht auf FATAL.
- Ab hier bis zum Ende des Kalendertages **kein Eingriff**. Jeder manuelle Aufruf
  entwertet die Abnahme; Beobachtung nur lesend über Panel, Horizon und Logdatei.

## 3. Der Kalendertag

Der Tag gilt als abgenommen, wenn die Kette ohne Eingriff durchläuft und der Bericht
um 20:00 im Postfach liegt. Ein leerer Slot ist kein Abbruch, sondern ein Befund —
er gehört mit Grund in die Tabelle.

## 4. Protokoll am Folgetag

Je Portal ausfüllen, Vorlage übernommen aus `tageslauf-abnahme-2026-09-09.md`:

| Portal | Ziel | Entwürfe | veröffentlicht | Zeitpunkte (Slot 1 / Slot 2) | Score-Spanne | Kosten USD |
| --- | --- | --- | --- | --- | --- | --- |
| Sanitär | 2 | | | | | |
| Handwerker | 2 | | | | | |
| Apotheke | 2 | | | | | |

Dazu in Fließtext: Summe der Entwurfskosten gegen den Tageswert des Kostenbuchs,
Anteil am Tagesbudget, Zahl der Entwürfe mit `quality.fix_runs = 1`, Zahl der
Entwürfe in der Prüf-Queue und jeder Alarm des Tages mit Uhrzeit.

Zwei Fallen bei den Zeitpunkten:
- Die Uhrzeit einer Aktualisierung kommt aus `updated_at ?? created_at`. Das
  `published_at` einer Kindfassung ist das Erstveröffentlichungsdatum und gehört
  nie in die Zeitspalte.
- Aktualisierungen zählen in die Kostenspalte, nicht in `veröffentlicht`. Sie
  bekommen eine eigene Zeile unter der Tabelle.

## 5. Die zwei Bildschirmfotos

**Bild 1 — Störungsband der Übersicht.** Aufgenommen im Content-Panel, Seite
Übersicht, angemeldet als Owner. Sichtbar sein müssen: der gesamte Störungsstapel
von oben bis zum letzten Band (höchstens drei), je Band die Stufe an Farbe und
Symbol, der deutsche Klartext samt Portalnamen und die Portalkacheln darunter mit
Punktestand und der Marke „Tagesziel gefährdet", falls gesetzt. Aufnahme in
Desktopbreite ab 1280 px, Browserzoom 100 Prozent, heller Modus, ohne
Entwicklerwerkzeuge im Bild. Ist der Tag störungsfrei, wird der Leerzustand des
Bandes fotografiert — auch das ist ein gültiger Beleg, muss aber im Text so benannt
werden.

**Bild 2 — Berichtsmail.** Aufgenommen im Postfach des Owner-Accounts, nicht aus
einer gerenderten HTML-Datei. Sichtbar sein müssen: Absender, Betreff mit Datum,
Empfängeradresse und der Anfang des Berichts bis einschließlich der Portalzeile
„veröffentlicht von Ziel". Ein zweites Bild oder ein Scrollbild ergänzt den Block
„Offene Alarme" und den Providerstatus. Adresszeile nicht schwärzen, sie ist der
Beleg für den Zustellweg.

Beide Bilder als PNG unter `docs/messungen/` mit Datum im Dateinamen ablegen und im
Protokoll verlinken.

## 6. Die Drei-Versuche-Strecke des Wachhunds

Nachweis: nach `content.pipeline.watchdog.max_attempts` (Vorgabe 3) Versuchen
entsteht je Slot **genau ein** Alarm `slot_exhausted`, und weitere Läufe zählen nur
`occurrences` hoch. Der Weg „Reserve leeren" deckt das nicht ab — er meldet
`no_reserve` schon beim ersten Lauf.

**Korrektur zur Aufgabenstellung: Budget 0 ist der falsche Hebel.**
`BudgetGuard::assertBelow()` steigt bei `$limit <= 0.0` ohne Prüfung aus — eine 0
schaltet die Grenze also *ab* statt sie zu schließen. Ebenso wenig taugt das
Tagesbudget (`SCOPE_DAILY`), weil es den Provider für alle Portale pausiert und den
Providerstatus verfälscht.

Richtiger Hebel ist die Portalgrenze, weil sie mit `pauses: false` prüft:

1. Die Strecke **nach** dem Abnahmetag fahren, auf demselben Kalendertag oder einem
   Tag, an dem das Portal bereits Kosten gebucht hat. `assertBelow` wirft erst, wenn
   `spent >= limit`; bei 0,00 USD Tagesverbrauch läuft jede positive Grenze durch und
   es entstehen echte Modellkosten.
2. `CONTENT_BUDGET_DAILY_USD_PER_TENANT=0.01` setzen, `config:cache` neu bauen.
   Jeder Generierungsversuch bricht dann sofort mit `BudgetExceededException`
   (`SCOPE_TENANT`) ab, vor dem ersten Modellaufruf — die Strecke kostet nichts.
3. Vorrat prüfen: der Wachhund zieht **je Versuch** einen neuen Reservekandidaten.
   Für zwei Slots mal drei Versuche braucht das Portal sechs freie Reservethemen des
   Tages. Sind sie vorher alle, kippt der Nachweis in den bereits abgenommenen
   `no_reserve`-Fall.
4. Drei Wachhundläufe abwarten oder mit `content:daily --stage=watchdog --force`
   auslösen. Erwartung: Lauf 1 und 2 melden `nachgezogen: 1,2`, Lauf 3 meldet
   `aufgegeben: 1,2`.
5. Belegen: je Slot ein Datensatz in den Alarmen mit `slot_exhausted`, Stufe
   critical, `meta.attempts = 3` und `no_reserve = false`; ein vierter Lauf erhöht nur
   `occurrences` und erzeugt keine zweite Mail. Die Mails laufen über
   `Mail::assertQueued`, nicht `assertSent`.
6. Die gescheiterten Entwürfe tragen `status = failed` und im Qualitätsbericht
   `generation.failed_reason = budget_exceeded` mit dem richtigen Slot. Prüfen, dass
   die Slotzuordnung stimmt — der Wachhund zählt die Versuche über
   `quality_report_json.generation.slot`.
7. Nach dem Nachweis: Portalgrenze auf den alten Wert, `config:cache` neu bauen,
   Testalarme und Testentwürfe löschen, Alarmtabelle leer nachweisen.
