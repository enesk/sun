# Anleitung: Produktionsschlüssel ausstellen (#115)

Anthropic, Voyage, Google-Dienstkonto. Herausgelöst aus #106 Abschnitt 3.

Stand der Erhebung: **09.09.2026**, per Lesezugriff auf `88.198.64.145`
(SSH-Kürzel `sun`, Benutzer `root`) gegen die laufende Installation
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev` geprüft.

Das Ausstellen der drei Schlüssel geht nur im jeweiligen Konto und kann von
keinem Agentenlauf erledigt werden. Alles, was ohne Kontozugang vorbereitbar
war, ist vorbereitet (Abschnitt 1). Abschnitte 2 bis 5 sind Handarbeit.

## 0. Befund auf dem Server

| Sache | Stand am 09.09.2026 |
| --- | --- |
| `ANTHROPIC_API_KEY` | fehlt in der `.env` **ganz** (nicht leer, gar keine Zeile) |
| `VOYAGE_API_KEY` | fehlt ganz |
| `GOOGLE_SERVICE_ACCOUNT_JSON` | fehlt ganz |
| Schalter und Budgets | gesetzt (`CONTENT_PIPELINE_ENABLED=true`, vier `CONTENT_BUDGET_*`) |
| `bootstrap/cache/config.php` | damals vom **12.05.2026** — inzwischen durch #117 neu gebaut, siehe Abschnitt 1c |
| Eigentümer der Installation | `sanitaerfinden:sanitaerfinden` |
| PHP der Webseite | PHP-FPM **8.5**, Pool `/etc/php/8.5/fpm/pool.d/sanitaerfinden.dev.conf`, `user = sanitaerfinden` |
| PHP des Schedulers | `/usr/bin/php8.4`, Minutentakt per `crontab -u sanitaerfinden` |
| Codestand dort | `fc06dd3` — `app/Content` existiert auf dem Server noch nicht (#109) |

Zwei Folgerungen daraus:

- Eigentümer der Schlüsseldatei ist **`sanitaerfinden`**, nicht `www-data`. Die
  ältere Angabe in `docs/messungen/metrik-abnahme-zugaenge.md` Abschnitt 2 gilt
  für einen Standard-Deployer-Host, nicht für diesen CloudPanel-Server. Eine
  Datei, die `www-data` gehört, ist für FPM und Scheduler nicht lesbar.
- Solange `app/Content` nicht auf dem Server liegt, gibt es dort kein
  `content:golive:check`. Die Abnahme dieses Tickets läuft deshalb **nach** dem
  Deploy aus #109/#117, nicht davor.

## 1. Bereits vorbereitet (ohne Kontozugang erledigt)

Der Ablageort der Schlüsseldatei liegt fertig auf dem Server:

```
/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/app/private        drwxr-x---  sanitaerfinden:sanitaerfinden
/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/app/private/google drwx------  sanitaerfinden:sanitaerfinden
```

`storage/` ist vollständig in `.gitignore` und liegt außerhalb von `public/`.
Die `.env` wurde **nicht** angefasst: leere Platzhalterzeilen wirken wie ein
fehlender Wert, sehen aber wie ein gepflegter aus. Der vorhandene
Kommentarblock in der `.env` erklärt bereits, warum die drei Zeilen fehlen.

## 1b. Nachprüfung vom 09.09.2026 (Stand vor dem Ausstellen)

Erneut per Lesezugriff auf `sun` geprüft, damit beim Ausstellen niemand raten
muss, was schon dasteht:

| Prüfpunkt | Befund |
| --- | --- |
| `ANTHROPIC_API_KEY` in der Produktions-`.env` | keine Zeile |
| `VOYAGE_API_KEY` | keine Zeile |
| `GOOGLE_SERVICE_ACCOUNT_JSON` | keine Zeile |
| `storage/app/private/google/` | vorhanden, `drwx------ sanitaerfinden`, **leer** |
| Codestand | `fc06dd3`, `app/Content` fehlt weiter |

Damit sind Abschnitt 2 bis 4 unverändert offen und Abschnitt 5 erst nach dem
Deploy aus #109/#117 ausführbar: ohne `app/Content` gibt es auf dem Server
weder `content:golive:check` noch `content:metrics:preflight`, die Abnahme
dieses Tickets ist vorher nicht durchführbar.

## 1c. Nachprüfung vom 09.09.2026, 15:16 Uhr (nach #117)

Dritter Lesezugriff auf `sun`, nach der Horizon-Umstellung aus #117:

| Prüfpunkt | Befund |
| --- | --- |
| `ANTHROPIC_API_KEY` / `VOYAGE_API_KEY` / `GOOGLE_SERVICE_ACCOUNT_JSON` | weiterhin keine Zeile in der `.env` |
| `storage/app/private/google/` | vorhanden, `drwx------ sanitaerfinden`, weiterhin leer |
| `QUEUE_CONNECTION` | `redis` (aus #117) |
| `bootstrap/cache/config.php` | neu gebaut am 09.09.2026, `queue.default = redis` |
| `config('content')` im Cache | **fehlt** — `config/content.php` liegt auf dem Server nicht vor |
| Codestand | unverändert `fc06dd3`, `app/Content` fehlt |

Damit bleibt die Abnahme dieses Tickets gesperrt: ohne `app/Content` gibt es
auf dem Server weder `content:golive:check` noch `content:metrics:preflight`
noch `content:llm:ping`. Die Schritte 2 bis 4 (Konten, Schlüssel, Datei) sind
davon unabhängig und können jederzeit erledigt werden; nur Abschnitt 5 und das
Protokoll in Abschnitt 7 warten auf den Deploy des Codestands.

## 1d. Nachprüfung vom 09.09.2026, nach dem Deploy (#125)

Vierter Lesezugriff auf `sun`, nachdem der Codestand nachgezogen wurde. Die
Vorbedingung dieses Tickets ist damit **erfüllt** — die Abnahme ist jetzt
durchführbar, sobald die Schlüssel da sind.

| Prüfpunkt | Befund |
| --- | --- |
| Codestand | `59ce93e`, `app/Content` und `config/content.php` liegen auf dem Server |
| `content:golive:check` / `content:metrics:preflight` / `content:llm:ping` | vorhanden und lauffähig |
| `ANTHROPIC_API_KEY` / `VOYAGE_API_KEY` / `GOOGLE_SERVICE_ACCOUNT_JSON` | weiterhin keine Zeile in der `.env` |
| `storage/app/private/google/` | vorhanden, `drwx------ sanitaerfinden`, weiterhin leer |
| Queue | `redis`, alle sechs `content-*`-Queues in einem Horizon-Supervisor |

Ausgangsstand der drei Proben (Befehle als Benutzer `sanitaerfinden` mit
`/usr/bin/php8.4`, so wie der Scheduler sie fährt):

```
content:golive:check        143 Prüfpunkte, 1 Fehler, 73 Warnungen
  ✗ Anthropic-Zugang               ANTHROPIC_API_KEY fehlt
  ! Voyage-Zugang (Embeddings)     VOYAGE_API_KEY fehlt
  ! Search-Console-Dienstkonto     GOOGLE_SERVICE_ACCOUNT_JSON nicht gesetzt
