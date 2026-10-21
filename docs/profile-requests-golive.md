# Rollout der Anfragen-Inbox: Pilot, Rueckstand, Abnahme (SUN-RQ #12)

Ablaufplan zu Ticket #12. Was hier steht, ist der Weg von „Code liegt im
Repo" zu „alle Portale laufen darueber" — mit den Schaltern, den Messungen
und den Grenzen.

Stand: 21.10.2026 · Autor: Dimitri (Backend) · Abnahme: Enes

> **Zwei Schritte gehoeren Enes, nicht den Agenten.** Das Sammelpostfach und
> die Weiterleitungen liegen beim Mailhoster, der Produktionslauf auf `sun`
> liegt auf dem Server. Nach `CLAUDE.md` („Externe Dienste und Konten") wird
> beides nicht von einem Agenten angefasst. Dieses Dokument ist die
> Handlungsanweisung dafuer, nicht ihr Ersatz.

---

## 1. Was fertig im Repo liegt

| Baustein | Ticket | Stand |
|---|---|---|
| Datenmodell `profile_requests` + Diff + Protokoll + Anhaenge (Central-DB) | #3 | liegt |
| Self-Service am Profil (aendern/loeschen, Turnstile) | #4 | liegt, **nur Theme `sun-v2`** (Abschnitt 6) |
| IMAP-Poller, Dedupe, Portal-Erkennung, Rohmail-Ablage | #5 | liegt, braucht `.env` |
| KI-Klassifikation, Magic-Link, Domain-Match | #6 | liegt, braucht `ANTHROPIC_API_KEY` |
| Central-Panel „Anfragen" mit Diff und Ein-Klick-Aktionen | #7 | liegt |
| Loeschpipeline mit Protokoll, 410, IndexNow | #8 | liegt |
| Auto-Regeln, Karenzfrist, Tagesdigest | #9 | liegt, per Schalter aus |
| Mails und Statusseite fuer Anfragende | #10 | liegt |
| Diff-Komponente des Panels | #11 | liegt |
| Security-Review | #12 | `docs/profile-requests-security-review.md`, ohne offene Punkte |
| Import des Rueckstands | #12 | `requests:import-mailbox` |
| Auswertung | #12 | `requests:pilot-report` |

---

## 2. Die drei Pilotportale

Gemessen am lokalen Spiegel (21.10.2026, `sun:db:pull`): Summe aus
`company_edit_suggestions` und `claim_requests` je Portal, also die Vorgaenge,
die die Inbox kuenftig traegt.

| Rang | Portal | tenants.id | Vorgaenge | davon offen | Theme |
|---:|---|---:|---:|---:|---|
| 1 | elektrikerportal.com | 30 | 45 | 32 | `sun-v2` |
| 2 | fahrschulefinder.de | 29 | 26 | 26 | `sun-v2` |
| 3 | zahnarzt.firmenfreund.de | 53 | 16 | 16 | `default` |
| 4 | malerfinder.de | 33 | 15 | 15 | `default` |
| 5 | bodenlegerfinden.com | 27 | 10 | 10 | `default` |
| 6 | kfzwerkstatt.io | 44 | 9 | 6 | `sun-v2` |

**Pilot: 30 (elektrikerportal.com), 29 (fahrschulefinder.de), 44
(kfzwerkstatt.io).**

Begruendung der Abweichung von der reinen Rangfolge: Der
Self-Service-Dialog ist Teil des Themes `sun-v2`, und darauf laufen nur 5 der
23 Portale. Rang 3 bis 5 koennten im Pilot **nur** den Mailweg zeigen, nicht
den Formularweg — und der Formularweg ist der Teil, der den Diff sauber
erzeugt und damit den Zeitgewinn tragen soll. Mit 30, 29 und 44 sind die drei
volumenstaerksten `sun-v2`-Portale dabei; sie tragen 80 der 185 Vorgaenge des
Bestands (64 der 160 offenen), also 43 % des Aufkommens bei drei von 23
Portalen.

---

## 3. Woche 1: Pilot ohne Automatik

### 3.1 Enes, einmalig (fremde Dienste)

1. Postfach `anfragen@sanitaerfinden.com` anlegen (eigenes Postfach, nicht
   Alias — der Poller braucht IMAP), Ordner `Verarbeitet` erstellen.
2. Weiterleitung der bestehenden Kontaktadresse **dieser drei Portale** auf
   das Sammelpostfach. Weiterleiten, nicht umziehen: die Adresse bleibt im
   Impressum gueltig.
3. Pruefen, dass die Weiterleitung `Delivered-To` oder `X-Original-To`
   setzt. Ohne diese Kopfzeile erkennt der Poller das Portal nicht und muss
   die KI fragen. Probe: eine Testmail an die Portaladresse schicken und im
   Sammelpostfach die Rohkopfzeilen ansehen.
4. Zugangsdaten in den Passwort-Manager, von dort in die `.env` der
   Produktion.

### 3.2 `.env` der Produktion

```dotenv
REQUESTS_MAILBOX_ADDRESS=anfragen@sanitaerfinden.com
REQUESTS_IMAP_HOST=…
REQUESTS_IMAP_USERNAME=…
REQUESTS_IMAP_PASSWORD=…
REQUESTS_IMAP_FOLDER=INBOX
REQUESTS_IMAP_PROCESSED_FOLDER=Verarbeitet
# Nur setzen, wenn der Hoster abweicht. Vorgaben aus
# config/profile-requests.php: Port 993, Verschluesselung ssl, Zertifikat
# wird geprueft.
# REQUESTS_IMAP_PORT=993
# REQUESTS_IMAP_ENCRYPTION=ssl
# REQUESTS_IMAP_VALIDATE_CERT=true

# Pilot: Self-Service nur auf diesen drei Portalen
REQUESTS_PILOT_SELF_SERVICE_TENANTS=30,29,44

# Woche 1: Auto-Regeln aus. Jede verifizierte Anfrage wartet im Panel.
REQUESTS_AUTO_DISABLED=true
```

Danach `php artisan config:clear` bzw. den Cache neu bauen — **erst nach dem
Setzen der Variablen**, sonst liest der Poller den alten Stand.

### 3.3 Probe, dass der Weg steht

```bash
# Poller: liest, legt an, klassifiziert, verschickt Magic-Links
php artisan requests:fetch-mail --limit=5

# Panel: /admin/anfragen zeigt die Vorgaenge aller Portale
# Statusseite: Link aus der Eingangsmail aufrufen
```

Erwartung: je Mail ein Vorgang mit Portal, KI-Vorschlag und — bei
hinterlegter Firmenadresse — versendetem Magic-Link. Eine Mail ohne
erkennbares Portal ist kein Fehler, sie liegt in der Inbox und wird im Panel
zugeordnet.

### 3.4 Rueckstand importieren

Der Poller liest nur **ungelesene** Mails. Die offenen Anfragen in den
Postfaechern sind laengst geoeffnet — fuer die gibt es
`requests:import-mailbox`. Er markiert nichts, verschiebt nichts und
verschickt keine Bestaetigungsmail (ein Magic-Link zu einer Mail von vor
drei Wochen ist fuer den Betrieb unverstaendlich); die Klassifikation laeuft
trotzdem, der Vorschlag ist der halbe Zeitgewinn.

```bash
# 1. Trockenlauf: was wuerde kommen?
php artisan requests:import-mailbox --since=2026-08-01 --dry-run

# 2. Echter Lauf
php artisan requests:import-mailbox --since=2026-08-01

# 3. Wiederholbar: ein zweiter Lauf meldet alles als „doppelt"
```

Jeder importierte Vorgang traegt eine Protokollzeile „Aus dem Rueckstand des
Sammelpostfachs importiert" — die Auswertung kann Neueingang und Nachtrag
sonst nicht trennen.

Der Rueckstand des **Formularwegs** (49 offene `company_edit_suggestions`,
111 offene `claim_requests` in den Tenant-DBs) wird **nicht** importiert: das
sind andere Tabellen mit eigener Oberflaeche unter `/verwaltung`, und ein
Umzug waere eine Datenmigration, nicht ein Rollout. Sie laufen dort aus; neue
Anfragen entstehen ueber die Inbox.

### 3.5 Was Enes in der Woche tut

Inbox taeglich abarbeiten — nichts anderes. Wichtig fuer die Messung: eine
falsche KI-Zuordnung **im Panel korrigieren** (Betrieb zuordnen), nicht durch
Ablehnen und Neuanlegen umgehen. Die Korrektur ist die Messgroesse.

---

## 4. Auswertung der Pilotwoche

```bash
php artisan requests:pilot-report --since=<Starttag> --tenant=30 --tenant=29 --tenant=44
php artisan requests:pilot-report --since=<Starttag> --json > docs/messungen/profile-requests-pilot/woche-1.json
```

Der Bericht liefert genau die Zahlen des Abnahmekriteriums:

| Kennzahl | Woher | Schwelle fuer Stufe 2 |
|---|---|---|
| Anzahl Anfragen, je Portal/Typ/Quelle | `profile_requests` im Zeitraum | — |
| Anteil verifiziert, getrennt nach Weg | `verified_at`, `verification_method` | — |
| Anteil automatisch erledigt | `applied_at` gesetzt, `applied_by` leer | — |
| **KI-Fehlzuordnungen** | Ereignis `ai_suggested` mit uebernommener `company_id`, danach Notiz „Betrieb zugeordnet" durch einen Menschen | **< 5 %** |
| Bearbeitungszeit (Median, h) | Eingang bis Abschluss | gegen 203 h von heute |

Vergleichsbasis vorher: Median **203 Stunden (8,5 Tage)** von Antrag bis
Freigabe, Rueckstand 160 offene Vorgaenge (`docs/profile-requests.md` 2.4).
Diese Zahl wird **nicht** neu gemessen — sie stammt aus einem Zeitraum, in
dem es die Inbox nicht gab.

In Woche 1 ist „Anteil automatisch erledigt" bauartbedingt 0 %: die
Auto-Regeln sind aus. Die Zahl wird in Woche 2 gemessen.

**Fehlzuordnungen in den Prompt (SUN-RQ-005):** Der Bericht gibt die IDs der
korrigierten Vorgaenge aus. Diese Faelle gehoeren als Beispiele in den
Klassifikations-Prompt (`RequestClassifier`) — kurz, als Gegenbeispiel, mit
der richtigen Zuordnung. Das ist die Nacharbeit nach Woche 1 und, wenn die
Quote ueber 5 % liegt, die Bedingung fuer Stufe 2.

---

## 5. Woche 2: Automatik an

Nur wenn die Fehlerquote unter 5 % liegt:

```dotenv
REQUESTS_AUTO_DISABLED=false
```

Danach pruefen, dass der Tagesdigest um 08:00 ankommt (`requests:daily-digest`)
und die Karenzfristen laufen (`requests:run-grace-deletions`, stuendlich zur
Minute 40). Erste automatische Loeschung bewusst beobachten: Protokoll in
`deletion_log`, 410 auf der alten Profil-URL, Eintrag in `gone_urls`.

**Notbremse:** `REQUESTS_AUTO_DISABLED=true` plus `config:clear`. Das haelt
jede Automatik an, auch bestehende Karenzfristen, und laesst den Panel-Weg
offen. Kein Deploy noetig.

---

## 6. Stufe 3: alle Portale

```dotenv
# leer = alle Portale
REQUESTS_PILOT_SELF_SERVICE_TENANTS=
```

Dazu die Weiterleitungen der uebrigen ausgelieferten Portale auf das
Sammelpostfach (Enes, Mailhoster) — ab dann traegt der Mailweg alle Portale.

**Die Grenze, die bleibt:** Der Self-Service-Dialog liegt im Theme `sun-v2`.
Nach der Umstellung haben ihn die 5 `sun-v2`-Portale (28, 29, 30, 44, 45);
die 18 Portale auf `default` behalten ihr altes Formular
`companies.suggest-edit`, das weiter in `company_edit_suggestions` der
Tenant-DB schreibt. „Alle Portale umgestellt" heisst also:

* **Mailweg:** alle Portale, sobald die Weiterleitung steht.
* **Formularweg:** die 5 `sun-v2`-Portale.

Der Dialog im `default`-Theme ist Frontend-Arbeit und kein Teil dieses
Tickets; er ist als eigenes Ticket notiert.

---

## 7. Abnahme

Abgenommen wird von Enes, nicht von einem Agenten. Vorzulegen:

1. `docs/profile-requests-security-review.md` — ohne offene Punkte (liegt).
2. Der Bericht der Pilotwoche aus `requests:pilot-report`, mit der
   Fehlerquote der KI-Zuordnung unter 5 %.
3. Die Bestaetigung, dass Weiterleitung und Postfach fuer alle
   ausgelieferten Portale stehen.
4. Eine erfolgreiche Loeschung ueber die Pipeline mit Protokoll und 410.

Abnahmeprobe auf der Produktion:

```bash
php artisan requests:pilot-report --since=<Starttag> --json
```

Der Befehl endet mit Exit-Code 0, sobald er durchlaeuft — er bewertet die
Quote nicht selbst. Entscheidend ist der Wert `ki.fehlerquote_prozent` im
JSON: er muss **unter 5** liegen. `null` heisst „keine KI-Zuordnung wurde
uebernommen" und ist **keine** erfuellte Bedingung, sondern fehlende
Grundlage — dann laeuft der Pilot weiter, bis Vorgaenge mit uebernommener
KI-Zuordnung vorliegen.

---

## 8. Befehle auf einen Blick

| Befehl | Zweck |
|---|---|
| `requests:fetch-mail [--limit=]` | Poller, alle 5 Minuten im Scheduler |
| `requests:import-mailbox [--since=] [--folder=] [--limit=] [--dry-run] [--verify] [--no-classify]` | Rueckstand nachtragen, einmalig |
| `requests:pilot-report [--since=] [--until=] [--tenant=] [--json]` | Auswertung, reine Leseabfragen |
| `requests:expire` | Magic-Links verfallen lassen, stuendlich :20 |
| `requests:run-grace-deletions` | Karenzfristen ausfuehren, stuendlich :40 |
| `requests:daily-digest` | Tagesdigest 08:00 |
| `requests:purge-raw-mail` | Rohmails/Anhaenge nach 90 Tagen, 03:55 |
| `company:delete <tenant> <company> [--dry-run]` | Loeschpipeline von Hand |
| `tenants:run companies:hard-delete-due` | Hartes Loeschen nach 30 Tagen, 02:30 |

---

## 9. Abhakliste

Zum Mitschreiben beim Durchgang. Jede Zeile ist ein Handgriff von Enes; kein
Punkt ist von einem Agenten erledigbar (fremder Dienst oder Produktion).

**Stufe 1 — Pilot ohne Automatik (30, 29, 44)**

* [ ] Postfach `anfragen@sanitaerfinden.com` angelegt (echtes Postfach, IMAP aktiv)
* [ ] Ordner `Verarbeitet` im Postfach vorhanden
* [ ] Weiterleitung `info@`/`kontakt@` von elektrikerportal.com, fahrschulefinder.de, kfzwerkstatt.io gesetzt (weiterleiten, nicht umziehen)
* [ ] Testmail an eine Portaladresse: im Sammelpostfach steht `Delivered-To` oder `X-Original-To` (Abschnitt 3.1, Punkt 3)
* [ ] `REQUESTS_IMAP_*` und `REQUESTS_MAILBOX_ADDRESS` in der Produktions-`.env`
* [ ] `REQUESTS_PILOT_SELF_SERVICE_TENANTS=30,29,44`
* [ ] `REQUESTS_AUTO_DISABLED=true`
* [ ] `php artisan config:clear` **nach** dem Setzen
* [ ] `php artisan requests:fetch-mail --limit=5` → je Mail ein Vorgang unter `/admin/anfragen`
* [ ] Starttag des Pilots notiert: ____________ (wird `--since` der Auswertung)
* [ ] `php artisan requests:import-mailbox --since=<Datum> --dry-run` gesichtet
* [ ] `php artisan requests:import-mailbox --since=<Datum>` gelaufen
* [ ] Woche 1: Inbox taeglich abgearbeitet, falsche KI-Zuordnungen **korrigiert**, nicht abgelehnt

**Auswertung**

* [ ] `php artisan requests:pilot-report --since=<Starttag> --tenant=30 --tenant=29 --tenant=44`
* [ ] `php artisan requests:pilot-report --since=<Starttag> --json > docs/messungen/profile-requests-pilot/woche-1.json`
* [ ] `ki.fehlerquote_prozent` liegt unter 5 (bei `null`: Grundlage fehlt, Pilot laeuft weiter)
* [ ] Korrigierte Vorgaenge (`ki.korrigierte_ids`) als Gegenbeispiele in den Klassifikations-Prompt (SUN-RQ-005, `RequestClassifier`)

**Stufe 2 — Automatik an**

* [ ] `REQUESTS_AUTO_DISABLED=false` + `config:clear`
* [ ] Tagesdigest um 08:00 angekommen
* [ ] Erste automatische Loeschung beobachtet: `deletion_log`, 410 auf der alten URL, Eintrag in `gone_urls`

**Stufe 3 — alle Portale**

* [ ] Weiterleitungen der uebrigen ausgelieferten Portale gesetzt
* [ ] `REQUESTS_PILOT_SELF_SERVICE_TENANTS=` geleert + `config:clear`
* [ ] Abgenommen: Security-Review, Pilotbericht, Weiterleitungen, eine Loeschung mit Protokoll (Abschnitt 7)
