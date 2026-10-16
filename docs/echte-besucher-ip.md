# Echte Besucher-IP am Ursprung

Ticket #43 (Befund S3 aus Review #39, `docs/ad-slots-security-review.md` §13.3).
Umgesetzt und abgenommen am **16.10.2026, 14:54 Uhr** auf der Produktion.

Diese Datei ist die **einzige Stelle**, an der steht, woher `$request->ip()`
kommt. Jede Aussage über ein Limit „je Besucher" hängt daran.

## 1 Wie der Ursprung aufgebaut ist

CloudPanel baut jeden vhost zweistufig
(`/etc/nginx/sites-enabled/sanitaerfinden.dev.conf`):

| Stufe | Block | Rolle |
| --- | --- | --- |
| außen | `listen 443` | TLS, Weiterleitungen, statische Dateien, `proxy_pass http://127.0.0.1:8080` mit `proxy_set_header X-Real-IP $remote_addr` |
| innen | `listen 8080` | `fastcgi_pass` an den PHP-Pool; löst `X-Real-IP` über das realip-Modul wieder auf |

`REMOTE_ADDR` in PHP — und damit `$request->ip()` — ist also das, was der
**innere** Block aus `X-Real-IP` liest, und das ist das `$remote_addr` des
**äußeren** Blocks.

## 2 Was gilt (Stand 16.10.2026)

In `/etc/nginx/nginx.conf`, http-Kontext, für **alle** Sites des Servers:

```nginx
set_real_ip_from 127.0.0.1;
set_real_ip_from 10.0.0.0/8;
set_real_ip_from 172.16.0.0/12;
set_real_ip_from 192.168.0.0/16;
set_real_ip_from ::1;
real_ip_header X-Real-IP;
```

Im **äußeren** 443-Block des Portal-vhosts zusätzlich:

```nginx
include /etc/nginx/cloudflare-ips.conf;   # 22 Cloudflare-Netze, v4 und v6
real_ip_header CF-Connecting-IP;
```

Daraus:

* Kommt die Verbindung **aus einem Cloudflare-Netz**, ist `$remote_addr` im
  äußeren Block die echte Besucheradresse. Das vorhandene
  `proxy_set_header X-Real-IP $remote_addr` trägt sie an den inneren Block und
  damit an PHP; das Zugriffslog des Blocks zeigt sie ebenfalls.
* Kommt die Verbindung **nicht** aus einem Cloudflare-Netz — das Portal ohne
  Kante, `sanitaerfinden.com`, siehe
  `docs/messungen/zonen-umzug-cloudflare-anleitung.md` —, bleibt es bei der
  Absenderadresse, also auch der echten. **Die Bemessung ist netzweit
  einheitlich.**
  (Nachgemessen am 16.10.2026 für #45: `elektrikerportal.com` liegt
  inzwischen hinter der Kante — autoritatives NS `sri.ns.cloudflare.com`,
  `cf-ray` vorhanden. Ohne Kante ist nur noch `sanitaerfinden.com`. Protokoll:
  `docs/messungen/aop-trockenlauf-2026-10-16.txt`.)
* `real_ip_recursive on` bleibt stehen; es wirkt nur auf Listen-Header, und
  `CF-Connecting-IP` wie `X-Real-IP` tragen genau einen Wert.

Angelegt und wiederholbar gemacht von **`scripts/nginx-echte-besucher-ip.sh`**
(läuft als root auf `88.198.64.145`, SSH-Kürzel `sun`):

| Aufruf | Wirkung |
| --- | --- |
| `--pruefen` | zeigt den Stand, schreibt nichts |
| `--netze-abgleichen` | vergleicht die Netzliste im Skript mit `cloudflare.com/ips-v4` und `ips-v6`, Exit 71 bei Abweichung |
| ohne Schalter | schreibt `/etc/nginx/cloudflare-ips.conf`, patcht `nginx.conf` und den vhost, `nginx -t`, `reload` |
| `--abnahme` | fährt die beiden Proben aus §3 |

Gepatcht wird — wie bei `scripts/vhost-www-weiterleitung.sh` — **sowohl** die
ausgelieferte vhost-Datei **als auch** `site.vhost_template` in
`/home/clp/htdocs/app/data/db.sq3`. Ohne das zweite ist die Änderung beim
nächsten Durchgang des CloudPanel-Vhost-Editors weg.

### Warum nginx und nicht `TrustProxies`

