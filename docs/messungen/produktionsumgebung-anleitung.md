# Anleitung: Produktionsumgebung für den Go-Live bereitstellen (#106)

Stand der Erhebung: **09.09.2026**, erhoben von einem Agentenlauf mit Lesezugriff
auf den Server (`ssh sun`). Die Schritte 3 bis 7 kann nur ein Mensch mit Zugang zu
den Schlüsselkonten und der Freigabe für Änderungen am Live-System ausführen.

## 0. Der wichtigste Befund vorweg

**Es gibt bereits eine Produktionsumgebung.** Die frühere Annahme („SUN hat keine
Produktion") ist falsch. Belege:

| Sache | Wert |
| --- | --- |
| Server | `88.198.64.145` (SSH-Kürzel `sun`, User `root`), Hetzner, Ubuntu 24.04 |
| Verwaltung | CloudPanel (`/home/clp`), nginx 1.28, PHP-FPM, PHP-CLI 8.4.21, Redis aktiv |
| Installation | `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, Git-Remote `git@github.com:enesk/sun.git` |
| Stand dort | Commit `fc06dd3` (lokaler HEAD ist `e7e4664`) |
| Umgebung | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sanitaerfinden.com`, `CENTRAL_DOMAIN=sanitaerfinden.com` |
| Portale | 23 Tenants mit **echten** Domains (Tabelle in Abschnitt 4) |

Was dort **fehlt**: das gesamte Content-Vorhaben. `app/Content` existiert auf dem
Server nicht, `php artisan content:golive:check` antwortet dort mit
„There are no commands defined in the `content:golive` namespace".

Grund: der Code ist nie eingecheckt worden. Im Arbeitsverzeichnis liegen
**363 unversionierte Dateien** und 59 geänderte, darunter `app/Content/`,
`config/content*.php`, `deploy/supervisor/`, `docs/`, `design/`. Ein Deploy zieht
aus GitHub — dort liegt nichts davon. Das ist die erste harte Vorbedingung und
wird als eigenes Ticket geführt (siehe Abschnitt 1).

Der Server hostet außerdem **fremde Live-Projekte** (widimedia.com,
kasernencheck.de, pickyourpic.de, eneskul.com, esygraphy.de, theone-sportsbar.de,
perigee/analytics.widimedia.com). Deshalb gilt ausnahmslos:

> `dep provision` und alle `provision:*`-Tasks dürfen auf diesem Server **nicht**
> laufen. Sie installieren nginx, PHP und Supervisor neu und nehmen die fremden
> Seiten mit. `deploy.php` trägt diesen Hinweis seit #106 im Kopf.

## 1. Vorbedingung: Code nach GitHub bringen

Ohne diesen Schritt ist jeder weitere sinnlos.

1. Arbeitsstand sichten und in nachvollziehbaren Commits ablegen (Secret-Scan
   läuft im Pre-Commit-Hook mit: `composer run secrets:scan`).
2. Nach `origin/main` pushen.

**Probe:** `git status --porcelain | wc -l` ist 0 und
`git ls-remote origin main` zeigt denselben Commit wie `git rev-parse HEAD`.

## 2. Deploy-Ziel

`deploy.php` trug bis #106 die Starterkit-Platzhalter. Jetzt eingetragen:

```php
$remoteUser = 'sanitaerfinden';
$host       = '88.198.64.145';
$domain     = 'sanitaerfinden.com';
$repository = 'git@github.com:enesk/sun.git';
$phpVersion = '8.4';
```

Die laufende Installation ist **von Hand** eingerichtet und liegt nicht in einer
Deployer-Release-Struktur. `dep deploy` legt eine neue Struktur unter
`~/app` (also `/home/sanitaerfinden/app`) an und ändert an der ausgelieferten
Seite nichts, solange der vhost `sanitaerfinden.dev.conf` weiter auf
`htdocs/sanitaerfinden.dev` zeigt. Der Wechsel des vhost-Roots auf
`{{deploy_path}}/current/public` ist ein bewusster, einmaliger Handgriff mit
Wartungsfenster — oder man bleibt beim heutigen Weg (`git pull` im
Installationsverzeichnis) und benutzt Deployer gar nicht.

**Probe:** `php artisan content:golive:check` zeigt bei „Deploy-Ziel (deploy.php)"
ein ✓ (lokal am 09.09.2026 bestätigt).

## 3. Produktions-`.env` ergänzen

In `/home/sanitaerfinden/htdocs/sanitaerfinden.dev/.env` fehlen die Schlüssel des
Vorhabens. Stand 09.09.2026 sind Schalter und Budgetgrenzen eingetragen,
`ANTHROPIC_API_KEY`, `VOYAGE_API_KEY` und `GOOGLE_SERVICE_ACCOUNT_JSON` fehlen
dagegen ganz (keine leeren Zeilen, gar keine Zeilen). Sie werden einzeln in
`docs/messungen/produktionsschluessel-anleitung.md` (#115) abgearbeitet. Zu ergänzen, Werte nach Abschnitt 2 der
Checkliste `docs/content-golive.md`:

```
CONTENT_PIPELINE_ENABLED=true
ANTHROPIC_API_KEY=…
VOYAGE_API_KEY=…
GOOGLE_SERVICE_ACCOUNT_JSON=storage/app/private/google/search-console.json
CONTENT_BUDGET_DAILY_USD=…
CONTENT_BUDGET_DAILY_USD_PER_TENANT=…
CONTENT_BUDGET_MONTHLY_USD=…
CONTENT_ALERT_MAIL=…
CONTENT_AUTHOR_USER_ID=…
```

Die Dienstkonto-Datei gehört nach `storage/app/private/`, **nie** unter `public/`;
`content:golive:check` weist das sonst als Fehler aus.

**Probe:** `php artisan content:metrics:preflight` läuft ohne Fehler, und
`content:golive:check` zeigt bei „Anthropic-Zugang", „Voyage-Zugang" und
„Search-Console-Dienstkonto" ✓.

## 4. Domains je Portal

Die echten Domains stehen bereits im vhost und in der Produktionsdatenbank. Die
lokale Entwicklungsdatenbank führt dieselben Portale auf `.test`; drei Werte waren
dort kaputt und sind mit #106 begradigt:

| Portal | lokal vorher | lokal jetzt | Produktion |
| --- | --- | --- | --- |
| Gerüstbauer | `geruestbuaer.gmbh` (Dreher, echte Domain) | `geruestbauer.test` | `geruestbauer.gmbh` |
| Zahnarzt | `zahnarzt.test#` (Doppelkreuz) | `zahnarzt.test` | `zahnarzt.firmenfreund.de` |
| Schlüsseldienst | `schlusseldienst` (ohne Endung) | `schluesseldienst.test` | `schluesseldienstportal.com` |

Annahme dazu: lokal bleibt es bei `.test`, damit kein Entwicklungslauf gegen die
echte Domain `geruestbauer.gmbh` läuft. Die Produktionsdatenbank führt die echten
Werte ohnehin selbst.

**Achtung:** derselbe Doppelkreuz-Fehler steckt auch in der Produktion, im vhost
und in CloudPanel: `tierarztportal.com#` steht als eigener `server_name` neben dem
richtigen `tierarztportal.com`. Der Tenant-Eintrag ist sauber, der vhost nicht —
beim nächsten CloudPanel-Durchgang mit entfernen.

Damit so etwas nicht wieder unbemerkt bleibt, prüft `content:golive:check` seit
#106 nicht mehr nur, ob eine Domain gesetzt ist, sondern auch ihre Schreibweise
(Protokollpräfix, fehlende Endung, unerlaubte Zeichen).

**Probe:** In `content:golive:check` steht in keiner Zeile „ist kein gültiger
Hostname"; alle IndexNow-Adressen sehen aufrufbar aus.

### 4.1 TLS-Zertifikat aller Portaldomains (#123)

Alle Portale hängen an einem einzigen vhost (`sanitaerfinden.dev.conf`) und
damit an **einem** Zertifikat: `/etc/nginx/ssl-certificates/sanitaerfinden.dev.crt`.
Wer eine Domain in `tenants.domain` einträgt, hat sie damit noch lange nicht
im Zertifikat — der `server_name` wird gepflegt, die SAN-Liste nicht.

**Befund 09.09.2026 (Auslöser #123, Randbefund aus #117).** Das Ticket nannte
zwei Portale. Tatsächlich deckte das Zertifikat nur sechs Namen ab
(`sanitaerfinden.dev`, `sanitaerfinden.com`, `bodenlegerfinden.com`,
`elektrikerportal.com`, `fahrschulefinder.de`, `firmenfreund.com`, je mit `www.`).
**19 der 23 Portaldomains** brachen im Browser mit einem TLS-Fehler ab, nicht
zwei. Der Test aus #117 hatte nur fünf Domains angefasst.

**Behoben am 09.09.2026** mit einem Zertifikat über **47 Namen**, am selben Tag
auf **55 Namen** erweitert (#131, Abschnitt 4.5; Laufzeit bis 08.12.2026):

```
clpctl lets-encrypt:install:certificate \
  --domainName=sanitaerfinden.dev \
  --subjectAlternativeName=www.sanitaerfinden.dev,firmenfreund.com,...
```

Der CloudPanel-Site-Name ist `sanitaerfinden.dev` (Tabelle `site` in
`/home/clp/htdocs/app/data/db.sq3`); er gehört in `--domainName`, alles übrige
in `--subjectAlternativeName`. Ein Wildcard `*.firmenfreund.de` per DNS-01 war
nicht nötig — die Portale liegen auf zwölf verschiedenen Registrierdomains, ein
Wildcard hätte nur einen Bruchteil erschlagen.

Vor dem Ausstellen gehören Zertifikat, Schlüssel, vhost und die CloudPanel-Datenbank
gesichert (am 09.09.2026 nach `/root/tls-backup-20260909/`): Let's Encrypt stellt
alles-oder-nichts aus, ein einziger nicht erreichbarer Name lässt den ganzen
Lauf scheitern.

**Vorprobe vor jedem Ausstellen.** Erst prüfen, ob jeder Name die HTTP-01-Antwort
dieses Servers erreicht — 19 der 23 Domains stehen hinter Cloudflare, die Antwort
kommt also nur durch, wenn Cloudflare wirklich auf diesen Ursprung zeigt:

```
echo probe > public/.well-known/acme-challenge/probe
curl -sL http://<domain>/.well-known/acme-challenge/probe
```

Kommt `probe` zurück, ist der Name ausstellbar. Die Datei danach wieder löschen.

Diese Vorprobe und das Ausstellen macht `scripts/tls-zertifikat-nachziehen.sh`
(als `root` auf dem Server, Erklärung in 4.2). Es liest die Namen aus dem
laufenden Zertifikat, nimmt die als Argument übergebenen dazu und bricht ab,
bevor es ausstellt, wenn ein Name den Server nicht erreicht.

**Drei Namen sind nicht im Zertifikat** — `tierarztportal.com` (ohne `www.`),
`firmenfreund.net` und `www.firmenfreund.net`. Sie erreichen den Server nicht,
weil Cloudflare sie vorher wegleitet. Der Vorgang ist Abschnitt 4.2 (#127).

**Verlängerung** läuft über den CloudPanel-Cron (`/etc/cron.d/clp`) aus dem in
der CloudPanel-Datenbank abgelegten CSR. Der CSR trägt alle 55 Namen, die
Verlängerung verliert also keinen. **Achtung:** eine neue Portaldomain macht den
CSR nicht größer — nach jedem neuen Tenant muss der Befehl oben mit der
vollständigen Liste erneut laufen.

**Probe (09.09.2026 durchgeführt):** alle Portaldomains einmal **ohne** `-k`:

```
for d in <alle domains>; do
  curl -s -o /dev/null -w "%{http_code}\n" --resolve $d:443:127.0.0.1 https://$d/
done
```

Ergebnis: kein `000` mehr außer bei den beiden oben genannten Namen. Von außen
liefert `curl -w "%{ssl_verify_result}"` den Wert `0`, die Kette ist also
vollständig ausgeliefert. Die Portale antworten im selben Lauf mit **HTTP 500**
— das ist nicht das Zertifikat, sondern das fehlende `public/build/manifest.json`
aus #126. Die `www.`-Namen antworteten im selben Lauf mit 301 **auf sich
selbst** statt auf die Hauptdomain — eine Weiterleitungsschleife über alle
`www.`-Namen dieses vhost. Behoben am 09.09.2026, siehe 4.4 (#129).

### 4.2 Drei Namen enden bei Cloudflare auf fremden Zielen (#127)

**Befund 09.09.2026, bestätigt am selben Tag.** Der Ursprung ist in Ordnung, die
Weiterleitung passiert an der Cloudflare-Kante, bevor der Server die Anfrage
sieht:

| Name | über Cloudflare | direkt am Ursprung (`--resolve <name>:443:88.198.64.145`) |
| --- | --- | --- |
| `tierarztportal.com` | 302 auf `https://pfotencheck.tierarztportal.com/` | **200**, Titel `Tierarztportal.com` (Tenant 43) |
| `firmenfreund.net` | 301 auf `https://solar-finden.de/` (fremder Apache-Host, dort 404) | **200**, Titel `Solar - Photovoltaik` (Tenant 51) |
| `www.firmenfreund.net` | 301 auf `https://solar-finden.de/` | 200 |

Daraus folgt: **auf dem Server ist nichts zu tun.** vhost, Tenant-Eintrag und
Anwendung liefern beide Portale unter genau diesen Namen korrekt aus. Die
Weiterleitungen stehen allein im Cloudflare-Konto (Page Rule, Redirect Rule oder
Bulk Redirect) der Zonen `tierarztportal.com` und `firmenfreund.net`. Der
`www.tierarztportal.com`-Name hat ein anderes Leiden, siehe die Schleife oben.

Handarbeit im Cloudflare-Konto, in dieser Reihenfolge:

1. Regel in Zone `tierarztportal.com` entfernen, die den Wurzelnamen auf
   `pfotencheck.tierarztportal.com` schickt. `pfotencheck` ist ein eigenes
   Projekt mit eigenem vhost und eigenem Zertifikat und bleibt unberührt.
2. Regel in Zone `firmenfreund.net` entfernen, die auf `solar-finden.de` schickt.
3. `A`-Eintrag beider Namen (und `www.firmenfreund.net`) auf `88.198.64.145`
   zeigen lassen; proxied ist in Ordnung.
4. Vorprobe je Name — `scripts/tls-zertifikat-nachziehen.sh --pruefen`.
5. Zertifikat neu ausstellen — dasselbe Skript ohne `--pruefen`.

**Schritt 4 und 5 sind ein Skript.** `scripts/tls-zertifikat-nachziehen.sh`
läuft als `root` auf dem Server und nimmt die nachzuziehenden Namen als
Argumente:

```
scripts/tls-zertifikat-nachziehen.sh --pruefen tierarztportal.com firmenfreund.net www.firmenfreund.net
scripts/tls-zertifikat-nachziehen.sh          tierarztportal.com firmenfreund.net www.firmenfreund.net
```

Es baut die SAN-Liste aus dem **heute ausgelieferten Zertifikat** plus den
Argumenten (der CSR wächst nicht mit, siehe 4.1), probt jeden Namen einzeln über
`.well-known/acme-challenge`, bricht bei auch nur einem nicht erreichbaren Namen
ab, ohne etwas auszustellen, und sichert vor dem Ausstellen Zertifikat,
Schlüssel, vhost und `db.sq3` nach `/root/tls-backup-<zeitstempel>/`. Damit ist
dieselbe Prozedur auch für jede künftige Portaldomain der richtige Weg — der
handgeschriebene `clpctl`-Aufruf mit abgetippter Liste entfällt.

Der Server steht noch auf einem älteren Commit (#125), das Skript liegt dort
deshalb schon als `/root/tls-zertifikat-nachziehen.sh` — bis zum Deploy ist das
der Aufrufpfad, danach der aus `current/scripts/`.

**Lauf vom 09.09.2026, 15:55 Uhr, auf dem Server:** 50 Namen geprüft, **47 ok**,
`FEHLT` genau bei `firmenfreund.net`, `tierarztportal.com` und
`www.firmenfreund.net`. Das Skript hat abgebrochen und nichts ausgestellt — der
erwartete Ausgang, solange die Cloudflare-Regeln stehen. Die Probendatei wird in
jedem Fall wieder entfernt.

**Die Domain im Tenant zu ändern ist hier keine Alternative.** Was Cloudflare
tatsächlich ausliefert, sind `pfotencheck.tierarztportal.com` (fremdes Projekt)
und `solar-finden.de` (fremder Host) — beides keine Namen, unter denen ein
Portal dieser Anwendung laufen kann. Die Tenant-Einträge 43 und 51 bleiben, wie
sie sind.

**Abnahme:** `curl -sL https://tierarztportal.com/` und
`curl -sL https://firmenfreund.net/` liefern die Portalseite (Titel
`Tierarztportal.com` beziehungsweise `Solar - Photovoltaik`),
`scripts/tls-zertifikat-nachziehen.sh --pruefen tierarztportal.com firmenfreund.net www.firmenfreund.net`
meldet für alle 50 Namen `ok`, danach stellt derselbe Aufruf ohne `--pruefen`
das Zertifikat über 50 Namen aus, und die Zeile in diesem Abschnitt wird auf
„erledigt" gesetzt.

**Nachprobe 09.09.2026 (Ausführungsticket #130).** Zustand unverändert:
`tierarztportal.com` 302 auf `pfotencheck.tierarztportal.com`, `firmenfreund.net`
und `www.firmenfreund.net` 301 auf `solar-finden.de`; der Lauf von
`/root/tls-zertifikat-nachziehen.sh --pruefen tierarztportal.com firmenfreund.net www.firmenfreund.net`
meldet weiter 47 ok und dieselben drei `FEHLT` und stellt nichts aus. Eine
erneute Suche nach einem Cloudflare-Zugang (Repository, lokale `.env`,
Produktions-`.env`, `/root`, `wrangler`/`flarectl` lokal) blieb ohne Treffer.

**Die Regeln greifen auch auf `/.well-known/acme-challenge/` (gemessen
09.09.2026, #130).** Das schließt jeden serverseitigen Ausweg aus:

| Probe | Antwort |
| --- | --- |
| `http://tierarztportal.com/.well-known/acme-challenge/x` | 301 auf `https://` derselben Adresse, dort 302 auf `pfotencheck.tierarztportal.com` |
| `http://firmenfreund.net/.well-known/acme-challenge/x` | 301 auf `https://solar-finden.de/.well-known/acme-challenge/x` |
| `http://www.firmenfreund.net/.well-known/acme-challenge/x` | 301 auf `https://solar-finden.de/.well-known/acme-challenge/x` |

Let's Encrypt folgt diesen Weiterleitungen und findet die Probendatei nicht. Die
`http-01`-Prüfung dieser drei Namen kann deshalb erst gelingen, nachdem die
Regeln im Konto weg sind — ein Zertifikat über 50 Namen vorab auszustellen ist
technisch unmöglich, nicht bloß vom Skript untersagt. Ein Ausstellen über die
47 erreichbaren Namen hilft ebenfalls nicht, denn die fehlenden Namen sind genau
die beiden Portale 43 und 51.

**Klickweg im Cloudflare-Dashboard (Schritt 1 bis 3).** Je Zone drei Orte
prüfen, die Regel steht in genau einem davon:

1. Zone wählen → **Rules** → **Redirect Rules**: Regel mit Ziel
   `pfotencheck.tierarztportal.com` beziehungsweise `solar-finden.de` löschen.
2. Zone → **Rules** → **Page Rules**: Eintrag mit `Forwarding URL` auf dasselbe
   Ziel löschen (ältere Konten haben die Weiterleitung hier).
3. Kontoebene → **Bulk Redirects**: Liste nach den drei Quellnamen durchsuchen
   und die Zeilen entfernen.
4. Zone → **DNS**: `A`-Eintrag `@` und (bei `firmenfreund.net`) `www` auf
   `88.198.64.145`. `pfotencheck` als eigener `A`/`CNAME`-Eintrag bleibt
   unangetastet.

**Selbstprobe vom Arbeitsplatz, direkt nach der Änderung** — erst wenn alle drei
Zeilen `200` zeigen, lohnt der Gang auf den Server:

```
for h in tierarztportal.com firmenfreund.net www.firmenfreund.net; do
  printf '%s ' "$h"
  curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://$h/"
done
```

**Weg ohne Dashboard.** Mit einem auf diese zwei Zonen beschränkten API-Token
(#138, Ablage `/root/.cloudflare-token`, Modus 0600) ist derselbe Vorgang aus
einem Agentenlauf heraus vollständig ausführbar: Zonen-ID über
`/client/v4/zones?name=<zone>`, Regeln über
`/client/v4/zones/<id>/rulesets` (Phase `http_request_dynamic_redirect`) und
`/client/v4/zones/<id>/pagerules` suchen und löschen, `A`-Einträge über
`/client/v4/zones/<id>/dns_records` setzen. Solange der Token fehlt, bleibt
Schritt 1 bis 3 Handarbeit.

**Stand 09.09.2026: Analyse und Ausführungsweg fertig, die Kontoarbeit ist
offen.** Ein Cloudflare-Zugang existiert nirgends in Reichweite eines
Agentenlaufs: weder im Repository noch in der lokalen oder der Produktions-`.env`,
weder in `/root` noch in der CloudPanel-Datenbank (der einzige Treffer auf
„cloudflare" dort ist die Spalte `site.allow_traffic_from_cloudflare_only`).
Beide Zonen liegen auf denselben Nameservern (`sri.ns.cloudflare.com`,
`ara.ns.cloudflare.com`), also vermutlich in einem Konto. Die zwei Regeln zu
entfernen ist deshalb Handarbeit wie #97 und #120 und wird als eigenes Ticket
geführt (#130); #127 (Befund, Eingrenzung, Ausführungsweg) ist damit abgeschlossen.

### 4.3 Sechs Namen im vhost ohne Tenant (#128)

**Befund 09.09.2026.** Nach dem Manifest-Ausfall (#126) antworteten 23 Portale
mit 200, sechs Namen aus dem `server_name` weiter mit **HTTP 500**. Die Ursache
liegt nicht am Build, sondern an der Tenant-Auflösung: der
`DomainTenantResolver` sucht ohne Zwischentabelle direkt über `tenants.domain`,
findet nichts und wirft `TenantCouldNotBeIdentifiedOnDomainException`. Die
Namen waren im vhost gepflegt, aber nie in der Datenbank hinterlegt.

`tenants.domain` ist **einwertig** — ein Portal kann keine zweite Domain führen.
Ein Alias braucht daher zwingend eine Weiterleitung im vhost. Entschieden und
umgesetzt am 09.09.2026:

| Name | Verhalten seit #128 |
| --- | --- |
| `apothekefinden.net` | 301 auf `apotheke.firmenfreund.de` (Tenant 50) |
| `arztfinder.net` | 301 auf `arztfinder.firmenfreund.de` (Tenant 56) |
| `unfallarzt.net` | 301 auf `unfallarzt.firmenfreund.de` (Tenant 52) |
| `zahnarztportal.com` | 301 auf `zahnarzt.firmenfreund.de` (Tenant 53) |
| `firmenfreund.com` | 301 auf `firmenfreund.de` (Tenant 24) |
| `sanitaerfinden.dev` | HTTP 404 mit Klartext, CloudPanel-Sitename, kein Portal |

Die `www.`- und `www1.`-Fassung jedes Namens verhält sich gleich; damit sind
diese sechs zugleich aus der Weiterleitungsschleife der `www.`-Namen (#129)
heraus. Der Pfad bleibt erhalten
(`/ratgeber/test?a=1` → `https://apotheke.firmenfreund.de/ratgeber/test?a=1`).

**Alle sechs Namen bleiben im vhost stehen.** Sie wandern nur aus dem
gemeinsamen `server_name` in je einen eigenen Serverblock, der `/.well-known/`
weiterhin aus dem Webroot beantwortet. Die SAN-Liste des gemeinsamen
Zertifikats bleibt damit unverändert und #130 wird nicht berührt. Nebenbei
entfernt: der Tippfehler `www1.bfirmenfreund.com`.

**Nachtrag (#131).** „Unverändert" hieß hier auch: vier dieser Namen standen
noch nie in der SAN-Liste. Sie beantworten seit #128 eine 301, aber ohne
eigenes Zertifikat — nachgezogen am 09.09.2026, siehe 4.5.

**Schritt 4 und 5 sind auch hier ein Skript.** `scripts/vhost-namen-ohne-tenant.sh`
läuft als `root`, ist wiederholbar (es entfernt einen früheren eigenen Abschnitt,
bevor es ihn neu schreibt), sichert den vhost nach
`sanitaerfinden.dev.conf.vor-128-<zeitstempel>` und spielt die Sicherung zurück,
falls `nginx -t` fehlschlägt:

```
scripts/vhost-namen-ohne-tenant.sh --pruefen   # nur den Unterschied zeigen
scripts/vhost-namen-ohne-tenant.sh
```

**Achtung:** CloudPanel erzeugt `sanitaerfinden.dev.conf` neu, sobald die Site
im Panel bearbeitet wird. Danach ist das Skript erneut zu laufen — der
`server_name` dieses vhost ist ohnehin seit jeher Handarbeit.

**Probe nach dem Lauf:** die sechs Namen antworten wie in der Tabelle, alle 23
Tenant-Domains weiter mit 200, und seit dem Reload steht kein neues
`TenantCouldNotBeIdentifiedOnDomain` mehr im Tageslog.

### 4.4 Weiterleitungsschleife der `www.`-Namen (#129)

**Befund 09.09.2026, Randbefund aus #127.** Der 443-Serverblock der
`www.`-Namen in `/etc/nginx/sites-enabled/sanitaerfinden.dev.conf` antwortete
mit `return 301 https://$host$request_uri;`. `$host` ist dort noch der
`www.`-Name, die Antwort auf `https://www.<domain>/` war damit eine 301 auf
genau dieselbe Adresse. Über Cloudflare brach `curl -L` nach fünf Sprüngen ab,
der Browser meldete „zu viele Weiterleitungen". Betroffen war jeder `www.`-Name
dieses vhost, also jedes Portal.

Behoben, indem dem `return` ein Abschneiden des `www.` vorangestellt wird:

```
if ($host ~* ^www\.(.+)$) {
  return 301 https://$1$request_uri;
}
return 301 https://$host$request_uri;
```

Bewusst kein `map` im `http`-Kontext: das bräuchte eine zweite Datei unter
`/etc/nginx/conf.d/`, die CloudPanel nicht kennt. So bleibt die Änderung an der
Stelle, an der sie gilt. Der alte `return` bleibt als Auffang stehen. Der
80er-Block darüber ist unberührt — dort ist `$host` richtig, `http://www.<domain>/`
läuft in zwei Sprüngen über `https://www.<domain>/` auf die Hauptdomain.

**Gepatcht wird an zwei Stellen.** Anders als bei #128 genügt die ausgelieferte
Datei nicht: CloudPanel hält den vhost als Vorlage in `site.vhost_template`
(`/home/clp/htdocs/app/data/db.sq3`) und schreibt die Datei daraus neu. Das ist
dieselbe Vorlage, die der Vhost-Editor der Site zeigt. Beide Stellen bekommen
denselben Block; die Zeilenenden bleiben, wie sie sind (die Vorlage hält CRLF,
die ausgelieferte Datei LF).

```
scripts/vhost-www-weiterleitung.sh --pruefen   # nur den Unterschied zeigen
scripts/vhost-www-weiterleitung.sh
```

Das Skript läuft als `root`, ist wiederholbar (es baut einen früheren eigenen
Abschnitt zurück, bevor es ihn neu schreibt), bricht ab, wenn die zu ersetzende
Zeile nicht **genau einmal** auf Serverebene steht, sichert vhost und `db.sq3`
nach `sanitaerfinden.dev.conf.vor-129-<zeitstempel>` bzw.
`/root/db.sq3.vor-129-<zeitstempel>` und spielt die Sicherung zurück, falls
`nginx -t` fehlschlägt. Die Vorlage wird erst nach erfolgreichem `nginx -t`
nachgezogen.

**Probe nach dem Lauf am 09.09.2026:** alle 25 `www.`-Namen des vhost antworten
mit 301 auf die Hauptdomain, das Ziel jeweils mit 200. `www.tierarztPortal.com`
landet dabei auf `https://tierarztportal.com/` — nginx setzt `$host` klein.
`www.www.kasernencheck.de` gehört zu einem fremden Projekt auf demselben Server
und wurde nicht angefasst. Die `www.`-Fassungen der sechs Namen aus 4.3 laufen
weiter über deren eigene Blöcke.

### 4.5 Vier Weiterleitungsnamen fehlten im Zertifikat (#131)

**Befund 09.09.2026, Randbefund aus #128.** Von den sechs Namen aus 4.3 waren
nur `firmenfreund.com` und `sanitaerfinden.dev` (je mit `www.`) im gemeinsamen
Zertifikat. Vier fehlten samt `www.`-Fassung:

- `apothekefinden.net` / `www.apothekefinden.net`
- `arztfinder.net` / `www.arztfinder.net`
- `unfallarzt.net` / `www.unfallarzt.net`
- `zahnarztportal.com` / `www.zahnarztportal.com`

Aufgefallen ist es nicht, weil alle vier hinter Cloudflare stehen und die
Ursprungsprüfung der Zonen auf „Full" statt „Full (strict)" steht: über die
Kante kam die 301 zurück, direkt am Ursprung brauchte es `curl -k`. Auf „Full
(strict)" oder mit einem unproxied `A`-Eintrag wäre die Weiterleitung im
Browser abgebrochen.

**Behoben am 09.09.2026** mit demselben Skript wie in 4.2 — Vorprobe zuerst,
danach derselbe Aufruf ohne `--pruefen`:

```
/root/tls-zertifikat-nachziehen.sh --pruefen \
  apothekefinden.net www.apothekefinden.net \
  arztfinder.net www.arztfinder.net \
  unfallarzt.net www.unfallarzt.net \
  zahnarztportal.com www.zahnarztportal.com
```

Die Vorprobe meldete **55 von 55 Namen `ok`** — die `/.well-known`-Location in
den Weiterleitungsblöcken aus #128 liefert wie vorgesehen weiter aus dem
Webroot. Das Ausstellen lief durch (`Certificate installation was successful`),
Sicherung nach `/root/tls-backup-20260909_161846/`, Laufzeit bis 08.12.2026,
**55 Namen** im neuen Zertifikat.

**Probe nach dem Lauf:** alle acht Namen antworten am Ursprung **ohne** `-k` mit
301 auf das jeweilige Portal (`--resolve <name>:443:88.198.64.145`), vier
Stichproben der Hauptportale weiter mit 200, `nginx -t` unverändert erfolgreich,
der vhost byte-gleich zur Sicherung — CloudPanel hat ihn beim Ausstellen nicht
neu geschrieben, die Blöcke aus #128 und #129 stehen unberührt.

**#130 bleibt offen.** Die drei dort genannten Namen wurden bewusst **nicht**
mitgezogen: `tierarztportal.com`, `firmenfreund.net` und `www.firmenfreund.net`
scheitern weiter an der HTTP-01-Probe (Messung im selben Lauf: dreimal `FEHLT`),
weil die Cloudflare-Regeln stehen. Sie hätten den ganzen Lauf zum Abbruch
gebracht. Nach der Kontoarbeit aus #130 sind es 58 Namen; das Skript baut die
Liste dann wieder aus dem laufenden Zertifikat plus den drei Argumenten.

## 5. Betrieb: Queues, Horizon, Cron

Heutiger Stand auf dem Server:

| Punkt | Stand nach der Umstellung (09.09.2026, #117) |
| --- | --- |
| `QUEUE_CONNECTION` | `redis` (vorher `database`) |
| `REDIS_HOST` | `127.0.0.1` (vorher `redis` — ein Docker-Hostname, der auf diesem Server nicht auflöst) |
| Horizon | läuft als Supervisor-Programm `sanitaerfinden-horizon`, `horizon:status` = `running` |
| Supervisor | `sanitaerfinden-horizon` RUNNING, `kasernencheck-moderation` RUNNING; `sanitaerfinden-worker` entfernt |
| systemd | `laravel-queue.service` (`queue:work database`) gestoppt und deaktiviert |
| Cron | eingetragen am 09.09.2026, `schedule:run` läuft im Minutentakt |

Der Stand davor: `QUEUE_CONNECTION=database`, kein Horizon, beide Prozesse von
`sanitaerfinden-worker` **FATAL** („Exited too quickly").

### 5.1 Wegentscheidung (#110): Horizon auf Redis

Es gibt zwei Wege, die Queues der Pipeline zu bedienen. Gewählt ist **Horizon
auf Redis**. Die drei Programme aus `deploy/supervisor/` bleiben ungenutzt und
`dep deploy:supervisor-content` wird auf diesem Server **nie** ausgeführt.

Gründe:

- Horizon ist der Standardweg des Projekts. `dep provision:supervisor` legt das
  Programm `horizon` an, `deploy.php` ruft nach jedem Release
  `artisan:horizon:terminate`. Der Ausweichweg ist eine Sonderlocke, die bei
  jedem Deploy mitgedacht werden muss.
- `config/horizon.php` führt die drei Content-Supervisoren bereits vollständig
  samt Produktions- und Staging-Skalierung; sie decken alle sechs Queues.
- Beide Wege setzen Redis voraus: die Programme in `deploy/supervisor/` starten
  `queue:work redis`. `QUEUE_CONNECTION=database` ist also in jedem Fall falsch.
- `content:golive:check` prüft die Queues gegen `config('horizon.defaults')`.
  Der Horizon-Weg erreicht Exit 0 ohne Eingriff in den Prüfcode. Den
  Ausweichweg anzuerkennen hieße, im Check einen Serverzustand zu raten, den
  die Konfiguration nicht kennt.
- Horizon liefert zusätzlich die Warteschlangen-Sicht unter `/horizon`, die der
  Betrieb aus #26 für Wartezeiten und fehlgeschlagene Jobs braucht.

### 5.2 Schritte auf dem Server

Reihenfolge einhalten — erst Redis, dann Horizon, dann den alten Worker
abräumen.

1. **Ursache des FATAL klären, bevor etwas geändert wird.** Das Log des
   Programms steht in seiner Conf:
   ```
   grep -h "stdout_logfile\|command" /etc/supervisor/conf.d/sanitaerfinden-worker.conf
   tail -n 50 <stdout_logfile>
   ```
   „Exited too quickly" heißt: der Prozess stirbt in unter einer Sekunde.
   Die üblichen drei Ursachen, in dieser Reihenfolge prüfen:
   `command` zeigt auf einen Pfad, den es nicht (mehr) gibt (falscher
   `deploy_path`, alter Release-Symlink); `queue:work redis` bei fehlender
   PHP-Erweiterung `redis` (`php8.4 -m | grep redis`); oder ein Fehler beim
   Hochfahren der Anwendung, der auch `php artisan about` auslöst. Der Befund
   gehört als Zeile in dieses Dokument, auch wenn das Programm danach entfällt.

   **Befund 09.09.2026 (#117): erste Ursache, falscher Pfad.** Die Conf startete
   `/usr/bin/php /home/sanitaerfinden/htdocs/sanitaerfinden.dev/current/artisan
   queue:work database`. Ein Verzeichnis `current` gibt es dort nicht — die
   Installation ist handgepflegt und liegt in keiner Deployer-Release-Struktur.
   Das Log wiederholt genau eine Zeile: `Could not open input file:
   /home/sanitaerfinden/htdocs/sanitaerfinden.dev/current/artisan`. Die beiden
   anderen Ursachen scheiden aus: `php -m` führt `redis`, und `artisan` läuft
   sonst fehlerfrei.
2. **`.env` auf Redis stellen** und den Config-Cache neu bauen:
   ```
   QUEUE_CONNECTION=redis
   REDIS_CLIENT=phpredis
   ```
   danach `php artisan config:cache`. Der bestehende Cache stammt vom
   12.05.2026, siehe Abschnitt „Achtung Config-Cache" — der Neuaufbau ist Teil
   des Deploys aus Abschnitt 1 und darf nicht vorgezogen werden.
   Wartende Jobs in der Tabelle `jobs` gehen mit dem Wechsel der Verbindung
   verloren: vorher `select count(*) from jobs` prüfen und den Rest abarbeiten
   lassen.
3. **Horizon-Programm anlegen.** Entweder `dep provision:supervisor` (legt
   `/etc/supervisor/conf.d/horizon.conf` an; Abschnitt 0 dieses Dokuments
   beachten, der Task fasst auch nginx und PHP an) oder die Conf von Hand mit
   demselben Inhalt und dem echten Pfad
   `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`. `APP_ENV` muss `production`
   sein, sonst startet Horizon keinen einzigen Supervisor: fehlt der Eintrag
   für die laufende Umgebung unter `horizon.environments`, lässt
   `ProvisioningPlan::deploy()` die Programme kommentarlos aus.
4. **Alten Worker entfernen.** `supervisor-1` in `config/horizon.php` bedient
   die Queue `default` mit zehn Prozessen; `sanitaerfinden-worker` wird damit
   überflüssig und würde die Portal-Jobs doppelt ziehen:
   ```
   supervisorctl stop sanitaerfinden-worker:*
   rm /etc/supervisor/conf.d/sanitaerfinden-worker.conf
   supervisorctl reread && supervisorctl update
   ```
   `kasernencheck-moderation` gehört zu einer fremden Anwendung und bleibt.
5. **Starten und prüfen:** `supervisorctl start horizon`, dann
   `php artisan horizon:status`.
6. `php artisan tenants:migrate` über alle Portale.

Bei jedem Release gilt weiter: `artisan:horizon:terminate` nach dem Wechsel des
Symlinks (steht bereits in `deploy.php`). Ein zusätzliches `queue:restart`
schadet nicht, ist mit Horizon aber überflüssig.

**Probe:** `php artisan horizon:status` sagt `running`, `supervisorctl status`
zeigt alle Programme `RUNNING` und kein Programm mehr aus `deploy/supervisor/`,
`crontab -u sanitaerfinden -l` enthält `schedule:run`, und
`php artisan content:golive:check` zeigt bei „Queue-Verbindung" und den sechs
Zeilen „Queue content-…" kein ✗.

## 6. Rollout und Abnahme

Erst danach greift #89: Sicherung (`content:golive:backup`), Freischaltung von
drei Portalen (`content:rollout --activate=…`), Woche 1, dann alle. Ablauf:
`docs/messungen/golive-durchfuehrung-anleitung.md`, Sichtprüfung:
`docs/messungen/golive-sichtpruefung-leitfaden.md`.

## 7. Abschlussprobe des Tickets

```
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && php artisan content:golive:check
```

Fertig ist #106, wenn dieser Aufruf **auf dem Server** mit Exit-Code 0 endet.
Lokal endet er am 09.09.2026 mit Exit 1 und drei Fehlern: Pipeline abgeschaltet
(so gewollt, lokal) und zwei leere `gsc_property` (#107).

## Protokoll

| Abschnitt | Datum | Wer | Ergebnis / Befund |
| --- | --- | --- | --- |
| 1 Code gepusht |  |  | offen — #109, 156 unversionierte Dateien unter `app/Content` |
| 2 Deploy-Ziel | 2026-09-09 | steffen | erledigt, `deploy.php` auf 88.198.64.145 / sanitaerfinden.com / enesk/sun |
| 3 Produktions-`.env` | 2026-09-09 | steffen | Schalter und Budget gesetzt; die drei Schlüssel fehlen, siehe unten |
| 4 Domains | 2026-09-09 | steffen | erledigt, 23 Portale gültig, vhost begradigt, 18 Stichproben HTTP 200 |
| 5 Betrieb | 2026-09-09 | rathana (#117) | erledigt: `QUEUE_CONNECTION=redis`, `REDIS_HOST=127.0.0.1`, Horizon als `sanitaerfinden-horizon` RUNNING, alter Worker entfernt, `laravel-queue.service` deaktiviert |
| 4.5 Zertifikat auf 55 Namen | 2026-09-09 | dimitri (#131) | erledigt: `apothekefinden.net`, `arztfinder.net`, `unfallarzt.net`, `zahnarztportal.com` je mit `www.` nachgezogen, Vorprobe 55/55 ok, Zertifikat über 55 Namen bis 08.12.2026, Sicherung `/root/tls-backup-20260909_161846/` |
| 4.2 Cloudflare-Weiterleitungen | 2026-09-09 | sebastian (#127) | eingegrenzt und Ausführung als Skript hinterlegt (`scripts/tls-zertifikat-nachziehen.sh`); Vorprobe am Server: 47 von 50 Namen ok, `tierarztportal.com`, `firmenfreund.net`, `www.firmenfreund.net` FEHLT. Kontoarbeit offen als #130, Kontozugang als #138; Nachprobe 09.09.2026 unverändert (47 ok / 3 FEHLT), Regeln greifen auch auf `/.well-known/acme-challenge/` |
| 6 Rollout |  |  | offen — #89 |
| 7 `content:golive:check` Exit 0 |  |  | offen, setzt Abschnitt 1 voraus |
| 10 Logrotation | 2026-09-09 | dimitri (#122) | erledigt: `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=14`, 1,1-GB-Datei gesichert und geleert, logrotate als Sicherheitsnetz |

## 8. Was am 09.09.2026 tatsächlich auf dem Server geändert wurde

Alle Eingriffe mit Sicherung, alle Proben nachgestellt.

**Domains (Abschnitt 4).** Im ausgelieferten vhost
`/etc/nginx/sites-enabled/sanitaerfinden.dev.conf` standen die Namen
`tierarztportal.com#` und `www1.tierarztportal.com#` in vier `server_name`-Zeilen.
Das Doppelkreuz beginnt in nginx einen Kommentar: alles dahinter bis zum
Zeilenende war wirkungslos, samt abschließendem Semikolon und den rund zwanzig
danach aufgeführten Portaldomains. Der `root`-Eintrag der folgenden Zeile wurde
in die `server_name`-Liste hineingezogen. Sichtbar war das nicht, weil dieser
Serverblock zugleich der Standardblock ist und unpassende Hostnamen ohnehin
auffängt. `nginx -t` meldet dabei keinen Fehler.

Die kaputten Namen sind ersatzlos entfernt; `tierarztPortal.com` steht bereits
in derselben Liste und `server_name` vergleicht ohne Rücksicht auf Groß- und
Kleinschreibung. Derselbe Text steckt in der CloudPanel-Datenbank
(`/home/clp/htdocs/app/data/db.sq3`, `site.vhost_template`, `id=1`) und ist dort
mitkorrigiert — sonst schriebe CloudPanel den Fehler beim nächsten Erzeugen des
vhost zurück. Sicherungen unter `/root/sanitaerfinden.dev.conf.pre106.*` und
`/root/clp-db.sq3.pre106.*`.

Probe: `nginx -t` sauber, `systemctl reload nginx`, danach 18 Portale einzeln
über `curl --resolve <domain>:443:127.0.0.1` mit HTTP 200.

**Produktions-`.env` (Abschnitt 3).** Ergänzt um `CONTENT_PIPELINE_ENABLED=true`,
die vier Budgetgrenzen aus Abschnitt 2 der Checkliste (35 / 1050 / 2,50 / 1,50)
und `CONTENT_ADSENSE_ENABLED=false`. Der abgeschaltete AdSense-Zustand ist in
`content:golive:check` bewusst nur eine Warnung. Sicherung unter
`/root/sanitaerfinden.env.pre106.*`.

Nicht gesetzt sind `ANTHROPIC_API_KEY`, `VOYAGE_API_KEY` und
`GOOGLE_SERVICE_ACCOUNT_JSON`. Der Anthropic-Schlüssel aus der Entwicklung
wurde absichtlich **nicht** übernommen: Abschnitt 1 der Checkliste verlangt
einen eigenen Produktionsschlüssel mit eigenem Ausgabenlimit, und ein geteilter
Schlüssel vermischt die Abrechnung und trifft bei einem Rückruf beide
Umgebungen. Die beiden anderen existieren nirgends im Projekt.

**Achtung Config-Cache.** `bootstrap/cache/config.php` stammt vom 12.05.2026.
Die neuen `.env`-Werte wirken deshalb erst, wenn `php artisan config:cache`
erneut läuft. Das gehört an das Ende des Deploys aus Abschnitt 1 und ist hier
bewusst nicht vorgezogen worden: ein Neuaufbau des Caches würde die seit Mai
aufgelaufenen Änderungen an `.env` und `config/` unbeaufsichtigt scharfschalten.

**Cron (Abschnitt 5).** Es gab auf dem Server überhaupt keine crontab — der
Laravel-Scheduler lief nie. Für den Benutzer `sanitaerfinden` eingetragen:

```
* * * * * cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && /usr/bin/php8.4 artisan schedule:run >> /home/sanitaerfinden/logs/schedule.log 2>&1
```

Probe: `journalctl -u cron` zeigt den Aufruf zur nächsten vollen Minute,
`/home/sanitaerfinden/logs/schedule.log` antwortet mit „No scheduled commands
are ready to run". Vertretbar war das, weil der Umfang klein ist: eine einzige
Subscription, keine wartenden Jobs. Die neun Wartungsbefehle aus
`schedule:list` laufen damit erstmals planmäßig.

**Migrationen (Abschnitt 5).** `migrate:status` meldet zentral nichts
Ausstehendes. `tenants:migrate` bringt vor dem Deploy nichts, weil die
Tenant-Migrationen der Pipeline noch nicht auf dem Server liegen.

**Queues.** Der Eingriff ist am selben Tag mit **#117** nachgeholt, siehe
Abschnitt 9. Die Provisionierungs-Tasks aus `deploy.php` blieben weiterhin aus,
siehe Abschnitt 0.

Randbefund ohne Handlungsbedarf: in `/etc/nginx/sites-enabled/` liegen
zahlreiche Sicherungen `sanitaerfinden.dev.conf.bak.<zeitstempel>`. Geladen
werden sie nicht, `nginx.conf` bindet nur `*.conf` ein.


## 9. Umstellung auf Horizon/Redis am 09.09.2026 (#117)

Ausgeführt als `root` über `ssh sun`. Reihenfolge wie in Abschnitt 5.2.

**Vorprüfung.** `select count(*) from jobs` = 0, es ging also kein wartender Job
verloren. `php -m` führt `redis`, `redis-cli ping` antwortet `PONG` auf
`127.0.0.1`.

**Ursache des FATAL.** Falscher Pfad in der Conf, Beleg in Abschnitt 5.2,
Schritt 1.

**`.env`.** `QUEUE_CONNECTION=database` → `redis` und `REDIS_HOST=redis` →
`127.0.0.1`. Der zweite Wert war der eigentliche Stolperstein: `redis` ist ein
Hostname aus `docker-compose.yml` und löst auf dem Server nicht auf, die
Umstellung allein hätte die Queues stillgelegt. `REDIS_CLIENT` bleibt
ungesetzt, die Vorgabe `phpredis` stimmt. `CACHE_STORE` und `SESSION_DRIVER`
bleiben auf `database`, damit die Umstellung nur die Queues betrifft.
Sicherung: `.env.bak-117-<zeitstempel>` neben der Datei.

**Config-Cache.** `php artisan config:cache` als Benutzer `sanitaerfinden`
ausgeführt (nicht als `root`, sonst gehört `bootstrap/cache/config.php` root und
PHP-FPM kann es nicht mehr schreiben). Der Cache stammte vom 12.05.2026; der
Neuaufbau war hier unvermeidbar, weil die Verbindung sonst nicht gewirkt hätte.
Probe danach: `sanitaerfinden.com`, `bodenlegerfinden.com` und
`elektrikerportal.com` antworten mit HTTP 200.

**Supervisor.** `/etc/supervisor/conf.d/sanitaerfinden-worker.conf` gestoppt,
nach `/root/supervisor-backup-117/` gesichert und entfernt. Neu angelegt
`/etc/supervisor/conf.d/sanitaerfinden-horizon.conf`:

```
[program:sanitaerfinden-horizon]
process_name=%(program_name)s
command=/usr/bin/php /home/sanitaerfinden/htdocs/sanitaerfinden.dev/artisan horizon
directory=/home/sanitaerfinden/htdocs/sanitaerfinden.dev
autostart=true
autorestart=true
user=sanitaerfinden
numprocs=1
redirect_stderr=true
stdout_logfile=/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/logs/horizon.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stopasgroup=true
killasgroup=true
stopsignal=TERM
stopwaitsecs=3600
```

Kein Pfadbestandteil `current`, kein `deploy/supervisor/`-Programm. `supervisor`
ist im systemd `enabled`, das Programm kommt also auch nach einem Neustart hoch.

**Zweiter Konsument gefunden.** Neben dem Supervisor lief ein
systemd-Dienst `laravel-queue.service` (`/usr/bin/php artisan queue:work
database …`, Benutzer `sanitaerfinden`) — er stand in keiner
`supervisorctl status`-Ausgabe und wäre bei der reinen Supervisor-Aufräumung
übersehen worden. Mit `systemctl disable --now laravel-queue.service` gestoppt
und aus dem Autostart genommen. Die Unit-Datei bleibt zur Nachvollziehbarkeit
unter `/etc/systemd/system/` liegen.

**Fremde Anwendung, gleiche Redis-Instanz.** `horizon.service` (systemd,
Benutzer `root`, Arbeitsverzeichnis `/home/kasernencheck/htdocs/kasernencheck.de`)
fährt ein zweites Horizon auf derselben Redis-Instanz. Kein Konflikt: die
Schlüssel tragen den aus `APP_NAME` abgeleiteten Präfix, hier `widimedia_horizon:`
gegen `kasernencheck_horizon:`. Wer `APP_NAME` auf diesem Server ändert, trennt
damit unbeabsichtigt die laufenden Queues von den eingereihten Jobs.

**Proben.**

| Probe | Ergebnis |
| --- | --- |
| `supervisorctl status` | `sanitaerfinden-horizon` RUNNING, `kasernencheck-moderation` RUNNING, sonst nichts |
| `php artisan horizon:status` | `Horizon is running.` |
| `ps -eo user,args` | Master, `horizon:supervisor supervisor-1` und ein `horizon:work` auf Queue `default`, alle als `sanitaerfinden` |
| Job durch die Kette | ein eingereihter Testjob wurde binnen acht Sekunden ausgeführt; Redis führt `widimedia_horizon:completed_jobs` |
| Portale | HTTP 200 auf `sanitaerfinden.com`, `bodenlegerfinden.com`, `elektrikerportal.com` |

**Offen bleibt** die Probe `php artisan content:golive:check`: der Server steht
auf Commit `fc06dd3`, `app/Content` liegt dort nicht, der Befehl existiert also
gar nicht. Der Check bewertet die Queues rein aus der Konfiguration
(`queue.default` ungleich `sync`, sechs Queues aus `content.pipeline.queues`
gegen `horizon.defaults`); beide Bedingungen erfüllt der Repository-Stand. Die
Zeilen werden grün, sobald der Code aus Abschnitt 1 auf dem Server liegt. Ein
Eingriff daran gehört zu #106/#89, nicht hierher — nach dem Deploy genügt
`php artisan config:cache` und `supervisorctl restart sanitaerfinden-horizon`,
damit Horizon die sechs Content-Supervisoren aus `config/horizon.php` startet.

**Randbefunde.** `storage/logs/laravel.log` ist 1,1 GB groß (keine Rotation).
Für `apotheke.firmenfreund.de` und `arztfinder.firmenfreund.de` fehlt ein
gültiges Zertifikat: mit `curl -k` antworten sie mit 200, ohne `-k` bricht der
TLS-Aufbau ab. Beides ist als eigenes Ticket vermerkt.

## 10. Logrotation am 09.09.2026 (#122)

`storage/logs/laravel.log` war 1.143.693.402 Byte (1,1 GB) groß und deckte den
Zeitraum 18.05.2026 bis 09.09.2026 in einer einzigen Datei ab. Es gab keine
Rotation: `LOG_CHANNEL=stack` zeigte auf den Kanal `single`.

**Was das Log gefüllt hat.** Ausgezählt über die letzten 80 MB:

| Anzahl | Meldung |
| --- | --- |
| 1.592 | `SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded` |
| 943 | `SQLSTATE[HY000] [2002] Connection refused` |
| 462 | `Tenant could not be identified on domain sanitaerfinden.dev` |
| 613 | `Cannot assign array to property App\Livewire\Portal\NewsletterSubscribeForm::$…` |

Das Volumen kommt nicht aus der Zahl der Ereignisse, sondern aus den
Stacktraces: 6,26 Millionen Zeilen für wenige tausend Fehler.

**Sicherung.** `gzip -c laravel.log > /home/sanitaerfinden/laravel-log-archiv-bis-2026-09-09.gz`
— 20 MB, mit `gzip -t` geprüft, Eigentümer `sanitaerfinden`. Erst danach
geleert mit `: > laravel.log`; die Datei wurde nicht gelöscht, weil PHP-FPM und
Horizon einen offenen Dateizeiger darauf halten.

**`.env` auf Produktion.** Sicherung als `.env.bak-122-20260909-152045`.

```
LOG_CHANNEL=daily
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
LOG_DAILY_DAYS=14
```

`LOG_LEVEL` bleibt bewusst auf `debug`: der Ballast steckt in Fehlern, nicht in
Debug-Meldungen, und für die Go-Live-Woche ist die volle Spur nützlich.
Anschließend `php artisan config:cache` als Benutzer `sanitaerfinden`
(nicht als `root`, sonst gehört `bootstrap/cache/config.php` root),
`systemctl reload php8.4-fpm` und `supervisorctl restart sanitaerfinden-horizon`
— ohne den Neustart schreiben die laufenden Prozesse mit der alten
Konfiguration weiter in `laravel.log`.

**Anpassung im Repository.** `config/logging.php` liest den Stapel jetzt aus
`LOG_STACK` und die Aufbewahrung aus `LOG_DAILY_DAYS` (vorher fest `['single']`
und `14`), `.env.example` führt beide Namen. Der Server steht auf einem älteren
Stand ohne diese Zeilen; dort wirkt `LOG_CHANNEL=daily` direkt, und der fest
verdrahtete Wert 14 entspricht dem eingetragenen `LOG_DAILY_DAYS`. Nach dem
Deploy aus #125 ändert sich das Verhalten also nicht.

**logrotate als Sicherheitsnetz.** Laravel begrenzt nur die Zahl der
Tagesdateien, nicht die Größe eines einzelnen Tages — am selben Nachmittag
wuchs die Tagesdatei wegen des Ausfalls aus #126 um rund 35 MB pro Minute. Neu
angelegt `/etc/logrotate.d/sanitaerfinden-laravel`:

```
/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage/logs/laravel-*.log {
    su sanitaerfinden sanitaerfinden
    daily
    size 200M
    rotate 14
    maxage 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    create 0644 sanitaerfinden sanitaerfinden
}
```

`copytruncate` ist Pflicht, weil PHP-FPM und Horizon die Datei offen halten;
`su sanitaerfinden sanitaerfinden` ist nötig, weil `logrotate` als `root` läuft,
das Verzeichnis aber dem Anwendungsbenutzer gehört. Geprüft mit
`logrotate -d /etc/logrotate.d/sanitaerfinden-laravel`.

**Nicht angefasst.** `horizon.log` rotiert der Supervisor selbst
(`stdout_logfile_maxbytes=10MB`, drei Sicherungen), `worker.log` gehört zum
abgelösten Dienst aus #117. Die nginx-Logs unter `/home/sanitaerfinden/logs/nginx/`
rotieren bereits täglich mit acht Tagen Vorhalt. Offen bleibt
`/home/sanitaerfinden/logs/schedule.log` (Anhang aus dem Minutentakt-Cron):
39 KB, wächst langsam, deshalb hier nur vermerkt.

**Proben.**

| Probe | Ergebnis |
| --- | --- |
| `config('logging.default')` | `daily` |
| `config('logging.channels.daily.days')` | `14` |
| Tagesdatei | `storage/logs/laravel-2026-09-09.log` wird angelegt und beschrieben |
| Alte Datei | `laravel.log` bleibt nach dem Leeren bei 0 Byte, es schreibt niemand mehr hinein |
| Archiv | `laravel-log-archiv-bis-2026-09-09.gz`, 20.200.674 Byte, `gzip -t` fehlerfrei |

## 10. Abschluss #106: Deploy-Restarbeiten und Prüfstand 09.09.2026, 15:52 Uhr

Bei Übernahme stand der Server bereits auf Commit `59ce93e` (Code-Pull, Composer/
npm-Build liefen in einer parallelen Sitzung, vermutlich im Rahmen von #125). Von
hier aus zu Ende gebracht:

1. `php artisan migrate --force` (zentral) — 4 ausstehende Content-Migrationen
   liefen durch (`create_content_central_tables` u. a., zusammen rund 100s).
2. `php artisan tenants:migrate --force` — bei Prüfung waren die 47
   Tenant-Migrationsdateien in allen 23 Portalen bereits als „Ran" vermerkt.
3. `php artisan config:cache` neu gebaut, `php artisan horizon:terminate`
   (Neustart mit dem frischen Cache), `supervisorctl status` → `RUNNING`,
   `horizon:status` → `running`.
4. `php artisan content:golive:backup` — Sicherung für 2026-09-09 nachgeholt,
   liegt in `storage/app/backups/content/2026-09-09/`.
5. `php artisan content:user:create --name=Enes --email=kul@widimedia.com
   --role=owner` — der bisher fehlende Redaktions-Account mit Rolle `owner`.
   Passwort zufällig erzeugt, root-only unter
   `/root/content-user-enes-106.pw` abgelegt; beim ersten Login zu ändern.
   **Für Uwe fehlt weiterhin ein Account** — im Projekt ist keine E-Mail-Adresse
   für ihn hinterlegt, `content:user:create --email=... --name=Uwe` nachholen,
   sobald sie bekannt ist.

**Probe:** `php artisan content:golive:check` auf dem Server: 143 Prüfpunkte,
**1 Fehler** (`ANTHROPIC_API_KEY fehlt`), 73 Warnungen. Stichproben
`sanitaerfinden.com` und `geruestbauer.gmbh` HTTP 200, kein neuer Fehler in
`storage/logs/laravel-2026-09-09.log`.

Der einzige verbleibende Fehler ist Kontoarbeit und liegt bei **#120**
(Anthropic-Produktionsschlüssel ausstellen). Damit ist #106 an der Stelle
fertig, die ein Agentenlauf leisten kann: `content:golive:check` erreicht auf
Produktion **Exit 0, sobald** der Anthropic-Schlüssel aus #120 eingetragen ist —
technisch ist das jetzt der einzige Schritt dazwischen.
| `logrotate -d` | Muster wird erkannt, ein Log verwaltet, keine Meldung |

## 11. Code-Deploy auf den aktuellen Stand am 09.09.2026 (#125)

Bis zu diesem Schritt stand die Installation auf `fc06dd3` (Stand vor #109),
`app/Content` gab es dort nicht, `config/content.php` fehlte im Config-Cache.
Damit war jede Abnahme über `content:golive:check`, `content:metrics:preflight`
oder `content:llm:ping` auf Produktion unmöglich.

Ausgeführt als `root` über `ssh sun`, alle Artisan- und Paketbefehle als Benutzer
`sanitaerfinden` (`su -s /bin/bash sanitaerfinden`), im Installationsverzeichnis
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`. Kein Deployer, kein
`provision:*` — die Installation ist handgepflegt.

| Schritt | Befehl | Ergebnis |
| --- | --- | --- |
| Sicherung | `cp -p .env .env.bak-125-20260909151851` | angelegt |
| Arbeitsbaum | `git checkout -- .` | nur Dateimodi und alte `public/build`-Artefakte verworfen |
| Code | `git pull --ff-only origin main` | `fc06dd3` → `59ce93e`, `app/Content` vorhanden |
| Abhängigkeiten | `composer install --no-dev -o --no-scripts`, danach `php artisan package:discover` | nichts nachzuinstallieren, Autoload neu |
| Frontend | `npm ci && npm run build` | 2 min 53 s, alle sieben Vite-Einstiegspunkte inkl. `resources/css/content/theme.css` |
| Eigentümer | `chown -R sanitaerfinden:sanitaerfinden` (ohne `storage`, `node_modules`) | gesetzt |
| Caches | `config:clear`, `config:cache`, `view:clear`, `route:clear`, `filament:optimize` | `config('content.enabled')` = true, `queue.default` = redis |
| Horizon | `horizon:terminate` | Supervisor startet `sanitaerfinden-horizon` neu, `horizon:status` = running |

### Migrationen

Zentral: `migrate:status` meldet **null** offene Migrationen; die Tabellen der
Pipeline (`content_source_settings`, `content_alerts`, `content_daily_reports`,
`content_central_tables`) liegen in Batch 6 bereits vor.

Tenants: `php artisan tenants:migrate --force`. Der erste Lauf brach beim Portal
`firmenfreund.net` mit `1050 Table 'source_items' already exists` ab — die
Tenant-Datenbanken trugen die Content-Tabellen also schon, bevor der Code auf dem
Server lag. Der zweite Lauf endet mit Exit 0 und meldet für **alle 23 Tenants**
„Nothing to migrate". Eine Erhebung über alle Tenant-Datenbanken bestätigt das:
jede führt `source_items` und `article_drafts` und hat alle 15
`2026_09_10_*`-Migrationen in ihrer `migrations`-Tabelle. Der Schemastand ist
damit einheitlich; die Herkunft dieses Vorlaufs ist nicht mehr rekonstruierbar.

### Probe

`php artisan content:golive:check` läuft durch: **143 Prüfpunkte, 1 Fehler, 73
Warnungen** (Exit 1). Der einzige Fehler ist `ANTHROPIC_API_KEY fehlt`; die
Warnungen sind `VOYAGE_API_KEY`, `GOOGLE_SERVICE_ACCOUNT_JSON`, AdSense
abgeschaltet, kein freigeschaltetes Portal sowie je Portal leere `gsc_property`,
Auto-Live-Schwelle 0 und `articles_per_day` 0. Genau das ist die erwartete Lage:
die drei Schlüssel gehören zu #120, die Properties zu #107, die Freischaltung zu
#89. Grün sind Pipeline-Schalter, Zeitzone, alle sieben Quellen, Budget
(35,00 USD/Tag, 1.050,00 USD/Monat, 2,50 USD je Portal × 23), Queue-Verbindung
redis, alle sechs Content-Queues in Horizon-Supervisoren, Alarmempfänger,
`APP_KEY`, Sicherung der Artikeltabellen und Deploy-Ziel.

`content:metrics:preflight` (28 Prüfpunkte, 2 Fehler, 25 Warnungen) und
`content:llm:ping` („ANTHROPIC_API_KEY ist nicht gesetzt") existieren jetzt
ebenfalls und antworten sachlich.

Auslieferung nach dem Deploy geprüft: `sanitaerfinden.com`, `firmenfreund.de`,
`malerfinder.de` und `sanitaerfinden.com/ratgeber` antworten mit HTTP 200.

### Offen

`origin/main` steht auf `59ce93e`; der lokale Arbeitsplatz führt darüber hinaus
den Commit `1542c19` (nur Dokumentation) und weitere nicht committete Änderungen.
Beides ist **nicht** auf dem Server. Der nächste Deploy ist wieder ein
`git pull --ff-only origin main` mit denselben Schritten wie oben.
