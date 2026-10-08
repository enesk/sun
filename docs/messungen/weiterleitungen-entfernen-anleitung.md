# firmenfreund.net und tierarztportal.com: Cloudflare-Weiterleitungen entfernen (#37)

Beide Zonen liegen hinter der Cloudflare-Kante (`cf-ray` vorhanden) und laufen
am Ursprung einwandfrei — die Kante leitet sie trotzdem auf **fremde
Installationen** weg. Folge: die IndexNow-Schluesseldatei ist unter der
gemeldeten Adresse nicht abrufbar, Bing und Yandex nehmen die Meldungen dieser
beiden Portale nicht an (#35), und die vier WAF-Regeln aus
`docs/bot-traffic.md` Abschnitt 2 greifen ins Leere, weil nie eine Anfrage am
Ursprung ankommt.

Das Entfernen braucht entweder einen Cloudflare-Token mit Zonen-Rechten oder
die Klickarbeit im Dashboard. Im Projekt gibt es den Token nicht (#18, siehe
`docs/messungen/cloudflare-token-anleitung.md`; Stand 08.10.2026 liegt in
`/root/sun-zugang.txt` nur der Turnstile-Token, der keine Zone sieht).
Mit Token erledigt `scripts/cloudflare-weiterleitungen-entfernen.sh
--entfernen` die Arbeit (Abschnitt 2, „Mit Token statt im Dashboard"); tokenfrei
sind die Proben `scripts/cloudflare-weiterleitungen-entfernen.sh --kante` und
`scripts/cloudflare-waf-regeln-setzen.sh --abnahme` (je Exit 71 bei Abweichung).

Jeder Abschnitt endet mit einer Probe. Ergebnisse unten in die Tabelle.

---

## 0. Ausgangsstand, gemessen 08.10.2026

| | `firmenfreund.net` | `tierarztportal.com` |
|---|---|---|
| Kante `/` | **301** → `https://solar-finden.de/` | **302** → `https://pfotencheck.tierarztportal.com/` |
| Kante `/staedte` | 301 → `…/staedte` (**pfadtreu**) | 302 → `…/` (**jeder Pfad auf die Wurzel**) |
| Kante `/<key>.txt` | 301 → `solar-finden.de/<key>.txt`, dort 404 | 302 → Wurzel |
| Kante `www` | 301 → `solar-finden.de/` | 301 → Apex (**richtig, bleibt**) |
| Kante `http://…/.well-known/acme-challenge/test` | 301 → `solar-finden.de/…` | 301 → HTTPS, dann 302 auf `pfotencheck.` |
| Ursprung (`--resolve <domain>:443:88.198.64.145 -k`) | 200, Titel „Solar - Photovoltaik" (Tenant 51) | 200, Titel „Tierarztportal.com" (Tenant 43) |
| Ursprung `/robots.txt`, `/llms.txt` | je 200 | je 200 |
| Ursprung `www` | 301 auf Apex (nginx, #129) | — |
| Name im Ursprungszertifikat | **nein** (nur `firmenfreund.de`/`.com`) | **nein** (nur `www.tierarztportal.com`) |

Vier Punkte daraus, die den Ablauf bestimmen:

1. **Die Weiterleitungsziele sind fremde Installationen**, keine Mandanten
   dieser Produktion: `solar-finden.de` → 213.133.104.170, `Server: Apache`,
   alter Codestand (kein `/llms.txt`, robots.txt ohne KI-Crawler-Block) — die
   Solar-Installation von vor der Uebernahme. `pfotencheck.tierarztportal.com`
   → eigener vhost am selben Server (`root /home/pfotencheck/htdocs/…`) mit
   einer **statischen** Seite (`index.html`, kein Laravel, kein Git),
   `/robots.txt` dort 404.
2. **`tenants.domain` ist in beiden Faellen richtig.** Die Domain in der
   Datenbank nicht anfassen: die Ziele gehoeren anderen Installationen und
   wuerden `keyLocation()` auf einen Host zeigen, den diese App nicht
   ausliefert.
3. **Das Ursprungszertifikat traegt die drei Namen nicht** (`tierarztportal.com`,
   `firmenfreund.net`, `www.firmenfreund.net`; gemessen am Ursprung mit
   `openssl s_client -servername …`, CN `sanitaerfinden.dev`, 55 Namen). Steht
   die Zone auf **Full (strict)**, antwortet Cloudflare nach dem Entfernen der
   Weiterleitung **526** statt die Seite auszuliefern. Deshalb Schritt 1 vor
   Schritt 2.
4. **Das Nachziehen des Zertifikats geht erst nach dem Entfernen.** Die
   HTTP-01-Probe laeuft ueber `http://<name>/.well-known/acme-challenge/…` —
   genau dieser Pfad wird heute weggeleitet (Messung oben). Das ist der Grund,
   warum die drei Namen seit #130 fehlen.

**Probe:** `scripts/cloudflare-waf-regeln-setzen.sh --abnahme` endet mit
Exit 71 und genau zwei Abweichungen (301 `firmenfreund.net`,
302 `tierarztportal.com`), alle uebrigen 21 Portal-Domains mit 200.

---

## 1. SSL-Modus beider Zonen auf „Full" pruefen (vor dem Entfernen)

Cloudflare → Zone waehlen → **SSL/TLS → Overview**. Steht dort
**Full (strict)**, voruebergehend auf **Full** stellen (nicht „Flexible" — das
macht aus HTTPS am Ursprung HTTP und bricht `URL::forceScheme`-Annahmen).
Nach Schritt 3 wieder zurueck auf Full (strict).

Steht die Zone schon auf **Full** oder **Flexible**: nichts tun, nur notieren.

**Probe:** keine eigene — wirkt sich erst in Schritt 2 aus. Notiere den
vorgefundenen Modus je Zone in die Tabelle unten, damit Schritt 4 ihn
wiederherstellen kann.

---

## 2. Die Weiterleitung entfernen

Sie kann an drei Stellen stehen. Der Reihe nach nachsehen, die gefundene
**loeschen** (nicht nur deaktivieren — eine deaktivierte Page Rule wird beim
naechsten Dashboard-Durchgang gern wieder angeschaltet):

1. **Rules → Redirect Rules** (Single Redirect). Erkennungsmerkmal: Ausdruck
   auf `http.host eq "firmenfreund.net"` bzw. die Zone, Ziel
   `concat("https://solar-finden.de", http.request.uri.path)` (pfadtreu, 301)
   bzw. ein statisches `https://pfotencheck.tierarztportal.com/` (302).
2. **Rules → Page Rules** (alte Oberflaeche, bei dieser Zone wahrscheinlich).
   Erkennungsmerkmal: Muster `*firmenfreund.net/*` mit Setting
   *Forwarding URL* → *301 Permanent Redirect* und Ziel
   `https://solar-finden.de/$1`. Bei `tierarztportal.com` ein
   *302 Temporary Redirect* ohne `$1` — das erklaert, warum dort jeder Pfad
   auf der Wurzel landet.
3. **Account → Bulk Redirects** (Konto-Ebene, nicht Zone): die *Lists*
   durchsehen und den Eintrag mit Quelle `firmenfreund.net/` bzw.
   `tierarztportal.com/` aus der Liste loeschen. Ist die Liste danach leer,
   auch die zugehoerige *Bulk Redirect Rule* deaktivieren.

Bei `tierarztportal.com` zusaetzlich klaeren, ob die statische Seite unter
`pfotencheck.` noch gewollt ist. Das Portal laeuft am Apex; der
`pfotencheck.`-vhost ist davon unabhaengig und bleibt erreichbar, egal wie die
Weiterleitung am Apex entschieden wird. Pragmatische Vorgabe: **stehen
lassen** (loeschen waere ein Eingriff in einen fremden vhost und ausserhalb
dieses Tickets); nur die Weiterleitung vom Apex dorthin fällt weg.

Den `www` → Apex 301 von `tierarztportal.com` **nicht** anfassen, der ist
richtig. Bei `firmenfreund.net` muss `www` nach dem Entfernen ebenfalls auf den
Apex zeigen — das macht der Ursprung schon selbst (nginx 301, #129), es
braucht dort keine Cloudflare-Regel.

### Mit Token statt im Dashboard

Liegt ein Token mit Zonen-Rechten vor, erledigt das Skript alle drei Stellen
auf einmal — es sucht in Redirect Rules, Page Rules und den Bulk-Redirect-
Listen des Kontos und entfernt nur, was wegfuehrt:

```bash
scripts/cloudflare-weiterleitungen-entfernen.sh --kante       # ohne Token: was antwortet die Kante heute?
scripts/cloudflare-weiterleitungen-entfernen.sh               # nur nachsehen (Exit 71 bei Fund)
scripts/cloudflare-weiterleitungen-entfernen.sh --entfernen   # loeschen
```

Gebraucht wird `Zone / Zone: Read` + `Zone / Config Rules: Edit` (Redirect
Rules), `Zone / Zone: Edit` (Page Rules) und `Account / Account Filter Lists:
Edit` (Bulk Redirects); fehlt eines davon, meldet das Skript die betroffene
Stelle als „nicht lesbar" und macht mit den uebrigen weiter. Es liest den Token
aus `CF_API_TOKEN`, sonst `CLOUDFLARE_API_TOKEN_SUN_REDIRECT` bzw.
`CLOUDFLARE_API_TOKEN_SUN_WAF` aus `/root/sun-zugang.txt` am Ursprung.
Der dort am 08.10.2026 hinterlegte Turnstile-Token traegt hier **nicht**: er ist
auf „Account / Turnstile: Edit" geschnitten und sieht keine Zone
(`/zones?name=firmenfreund.net` liefert mit ihm eine leere Liste) — das Skript
bricht dann mit Exit 65 ab, ohne etwas anzufassen.

Was als „fremd" gilt und entfernt wird: jede Weiterleitung, deren Ziel-Host
nicht der Apex der Zone selbst ist. Damit bleibt `www` → Apex stehen, waehrend
`solar-finden.de` und die eigene Unterdomain `pfotencheck.tierarztportal.com`
erfasst werden. Beim Bulk Redirect faellt nur der einzelne Listeneintrag,
nicht die Liste und nicht die Regel (sie koennen fremde Zonen des Kontos
bedienen).

Von Hand mit `curl` geht es auch. Zonen-ID aus dem Dashboard (Overview, rechte
Spalte):

```bash
TOKEN=…; ZONE=…
# Single Redirects dieser Zone anzeigen
curl -sS -H "Authorization: Bearer $TOKEN" \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/rulesets/phases/http_request_dynamic_redirect/entrypoint" | jq .
# Page Rules anzeigen
curl -sS -H "Authorization: Bearer $TOKEN" \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/pagerules" | jq '.result[]|{id,targets,actions,status}'
# Page Rule loeschen
curl -sS -X DELETE -H "Authorization: Bearer $TOKEN" \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/pagerules/<rule-id>" | jq .success
```

Beim Ruleset-Weg nicht das ganze Entrypoint-Ruleset loeschen, sondern die eine
Regel aus `rules[]` nehmen und das Ruleset per `PUT` mit der verkuerzten Liste
schreiben — sonst reisst man andere Redirect Rules derselben Zone mit.

**Probe:**

```bash
curl -sS -o /dev/null -w "%{http_code} %{redirect_url}\n" https://firmenfreund.net/
curl -sS -o /dev/null -w "%{http_code} %{redirect_url}\n" https://tierarztportal.com/
```

Beide muessen **200** liefern (kein `redirect_url`). Kommt **526**, steht die
Zone noch auf Full (strict) → Schritt 1.

---

## 3. Zertifikat am Ursprung nachziehen

Erst jetzt ist `/.well-known/acme-challenge/` unter den drei Namen erreichbar.
Am Ursprung als root (SSH-Kuerzel `sun`):

```bash
/root/tls-zertifikat-nachziehen.sh --pruefen tierarztportal.com firmenfreund.net www.firmenfreund.net
/root/tls-zertifikat-nachziehen.sh tierarztportal.com firmenfreund.net www.firmenfreund.net
```

`--pruefen` probt jeden Namen einzeln und stellt nichts aus; es bricht ab,
wenn **ein** Name die Antwort dieses Servers nicht erreicht (Let's Encrypt
stellt alles-oder-nichts aus). Danach sind es 58 Namen statt 55.

Wichtig: Cloudflares Proxy muss fuer den ACME-Pfad durchlassen. Die vier
WAF-Regeln aus `docs/bot-traffic.md` tun das (Regel 1 ist ein Skip und trifft
`/.well-known/`), eine „Under Attack"-Einstellung oder ein Managed-Challenge
auf `/*` nicht. Scheitert die Probe trotz entfernter Weiterleitung, in der Zone
unter **Security → WAF** nachsehen.

**Probe:**

```bash
echo | openssl s_client -connect 88.198.64.145:443 -servername firmenfreund.net 2>/dev/null \
  | openssl x509 -noout -ext subjectAltName | tr ',' '\n' | grep -cE ' DNS:(firmenfreund\.net|www\.firmenfreund\.net|tierarztportal\.com)$'
```

Muss **3** ergeben.

---

## 4. SSL-Modus zuruecksetzen und Abnahme

Beide Zonen wieder auf den in Schritt 1 notierten Modus, Ziel ist
**Full (strict)** (jetzt traegt der Ursprung die Namen).

```bash
scripts/cloudflare-waf-regeln-setzen.sh --abnahme   # muss Exit 0 sein, alle Domains 200
ssh sun 'cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && sudo -u sanitaerfinden /usr/bin/php8.4 artisan guide:golive:check'
```

Erst wenn die Schluesseldatei unter der gemeldeten Adresse direkt 200 liefert,
nehmen Bing und Yandex die IndexNow-Meldungen dieser beiden Portale an. Danach
`#35` gegenpruefen (dort steht die Messung, die den Befund ausgeloest hat).

---

## Ergebnisse

| Schritt | `firmenfreund.net` | `tierarztportal.com` |
|---|---|---|
| 1. SSL-Modus vorgefunden | | |
| 2. Weiterleitung gefunden unter | | |
| 2. Probe `/` = 200 | | |
| 3. Zertifikat 58 Namen | | |
| 4. `--abnahme` Exit 0 | | |
| 4. `guide:golive:check` Exit 0 | | |