content:metrics:preflight   28 Prüfpunkte, 2 Fehler, 25 Warnungen
  ✗ Dienstkonto                    GOOGLE_SERVICE_ACCOUNT_JSON fehlt oder die Datei ist nicht lesbar
  ✗ Gap-Connector registriert      CONTENT_SOURCES_GSC_ENABLED=true setzen
content:llm:ping            ANTHROPIC_API_KEY ist nicht gesetzt.
```

Der eine Fehler des Go-Live-Checks ist genau der fehlende Anthropic-Schlüssel;
alles andere an dieser Stelle sind Warnungen. Die Zeile „Sichtbare Properties"
taucht in `content:metrics:preflight` erst auf, wenn das Dienstkonto lesbar
ist — vorher bricht die Prüfung bei „Dienstkonto" ab.

Zwei Punkte fallen dabei an, die **nicht** an einem Schlüssel hängen und
deshalb auch nach dem Ausstellen offen bleiben:

- `CONTENT_SOURCES_GSC_ENABLED` fehlt in der Produktions-`.env`. Ohne den
  Schalter kennt `content:sources:run` den Connector `gsc_gap` nicht.
- `gsc_property` ist bei allen Portalen leer (#107) und `ADSENSE_ACCOUNT_ID`
  fehlt. „Sichtbare Properties" kann deshalb erst ✓ zeigen, wenn zusätzlich zu
  Abschnitt 4 Schritt 5 mindestens eine Property gepflegt ist.

## 2. Anthropic-Schlüssel

1. Im Anthropic-Konto ein **eigenes Projekt** für die Produktion anlegen
   (nicht das Projekt der Entwicklung).
2. Dort ein **Ausgabenlimit** setzen. Das ist die zweite Sicherung neben dem
   `BudgetGuard`; das Monatsbudget der `.env` steht auf 1050 USD, das
   Kontolimit gehört in dieselbe Größenordnung.
3. API-Schlüssel in diesem Projekt ausstellen.

Den Entwicklungsschlüssel **nicht** übernehmen: ein geteilter Schlüssel
vermischt die Abrechnung und trifft bei einem Rückruf beide Umgebungen.

```dotenv
ANTHROPIC_API_KEY=sk-ant-…
```

Achtung Guthaben: das Konto hinter dem Entwicklungsschlüssel ist seit dem
09.09.2026 leer (jeder Aufruf endet mit „credit balance is too low"). Das
Produktionsprojekt braucht eigenes Guthaben, sonst scheitert der erste
Tageslauf trotz gültigem Schlüssel — siehe #105.

**Probe:** `php artisan content:llm:ping` antwortet; `content:golive:check`
zeigt bei „Anthropic-Zugang" ✓. Der Go-Live-Check prüft nur, ob der Wert
gesetzt ist — Guthaben und Limit sieht er nicht.

## 3. Voyage-Schlüssel

Embeddings der Duplikatsprüfung. Existiert im Projekt bisher nirgends.

1. Konto bei Voyage AI anlegen, API-Schlüssel ausstellen.
2. Eintragen:

```dotenv
VOYAGE_API_KEY=pa-…
```

Im Go-Live-Check ist der fehlende Schlüssel nur eine **Warnung**. Der Go-Live
hängt nicht daran; ohne den Schlüssel wirft `EmbeddingClient` beim ersten
Duplikatsabgleich `VOYAGE_API_KEY ist nicht gesetzt`. Wenn das Konto nicht
rechtzeitig steht, ist das kein Grund, Woche 1 zu verschieben.

## 4. Google-Dienstkonto

Ein Dienstkonto bedient Search Console und AdSense; ein zweiter Schlüssel ist
unnötig, `AdSenseClient` fällt auf denselben Pfad zurück.

1. Im Google-Cloud-Projekt Dienstkonto anlegen, JSON-Schlüssel herunterladen.
   IAM-Rolle egal — der Zugriff kommt über die Property-Freigabe.
2. Im selben Projekt **Google Search Console API** aktivieren (und AdSense
   Management API, falls `CONTENT_ADSENSE_ENABLED` später auf `true` geht).
   Fehlt die Aktivierung, antwortet Google mit 403 `accessNotConfigured`.
3. Datei auf den Server legen, ohne sie je durch `public/` zu schleusen:

```bash
scp search-console.json sun:/tmp/search-console.json
ssh sun 'install -m 0600 -o sanitaerfinden -g sanitaerfinden \
  /tmp/search-console.json \
  /home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/app/private/google/search-console.json \
  && rm -f /tmp/search-console.json'