Ein `TrustProxies` in `bootstrap/app.php` würde nur PHP erreichen. Die
nginx-Lösung gilt zusätzlich für das Zugriffslog, für
`include /etc/nginx/blocked_ips` und für `limit_req_zone $binary_remote_addr`
— und es gibt nur eine Quelle statt zweier, die auseinanderlaufen können.
`bootstrap/app.php` ist deshalb **unverändert**; wer dort ein `TrustProxies`
nachträgt, baut eine zweite Wahrheit.

## 3 Was vorher falsch war — beides belegt

### 3.1 Jede IP war von außen frei wählbar (schwerer als S3)

`real_ip_header` war in `nginx.conf` **auskommentiert** (Zeile 28). nginx nahm
damit die Vorgabe `X-Real-IP` — und `set_real_ip_from 0.0.0.0/0` machte den
Header von **jedem** Absender vertrauenswürdig. Probe gegen den Ursprung,
16.10.2026 14:51 Uhr:

```
curl --resolve elektrikerportal.com:443:88.198.64.145 \
     -H 'X-Real-IP: 203.0.113.77' https://elektrikerportal.com/robots.txt

203.0.113.77 - - [16/Oct/2026:14:51:07 +0200] "GET /robots.txt HTTP/2.0" 200 …
```

`$remote_addr` und `REMOTE_ADDR` standen auf dem gefälschten Wert. Jedes
`throttle`, jede Turnstile-IP-Bindung, jede Netzprüfung und jedes
`blocked_ips` war mit einem Header aushebelbar — netzweit und auch für die
fremden Projekte auf demselben Server. Gleiche Probe um 14:55 Uhr, mit
`X-Real-IP` **und** `CF-Connecting-IP` gefälscht: im Log steht die echte
Absenderadresse `89.246.160.215`.

### 3.2 Hinter Cloudflare zählte die Edge (Befund S3)

Der äußere Block sah die Edge-Adresse und gab sie als `X-Real-IP` weiter.
`$request->ip()` war die Edge, `throttle:60,1` damit ein Eimer für viele
Besucher; eine einzelne Edge fuhr bis zu 242 Requests je Minute gegen den
Ursprung. Gegenprobe über die Kante um 14:55 Uhr:

```
2a01:4f8:140:44d4::2 - - [16/Oct/2026:14:55:33 +0200] "GET /robots.txt?cb=… HTTP/2.0" 200 …
                                                        … "2a01:4f8:140:44d4::2"
```

Geloggte Adresse = letztes Glied von `X-Forwarded-For`, das Cloudflare auf die
Besucheradresse setzt. Keine `162.158.*`/`172.69.*` mehr.

Dass **PHP** die Adresse sieht, ist an `tracking_events` des Portals
`firmenfreund.de` belegt (`ip_address` wird auf /24 bzw. /48 gekürzt
gespeichert):

```
2026-10-16 12:54:53  2a00:20:c349::     <- nach dem Reload
2026-10-16 12:48:54  104.23.239.0       <- vorher: Cloudflare
2026-10-16 12:48:46  172.71.160.0
2026-10-16 12:41:40  162.158.202.0
```

## 4 Nebenwirkungen, vorher durchgegangen

