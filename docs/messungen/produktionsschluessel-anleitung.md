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
| `bootstrap/cache/config.php` | vom **12.05.2026** — die `.env` wird bis zum Neuaufbau nicht gelesen |
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

Der letzte Fall prüft nur `is_file()`, nicht die Leserechte des ausführenden
Benutzers. Eine Datei mit falschem Eigentümer meldet trotzdem ✓ und fällt erst
beim ersten API-Aufruf auf (eigenes Ticket).

Solange alle Portale auf `.test`-Domains laufen, gibt es keine verifizierbare
Property (#106, #107). Schritt 5 ist dann erst nach der Domain-Umstellung
abschließbar; Schritte 1 bis 4 sind davon unabhängig.

## 5. Scharfschalten

```bash
php artisan config:cache
php artisan queue:restart
```

`bootstrap/cache/config.php` stammt vom 12.05.2026. Der Neuaufbau schaltet
nicht nur die drei Schlüssel scharf, sondern **alle** seit Mai aufgelaufenen
`.env`- und `config/`-Änderungen. Deshalb gehört er ans Ende des Deploys aus
#109/#117 und nicht als Einzelgriff davor. `queue:restart` ist beim Umstieg auf
Horizon (#117) durch `horizon:terminate` zu ersetzen.

**Fertig, wenn:**

```bash
php artisan content:golive:check
```

bei „Anthropic-Zugang" und „Search-Console-Dienstkonto" ✓ meldet und
`php artisan content:metrics:preflight --offline` die `client_email` der
Schlüsseldatei ausgibt.

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
