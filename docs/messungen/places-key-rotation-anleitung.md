# Anleitung: Google-Places-Schlüssel rotieren (#88, Ausführung #97)

Diese Schritte kann nur ein Mensch mit Zugang zur Google Cloud Console ausführen.
Sie werden unter Ticket **#97** geführt; #88 ist mit der Repository-Seite abgeschlossen.
Der Code ist fertig: `app/Console/Commands/GetCompanies.php` liest den Schlüssel
ausschließlich aus `GOOGLE_PLACES_API_KEY` und bricht seit #88 sofort ab, wenn
Google den Schlüssel ablehnt.

**Betroffene Schlüssel:** Die Zeile `env('GOOGLE_PLACES_API_KEY', '<Wert>')` in
`GetCompanies.php` trug über die Zeit **vier** verschiedene Schlüssel im Klartext.
Alle vier stehen dauerhaft in der Versionsgeschichte und gelten als kompromittiert:

| Schlüssel | Commit | Antwort am 09.09.2026 |
| --- | --- | --- |
| `AIzaSyD-efl6…YDoUw` | `e7e4664` (zuletzt gültiger Stand) | `REQUEST_DENIED`, Abrechnung nicht aktiv |
| `AIzaSyDzqxLV…A19Xc` | `fc06dd3` | `REQUEST_DENIED`, Abrechnung nicht aktiv |
| `AIzaSyBzifdh…T_yHE` | `437556e` | `REQUEST_DENIED`, Abrechnung nicht aktiv |
| `AIzaSyAal_5h…KcchU` | `1096a1a` | `REQUEST_DENIED`, APIs des Projekts deaktiviert |

Die vollständigen Werte stehen in den genannten Commits; sie sind hier absichtlich
gekürzt, damit sie nicht zusätzlich im aktuellen Stand landen.

**Wichtig:** „Abrechnung nicht aktiv" heißt **nicht** gesperrt. Drei der vier
Schlüssel sind gültig und funktionieren wieder, sobald auf ihrem Projekt die
Abrechnung eingeschaltet wird. Sie müssen gelöscht werden, nicht nur abgewartet.