| Stelle | vorher | nachher |
| --- | --- | --- |
| `tracking_events.ip_address` | Cloudflare-Edge, auf /24 gekürzt | echte Besucheradresse, auf /24 (v4) bzw. /48 (v6) gekürzt. `TrackingService::anonymizeIp()` kann beide Familien, nichts anzupassen. **Zeilen vor dem 16.10.2026 14:54 Uhr sind je IP nicht mit späteren vergleichbar** — Summen und Zeitreihen bleiben gültig, die IP-Spalte ist nur davor wertlos. |
| `App\AntiSpam\Support\BotTraffic` (`ip_prefixes`, heute `47.79.200.0/21`) | lief gegen Edge-Adressen und traf auf den Cloudflare-Portalen **nie** | trifft jetzt. Die Liste enthält kein Cloudflare-Netz, es wird also nichts fälschlich zum Bot. Prüfung arbeitet auf der vollen Adresse, `inCidr()` kann v4 und v6. |
| `throttle:60,1` (`/_ads/impressions`), `throttle:120,1` (`/stats/beacon`), Lead-Routen, `RateLimitGuard`, `RouteServiceProvider` | je Edge | je Besucher. Die Eimer werden **kleiner**, nicht größer — kein Risiko, dass ein Limit plötzlich greift, wo es vorher offen war. |
| `CompanyStatsRecorder::anonymousHash()` (nur für Aufrufe **ohne** Sitzung) | ein Hash je Edge + User-Agent und Tag, also mehrere Besucher in einem Topf | ein Hash je Besucher. Sitzungslose Aufrufe können dadurch **mehr** Profilaufrufe ergeben als vorher. Aufrufe mit Sitzung sind unberührt (`sessionHash()`). |
| Turnstile | band an die Edge | bindet an den Besucher. `siteverify` bekommt damit erstmals die Adresse, die Cloudflare selbst gesehen hat. |
| bestehende Zählerstände | — | nichts wird umgeschrieben. `ad_impression_buffer`/`-_daily` kennen keine IP. |
| Zugriffslog `/home/sanitaerfinden/logs/nginx/access.log` | Edge in `$remote_addr`, Besucher nur in `$http_x_forwarded_for` | Besucher in beiden. Auswertungen von Hand, die auf Spalte 1 eine Edge erwarten, laufen ab dem 16.10.2026 14:54 Uhr auf echte Adressen; kein eingechecktes Skript wertet dieses Log aus. |
| fremde Projekte auf dem Server | trauten `X-Real-IP` von jedem Absender | trauen ihm nur noch vom Vorbau. Alle `proxy_pass`-Ziele auf dem Server sind `127.0.0.1`, es bricht nichts. |

## 5 Grenzen

* Vertraut wird jedem Absender aus einem Cloudflare-Netz. Wer **selbst** aus
  einem solchen Netz heraus den Ursprung direkt anspricht (etwa ein fremder
  Cloudflare-Worker), kann `CF-Connecting-IP` setzen. Das ist der offene Rest
  aus #43; wie er geschlossen wird, steht in **§6**.
* Der innere Block lauscht auf `0.0.0.0:8080`, ist von außen aber nicht
  erreichbar (Probe 16.10.2026: Verbindung läuft in den Timeout). Käme Port
  8080 je frei, wäre `X-Real-IP` dort wieder fälschbar — das Vertrauen gilt
  `127.0.0.1`/`::1`, und ein direkter Treffer käme mit einer anderen
  Absenderadresse, würde also abgelehnt. Der Zustand ist damit abgedeckt,
  gehört aber ins Gedächtnis.
* Die Netzliste veraltet. `--netze-abgleichen` gehört in jede Runde, die am
  Ursprung arbeitet; Abweichung ist Exit 71.
* `nginx.conf` und der vhost sind CloudPanel-Dateien. Nach einem
  CloudPanel-Update `--pruefen` fahren; die Vorlage in `db.sq3` ist gepatcht,
  `nginx.conf` ist es nicht und könnte überschrieben werden.
* Das Schema (`http`/`https`) ist davon **unberührt** — dafür steht weiter
  `fastcgi_param HTTPS "on"` im inneren Block, und der Kommentar in
  `app/Http/Middleware/ContentSecurityPolicy.php` gilt unverändert.

## 6 Gefälschte CF-Connecting-IP (#45)

Die Lücke aus §5: Vertrauen hängt allein am Absendernetz. Jeder, der aus
einem Cloudflare-Netz heraus `88.198.64.145:443` direkt anspricht — fremder
Worker, fremder Tunnel —, setzt `CF-Connecting-IP` frei und hebelt damit
jedes `throttle`, jede Turnstile-IP-Bindung, `blocked_ips` und jede
Netzprüfung aus.

### 6.1 Die Firewall (Weg 1) hilft hier nicht

443 am Ursprung auf die Cloudflare-Netze zu beschränken wirkt gegen den
*falschen* Angreifer: der hier beschriebene sitzt **in** diesen Netzen und
käme weiter durch. Sie nimmt zudem Namen mit, die ohne Kante laufen —
gemessen am 16.10.2026 über alle 48 `server_name`-Einträge des Servers
(`scripts/nginx-origin-pull-absichern.sh --zonen-pruefen`): 45 hinter der
Kante, 3 direkt (`isitangeruestbau.de`, `sanitaerfinden.com`,
`sanitaerfinden.dev`). Also: Aufwand und Kollateralschaden ohne Wirkung auf
den Befund. **Nicht umgesetzt, bewusst.**

### 6.2 Authenticated Origin Pulls, host-gebunden (Weg 2)

Umgesetzt in **`scripts/nginx-origin-pull-absichern.sh`**. Der Portal-Block
bekommt das Client-Zertifikat der Kante und weist ab, was ohne beglaubigtes
Zertifikat kommt — aber nur für Hosts, die ausdrücklich scharf geschaltet
sind:

```nginx
ssl_client_certificate /etc/nginx/cloudflare-origin-pull-ca.pem;
ssl_verify_client optional;
if ($aop_abweisen) { return 403; }
```

`optional` statt `on` ist Pflicht: derselbe `server_name` bedient alle
Portale **und** fremde Namen (`analytics.widimedia.com`,
`www.kasernencheck.de`, `perigee.widimedia.com`) sowie
`sanitaerfinden.com` ohne Kante. Ein `ssl_verify_client on` für den Block
erschlägt die alle. Die Entscheidung fällt in
`/etc/nginx/conf.d/aop-scharfe-hosts.conf` über vier `map`-Stufen
(`$aop_pflicht` je `$host`, `$aop_beglaubigt` je `$ssl_client_verify`,
`$aop_ausnahme` für `/.well-known/`, daraus `$aop_abweisen`); die Liste der
scharfen Hosts liegt in `/etc/nginx/aop-scharfe-hosts`.

| Aufruf | Wirkung |
| --- | --- |
| `--pruefen` | Stand: Zertifikat, vhost-Patch, scharfe Hosts |
| `--zonen-pruefen` | Folgenabschätzung für Weg 1 (§6.1) |
| `--einrichten` | Zertifikat holen (CN + SHA-256 geprüft), map und leere Host-Liste anlegen, vhost **und** `site.vhost_template` patchen, `nginx -t`, reload. **Ändert für sich nichts am Verhalten** |
| `--scharf <host>` | Host in die Liste; bricht ab, wenn der Host nicht hinter der Kante liegt; prüft nach dem Reload über die Kante nach und **nimmt alles zurück**, wenn ein scharfer Host nicht mehr mit 200 antwortet |
| `--entschaerfen <host>` | zurück in den vorherigen Zustand für diesen Host |
| `--abnahme` | je scharfer Host: direkt mit gefälschter `CF-Connecting-IP` muss 403 sein, über die Kante 200 |
| `--zurueck` | vhost-Patch und map entfernen |

`/.well-known/` bleibt immer offen, sonst stirbt die http-01-Erneuerung des
gemeinsamen Zertifikats (`scripts/tls-zertifikat-nachziehen.sh`,
`produktion-tls-ein-zertifikat-alle-portale`). Die `www1.`-Variante jedes
scharfen Hosts wird automatisch mitgenommen, weil sie im selben
`server_name` steht.

Belegt im Trockenlauf vom 16.10.2026 auf einem zweiten nginx-Prozess mit
Ersatzports, ohne die Live-Konfiguration anzufassen —
`docs/messungen/aop-trockenlauf-2026-10-16.txt`: scharfer Host 403 (http und
https), ACME-Pfad 404, übrige Portale und fremde Namen 200.

### 6.3 Reihenfolge — und was zuerst von Hand passieren muss

`--scharf` setzt voraus, dass in der Cloudflare-Zone des Hosts
**SSL/TLS → Origin Server → Authenticated Origin Pulls** eingeschaltet ist
und die Zone auf *Full* bzw. *Full (strict)* steht; bei *Flexible* holt die
Kante über http und kann kein Zertifikat vorlegen. Das ist Kontoarbeit und
damit Handarbeit von Enes (`sun-kein-cloudflare-zugang`,
`portale-blockiert-keine-cloudflare-eingriffe`) — eigenes Ticket. Umgekehrt
ist die Reihenfolge harmlos: ein Client-Zertifikat, das niemand anfordert,
schickt niemand, und `optional` weist nichts ab, solange die Liste leer ist.

Reihenfolge je Portal:

1. Zone: Authenticated Origin Pulls einschalten.
2. `/root/nginx-origin-pull-absichern.sh --einrichten` (einmalig).
3. `/root/nginx-origin-pull-absichern.sh --scharf <domain>` — rollt bei
   Fehlschlag selbst zurück.
4. `/root/nginx-origin-pull-absichern.sh --abnahme` **und**
   `/root/nginx-echte-besucher-ip.sh --abnahme` (muss grün bleiben).

#### Stand 16.10.2026 (#49)