```

4. Eintragen (relativer Pfad, `serviceAccount()` löst ihn über `base_path()`
   auf — bei dieser Installation ohne Release-Struktur ist das stabil):

```dotenv
GOOGLE_SERVICE_ACCOUNT_JSON=storage/app/private/google/search-console.json
```

5. **Handarbeit, die keine Prüfung ersetzt:** die `client_email` der Datei in
   **jeder** Search-Console-Property unter *Einstellungen → Nutzer und
   Berechtigungen* mit Leserecht eintragen. Ohne diesen Eintrag liefert die API
   leere Antworten statt eines Fehlers — der häufigste Grund für „läuft durch,
   schreibt nichts". Der Go-Live-Check erkennt diesen Fall nicht, nur
   `content:metrics:preflight` und die Arbeit aus #116.

Was der Go-Live-Check aus dem Pfad macht:

| Zustand | Zeile „Search-Console-Dienstkonto" |
| --- | --- |
| nicht gesetzt | Warnung |
| gesetzt, Datei fehlt | **Fehler** |
| gesetzt, Datei unter `public/` | **Fehler** |
| gesetzt, Datei da, außerhalb `public/` | ✓ „lesbar, außerhalb von public/" |

Seit #119 prüft der letzte Fall zusätzlich `is_readable()` und ob sich die
Datei als JSON mit `client_email` und `private_key` lesen lässt; ein falscher
Eigentümer meldet jetzt Fehler statt ✓ und nennt Eigentümer, Modus und den
ausführenden Benutzer. Was er weiterhin nicht sieht: ob die Search Console API
im Cloud-Projekt aktiviert ist und ob die `client_email` in den Properties
eingetragen wurde (Abschnitt 6).

Solange alle Portale auf `.test`-Domains laufen, gibt es keine verifizierbare
Property (#106, #107). Schritt 5 ist dann erst nach der Domain-Umstellung
abschließbar; Schritte 1 bis 4 sind davon unabhängig.

## 5. Scharfschalten

```bash
php artisan config:cache
php artisan queue:restart
```

Der Config-Cache ist seit #117 auf dem Stand vom 09.09.2026 und trägt bereits
`QUEUE_CONNECTION=redis`; die alte Mai-Fassung ist weg. Der Neuaufbau schaltet
trotzdem weiterhin **alle** `.env`- und `config/`-Änderungen mit scharf, die
der Deploy aus #109 mitbringt — `config/content.php` fehlt im gecachten Stand
noch ganz. Deshalb gehört er ans Ende dieses Deploys und nicht als Einzelgriff
davor. `queue:restart` ist beim Umstieg auf
Horizon (#117) durch `horizon:terminate` zu ersetzen.

**Fertig, wenn:**

```bash
php artisan content:golive:check
```

bei „Anthropic-Zugang" und „Search-Console-Dienstkonto" ✓ meldet und
`php artisan content:metrics:preflight` bei „Sichtbare Properties" ✓ zeigt.
Ohne Netzzugang prüft `content:metrics:preflight --offline` nur die
Konfiguration und die Lesbarkeit der Schlüsseldatei.

## 6. Nachweis, dass ein Schlüssel wirklich trägt

Ein gültiger Dateiinhalt heißt noch nicht, dass der Zugang trägt. Am
09.09.2026 wurde das mit einem vorhandenen Dienstkonto lokal durchgespielt:
die Datei war formal in Ordnung, das Token wurde ausgestellt, `sites.list`
antwortete trotzdem mit

```
403 Google Search Console API has not been used in project … before
```

Das ist der Fall „Schlüssel gut, API im Cloud-Projekt nicht aktiviert" aus
Abschnitt 4 Schritt 2. `content:golive:check` sieht ihn nicht — er prüft nur
den Pfad. Sichtbar wird er allein in `content:metrics:preflight` in der Zeile
„Sichtbare Properties". Reihenfolge der Proben deshalb immer:

1. `content:golive:check` — sind die Werte überhaupt gesetzt und die Datei da?
2. `content:metrics:preflight --tenant=<uuid>` — trägt der Zugang wirklich?
3. `content:llm:ping` — antwortet das Modell und wird die Kostenzeile
   geschrieben?

## 7. Protokoll (beim Ausstellen ausfüllen)

| Schritt | Datum | Von wem | Befund |
| --- | --- | --- | --- |
| Anthropic-Projekt „Produktion" angelegt | | | |
| Ausgabenlimit im Anthropic-Konto gesetzt (Betrag notieren) | | | |
| `ANTHROPIC_API_KEY` in Produktions-`.env` eingetragen | | | |
| Voyage-Konto angelegt, `VOYAGE_API_KEY` eingetragen | | | |
| Google-Dienstkonto angelegt, JSON heruntergeladen | | | |
| Search Console API im Cloud-Projekt aktiviert | | | |
| Schlüsseldatei auf dem Server (0600, `sanitaerfinden`) | | | |
| `GOOGLE_SERVICE_ACCOUNT_JSON` eingetragen | | | |
| `client_email` in jeder Property als Leser eingetragen | | | |
| `config:cache` + `queue:restart` (bzw. `horizon:terminate`) | | | |
| `content:golive:check`: „Anthropic-Zugang" ✓ | | | |
| `content:golive:check`: „Search-Console-Dienstkonto" ✓ | | | |
| `content:metrics:preflight`: „Sichtbare Properties" ✓ | | | |
