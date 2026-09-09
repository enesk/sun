# Kurzprotokoll Tageslauf-Abnahme (#81) — 09.09.2026

Abnahme der Definition of Done aus #22. Durchgefuehrt auf der Arbeitsmaschine gegen die
zentrale Datenbank `sun` und die drei freigeschalteten Portale, nicht auf einem Staging-Host.
Das Modell wurde real gerufen (Anthropic), bis das Guthaben des Kontos aufgebraucht war.

## Aufstellung

| Punkt | Wert |
| --- | --- |
| Portale (content:rollout aktiv) | 1 Sanitaer, 3 Handwerker, 23 Apotheke |
| Tagesziel je Portal | 2 |
| Redaktions-Account Rolle owner | abnahme@sun.test (aktiv) |
| Schalter | CONTENT_PIPELINE_ENABLED=true (je Aufruf gesetzt, .env unveraendert) |

## 1. Kette und Zeiten

`schedule:list` deckt sich mit der Vorgabe: 02:00 discover, 03:00 select, 03:30 generate,
alle 30 Minuten watchdog, 20:00 Tagesbericht — alle mit `--queue`.
Ausgefuehrt wurden Quellenlauf, discover, select und generate je Portal; die Fachjobs lief
ein Queue-Worker ab (Protokolle in `storage/app/abnahme/81/00-sources.log` bis `04-queue.log`).
Laufzeiten aus dem Queue-Protokoll: GenerateDraftJob 2:02 bis 2:22 min, QualityCheckJob
1:06 bis 1:36 min je Entwurf.

## 2. Zahlen je Portal (Anlagetag 2026-09-09)

| Portal | Ziel | Entwuerfe | veroeffentlicht | Score-Spanne | Kosten USD |
| --- | --- | --- | --- | --- | --- |
| Sanitaer | 2 | 5 | 2 (eine URL, siehe unten) | 67,4 – 88,8 | 0,8334 |
| Handwerker | 2 | 3 | 0 | 80,8 – 87,3 | 0,6747 |
| Apotheke | 2 | 4 | 0 | 67,9 – 82,1 | 1,1209 |

Summe der Entwurfskosten 2,6290 USD; das Kostenbuch weist fuer den Tag 4,32 USD aus
(12,3 Prozent des Tagesbudgets), Differenz sind Qualitaetsgate und Korrekturlaeufe.
Kosten je Entwurf 0,2023 bis 0,4499 USD.

Erreicht wurde das Tagesziel bei keinem Portal automatisch: von 12 Entwuerfen kamen drei
ueber die Freigabeschwelle 85 (88,8 / 88,2 / 87,3), alle uebrigen landeten in der Pruef-Queue.
`quality.fix_runs` steht bei elf von zwoelf Entwuerfen auf 1, der Korrekturlauf greift also.

## 3. Tagesbericht

Gebaut mit `content:report:daily --date=2026-09-09 --no-mail`, gerendert nach
`storage/app/abnahme/81/tagesbericht-2026-09-09.html` (25,7 kB).
Enthalten sind alle sechs geforderten Angaben: Portale mit veroeffentlicht/Ziel, Titel mit
URL, Score, Kosten je Artikel, Kosten Tag und Monat mit Budgetanteil, Block „Offene Alarme"
und Providerstatus (11 Provider, `foerderdatenbank` und `rss_feeds` als verzoegert).
Drei Maengel des Berichts sind als eigene Tickets erfasst (Portalliste, Kostenspalte,
Doppelzaehlung der Kindfassung, Providerstatus bei erschoepftem Guthaben).

## 4. Slot bewusst scheitern lassen

Ohne Reserve-Kandidat, Wachhund mit `--force` ueber alle drei Portale:

- je Portal beide Slots als verloren gemeldet (`exhausted: [1,2]`), keine Wiederholung ohne Reserve,
- sechs Alarme `slot_exhausted`, Stufe critical, je Slot genau einer,
- sechs Mails `ContentAlertRaised` an abnahme@sun.test eingereiht (die Mailable ist
  `ShouldQueue`, der Nachweis laeuft deshalb ueber `Mail::assertQueued`, nicht `assertSent`),
- Wiederholung des Alarms zaehlt nur `occurrences` hoch, keine zweite Mail,
- das Stoerungsband der Uebersicht zeigt den Alarm als `level: failed` mit dem Klartext
  („Fuer Handwerker fehlt der 2. Artikel des Tages …"), festgehalten in
  `storage/app/abnahme/81/stoerungsband-2026-09-09.json`.

Die Drei-Versuche-Strecke selbst (`watchdog.max_attempts`) liess sich nicht durchfahren:
jeder Anlauf braucht einen echten Generierungsversuch, und dafuer fehlte das Guthaben.
Alle Testalarme wurden nach der Pruefung wieder geloescht, die Alarmtabelle ist leer.

## 5. Nachhol-Weg

`php artisan content:daily --tenant=1 --date=2026-09-08 --stage=publish` meldet
„Veroeffentlichung angestoszen", `--stage=watchdog --force` meldet „belegt: -, nachgezogen: -,
aufgegeben: 1,2". Der Nachholweg arbeitet also auf dem angegebenen Tag, nicht auf heute.
Die Stufen discover und generate sind ohne Modellguthaben nicht nachholbar.

## Nicht abgenommen

Der ununterbrochene Kalendertag ohne manuellen Eingriff auf einem Staging-Host mit Cron und
Horizon steht aus; er scheitert nicht an der Software, sondern am aufgebrauchten Guthaben des
Anthropic-Kontos (`discover` bricht mit „credit balance is too low" ab) und am fehlenden
Staging-Zugang. Das ist als eigenes Ticket erfasst und gehoert in die Go-Live-Woche (#89).