**Nachmessung 09.09.2026 (vierter und fünfter Agentenlauf zu #97, `php artisan places:keys:audit`):**
Alle vier Werte antworten unverändert — drei mit „You must enable Billing", einer
mit „Google has disabled the use of APIs from this API project". Es ist also
weiterhin kein Schlüssel gelöscht; die Konsolenarbeit steht vollständig aus.

**Warum kein Agent das erledigen kann (geprüft am 09.09.2026, bitte nicht erneut prüfen):**
Auf der Arbeitsmaschine ist `gcloud`/`gsutil`/`bq` nicht installiert, `~/.config/gcloud`
existiert nicht, im Repository und in `storage/` liegt keine Dienstkonto-Datei
(`"type": "service_account"`), und die Umgebung führt weder `GOOGLE_APPLICATION_CREDENTIALS`
noch ein OAuth-Token. `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` in der `.env` sind leer und
wären ohnehin Socialite-Zugänge ohne Rechte auf der Cloud Console. Damit gibt es keinen
Weg zur Cloud Resource Manager- oder API-Keys-API. Abschnitt 0 bis 4 bleiben Handarbeit
für den Kontoinhaber; ein weiterer Agentenlauf kann daran nichts ändern, nur
`places:keys:audit` erneut ausführen. Der `gcloud`-Weg in Abschnitt 0 ändert daran
nichts — er braucht `brew install` und ein interaktives `gcloud auth login`, spart
dem Kontoinhaber aber die Suche nach dem richtigen Projekt.

**Befund vom 09.09.2026:** Auch der aktuell in der lokalen `.env` eingetragene
Schlüssel (Endung `…6ELWLM`) antwortet mit `REQUEST_DENIED` und
`"Google has disabled the use of APIs from this API project."`. `tenants:import-google`
läuft damit derzeit nicht — vor Abschnitt 4 muss also erst ein nutzbares Projekt
mit aktiver Abrechnung bereitstehen.

Trage jedes Ergebnis in die Tabelle am Ende ein.

## Kurzfassung (15 Minuten, alles Weitere ist Begründung)

Vorher bereitlegen: Zugang zur Google Cloud Console mit dem Konto, dem die
Places-Projekte gehören, und die öffentliche IP des Produktionsservers.
**Die IP steht nirgends im Repository** — `deploy.php` führt weiterhin den
Platzhalter `1.2.3.4` (Zeile 15). Ohne sie lässt sich Schritt 3 nur
unvollständig abschließen; siehe Hinweis dort.

1. **Abrechnung ansehen** — https://console.cloud.google.com/billing → Berichte,
   12 Monate, Dienst „Places API". Frage: gibt es Verbrauch ohne eigenen Importlauf?
2. **Vier alte Schlüssel deaktivieren** — schneller über `gcloud`, Abschnitt 0:
   der Klickweg sagt nicht, in welchem Projekt ein Schlüssel liegt, die CLI schon.
   Klickweg: https://console.cloud.google.com/apis/credentials,
   jedes Projekt durchgehen, das Places-Schlüssel führt. Die vier Werte gekürzt:
   `AIzaSyD-ef…YDoUw`, `AIzaSyDzqx…A19Xc`, `AIzaSyBzif…T_yHE`, `AIzaSyAal_…KcchU`.
   Dazu der tote `.env`-Wert `AIzaSyBeYd…ELWLM`, falls er im selben Projekt liegt.
3. **Neuen Schlüssel `sun-places-server` ausstellen** — API-Einschränkung
   „Places API (New)", Anwendungseinschränkung „IP-Adressen", Tageskontingent setzen.
4. **Wert eintragen** — Produktions-`.env` und lokale `.env`, dann `php artisan config:clear`.
5. **Zwei Proben laufen lassen**, beide müssen grün sein:

```bash
php artisan places:keys:audit    # muss mit Rückgabewert 0 enden
php artisan tenants:import-google --tenant=<id> --query=Sanitär --city=<Stadt> --limit=1 --dry-run
```

6. **Alte Schlüssel löschen** (erst jetzt, nach dem grünen Lauf) und die
   Protokolltabelle unten ausfüllen.

Reihenfolge nicht tauschen: löschen vor dem grünen Lauf nimmt dir den Rückweg,
wenn der neue Schlüssel noch klemmt.

## 0. Abkürzung: alles per `gcloud` statt Klickweg (empfohlen)

Der Klickweg in Abschnitt 1 bis 3 hat eine Lücke: **die Konsole verrät nicht, in
welchem Projekt ein Schlüssel liegt**, und der Fehlerkörper von Google nennt keine
Projekt-ID (nur den generischen Link `.../project/_/billing/enable`). Wer die
Projekte nicht kennt, muss jedes Projekt einzeln öffnen. Mit der `gcloud`-CLI
entfällt das: sie kann den Schlüsselwert zu jedem Schlüssel ausgeben und damit die
vier Verlaufswerte automatisch dem Projekt zuordnen.

`gcloud` ist auf dieser Maschine **nicht** installiert. Einmalig:

```bash
brew install --cask google-cloud-sdk    # oder https://cloud.google.com/sdk/docs/install
gcloud auth login                       # Konto, dem die Places-Projekte gehören
```

**0.1 Schlüssel den Projekten zuordnen.** Der Block liest die vier Werte selbst aus
der Versionsgeschichte, damit niemand einen kompromittierten Schlüssel von Hand
kopiert, und vergleicht sie mit allen Schlüsseln aller Projekte:

```bash
KEYS=$(for c in e7e4664 fc06dd3 437556e 1096a1a; do
  git show "${c}":"app/Console/Commands/GetCompanies.php" \
    | grep -oE "AIzaSy[A-Za-z0-9_-]{20,}" | head -1
done; grep -E "^GOOGLE_PLACES_API_KEY=" .env | cut -d= -f2)

for p in $(gcloud projects list --format="value(projectId)"); do
  gcloud services enable apikeys.googleapis.com --project="$p" 2>/dev/null
  for id in $(gcloud services api-keys list --project="$p" --format="value(uid)" 2>/dev/null); do
    s=$(gcloud services api-keys get-key-string "$id" --project="$p" --format="value(keyString)" 2>/dev/null)
    case "$KEYS" in *"$s"*) echo "TREFFER $p $id ${s:0:10}…${s: -5}";; esac
  done
done
```

**Probe:** Für jeden der vier Verlaufswerte erscheint genau eine `TREFFER`-Zeile.
Erscheint für einen Wert keine, gehört er einem Projekt außerhalb dieses Kontos —
dann fehlt der Zugang und der Wert bleibt unlöschbar; das gehört so ins Protokoll.

**0.2 Neutralisieren statt deaktivieren.** `gcloud` kennt kein „Deaktivieren".
Gleichwertig und rückholbar ist eine IP-Einschränkung auf eine Adresse, von der
niemand ruft (`192.0.2.1` ist per RFC 5737 für Dokumentation reserviert):

```bash
gcloud services api-keys update <KEY_ID> --project=<PROJEKT> --allowed-ips=192.0.2.1
```

Danach antwortet der Schlüssel mit `API_KEY_HTTP_REFERRER_BLOCKED`/`REQUEST_DENIED`,
ist aber noch vorhanden. `places:keys:audit` wertet ihn weiterhin als *lebt* — das
ist richtig, denn Abschnitt 2 fordert das Löschen.

**0.3 Löschen** (erst nach dem grünen Lauf aus Abschnitt 4):

```bash
gcloud services api-keys delete <KEY_ID> --project=<PROJEKT>
```

**0.4 Neuen Schlüssel ausstellen.** Ein Aufruf, gleich mit beiden
Einschränkungen; das Tageskontingent aus Abschnitt 3.5 bleibt Konsolenarbeit:

```bash
gcloud services enable places.googleapis.com --project=<PROJEKT>
gcloud services api-keys create \
  --project=<PROJEKT> \
  --display-name="sun-places-server" \
  --api-target=service=places.googleapis.com \
  --allowed-ips=<PRODUKTIONS_IP>
gcloud services api-keys get-key-string <NEUE_KEY_ID> --project=<PROJEKT> \
  --format="value(keyString)"
```

`--allowed-ips` weglassen, solange die Produktions-IP fehlt (siehe Abschnitt 3.3),
und später mit `gcloud services api-keys update … --allowed-ips=<IP>` nachziehen.

**Der Dienst heißt seit #108 `places.googleapis.com`.** `GetCompanies` ruft die
**Places API (New)** auf (`POST /v1/places:searchText` und `GET /v1/places/{id}`),
nicht mehr die alte Places API (`maps.googleapis.com/maps/api/place`, Dienst
`places-backend.googleapis.com`). Die API-Einschränkung des Schlüssels
`sun-places-server` muss deshalb auf **„Places API (New)"** lauten; die Auswahl
„Places API" ohne Zusatz ist der Legacy-Dienst und führt zu HTTP 403.
Das neue Projekt darf frisch angelegt sein — die Legacy-Freigabe, die Google für
neue Projekte nicht mehr erteilt, braucht der Importer nicht mehr.

Nur `places:keys:audit` fragt die **alte** API weiter ab, und zwar ausschließlich
für die vier Verlaufsschlüssel: dort ist die Frage „gelöscht oder nicht", und
darauf antwortet der Legacy-Endpunkt unabhängig von der Freischaltung. Die
`.env`-Zeile desselben Befehls prüft gegen `places.googleapis.com`.
## 1. Abrechnung des alten Schlüssels prüfen

1. Cloud Console öffnen: https://console.cloud.google.com/billing
2. Projekt auswählen, in dem der Places-Schlüssel liegt.
3. Unter „Berichte" den Zeitraum auf die letzten 12 Monate stellen und nach
   Dienst „Places API" filtern.
4. Auf Ausschläge achten, die nicht zu Importläufen passen (Wochenenden, Nachtstunden,
   Verbrauch ohne begleitenden `tenants:import-google`-Lauf).
5. Klären, warum das Projekt deaktiviert ist: Banner oben in der Console, sonst
   „Abrechnung → Kontostatus".

**Probe:** Du kannst benennen, ob es im Verlauf einen Verbrauch gibt, der keinem
eigenen Importlauf zuzuordnen ist — ja oder nein.

## 2. Alte Schlüssel sperren und löschen

1. „APIs & Dienste → Anmeldedaten" öffnen. Dabei jedes Projekt durchgehen, das
   Places-Schlüssel führt — die vier Werte stammen nicht alle aus demselben Projekt.
2. Jeden der vier Schlüssel aus der Tabelle oben heraussuchen und „Deaktivieren".
3. Nach dem erfolgreichen Lauf aus Abschnitt 4 alle vier „Löschen".

**Probe:** Für jeden der vier Werte antwortet der Aufruf mit `REQUEST_DENIED` und
der Meldung, dass der Schlüssel ungültig oder gelöscht ist — **nicht** mit
„You must enable Billing", denn das wäre nur die Abrechnung und keine Sperre:

```bash
curl -s "https://maps.googleapis.com/maps/api/place/textsearch/json?query=Test&key=<Schlüssel>" | head -c 200
```

Dafür gibt es seit #97 einen Befehl, der die vier Werte selbst aus der
Versionsgeschichte liest, sie abfragt und nur gekürzt ausgibt — so muss niemand
einen kompromittierten Schlüssel von Hand kopieren:

```bash
php artisan places:keys:audit             # Tabelle
php artisan places:keys:audit --markdown  # Zeilen zum Einkleben ins Protokoll
php artisan places:keys:audit --json
```

Der Befehl endet mit Rückgabewert 0 **nur dann**, wenn jeder Verlaufsschlüssel als
gelöscht antwortet; er unterscheidet dabei „gelöscht" von „lebt" und wertet sowohl
„You must enable Billing" als auch „Google has disabled the use of APIs" als
*lebt*. Die letzte Zeile zeigt zusätzlich, ob der Schlüssel aus der `.env` nutzbar
ist. Einzeln von Hand geht es weiterhin so:

```bash
for c in e7e4664 fc06dd3 437556e 1096a1a; do
  k=$(git show "${c}":"app/Console/Commands/GetCompanies.php" \
      | grep -oE "AIzaSy[A-Za-z0-9_-]{20,}" | head -1)
  echo "$c ${k:0:10}…${k: -5} => $(curl -s \
      "https://maps.googleapis.com/maps/api/place/textsearch/json?query=Test&key=$k" \
      | tr -d '\n' | grep -oE '"(status|error_message)" : "[^"]*"' | tr '\n' ' ')"
done
```

In zsh müssen `${c}` und der Pfad wie oben getrennt in Anführungszeichen stehen —
sonst deutet die Shell `$c:a` als Verlaufsmodifikator und `git show` bricht ab.

Erwartet **nach** der Löschung für alle vier Zeilen:
`The provided API key is invalid` oder `API key not valid`. Solange dort
`You must enable Billing` steht, ist der Schlüssel weiterhin gültig.

## 3. Neuen Schlüssel ausstellen und einschränken

1. „Anmeldedaten → Anmeldedaten erstellen → API-Schlüssel".
2. Name: `sun-places-server`.
3. Anwendungseinschränkung: „IP-Adressen", die öffentliche IP des Produktionsservers
   eintragen. Für lokale Entwicklung einen zweiten Schlüssel mit der eigenen IP
   ausstellen, nicht den Produktionsschlüssel weitergeben.
   **Ist die Produktions-IP noch nicht bekannt** — `deploy.php` trägt in Zeile 15
   weiterhin den Platzhalter `1.2.3.4` —, dann den Schlüssel trotzdem ausstellen,
   aber mit API-Einschränkung „Places API (New)" *und* einem knapp bemessenen Tageslimit
   (Größenordnung eines Importlaufs). Die IP-Einschränkung wird nachgezogen, sobald
   der Produktionsserver steht; das gehört dann in die Protokollzeile 3.
4. API-Einschränkung: nur „Places API (New)" (Dienst `places.googleapis.com`).
   Nichts sonst anhaken — „Places API" ohne Zusatz ist der Legacy-Dienst, den der
   Importer seit #108 nicht mehr aufruft.
5. Kontingent setzen, damit ein Leck begrenzt bleibt: „APIs & Dienste →
   Places API (New) → Kontingente", Tageslimit auf einen Wert oberhalb des
   üblichen Importlaufs.

**Probe:** Der neue Schlüssel steht in der Liste, Spalte „Einschränkungen" zeigt
„Places API (New)" und eine IP-Einschränkung.

## 4. Neuen Wert eintragen und Lauf prüfen

1. Produktions-`.env`: `GOOGLE_PLACES_API_KEY=<neuer Wert>`, danach
   `php artisan config:clear`.
2. Lokale `.env` jedes Entwicklers auf den jeweils eigenen Schlüssel setzen.
3. Der Wert gehört nirgends sonst hin: nicht in `config/services.php` als Vorgabe,
   nicht in `deploy.php`, nicht in ein Ticket.
4. Trockenlauf auf einer einzigen Stadt starten, damit die Prüfung nichts kostet, was
   sie nicht muss:

```bash
php artisan tenants:import-google --tenant=<id> --query=Sanitär --city=<Stadt> --limit=1 --dry-run
```

**Der Befehl heißt `tenants:import-google`, nicht `companies:get`** — unter dem
Namen aus #88/#97 existiert kein Befehl, `php artisan list` kennt nur diesen einen.
Ohne `--query` sucht er nichts; `--limit` gilt je Stadt und Suchbegriff, deshalb
zusätzlich `--city`, damit die Probe wirklich nur einen Aufruf kostet.

**Probe:** Der Lauf zeigt Treffer und bricht **nicht** mit „Abbruch: Der Schlüssel in
GOOGLE_PLACES_API_KEY ist gesperrt" ab.

## 5. Nacharbeit

1. Ticket #97 mit dem ausgefüllten Protokoll kommentieren.
2. Prüfen, ob weitere Schlüssel desselben Projekts im Repository stehen:

```bash
git log --all -G "AIzaSy" --oneline | cat
```

**`-G`, nicht `-S`.** `-S` zählt nur, wie oft eine Zeichenkette vorkommt, und
schlägt deshalb nicht an, wenn ein Commit einen Schlüssel bloß gegen einen anderen
austauscht. `-S "AIzaSy"` findet in diesem Repository genau einen Commit (`1096a1a`),
`-G "AIzaSy"` alle vier. Wer mit `-S` prüft, hält drei kompromittierte Schlüssel
für nicht vorhanden.

**Probe:** Die Ausgabe enthält außer `e7e4664`, `fc06dd3`, `437556e` und `1096a1a`
keine weiteren Commits, oder die zusätzlichen sind als eigene Tickets erfasst.

3. Prüfen, welche Dateien im Verlauf je einen Schlüssel getragen haben:

```bash
for c in $(git log --all -G "AIzaSy" --format=%H); do \
  git grep -l -E "AIzaSy[A-Za-z0-9_-]{20,}" $c -- . ; done | sed 's/^[0-9a-f]*://' | sort -u
```

**Probe (Stand 09.09.2026):** genau eine Datei,
`app/Console/Commands/GetCompanies.php`. Kommt eine weitere hinzu, gehört sie in
denselben Rotationslauf.

## Protokoll

Die Zeilen 2 und 4 lassen sich mit `php artisan places:keys:audit --markdown`
belegen; die Ausgabe gehört als Beleg unter die Tabelle.

| Abschnitt | Ausgeführt am | Von | Ergebnis / Probe erfüllt |
|---|---|---|---|
| 0 Schlüssel den Projekten zugeordnet (`gcloud`) | | | |
| 1 Abrechnung geprüft | | | |
| 2 Alle vier Schlüssel deaktiviert | | | |
| 2 Alle vier Schlüssel gelöscht | | | |
| 3 Neuer Schlüssel ausgestellt + eingeschränkt | | | |
| 4 Produktions-`.env` gesetzt | | | |
| 4 Lokale `.env` gesetzt | | | |
| 4 `tenants:import-google --dry-run` läuft durch | | | |
| 5 Keine weiteren Schlüssel im Verlauf | | | |

**Stand 09.09.2026 (fünfter Agentenlauf, vor der Konsolenarbeit):** `places:keys:audit` meldet
4 von 4 Verlaufsschlüsseln als *lebt* (Rückgabewert 1), die `.env`-Zeile als
*nicht nutzbar*. Abschnitt 5 ist geprüft und erfüllt: `git log --all -G "AIzaSy"`
nennt genau die vier bekannten Commits, betroffen ist nur
`app/Console/Commands/GetCompanies.php`. Die Abschnitte 1 bis 4 brauchen den
Kontozugang und stehen offen.