Am Ursprung ist **nichts** scharf und nichts gepatcht, gemessen mit
`--pruefen` auf 88.198.64.145 (Protokoll:
`docs/messungen/aop-stand-2026-10-16.txt`): kein `/etc/nginx/cloudflare-origin-pull-ca.pem`,
kein `conf.d/aop-scharfe-hosts.conf`, kein vhost-Patch, leere Host-Liste.
`CF-Connecting-IP` ist damit weiter frei setzbar, wenn der Absender in einem
Cloudflare-Netz sitzt. Erster Schritt bleibt Kontoarbeit.

Beide Skripte liegen als root-Werkzeug unter `/root/` (gleiche SHA-256 wie im
Repo, Stand 16.10.2026) — das Modul selbst ist nicht Teil des
Produktions-Checkouts, deshalb sind die Befehle oben auf `/root/` gemünzt.

Kantenstand derselben Stunde (`--zonen-pruefen`, alle 48 `server_name`-Einträge):
**46 hinter der Kante, 3 direkt** — `isitangeruestbau.de`,
`sanitaerfinden.com`, `sanitaerfinden.dev`. `elektrikerportal.com` liegt
seit dem Zonenumzug hinter der Kante und kommt mit.

Zu schalten sind **17 Cloudflare-Zonen** für 22 Portale — die fünf
`*.firmenfreund.de`-Portale hängen an der Elternzone, brauchen also keinen
eigenen Schalter:

| Zone | AOP an | `--scharf` gelaufen | Portal-Hosts der Zone |
| --- | --- | --- | --- |
| firmenfreund.de | ☐ | ☐ | firmenfreund.de, apotheke., arztfinder., klempner., unfallarzt., zahnarzt. |
| firmenfreund.net | ☐ | ☐ | firmenfreund.net |
| sanitaerfinder.com | ☐ | ☐ | sanitaerfinder.com |
| fliesenleger.io | ☐ | ☐ | fliesenleger.io |
| kfzwerkstatt.io | ☐ | ☐ | kfzwerkstatt.io |
| metallbauer.io | ☐ | ☐ | metallbauer.io |
| malerfinder.de | ☐ | ☐ | malerfinder.de |
| fahrschulefinder.de | ☐ | ☐ | fahrschulefinder.de |
| findegutachter.de | ☐ | ☐ | findegutachter.de |
| bodenlegerfinden.com | ☐ | ☐ | bodenlegerfinden.com |
| elektrikerportal.com | ☐ | ☐ | elektrikerportal.com |
| schluesseldienstportal.com | ☐ | ☐ | schluesseldienstportal.com |
| speditionportal.com | ☐ | ☐ | speditionportal.com |
| tierarztportal.com | ☐ | ☐ | tierarztportal.com |
| energieberaterportal.net | ☐ | ☐ | energieberaterportal.net |
| geruestbauer.gmbh | ☐ | ☐ | geruestbauer.gmbh |
| mjet.net | ☐ | ☐ | mjet.net |

`sanitaerfinden.com` fehlt mit Absicht: keine Kante, dort wäre die Sperre
eine Totalsperre und `real_ip_header` ist ohnehin wirkungslos. Fremde Namen
im selben `server_name` (`analytics.widimedia.com`, `www.kasernencheck.de`,
`perigee.widimedia.com`, `minber.app` …) und die Alias-Namen ohne Tenant
(`zahnarztportal.com`, `arztfinder.net`, `unfallarzt.net`,
`apothekefinden.net`, `anlagenkarte.de`, `pfotencheck.tierarztportal.com`)
bleiben unscharf — `ssl_verify_client optional` lässt sie unberührt.

Nach jedem `--scharf` hier Datum und Host eintragen (scharfe Hosts:
*noch keine*).

### 6.4 Was auch danach offen bleibt

Das Zertifikat ist Cloudflares **globales** Origin-Pull-Zertifikat
(`CN=origin-pull.cloudflare.net`, SHA-256
`9A:1A:…:A2:B9`, gültig bis 01.11.2029). Es beweist „eine
Cloudflare-Zone", nicht „unsere Zone": wer selbst eine Zone bei Cloudflare
mit Authenticated Origin Pulls auf unsere Ursprungsadresse zeigen lässt,
legt dasselbe Zertifikat vor. Der Rest schließt sich nur mit einem
zonen-eigenen Zertifikat (*Custom Certificate*, Cloudflare-API, Business/
Enterprise) — nochmals Kontoarbeit und nicht Teil von #45. Der Abstand zu
heute ist trotzdem groß: statt „irgendein Absender aus einem der 22
Cloudflare-Netze" braucht es dann eine eigene Cloudflare-Zone, die auf
unseren Ursprung zeigt.
