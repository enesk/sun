# Zugaenge fuer die Metrik-Abnahme (#23) — Durchfuehrung (Ticket #90)

Abgespalten von #80. Die Code-Seite des Metrik-Collectors und der Lernschleife
ist lokal nachgewiesen. Was hier steht, kann nur ein Mensch mit Konto-Zugriff
tun: Staging-Host, Google-Dienstkonto, echte Search-Console-Property,
AdSense-Freischaltung. Danach folgt der Abnahmelauf in Abschnitt 5.

Reihenfolge ist bindend. Jeder Abschnitt endet mit einer Probe, die vor dem
naechsten Schritt gruen sein muss.

Die Proben der Abschnitte 2 bis 4 laufen alle ueber einen Befehl:

```bash
php artisan content:metrics:preflight
php artisan content:metrics:preflight --tenant=<uuid>
php artisan content:metrics:preflight --offline   # ohne Aufrufe bei Google
```

Er fragt mit echten Aufrufen nach, was der Go-Live-Check nur ansieht: welche
Adresse das Dienstkonto hat, welche Properties es sehen darf, ob die
`gsc_property` jedes Portals darunter ist, ob es fuer das Fenster des
Collectors ueberhaupt Zeilen gibt und ob das AdSense-Konto sichtbar ist.
Rueckgabewert 0, wenn kein Fehler offen ist. AdSense meldet er nie als Fehler,
sondern als Warnung — siehe Abschnitt 4.

---

## 1. Staging-Host benennen

`deploy.php` traegt noch die Platzhalter des Starterkits:

| Variable       | Stand heute       | Einzutragen                       |
| -------------- | ----------------- | --------------------------------- |
| `$host`        | `1.2.3.4`         | IP oder Hostname des Staging-Servers |
| `$domain`      | `yourdomain.com`  | oeffentliche Staging-Domain       |
| `$repository`  | `git@github.com:username/saasykit.git` | SSH-URL dieses Repositories |
| `$remoteUser`  | `deployer`        | bleibt, falls der Benutzer so heisst |
| `$deployPath`  | `~/app`           | bleibt                            |

Der Wert wandert nicht in die `.env`, sondern in `deploy.php`; das ist die
einzige Stelle, die Deployer liest.

Zusaetzlich auf dem Server:

- `APP_URL` auf die Staging-Domain. Ohne diesen Wert baut die Platte `public`
  relative Bild-URLs, was auch den Rich-Results-Test (#84) verfaelscht.
- `APP_KEY` einmal erzeugen und danach **nie** neu erzeugen. Ein Wechsel
  entwertet alle IndexNow-Schluessel und alle signierten Vorschau-Adressen.

**Probe:** `https://<staging>/bot` liefert 200.

Ohne diesen Abschnitt haengen ausserdem #84 (Rich Results Test) und #86
(Refresh-Lauf auf Staging).

---

## 2. Google-Dienstkonto einrichten

Ein Dienstkonto bedient beide APIs. Im Google-Cloud-Projekt:

1. Dienstkonto anlegen, Rolle irrelevant (der Zugriff kommt ueber die
   Property-Freigabe, nicht ueber IAM).
2. JSON-Schluessel erzeugen und herunterladen.
3. Im selben Projekt beide APIs aktivieren: **Google Search Console API** und
   **AdSense Management API**. Fehlt die Aktivierung, antwortet Google mit
   403 `accessNotConfigured`, nicht mit einer leeren Liste.

Auf dem Server:

```bash
install -d -m 0700 -o www-data -g www-data storage/app/private/google
install -m 0600 -o www-data -g www-data search-console.json \
  storage/app/private/google/search-console.json
```

```dotenv
GOOGLE_SERVICE_ACCOUNT_JSON=/home/deployer/app/current/storage/app/private/google/search-console.json
```

Drei Regeln, die der Code selbst durchsetzt:

- Der Pfad darf **nicht** unter `public/` liegen. `SearchConsoleClient` und
  `GoogleServiceAccountToken` pruefen das bei jedem Zugriff und werfen sonst.
- Die Datei muss `client_email` und `private_key` tragen, sonst bricht der
  Aufruf mit Klartextmeldung ab.
- `storage/` ist vollstaendig in `.gitignore`; die Schluesseldatei kann dort
  nicht versehentlich eingecheckt werden.

Ein eigener AdSense-Schluessel ist moeglich
(`ADSENSE_SERVICE_ACCOUNT_JSON`), aber unnoetig: ohne diesen Wert nimmt
`AdSenseClient` denselben Pfad wie die Search Console.

**Probe:** `php artisan content:metrics:preflight --offline` meldet die Zeile
„Dienstkonto" mit der `client_email` der Schluesseldatei. Genau diese Adresse
wird in Abschnitt 3 und 4 als Nutzer eingetragen.

---

## 3. Echte Property pflegen

In dieser Umgebung hat genau ein Mandant eine `tenant_content_settings.gsc_property`,
und die zeigt auf die Testdomain `sc-domain:sanitaer.test`. Fuer die Abnahme
braucht mindestens ein Mandant auf Staging eine echte Property.

1. In der Search Console die Property der Staging-Domain anlegen und
   verifizieren (Domain-Property bevorzugt).
2. Das Dienstkonto (`client_email` aus der Schluesseldatei) dort unter
   *Einstellungen → Nutzer und Berechtigungen* mit Leserecht eintragen.
   **Ohne diesen Eintrag liefert die API leere Antworten statt eines Fehlers** —
   der haeufigste Grund fuer „laeuft durch, schreibt nichts".
3. Property je Portal eintragen, Form `sc-domain:example.de` oder
   `https://example.de/`. Weg wahlweise Content-Panel → *Einstellungen* oder
   direkt in `tenant_content_settings.gsc_property`.

**Probe:** `php artisan content:metrics:preflight` meldet „Sichtbare
Properties" und je Portal die eigene Property mit Berechtigungsstufe. Steht
dort *nicht sichtbar*, fehlt der Nutzereintrag aus Schritt 2 — der Abnahmelauf
wuerde stumm nichts schreiben. Steht dort *0 Zeilen*, ist die Freigabe da und
nur die Daten fehlen noch.

**Erwartung an die Daten:** Search Console liefert mit `lag_days: 3` und
`data_state: final`. Eine frisch angelegte Property hat fuer die letzten Tage
nichts. Rechne mit mehreren Tagen Vorlauf, bevor Zeilen entstehen.

---

## 4. AdSense freischalten

1. Dienstkonto im AdSense-Konto als Nutzer eintragen.
2. Konto-Kennung notieren, Form `accounts/pub-1234567890123456`
   (das Praefix `accounts/` darf fehlen, der Code ergaenzt es).

```dotenv
CONTENT_ADSENSE_ENABLED=true
ADSENSE_ACCOUNT_ID=accounts/pub-1234567890123456
```

`AdSenseClient::isConfigured()` verlangt alle drei Dinge: Schalter, Konto-Kennung
und lesbaren Schluessel. Fehlt eines, bleibt das Feature aus und der Collector
schreibt `pageviews` und `adsense_revenue_usd` als **null**, nicht als 0 — das
ist der Ausfallweg, kein Fehler. Die Performance-Ansicht (#25) unterscheidet
daran „ungemessen" von „ertraglos".

**Probe:** `php artisan content:metrics:preflight` meldet „AdSense-Konto" mit
dem Anzeigenamen des Kontos. Passt `ADSENSE_ACCOUNT_ID` nicht zu dem, was das
Dienstkonto sieht, zaehlt der Befehl die sichtbaren Kennungen auf.

Laesst AdSense das Dienstkonto nicht als Nutzer zu, ist das kein Grund, die
Abnahme zu stoppen: Abschnitt 5 ist ohne AdSense vollstaendig durchfuehrbar, nur
die beiden Ertragsspalten bleiben null. Dann diesen Fall hier vermerken und
Ticket #90 mit dem Teilergebnis schliessen.

---

## 5. Abnahmelauf

Bis die Queue `content-metrics` ein Supervisor-Programm hat (#22), nur mit
`--sync` fahren. `deploy/supervisor/` kennt heute nur `content-sources`,
`content-generate` und `content-publish`.

```bash
# 0. Vorabpruefung. Bricht hier etwas, spart das einen Leerlauf der drei
#    folgenden Befehle.
php artisan content:metrics:preflight

# 1. Rohzeilen holen. Ohne CONTENT_SOURCES_GSC_ENABLED ist der Connector gar
#    nicht registriert und der Aufruf endet mit
#    "Unbekannter Quell-Connector 'gsc_gap'".
CONTENT_PIPELINE_ENABLED=true CONTENT_SOURCES_GSC_ENABLED=true \
  php artisan content:sources:run --connector=gsc_gap --tenant=<uuid> --sync

# 2. Verdichten auf Tageswerte je Ratgeber, AdSense dazu.
CONTENT_PIPELINE_ENABLED=true \
  php artisan content:metrics:collect --tenant=<uuid> --sync

# 3. Lernschleife.
CONTENT_PIPELINE_ENABLED=true \
  php artisan content:learning:run --tenant=<uuid> --sync --show
```

Steht `CONTENT_PIPELINE_ENABLED` bereits in der `.env` des Servers, entfallen
die Praefixe. Ohne den Schalter steigen alle drei Befehle wirkungslos aus und
melden „Die Content-Pipeline ist deaktiviert".

---

## 6. Abnahmekriterium

In der Mandanten-Datenbank:

```sql
SELECT date, article_id, impressions, clicks, ctr, position,
       pageviews, adsense_revenue_usd
FROM article_metrics
ORDER BY date DESC, impressions DESC
LIMIT 20;
```

Bestanden, wenn:

- Zeilen existieren und `impressions`, `clicks`, `ctr`, `position` plausible
  Werte aus echter Messung tragen (`position` zwischen 1 und 100,
  `ctr` = `clicks` / `impressions`).
- Nach der AdSense-Freischaltung `pageviews` und `adsense_revenue_usd` nicht
  mehr null sind. Ohne Freischaltung bleiben beide null, siehe Abschnitt 4.
- Zusaetzlich fuellt Schritt 1 `article_metrics_raw` mit einem Snapshot je
  `window_end` (Suchanfrage x Seite, 28-Tage-Fenster).

Bleibt die Rangliste der Lernschleife auf frischen Daten leer, ist das laut #23
das Ergebnis und keine Stoerung: ohne D7-Fenster gibt es nichts zu ranken.

## 7. Ergebnis festhalten

Auszuege der drei Laeufe und der SQL-Probe als
`metrik-abnahme-<datum>.txt` in diesem Ordner ablegen, wie bei den uebrigen
Messungen. Danach #90 schliessen und #86 (Refresh-Lauf) anstossen.
