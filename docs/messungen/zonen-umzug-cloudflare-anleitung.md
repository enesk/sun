# elektrikerportal.com und sanitaerfinden.com hinter die Cloudflare-Kante (#34)

**Stand 08.10.2026: `elektrikerportal.com` ist umgezogen und abgenommen**
(`scripts/zone-dns-umzug.sh --abnahme --zone=elektrikerportal.com` endet mit
Exit 0, `cf-ray` kommt von der Kante). Offen ist nur noch
`sanitaerfinden.com` — die Zone liegt weiter bei name.com und zeigt mit
A-Records direkt auf den Ursprung. Dort wirkt **keine** Cloudflare-Massnahme,
weder die vier WAF-Regeln aus `docs/bot-traffic.md` Abschnitt 2 noch Bot Fight
Mode.

Der Umzug ist Kontoarbeit in zwei fremden Oberflaechen (Cloudflare, name.com)
und bleibt Handarbeit: im Projekt gibt es keinen Cloudflare-Token mit DNS- oder
Zonen-Rechten (#18). Automatisiert ist der Teil, der dabei kaputtgehen kann —
der Abgleich der Records vorher/nachher: `scripts/zone-dns-umzug.sh`.

Fuer `sanitaerfinden.com` dieselben Abschnitte unten, jeweils mit
`--zone=sanitaerfinden.com`.

**Eine Falle vorweg: nicht mit dem eigenen Resolver messen.** Ein
zwischenspeichernder Resolver (Fritz!Box, Firmen-DNS) haelt nach dem Umzug noch
die alte Ursprungsadresse; `curl https://<domain>/` laeuft dann an der Kante
vorbei, `cf-ray` fehlt und der Umzug sieht fehlgeschlagen aus. Bei
`elektrikerportal.com` ist das am 08.10.2026 zweimal passiert und hat das
Ticket zweimal zurueckgeworfen. Beide Pruefskripte fragen die Adresse deshalb
am autoritativen Nameserver und schicken `curl` mit `--resolve` dorthin; weicht
der eigene Resolver ab, sagen sie es. Von Hand entsprechend:

```bash
dig +short A <domain> @"$(dig +short NS <domain> | head -1)"
curl -sSk -D - -o /dev/null --resolve <domain>:443:<adresse> https://<domain>/ | grep -i cf-ray
```

Jeder Abschnitt endet mit einer Probe. Ergebnisse unten in die Tabelle.

---

## 0. Ausgangsstand, gemessen 08.10.2026

Rohdaten: `docs/messungen/zone-dns-elektrikerportal.com-2026-10-08.txt` und
`docs/messungen/zone-dns-sanitaerfinden.com-2026-10-08.txt`
(erzeugt mit `scripts/zone-dns-umzug.sh --bestand`, abgefragt am autoritativen
Nameserver, nicht am Resolver).

| | elektrikerportal.com | sanitaerfinden.com |
|---|---|---|
| Nameserver | `ns1hwy`/`ns2jqz`/`ns3nrz`/`ns4qxz.name.com` | dieselben |
| `@` A | 88.198.64.145 | 88.198.64.145 |
| `www` A | 88.198.64.145 | 88.198.64.145 |
| Platzhalter `*` A | 88.198.64.145 | 88.198.64.145 |
| MX | `1 smtp.google.com.` | keiner |
| TXT `@` | 2× `google-site-verification=…` | 1× `google-site-verification=…` |
| SPF, DMARC, DKIM | keine gefunden | keine gefunden |
| CAA | keine | keine |
| `cf-ray` | nein | nein |

Drei Punkte daraus, die den Ablauf bestimmen:

1. **Es gibt einen Platzhalter-Record.** Die Namen `mail`, `smtp`, `imap`,
   `pop`, `webmail`, `autodiscover`, `autoconfig`, `ftp` haben keine eigenen
   Records, sie antworten aus `*`. Vorsatz war, `*` grau zu lassen; bei
   `elektrikerportal.com` ist der Platzhalter beim Umzug **orange** geworden
   und Cloudflare proxyt ihn auch im Free-Tarif. Folge: ueber `mail`, `smtp`,
   `imap` usw. antwortet nur noch HTTP(S), SMTP/IMAP/FTP ueber diese Namen
   nicht mehr. Hier ohne Schaden — Mail laeuft ueber den MX von Google, die
   Namen trugen keinen Dienst. Haengt an einer Zone doch ein Mailclient oder
   FTP an so einem Namen, den Record einzeln grau schalten.
   `scripts/zone-dns-umzug.sh --vergleichen` meldet den Fall als `i`-Zeile.
2. **Es gibt keine Subdomain-Portale unter diesen beiden Zonen.** Die
   Tenant-Liste der Produktion nennt zu beiden nur die Hauptdomain
   (Subdomain-Portale haengen an `firmenfreund.de` und `tierarztportal.com`).
   Proxied gehoeren damit genau `@` und `www`.
3. **Das Ursprungszertifikat traegt beide Namen** (`@` und `www` je Zone,
   geprueft am 08.10.2026, gueltig bis 08.12.2026, CN `sanitaerfinden.dev`,
   siehe `[[sun-tls-zertifikat-nachziehen]]`). "Full (strict)" ist damit sofort
   moeglich; laeuft das Zertifikat aus, antwortet Cloudflare 526 statt die
   Warnung durchzulassen — die Verlaengerung ist nach dem Umzug also
   kritischer als vorher.

**Probe:** `scripts/zone-dns-umzug.sh --vergleichen` endet mit Exit 0 und
"Kein kritischer Record verloren" (vergleicht gegen die Dateien oben).

---

## 1. Bestand frisch aufnehmen

Nur noetig, wenn seit dem 08.10.2026 am DNS geschraubt wurde — sonst mit
Abschnitt 2 weitermachen.

```bash
scripts/zone-dns-umzug.sh --bestand
```

Schreibt je Zone `docs/messungen/zone-dns-<zone>-<datum>.txt`. `--vergleichen`
nimmt immer die neueste Datei je Zone.

**Probe:** Beide Dateien existieren und enthalten die MX- und TXT-Zeilen aus
Abschnitt 0.

---

## 1a. Besonderheit `sanitaerfinden.com`: das ist die zentrale Domain

`sanitaerfinden.com` ist nicht nur Portal (Tenant 28), sondern `CENTRAL_DOMAIN`
und `APP_URL` der Installation (`deploy.php`, `config/tenancy.php`,
`docs/messungen/produktionsumgebung-anleitung.md`). Darunter liegt das ganze
Backoffice: `/admin`, `/content`, `/dashboard`, `/horizon`, `/telescope`. Der
Umzug ist technisch derselbe wie bei einer Portal-Zone, die Nebenwirkungen sind
andere — vor dem Umschalten der Nameserver bedenken:

1. **Cloudflare Free bricht Anfragen ueber 100 MB ab.** `post_max_size` und
   `upload_max_filesize` stehen auf 100 M (`docker/*/php.ini`); ein
   Media-Library-Upload am oberen Rand laeuft hinter der Kante in 413 statt in
   die PHP-Grenze.
2. **Cloudflare bricht nach 100 Sekunden mit 524 ab.** Lange Aktionen im Admin
   (Importe, Ratgeber-Erzeugung, Exporte) duerfen nicht am Request haengen,
   sondern gehoeren in die Queue. Was heute laenger als 100 s braucht, ist nach
   dem Umzug kaputt — vorher pruefen, nicht danach.
3. **Regel 2 sperrt `curl/*`, `python-requests`, `go-http-client`.** Jeder
   eigene Aufruf gegen `https://sanitaerfinden.com` (Cron, Monitoring,
   Health-Check, Webhook-Test) bekommt dann 403. Deployer selbst ist nicht
   betroffen, der laeuft ueber SSH.
4. **Regel 4 (120 GET/min je IP, Managed Challenge) trifft auch das
   Backoffice.** Bevor die vier Regeln auf dieser Zone gesetzt werden, braucht
   Regel 1 (Skip) dort zusaetzlich die Backoffice-Praefixe
   (`/admin`, `/content`, `/dashboard`, `/horizon`, `/telescope`, `/livewire`)
   — sonst challenged die Kante die eigene Verwaltung.

Punkt 4 ist Repo-Arbeit und nicht Teil dieses Umzugs: solange
`scripts/cloudflare-waf-regeln-setzen.sh` allen Zonen denselben Regelsatz
gibt, fehlt die zonenspezifische Ausnahme. Deshalb Reihenfolge:
**Umzug ja, Regeln auf dieser Zone erst nach der Ausnahme.**

---

## 2. Zonen in Cloudflare aufnehmen und Records abgleichen

Cloudflare → Add site → Domain eintragen → Tarif Free → Zonen-Scan abwarten.
Der Scan uebernimmt nicht zuverlaessig alles. Deshalb Zeile fuer Zeile gegen
die Datei aus Abschnitt 0/1 abgleichen, besonders:

* `MX 1 smtp.google.com.` (nur elektrikerportal.com) — Prioritaet 1,
* jeden `TXT`-Eintrag am Apex (`google-site-verification=…`) wortgleich,
* `*` A 88.198.64.145,
* `@` und `www` A 88.198.64.145.

Fehlende Eintraege von Hand nachtragen. Noch **nicht** die Nameserver
umstellen.

**Probe:** In Cloudflare "DNS → Records" so viele Zeilen wie in der
Bestandsdatei (ohne die vier NS-Zeilen und ohne die Namen, die nur aus `*`
kamen).

---

## 3. Proxy-Schalter setzen

| Record | Schalter |
|---|---|
| `@` A | **orange** (proxied) |
| `www` A | **orange** (proxied) |
| `*` A | **grau** (DNS only), solange an `mail`/`smtp`/`ftp` ein Dienst haengt — sonst egal, siehe Abschnitt 0 Punkt 1 |
| `MX`, alle `TXT` | kein Schalter bzw. grau |

**Probe:** In der Record-Liste steht orange nur an `@` und `www`.

---

## 4. TLS auf "Full (strict)"

SSL/TLS → Overview → Encryption mode: **Full (strict)**. Nicht "Flexible":
das wuerde unverschluesselt zum Ursprung gehen und die Weiterleitung
`www → @` aus dem nginx-vhost in eine Schleife drehen.

SSL/TLS → Edge Certificates: "Always Use HTTPS" an, "Automatic HTTPS
Rewrites" an.

**Probe (vor der NS-Umstellung moeglich):**

```bash
curl -sS -o /dev/null -D - --resolve elektrikerportal.com:443:88.198.64.145 \
  https://elektrikerportal.com/robots.txt | head -1
```

muss 200 liefern — das zeigt, dass der Ursprung unter diesem Namen ein
gueltiges Zertifikat vorweist, worauf "Full (strict)" besteht.

---

## 5. Nameserver bei name.com umstellen

name.com → Domain → Nameservers → die beiden von Cloudflare genannten
`*.ns.cloudflare.com` eintragen, die vier `*.name.com` entfernen. Die
uebrigen 16 Portal-Zonen liegen auf `ara`/`sri.ns.cloudflare.com`; Cloudflare
nennt beim Add site das Paar fuer diese Zone.

Propagation dauert bis zu einer Stunde (SOA-TTL der Zone: 3600 s).

**Probe:**

```bash
dig +short NS elektrikerportal.com
dig +short NS sanitaerfinden.com
```

nennen nur noch `*.ns.cloudflare.com`.

---

## 6. Abnahme

```bash
scripts/zone-dns-umzug.sh --abnahme
```

Macht beides in einem Lauf: DNS-Vergleich gegen den Bestand (jeder fehlende
MX-, TXT-, CAA- oder DKIM-Record ist ein Fehler; geaenderte A-Records der
Web-Namen werden nur gemeldet, die zeigen nach dem Umzug erwartungsgemaess auf
Cloudflare-Adressen) und die Kantenprobe aus
`scripts/cloudflare-waf-regeln-setzen.sh --kanten-pruefen`.

**Probe:** Exit 0, "Kein kritischer Record verloren" und die Zone mit
`cf-ray: ja`. Fuer `elektrikerportal.com` am 08.10.2026 erreicht.

Zusaetzlich, weil Mail an der Zone haengt: eine Testmail an eine Adresse der
Domain schicken und auf Zustellung warten (elektrikerportal.com, Google
Workspace).

---

## 7. Erst danach: WAF-Regeln und Nachmessung (#24)

```bash
scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com
scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com --pruefen
scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com --abnahme
```

Braucht den WAF-Token aus `docs/bot-traffic.md` Abschnitt 6 Schritt 1. Nach
einigen Tagen:

```bash
php artisan antispam:bot-traffic --tenant=elektrikerportal.com --days=7
```

**Probe:** `--pruefen` Exit 0, `--abnahme` Exit 0 (IndexNow-Schluesseldatei
weiterhin 200 mit Bingbot-Kennung), Bot-Quote in der Nachmessung niedriger als
die ~33 % aus `docs/bot-traffic.md` Abschnitt 1, Ereigniszahl echter Besucher
nicht gefallen.

---

## Protokoll

| Abschnitt | Datum | Durch | Ergebnis | Bemerkung |
|---|---|---|---|---|
| 0 Ausgangsstand | 08.10.2026 | Sebastian | festgehalten | Rohdaten in docs/messungen/ |
| 2 Zone aufgenommen | 08.10.2026 | Enes | elektrikerportal.com | MX + TXT uebernommen |
| 3 Proxy | 08.10.2026 | Enes | elektrikerportal.com | `@`/`www` orange, `*` ebenfalls proxied |
| 5 Nameserver | 08.10.2026 | Enes | elektrikerportal.com | `ara`/`sri.ns.cloudflare.com` |
| 6 Abnahme | 08.10.2026 | Dimitri | Exit 0 | `cf-ray` ueber die autoritative Adresse |
| 2–6 sanitaerfinden.com | | | offen | Zone liegt weiter bei name.com |
| 7 WAF + Nachmessung | | | offen | braucht den WAF-Token, #40 |
