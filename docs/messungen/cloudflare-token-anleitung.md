# Anleitung: Cloudflare-API-Token `sun-portale-dns` ausstellen (#138)

Vorbedingung für #130. Ohne diesen Token ist die Kantenarbeit an
`tierarztportal.com` und `firmenfreund.net` aus keinem Agentenlauf heraus
ausführbar, und ohne die Kantenarbeit gibt es für diese beiden Namen kein
Let's-Encrypt-Zertifikat.

Stand der Erhebung: **09.09.2026**, gemessen von der Arbeitsmaschine aus und
per Lesezugriff auf `88.198.64.145` (SSH-Kürzel `sun`, Benutzer `root`).

Das Ausstellen selbst geht nur im Cloudflare-Konto und kann von keinem
Agentenlauf erledigt werden. Abschnitt 1 hält den gemessenen Ausgangszustand
fest, Abschnitt 2 bis 5 sind Handarbeit, Abschnitt 6 beschreibt, was danach
automatisch läuft.

## 1. Befund am 09.09.2026

| Prüfpunkt | Befund |
| --- | --- |
| `/root/.cloudflare-token` auf `sun` | existiert nicht |
| Nameserver beider Zonen | `sri.ns.cloudflare.com`, `ara.ns.cloudflare.com` |
| `A` von `tierarztportal.com` | `172.67.218.195`, `104.21.38.40` — Cloudflare-Adressen, also **proxied** (orange Wolke) |
| `A` von `firmenfreund.net` und `www.firmenfreund.net` | `104.21.55.23`, `172.67.144.33` — ebenfalls proxied |

Gemessene Antworten auf dem ACME-Pfad `/.well-known/acme-challenge/x`:

| Anfrage | Antwort | `Location` |
| --- | --- | --- |
| `http://tierarztportal.com/...` | 301 | `https://tierarztportal.com/...` (Pfad erhalten) |
| `https://tierarztportal.com/...` | 302 | `https://pfotencheck.tierarztportal.com/` (**Pfad verworfen**) |
| `http://firmenfreund.net/...` | 301 | `https://solar-finden.de/...` (Pfad erhalten) |
| `https://firmenfreund.net/...` | 301 | `https://solar-finden.de/...` (Pfad erhalten) |

Alle vier Antworten tragen `server: cloudflare` und eine `CF-RAY`-Kennung, aber
keinen Origin-Header. Sie entstehen an der Kante, der Ursprungsserver sieht die
Anfrage nie.

Drei Folgerungen, die die Aufgabenstellung im Ticket präzisieren:

1. Der 301 von `http://tierarztportal.com` ist die Zoneneinstellung
   **Always Use HTTPS**, nicht die störende Regel. Er ist harmlos und darf
   bleiben — Let's Encrypt folgt Weiterleitungen. Die störende Regel ist der
   **302 auf `pfotencheck.`**, der den Pfad wegwirft.
2. Bei `firmenfreund.net` greift die Regel schon auf Port 80, also **vor**
   Always Use HTTPS. Das ist typisch für eine Redirect Rule oder eine Bulk
   Redirect, nicht für eine Page Rule.
3. Beide Zonen sind proxied. `dig` zeigt deshalb Cloudflare-Adressen und sagt
   **nichts** darüber, ob der Ursprung richtig auf `88.198.64.145` zeigt. Die
   Ursprungsadresse ist nur über die API oder das Dashboard prüfbar. Das im
   Ticket genannte „`A`-Einträge prüfen" heißt konkret: Inhalt des
   `A`-Datensatzes muss `88.198.64.145` sein, `proxied` darf true bleiben.

## 2. Token anlegen

Cloudflare-Dashboard → Profilmenü rechts oben → **My Profile** → **API Tokens**
→ **Create Token** → ganz unten **Create Custom Token** → **Get started**.

Name: `sun-portale-dns`

## 3. Berechtigungen

Die Liste im Ticket war an einer Stelle falsch: **Transform Rules** deckt nur
Umschreibungen (URL Rewrite, Header) ab, nicht Weiterleitungen. Weiterleitungen
an der Kante hängen an der Berechtigung **Dynamic Redirect**. Richtig sind
diese fünf Zeilen, alle in der Gruppe **Zone**:

