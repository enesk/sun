# Bot-Schutz: Abnahme der Einstellungen eines Portals (#20)

Letzter Punkt der Definition of Done von #9: Einstellungen im Panel ändern und
die Wirkung im Frontend sehen. Abnahmeportal: **sanitaerfinden.com**
(Prod-Tenant-ID 28, kleinstes Portal laut `docs/turnstile.md` §9).

## Stand 08.10.2026 und verbliebene Handarbeit

- **Maschinenanteil: fertig und grün** (Abschnitt 1, lokal gegen
  `sanitaer.test`, Rückgabe 0). Er deckt die Prüfpunkte 1, 3, 4, 5, 6 und 7 der
  Liste aus #20 vollständig ab.
- **Offen bleibt nur Abschnitt 2 (H1–H10) — Handarbeit von Enes im Browser.**
  Sie geht erst nach dem Rollout (#13): Der Bot-Schutz-Stand ist noch nicht auf
  der Produktion, und Prüfpunkt 2 braucht echte Cloudflare-Schlüssel aus #14
  (das wiederum hängt an #18). Vorher ist H2 zwangsläufig rot.
- Diese Datei ist die Stelle, an der abgehakt wird. Die DoD von #9 gilt für
  diesen Punkt als erfüllt, wenn Abschnitt 1 grün läuft **und** H1–H10
  abgehakt sind.

## Rahmenbedingungen

- **Eine eigene Staging-Umgebung gibt es nicht** (wie beim Premium-Modul, siehe
  `docs/qa/premium-acceptance.md`). „Staging“ heißt hier: der ausgerollte Stand
  auf der Produktion, Portal `sanitaerfinden.com`, Welle 0 aus
  `docs/turnstile.md` §9 — nur die Aktion `company_listing`.
- **Punkt 2 kann erst grün werden, wenn #14 erledigt ist.** Solange in der
  `.env` die Cloudflare-Testschlüssel stehen, meldet „Verbindung testen“
  zwangsläufig „nimmt jeden Token an“. #14 wiederum hängt an #18
  (Cloudflare-Zugang). Alle übrigen Punkte sind davon unabhängig und laufen
  auch mit Testschlüsseln.
- Umschalten ist in jedem Punkt folgenlos: Alle Werte stehen in
  `tenant_turnstile_settings`, der Resolver cacht nur innerhalb einer Anfrage
  (`docs/turnstile.md` §12). Kein Deployment, kein `config:clear`.
- Abweichungen werden **nicht** hier notiert, sondern als eigenes Ticket
  angelegt.

## 1. Maschinenanteil: `turnstile:acceptance`

Was ohne Auge prüfbar ist, prüft ein Kommando. Es ändert die Einstellungen des
Portals wie ein Mensch im Panel, liest das Ergebnis am Widget und am Log und
setzt die Zeile danach auf ihre Ausgangswerte zurück (die Probezeile im Log
läuft in einer Transaktion und wird zurückgerollt):

```bash
# lokal
php artisan turnstile:acceptance --portal=sanitaer.test

# Produktion, mit Schlüsselprobe gegen Cloudflare
sudo -u sanitaerfinden php artisan turnstile:acceptance \
    --portal=sanitaerfinden.com --siteverify
```

Auf der Produktion fragt das Kommando vorher nach, weil der Schutz des Portals
für wenige Sekunden aus und auf `invisible` geht (`--force` überspringt die
Frage). Rückgabe 0 heißt: kein Punkt meldet `FEHLER`. Ein Secret gibt das
Kommando nie aus, auch nicht gekürzt.

Stand 08.10.2026, lokal gegen `sanitaer.test`:

| # | Prüfpunkt | Zustand | Befund |
|---|---|---|---|
| 1 | Panel-Stellen und Portalauswahl | ok | drei Stellen vorhanden |
| 2 | Verbindung testen (Secret) | offen | Cloudflare-Testschlüssel (Gruppe A) — echte Schlüssel kommen aus #14 |
| 3 | Schalter aus / an | ok | aus: Widget weg, Prüfung aus · an: Widget da, Prüfung aktiv |
| 4 | Darstellung managed → invisible | ok | invisible: kein Kasten, `appearance=interaction-only`, Prüfung läuft weiter |
| 5 | Logzeile je Versuch | ok | Versuch ohne Token: abgewiesen, 1 neue Zeile, Ergebnis `failed` |
| 6 | Secret bleibt gesetzt und geheim | ok | Anzeige „gesetzt“ bleibt, kein Klartext in DB, `toArray()`, Logdateien |
| 7 | Kennzahlen 24 h | ok | 20 Portale gelesen |

Dazu gehören weiterhin:

- `php artisan turnstile:keys:check --siteverify` — Schlüsselpaare und
  Hostname-Zuordnung aller drei Widget-Gruppen (#14).
- `php artisan turnstile:rollout` — welcher Schalter je Portal gerade steht (#13).

## 2. Handarbeit im Browser

Nur das, was ein Mensch sehen muss. Reihenfolge einhalten, Portal überall
`sanitaerfinden.com`, Formular `https://sanitaerfinden.com/eintragen`
(Gast, nicht eingeloggt).

| # | Schritt | Erwartung | Ergebnis |
|---|---|---|---|
| H1 | `/admin/bot-protection`, oben Portal `sanitaerfinden.com` wählen | Formular zeigt die Werte des Portals; rechts steht die Widget-Gruppe der Domain und ob das Schlüsselpaar vollständig ist | ☐ |
| H2 | **Verbindung testen** drücken | grüne Meldung „Verbindung in Ordnung“ (`invalid-input-response`). `invalid-input-secret` = Schlüssel passt nicht zum Widget → #14. „nimmt jeden Token an“ = es liegt noch ein Testschlüssel in der `.env` → #14 | ☐ |
| H3 | „Bot-Schutz für dieses Portal aktiv“ **aus**, speichern, Eintragsformular in einem neuen Tab (ohne Deployment, ohne Cache-Clear) | kein Widget, kein Hinweistext, Formular lässt sich abschicken | ☐ |
| H4 | Schalter wieder **an**, speichern, Formular neu laden | Widget und Hinweistext sind zurück, Absenden ohne Lösung wird abgewiesen | ☐ |
| H5 | Darstellung von `company_listing` auf **invisible** stellen, speichern, Formular neu laden | kein sichtbarer Kasten, kein reservierter Platz, Absenden funktioniert weiter — die Prüfung läuft unsichtbar | ☐ |
| H6 | Darstellung zurück auf **managed** | sichtbarer Kasten wie in H4 | ☐ |
| H7 | `/admin/sicherheitspruefungen`, Portal `sanitaerfinden.com` | je Versuch aus H3–H6 genau eine Zeile mit Portal, Formular, Ergebnis; Filter nach Formular, Ergebnis und Zeitraum greifen | ☐ |
| H8 | In derselben Liste die Spalten IP und E-Mail ansehen (eine Detailansicht gibt es bewusst nicht, die Liste ist alles) | nur Hashes, kein Token, keine Klartext-IP, keine Klartext-E-Mail | ☐ |
| H9 | Auf der Einstellungsseite das Secret-Feld **leer** speichern | neben dem Feld steht weiter „gesetzt“; der Wert erscheint nicht im Feld, nicht in der Seitenquelle und nicht in `storage/logs/laravel.log` | ☐ |
| H10 | `/admin` (Übersicht) | Kennzahlen-Widget zeigt die Versuche der letzten 24 h, die Zahlen passen zu H7 | ☐ |

Erwartetes Ergebnis von H3/H4 im Log: `skipped` bei ausgeschaltetem Portal,
`failed` beim Absenden ohne Lösung, `passed` beim gelösten Widget
(`docs/turnstile.md` §3).

## 3. Abhaken

Laufen Maschinenanteil (Abschnitt 1, Rückgabe 0) und Handarbeit (H1–H10)
durch, ist der offene Punkt der DoD von #9 erledigt. Jede Abweichung wird als
eigenes Ticket angelegt und hier nur mit der Ticketnummer vermerkt.
