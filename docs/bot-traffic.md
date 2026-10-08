# Maschineller GET-Verkehr auf den Portalen (#17)

Turnstile schuetzt Formulare (docs/turnstile.md). Dieses Papier behandelt
Seitenabrufe: Scraper, Lead-Sammler und Datenhaendler, die ueber GET laufen und
von Turnstile nie beruehrt werden.

Stand: 08.10.2026. Grundlage ist die Messung aus docs/turnstile.md §1.3 C.

---

## 1. Befund

elektrikerportal.com, `tracking_events`, 30 Tage, 530.152 Ereignisse:

| User-Agent | Ereignisse | /24-Netze |
|---|---|---|
| `Mozilla/5.0 (compatible; HIMZO-DataQuality/1.0; B2B HR data verification…)` | 24.480 | 1 |
| `Mozilla/5.0` (nackt, ohne alles) | 11.241 | 48 |
| `Mozilla/5.0 (compatible; NoscienceLeadResearch/1.0; …)` | 5.523 | 1 |
| `webapp-mapper-authorized-probe/0.1` | 5.482 | 17 |
| `Mozilla/5.0 (compatible; Google-Apps-Script; beanserver; …)` | 5.048 | 7 |
| `python-httpx`, `Python-urllib`, `aiohttp`, `axios/1.x` | ~250 | je 1–10 |
| diverse `*LeadResearch/1.0` (Cloppenburger, Vexara, Bilbao, PRYX) | ~70 | je 1 |

Dazu die Netze `47.79.200.0`–`47.79.207.0` (Alibaba Cloud) mit rund 123.000
Ereignissen. Sie geben sich als Android-Chrome aus und rufen je /24 zwischen
9.000 und 10.500 **verschiedene** Firmenprofile ab. Am User-Agent ist dieser
Verkehr nicht zu erkennen, nur am Netz und am Abrufmuster.

Zusammen sind rund 175.000 von 530.152 Ereignissen (33 %) maschinell.

---

## 2. Cloudflare: Regeln fuer die WAF