| Gruppe | Recht | Wofür |
| --- | --- | --- |
| Zone | Zone → **Read** | Zonen-Kennung auflösen |
| Zone | DNS → **Edit** | `A`-Einträge prüfen und richtigstellen |
| Zone | **Dynamic Redirect** → **Edit** | Redirect Rules lesen und löschen (`http_request_dynamic_redirect`) |
| Zone | **Page Rules** → **Edit** | falls die Weiterleitung eine alte Page Rule ist |
| Zone | **Zone Settings** → **Edit** | Always Use HTTPS prüfen, nur lesend gebraucht |

**Transform Rules wird nicht gebraucht** und sollte weggelassen werden.

## 4. Geltungsbereich

**Zone Resources:**

- Zeile 1: `Include` → `Specific zone` → `tierarztportal.com`
- Zeile 2: `Include` → `Specific zone` → `firmenfreund.net`

Keine kontoweite Freigabe. Die übrigen 21 Portaldomains bleiben außen vor.

**Client IP Address Filtering** und **TTL** leer lassen. Dann
**Continue to summary** → **Create Token** → den Wert einmalig kopieren.

## 5. Ablegen und prüfen

Auf `88.198.64.145` als `root`:

```
install -m 0600 /dev/null /root/.cloudflare-token
printf '%s\n' '<TOKEN>' > /root/.cloudflare-token
```

Nicht ins Repository, nicht in `.env.example`, kein Vorgabewert im Code.

Probe:

```
curl -s -H "Authorization: Bearer $(cat /root/.cloudflare-token)" \
  https://api.cloudflare.com/client/v4/user/tokens/verify
```

Erwartet: `"status":"active"`. Zweite Probe, die auch den Geltungsbereich
belegt — sie muss genau zwei Zonen liefern:

```
curl -s -H "Authorization: Bearer $(cat /root/.cloudflare-token)" \
  "https://api.cloudflare.com/client/v4/zones?per_page=50" \
  | grep -o '"name":"[^"]*"'
```

## 6. Wenn eine der Weiterleitungen eine Bulk Redirect ist

Bulk Redirects sind **kontoweit**, nicht zonengebunden. Ein zonenbeschränkter
Token kann sie weder lesen noch löschen; die Liste liegt unter
`/accounts/<id>/rules/lists`. Falls die Suche in den Zonen-Rulesets und in den
Page Rules beider Zonen nichts findet, ist genau das der Fall. Dann bleiben
zwei Wege:

- **Bevorzugt, weil eng:** die betroffene Zeile im Dashboard von Hand aus der
  Bulk-Redirect-Liste entfernen (Account Home → Bulk Redirects). Zwei Klicks,
  kein weiterer Token.
- Notfalls einen zweiten Token mit `Account` → `Account Rulesets` → `Edit`
  ausstellen. Das ist deutlich breiter und sollte nach Gebrauch gelöscht
  werden.

## 7. Danach

#130 läuft ohne weitere Handarbeit: Regeln über
`/zones/<id>/rulesets` (Phase `http_request_dynamic_redirect`) und
`/zones/<id>/pagerules` suchen und löschen, `A`-Einträge gegen
`88.198.64.145` prüfen, dann `scripts/tls-zertifikat-nachziehen.sh` einmal mit
und einmal ohne `--pruefen`.

Fertig ist die Kantenarbeit, wenn diese Probe für beide Namen eine Antwort vom
Ursprung liefert statt einer Weiterleitung:

```
curl -sS -o /dev/null -D - http://tierarztportal.com/.well-known/acme-challenge/probe
curl -sS -o /dev/null -D - http://firmenfreund.net/.well-known/acme-challenge/probe
```

Erwartet ist 404 vom Portal (oder 301 auf denselben Host mit erhaltenem Pfad),
**nicht** eine `Location` auf einen fremden Host.

Der Token wird nur für Domainarbeit gebraucht, nicht im Betrieb. Nach dem
Go-Live darf er gelöscht werden — dann auch `/root/.cloudflare-token` entfernen.
