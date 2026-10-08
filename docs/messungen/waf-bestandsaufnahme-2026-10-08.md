# WAF-Regeln #40: Bestandsaufnahme vor dem Setzen (08.10.2026)

Alles hier ist **ohne** Cloudflare-Token von aussen gemessen. Die Werte, die nur
die API hergibt (Bot Fight Mode, Super Bot Fight Mode, JS-Erkennung je Zone),
fehlen weiterhin — dafuer braucht es den Token aus Schritt 1 des Tickets und
danach `scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme`.

## 1. Token-Lage am Ursprung

`/root/sun-zugang.txt` existiert wieder (angelegt 2026-10-08T17:50:46Z, chmod 600,
239 Byte) und traegt:

| Schluessel | Stand |
| --- | --- |
| `CLOUDFLARE_API_TOKEN_SUN_TURNSTILE` | vorhanden (`cfut_…`, 53 Zeichen) |
| `CLOUDFLARE_ACCOUNT_ID_SUN` | vorhanden (32 Zeichen) |
| `CLOUDFLARE_API_TOKEN_SUN_WAF` | **fehlt** |

Gegenprobe, dass der Turnstile-Token hier nicht traegt — er ist gueltig, sieht
aber keine Zone:

    GET /user/tokens/verify          -> success:true, "valid and active"
    GET /zones?name=fahrschulefinder.de -> success:true, result: [], total_count: 0
    GET /zones?per_page=1               -> success:true, total_count: 0

Damit ist Schritt 1 (eigener Token mit Zone: Read + Firewall Services: Edit +
Bot Management: Read) nicht umgehbar.

## 2. Kante je Zone — `--kanten-pruefen`

17 von 18 Zonen liegen bei Cloudflare (Nameserver `ara/sri.ns.cloudflare.com`,
`cf-ray` vorhanden). Einzige Ausnahme:

* **sanitaerfinden.com** — Nameserver `ns1hwy.name.com` …, A-Record
  `88.198.64.145` (Ursprung direkt), kein `cf-ray`. Offen als #42.

elektrikerportal.com liegt seit #34 hinter der Kante (A `172.67.133.141`); der
lokale Resolver haelt dort noch die alte Adresse, Messungen von hier aus
brauchen `--resolve`.

## 3. Wirkung der Regeln — `scripts/waf-wirkung-pruefen.sh`

| Zone | Kante | `python-requests` GET / | Befund |
| --- | --- | --- | --- |
| 15 Portalzonen (fahrschulefinder.de, elektrikerportal.com, sanitaerfinder.com, malerfinder.de, fliesenleger.io, kfzwerkstatt.io, findegutachter.de, bodenlegerfinden.com, energieberaterportal.net, firmenfreund.de, geruestbauer.gmbh, metallbauer.io, mjet.net, schluesseldienstportal.com, speditionportal.com) | ja | **200** (Soll 403) | Regel 2 wirkt nicht |
| tierarztportal.com, firmenfreund.net | ja | 302 / 301 | Weiterleitung, nicht beurteilbar (#37) |
| sanitaerfinden.com | nein | – | keine Kante (#42) |

Bot Fight Mode ist damit auch ohne API-Blick nachweislich aus.

## 4. IndexNow-Schluesseldateien vorher — `--abnahme`

Exit 71, aber ausschliesslich wegen der zwei Weiterleitungszonen aus #37:
`tierarztportal.com` (302 auf pfotencheck.tierarztportal.com) und
`firmenfreund.net` (301 auf solar-finden.de). Alle uebrigen 18 Adressen
(inkl. der `*.firmenfreund.de`-Subportale) liefern **200**. Das ist der
Vergleichswert fuer die Abnahme nach dem Setzen: diese 200er muessen 200
bleiben.

## 5. Im Dashboard nachzusehen (nicht per API)

Security → Events, letzte 24 h, Filter „Bot Detection = not verified bot":
stehen die User-Agents aus `docs/bot-traffic.md` Abschnitt 1 auf „allowed"?