**Diese Arbeit ist Handarbeit im Cloudflare-Konto.** Im Repo, in der
Produktions-`.env` und auf dem Server gibt es keinen API-Token mit Rechten fuer
WAF oder Bot-Einstellungen (Vorbedingung: #18). Die Ausdruecke unten sind
fertig zum Einfuegen; sie sind hier abgelegt, damit sie nicht jedes Mal neu
hergeleitet werden muessen.

Reihenfolge ist entscheidend: **die Ausnahme steht vor der Sperre.**

### 2.0 Zuerst: liegt die Zone ueberhaupt bei Cloudflare?

Stand 08.10.2026, **17 von 18 Portal-Zonen** laufen ueber `ara/sri.ns.cloudflare.com`
und antworten mit `cf-ray`. Eine nicht:

| Zone | DNS | `cf-ray` |
|---|---|---|
| **sanitaerfinden.com** | name.com, A-Record direkt auf 88.198.64.145 | nein |
| **elektrikerportal.com** | seit 08.10.2026 Cloudflare, `@`/`www` proxied | ja |
| die uebrigen 16 Portal-Zonen | `ara/sri.ns.cloudflare.com` | ja |

`elektrikerportal.com` ist die Zone, auf der **der gesamte Befund aus Abschnitt 1
gemessen wurde**, und sie liegt jetzt hinter der Kante — die vier Regeln unten
wirken dort also, sobald sie gesetzt sind (#40). Bei `sanitaerfinden.com` wirken
sie weiterhin nicht, egal was im Konto steht: der Verkehr erreicht den Ursprung
ohne Kante. Umzug nach #34 — dort mit einer Besonderheit: `sanitaerfinden.com`
ist zugleich `CENTRAL_DOMAIN` und traegt `/admin`, `/content`, `/dashboard`,
`/horizon`. Regel 1 braucht auf dieser Zone zusaetzlich die
Backoffice-Praefixe, sonst challenged Regel 4 die eigene Verwaltung
(`docs/messungen/zonen-umzug-cloudflare-anleitung.md` Abschnitt 1a).

**Messen, nicht glauben.** Die Kantenprobe fragt die Adresse am *autoritativen*
Nameserver der Zone und schickt `curl` mit `--resolve` dorthin. Das ist kein
Beiwerk: ein zwischenspeichernder Resolver (Fritz!Box, Firmen-DNS) haelt nach
einem Zonenumzug noch die alte Ursprungsadresse, `curl` laeuft dann an der Kante
vorbei und `cf-ray` fehlt, obwohl die Zone proxied ist. Genau so wurde
`elektrikerportal.com` am 08.10.2026 zweimal falsch als „ohne Kante" gemeldet.
Weicht der eigene Resolver ab, sagt das Skript es ausdruecklich — eine Probe mit
`curl https://<domain>/` von Hand ist dann wertlos.

Fuer die Zonen hinter der Kante sind die Regeln sofort wirksam und sinnvoll:
die Datensammler aus Abschnitt 1 laufen ueber User-Agent-Merkmale, nicht ueber
eine einzelne Domain.

Der Umzug der beiden Zonen ist Kontoarbeit und steht Schritt fuer Schritt in
`docs/messungen/zonen-umzug-cloudflare-anleitung.md` (#34), mit dem
DNS-Bestand von vorher als Rohdaten daneben. Der fehleranfaellige Teil —
Cloudflares Zonen-Scan uebernimmt beim Anlegen nicht zwingend jeden MX-, SPF-
oder DKIM-Record — ist automatisiert:

```bash
scripts/zone-dns-umzug.sh --bestand       # vorher: Stand am autoritativen NS festhalten
scripts/zone-dns-umzug.sh --abnahme       # nachher: Record-Vergleich + Kantenprobe
```

### 2.1 Regel 1 — Ausnahme (Action: Skip)

Vor allen anderen Regeln. Skip auf alle weiteren Custom Rules, Rate Limiting
und den Bot Fight Mode. Ohne diese Regel bricht docs/guide-golive.md §1.4
(IndexNow) und die Indexierung.

```
(cf.client.bot)
or (http.request.uri.path in {"/robots.txt" "/sitemap.xml" "/llms.txt" "/llms-full.txt" "/ads.txt"})
or (http.request.uri.path matches "^/sitemap[^/]*\.xml$")
or (http.request.uri.path matches "^/[a-f0-9]{8,128}\.txt$")
or (lower(http.user_agent) contains "bingbot")
or (lower(http.user_agent) contains "googlebot")
```

* `cf.client.bot` ist Cloudflares *verifizierter* Bot — Googlebot, Bingbot,
  GPTBot, ClaudeBot, PerplexityBot. Die KI-Crawler sind laut
  `App\Services\RobotsTxtBuilder::AI_CRAWLERS` ausdruecklich erwuenscht und
  duerfen nicht gesperrt werden.
* Das Hex-Muster trifft die IndexNow-Key-Dateien; der Key ist
  `HMAC-SHA256('indexnow|<tenant-key>', APP_KEY)`, die Datei liegt unter
  `/<key>.txt` (docs/guide-golive.md §1.4, Haken "Cloudflare-Regeln blockieren
  `/<key>.txt` nicht"). Die Spanne `{8,128}` ist dieselbe wie in der Route
  (`routes/tenant.php`, `->where('key', '[a-f0-9]{8,128}')`): die Laenge haengt
  an `guide.publishing.indexnow.key_length` (Vorgabe 32, zulaessig bis 128).
  Eine engere Spanne in der WAF wuerde die Datei nach einer Erhoehung still
  aussperren.
* Die beiden User-Agent-Zeilen sind doppelter Boden fuer den Fall, dass die
  Verifizierung einmal nicht greift. Sie sind faelschbar — genau deshalb sind
  sie nur in der *Ausnahme*, nie als Beweis fuer irgendetwas.

### 2.2 Regel 2 — Selbstbenannte Datensammler (Action: Block)

Diese Bots nennen sich im User-Agent. Sie werden gesperrt, nicht gechallenged:
hinter diesen Zeichenketten sitzt kein Mensch, der eine Challenge loesen
koennte.

```
(lower(http.user_agent) contains "dataquality")
or (lower(http.user_agent) contains "leadresearch")
or (lower(http.user_agent) contains "lead-research")
or (lower(http.user_agent) contains "authorized-probe")
or (lower(http.user_agent) contains "webapp-mapper")
or (lower(http.user_agent) contains "google-apps-script")
or (lower(http.user_agent) contains "beanserver")
or (lower(http.user_agent) contains "python-httpx")
or (lower(http.user_agent) contains "python-urllib")
or (lower(http.user_agent) contains "python-requests")
or (lower(http.user_agent) contains "aiohttp")
or (lower(http.user_agent) contains "scrapy")
or (lower(http.user_agent) contains "go-http-client")
or (starts_with(lower(http.user_agent), "axios/"))
or (starts_with(lower(http.user_agent), "curl/"))
or (starts_with(lower(http.user_agent), "wget/"))
or (http.user_agent eq "Mozilla/5.0")
or (http.user_agent eq "Mozilla/4.0")
or (http.user_agent eq "")
```

Die Liste ist dieselbe wie in `config/antispam.php` `bot_traffic`; wird dort
etwas ergaenzt, gehoert es auch hierher und umgekehrt.

### 2.3 Regel 3 — Scraper-Netze (Action: Managed Challenge)

```
(ip.src in {47.79.200.0/21})
```

Bewusst **Managed Challenge** und nicht Block: in einem Cloud-/24 kann auch ein
VPN-Ausgang eines echten Besuchers liegen. Ein Scraper loest die Challenge
nicht, ein Browser schon. `47.79.200.0/21` deckt `47.79.200.0`–`47.79.207.255`
und damit alle acht gemessenen Netze ab.

### 2.4 Regel 4 — Rate Limiting auf Seitenabrufe

Kein Pfad-Praefix moeglich: Firmenprofile liegen auf `/{companySlug}`, also
direkt unter der Wurzel (`routes/tenant.php`). Die Regel muss deshalb am
Verfahren haengen, nicht am Pfad.

* Characteristics: IP
* Expression: `(http.request.method eq "GET" and not http.request.uri.path matches "^/(build|storage|img|css|js|favicon)")`
* Schwelle: 120 Anfragen / 1 Minute, Action: Managed Challenge, Dauer 1 Minute

Die Ausnahmen sind Praefixe, keine Verzeichnisse — das ist Absicht und
geprueft: `storage` deckt damit auch die Tenant-Platten `/storage-<uuid>/...`
ab (eine je Mandant, siehe `public/`), `img` auch `/images/...`. Die Schriften
brauchen keinen eigenen Eintrag, Vite liefert sie unter `/build/assets/` aus
(`resources/views/themes/sun-v2/views/layouts/sun.blade.php`).

120/min ist weit ueber dem, was ein Mensch mit Bildern und Vorschauen erzeugt,
und weit unter den 9.000–10.500 Profilen, die die Alibaba-Netze je /24 geholt
haben. Regel 1 nimmt Suchmaschinen vorher heraus — ein Googlebot-Crawl darf
diese Schwelle reissen.

### 2.5 Mit Skript statt im Dashboard

`scripts/cloudflare-waf-regeln-setzen.sh` setzt die vier Regeln je Portal-Zone
und prueft sie gegen diesen Abschnitt. Die Ausdruecke im Skript sind wortgleich
mit den obigen; geaendert wird an beiden Stellen oder an keiner.

```bash
scripts/cloudflare-waf-regeln-setzen.sh --kanten-pruefen     # §2.0, ohne Token
scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme   # §2.6, nur lesen
scripts/cloudflare-waf-regeln-setzen.sh --pruefen            # Soll-Ist
scripts/cloudflare-waf-regeln-setzen.sh                      # setzen
scripts/cloudflare-waf-regeln-setzen.sh --abnahme            # §6 Schritt 4, ohne Token
```

`--kanten-pruefen` und `--abnahme` brauchen **keinen** Cloudflare-Token:
das erste liest Nameserver und `cf-ray` je Zone, das zweite ermittelt die
IndexNow-Schluesseladressen am Ursprung (der Schluessel haengt an `APP_KEY`,
steht also nirgends im Repo) und ruft jede mit der Kennung `Bingbot` ab.
Beide enden mit Exit 71, wenn etwas abweicht.

Der Lauf ist wiederholbar: die drei Custom Rules stehen danach immer auf den
Plaetzen 1 bis 3 — die Ausnahme also vor der Sperre —, fremde Regeln der Zone
bleiben dahinter erhalten und werden gemeldet. Ein zweiter Lauf erzeugt keine
Dubletten, weil eigene Regeln an der Kennung `SUN #24` in der Beschreibung
erkannt und ersetzt werden.

Der Token dafuer ist **nicht** der aus #18: jener ist auf
"Account / Turnstile: Edit" geschnitten. Gebraucht werden
`Zone / Zone: Read`, `Zone / Firewall Services: Edit` und fuer die
Bestandsaufnahme `Zone / Bot Management: Read`. Ohne Token bleibt der Weg
ueber das Dashboard mit denselben Angaben.

### 2.6 Vorher pruefen

Vor dem Setzen festhalten (Screenshot oder Notiz ins Ticket). Den ersten
Punkt liest `scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme` je
Zone aus, der zweite ist Dashboard-Arbeit:

* Ist **Bot Fight Mode** ueberhaupt an? Wenn ja, in welcher Form
  (Bot Fight Mode / Super Bot Fight Mode)? Super Bot Fight Mode blockt mit
  "Definitely automated" bereits einen Teil des oben gemessenen Verkehrs — dann
  erklaeren sich die Zahlen nur, wenn er aus ist.
* Security Events der letzten 24 h nach `cf.client.bot = false` filtern und die
  obigen User-Agents suchen: durchgelassen oder gesperrt?
* Nach dem Setzen erneut messen, mit `antispam:bot-traffic` (§5). Die Quote darf
  fallen, die Zahl der Ereignisse echter Besucher nicht.

Beide Punkte setzen voraus, dass die Zone ueberhaupt hinter der Kante liegt —
erst §2.0 (`--kanten-pruefen`), dann das hier. Der Vorsatz zum Abhaken steht in
§6.

### 2.7 Wirkung ohne Kontozugang nachmessen

`scripts/cloudflare-waf-regeln-setzen.sh --pruefen` vergleicht das Soll gegen
die API und braucht einen Token. Ob die Regeln *wirken*, laesst sich auch ohne
Konto messen, an der Kante:

```bash
scripts/waf-wirkung-pruefen.sh
scripts/waf-wirkung-pruefen.sh --zone=elektrikerportal.com
```

Fuenf einzelne GET-Anfragen je Zone, keine Last. Geprueft wird: steht die Zone
ueberhaupt hinter Cloudflare (Header `cf-ray`), sperrt `/` mit User-Agent
`python-requests/2.31.0` (erwartet 403, Regel 2), kommt ein Browser durch
(200), kommt `/robots.txt` **trotz** Bot-Kennung durch (200 — Regel 1 steht
vor Regel 2), und bleibt ein Pfad nach dem IndexNow-Muster frei. Der dafuer
abgerufene Pfad `/0123456789abcdef0123456789abcdef.txt` trifft absichtlich
keine echte Schluesseldatei: eine 404 vom Ursprung beweist, dass das
Hex-Muster der Ausnahme greift, ohne den Schluessel der Produktion zu kennen.

Exit 0 = wirksam, 72 = Bot kommt durch (Regeln fehlen), 73 = es wird zu viel
gesperrt (Besucher, `robots.txt` oder IndexNow betroffen; der schlimmere Fall,
Regel 1 steht dann nicht vorn).

Das Skript beantwortet nebenbei die erste Frage aus 2.6: kommt ein
`python-requests` mit 200 durch, ist auch Bot Fight Mode fuer diesen Verkehr
nicht aktiv.

**Messung vom 08.10.2026, vor dem Setzen** (`exit 72`): 14 Zonen hinter der
Kante lassen `python-requests` mit 200 durch — es blockt nichts, Bot Fight Mode
ist aus. `elektrikerportal.com` und `sanitaerfinden.com` haben gar keine
Cloudflare-Kante (#34), dort kann keine Regel greifen; `tierarztportal.com`
(302) und `firmenfreund.net` (301) sind Weiterleitungen (#37). Ausgerechnet das
Portal, aus dem die Messung in Abschnitt 1 stammt, liegt also nicht hinter der
Kante — die Regeln senken dessen Bot-Quote erst, wenn #34 erledigt ist.

---

## 3. Entscheidung: dieser Verkehr zaehlt nicht in der Statistik

**Nein, er landet nicht mehr in der Statistik der Betriebe.** Er verfaelscht
die Premium-Statistik (#9): ein Betrieb sieht Profilaufrufe, die niemand
gemacht hat, und darauf stuetzt sich eine Kaufentscheidung.

Umgesetzt als eine Stelle: `App\AntiSpam\Support\BotTraffic`, Liste in
`config/antispam.php` `bot_traffic`. Daran haengen beide Schreibwege:

* `App\Services\TrackingService` (`tracking_events`) — die frueher dort
  verdrahtete Konstante `BOT_PATTERNS` ist weg.
* `App\Services\Premium\CompanyStatsRecorder` (`company_events`, #9) — die
  frueher dort gelesene Liste `config/premium.php` `stats.bot_user_agents` ist
  weg, die Datei verweist nur noch auf die neue Stelle.

Vorher gab es zwei Listen mit zwei Staenden, und **keine der beiden kannte
einen der in §1 gemessenen Scraper**: weder `HIMZO-DataQuality` noch
`*LeadResearch`, `webapp-mapper-authorized-probe`, `Google-Apps-Script` oder
den nackten `Mozilla/5.0`. Rund 175.000 Ereignisse in 30 Tagen auf einem Portal
sind also bisher als echte Aufrufe gezaehlt worden.

Drei Merkmale, in dieser Reihenfolge:

1. kein User-Agent (oder `-`) → Bot
2. User-Agent exakt auf `bare_user_agents` (`Mozilla/5.0` und nichts weiter) → Bot
3. Teilstring auf `user_agents` oder Netz auf `ip_prefixes` → Bot

Die Liste ist absichtlich grob. Ein falscher Treffer kostet eine Zeile in der
Statistik, ein Durchrutscher verfaelscht sie.

**`BotTraffic` sperrt nichts.** Das Abweisen gehoert in die WAF (§2), aus zwei
Gruenden: es spart Rechenzeit vor dem Ursprung, und eine App-Sperre mit dieser
groben Liste wuerde Suchmaschinen und die IndexNow-Wege mittreffen — die darf
Cloudflare mit `cf.client.bot` sauber ausnehmen, die App nicht. Je Portal
abschaltbar ist die Erkennung ueber `bot_traffic.enabled`.

Nicht angefasst: der Bestand. `tracking_events` und `company_events` werden
nicht nachtraeglich bereinigt — die Rohdaten laufen nach 14 Tagen aus
(`premium.stats.raw_retention_days`), und `company_stats_daily` nachtraeglich
zu korrigieren waere eine Umschreibung von Zahlen, die Betriebe schon gesehen
haben. Die Zahlen werden ab jetzt richtig, nicht rueckwirkend.

Hoeflicher Zusatz ohne Durchsetzungskraft: `App\Services\RobotsTxtBuilder`
listet die selbstbenannten Sammler als eigene `robots.txt`-Gruppe mit
`Disallow: /` (`UNWANTED_CRAWLERS`). Dass sie sich daran halten, ist nicht zu
erwarten; die Gruppe dokumentiert den Willen des Betreibers und kostet nichts.
Suchmaschinen und KI-Crawler stehen dort nie drin.

---

## 4. `tracking_events.ip_address` ist absichtlich auf /24 gekuerzt

Ja, Absicht. `TrackingService::anonymizeIp()` setzt bei IPv4 das letzte Oktett
auf `0` und nullt bei IPv6 die letzten 80 Bits, bevor geschrieben wird —
begruendet mit der DSGVO. Deshalb enden in 7 Tagen alle 221.913 Adressen auf
`.0`.

Folgen, die festzuhalten sind:

* Eine **ASN-Zuordnung** ist aus dieser Quelle nicht moeglich, eine
  Netz-Zuordnung auf /24 schon.
* Ein **Limit je Einzel-IP** braucht eine eigene Quelle. Es gibt sie: #8 zaehlt
  auf dem HMAC-Hash der vollen Adresse (`TurnstileVerification::hashIp()`,
  `App\AntiSpam\Support\RateLimitGuard`) und nicht auf dieser Spalte. Fuer GET
  uebernimmt das Rate Limiting Cloudflare (§2.4), wo die volle Adresse vorliegt.
* Die Bot-Erkennung aus §3 laeuft **vor** dem Schreiben auf `$request->ip()`,
  also auf der vollen Adresse. `bot_traffic.ip_prefixes` funktioniert daher
  praezise, obwohl die Spalte gekuerzt ist. Dass ein /24-Praefix auch gegen die
  gekuerzte Spalte passt, nutzt nur der Auswertung in §5.

Die Kuerzung bleibt. Sie aufzugeben hiesse, personenbezogene Vorratsdaten ueber
jeden Seitenaufruf jedes Besuchers anzulegen — fuer einen Zweck, den Cloudflare
vor dem Ursprung besser erfuellt.

---

## 5. Nachmessen

```bash
php artisan antispam:bot-traffic --tenant=elektrikerportal.com --days=30
php artisan antispam:bot-traffic --days=7 --top=40 --min=50
```

`App\Console\Commands\AntispamBotTraffic` liest `tracking_events` je Portal und
zeigt drei Dinge:

1. den Anteil der Ereignisse, den die Liste aus §3 trifft,
2. die groessten User-Agents, die sie **noch nicht** kennt,
3. die /24-Netze sortiert nach der Zahl verschiedener Betriebe — das Muster
   eines Scrapers, auch wenn der User-Agent wie ein Browser aussieht.

Der Lauf schreibt nichts. Aus Punkt 2 und 3 werden `config/antispam.php`
`bot_traffic` und die Regeln in §2 gepflegt — beides zusammen, sonst laufen sie
auseinander.

---

## 6. Handgriff im Cloudflare-Konto (#24) — abhaken

Alles Maschinelle liegt im Repo: die Ausdruecke in Abschnitt 2,
`scripts/cloudflare-waf-regeln-setzen.sh` fuer das Setzen und die beiden
tokenfreien Pruefungen, die Liste in `config/antispam.php` `bot_traffic`
dazu abgeglichen. Was bleibt, ist Kontoarbeit und steht hier als Vorsatz.

**Ausgangsstand, gemessen am 08.10.2026** (gegen diese Werte wird nachher
verglichen — beide Abweichungen bestehen *vor* dem Setzen, sie sind keine Folge
der Regeln):

* `--kanten-pruefen`: 17 von 18 Zonen hinter Cloudflare, nur sanitaerfinden.com
  nicht (Abschnitt 2.0, #34; elektrikerportal.com ist am 08.10.2026 nachgezogen
  und mit `scripts/zone-dns-umzug.sh --abnahme` abgenommen).
* `--abnahme`: 21 von 23 Portal-Domains liefern die IndexNow-Schluesseldatei
  mit Bingbot-Kennung mit 200. firmenfreund.net: 301 auf solar-finden.de, dort
  404. tierarztportal.com: 302 auf pfotencheck.tierarztportal.com, dort 200
  (#35).
* Bot Fight Mode / Super Bot Fight Mode: fuer elektrikerportal.com nicht
  feststellbar und ohne Bedeutung — die Zone laeuft nicht ueber Cloudflare.
  Fuer die 16 Zonen an der Kante liest `--bestandsaufnahme` die Schalter aus,
  sobald ein Token vorliegt.

Reihenfolge:

- [ ] **1. Token anlegen** (vormals #18). Cloudflare → My Profile → API Tokens →
  Create Token mit `Zone / Zone: Read`, `Zone / Firewall Services: Edit`,
  `Zone / Bot Management: Read`, Zonen: alle Portal-Zonen. Der Turnstile-Token
  traegt hier nicht (Abschnitt 2.5). Dann einmalig
  `scripts/cloudflare-waf-regeln-setzen.sh --token-ablegen`.
- [ ] **2. Vorher festhalten.**
  `scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme` (Ausgabe als
  Kommentar ins Ticket) und im Dashboard Security → Events, 24 h, Filter
  „Bot Detection = not verified bot" (Abschnitt 2.6).
- [ ] **3. Setzen.** `--pruefen`, dann ohne Option setzen, dann `--pruefen`
  erneut: muss mit Exit 0 enden. Ohne Token: dieselben vier Regeln von Hand
  nach Abschnitt 2, Reihenfolge Skip → Block → Managed Challenge →
  Rate Limiting.
- [ ] **4. Abnahme.** `scripts/cloudflare-waf-regeln-setzen.sh --abnahme` —
  erlaubt ist hoechstens der Ausgangsstand von oben, keine neue Zeile mit
  einem anderen Status als 200. Gegenprobe am Ursprung:
  `php artisan guide:golive:check`.
- [x] **4a-1. elektrikerportal.com nachgezogen** (#34, 08.10.2026): Zone bei
  Cloudflare, `@` und `www` proxied, MX `smtp.google.com` und die
  `google-site-verification`-TXT uebernommen,
  `scripts/zone-dns-umzug.sh --abnahme --zone=elektrikerportal.com` Exit 0.
  Nebenbefund: der Platzhalter `*` und damit `mail`/`smtp`/`imap`/`pop`/
  `webmail`/`autodiscover`/`autoconfig`/`ftp` liegen mit hinter dem Proxy —
  ueber diese Namen antwortet nur noch HTTP(S). Mail laeuft ueber den MX von
  Google und ist davon nicht betroffen; sollte dort je ein Mailclient oder
  FTP haengen, die Namen einzeln grau schalten. `--vergleichen` meldet den
  Fall jetzt als `i`-Zeile.
- [ ] **4a-2. sanitaerfinden.com nachziehen** (#34,
  `docs/messungen/zonen-umzug-cloudflare-anleitung.md`): Zone in Cloudflare
  aufnehmen, Records abgleichen (kein MX vorhanden, nur eine
  `google-site-verification`-TXT), Proxy an `@`/`www`, Nameserver bei name.com
  umstellen, dann `scripts/zone-dns-umzug.sh --abnahme --zone=sanitaerfinden.com`
  (Exit 0) und erst danach die vier Regeln auch auf dieser Zone setzen.
- [ ] **4b. Die beiden weggeleiteten Zonen freiraeumen** (#37,
  `docs/messungen/weiterleitungen-entfernen-anleitung.md`): bei
  `firmenfreund.net` und `tierarztportal.com` leitet die Cloudflare-Kante auf
  fremde Installationen weg (301 auf `solar-finden.de`, 302 auf
  `pfotencheck.tierarztportal.com`). Solange das so ist, kommt dort keine
  Anfrage am Ursprung an, die Regeln wirken nicht und IndexNow faellt aus
  (#35). Reihenfolge: SSL-Modus auf „Full", Weiterleitung loeschen
  (Redirect Rule / Page Rule / Bulk Redirect), dann
  `scripts/tls-zertifikat-nachziehen.sh` fuer die drei fehlenden Namen, dann
  zurueck auf „Full (strict)" und `--abnahme` mit Exit 0.
- [ ] **5. Nach einigen Tagen nachmessen** (Abschnitt 5):
  `php artisan antispam:bot-traffic --days=7 --top=40 --min=50`. Fuer
  elektrikerportal.com ist die Messung seit dem Umzug (08.10.2026)
  aussagekraeftig, sobald die Regeln stehen; fuer sanitaerfinden.com erst nach
  dem Umzug. Die
  Bot-Quote muss fallen, die Zahl der Ereignisse echter Besucher nicht.
- [ ] **6. Gepflegt wird zu zweit.** Jede Ergaenzung an einer WAF-Regel gehoert
  in Abschnitt 2 **und** in `config/antispam.php` `bot_traffic`, sonst laufen
  Sperre und Statistik-Erkennung auseinander.
