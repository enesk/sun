# Belege zum Go-Live der Werbeplätze SUN-V2

Ablage für §3 (Pilot), §4 (Rollout) und §6 (Abnahme) aus
`docs/ad-slots-golive.md`. Jede abgehakte Zeile dort braucht hier einen Beleg.

Stand: 2026-10-16 — Deploy nach §2 gefahren (Ticket #38), Vorlauf zum Pilot
gemessen und **Pilot durchgeführt** (Ticket #31,
`pilot-bodenlegerfinden-2026-10-16.md`). Kontogebunden offen bleiben die
Feldwerte aus CrUX und der AdSense-Report je Anker.

## Namensschema

| Art | Dateiname |
| --- | --- |
| Prüflauf `ads:golive:check` | `golive-check-<datum>.txt` (Produktion) bzw. `…-<datum>-lokal.txt` |
| Takeover, Trockenlauf | `legacy-takeover-trockenlauf-<datum>.txt` |
| Takeover, echter Lauf | `legacy-takeover-<datum>.txt` |
| Lighthouse vorher | `pilot-<portal>-vorher-<seitentyp>-<lauf>.json` |
| Lighthouse nachher | `pilot-<portal>-nachher-<seitentyp>-<lauf>.json` |
| Search Console / CrUX | `pilot-<portal>-cwv-<vorher\|nachher>-<mobil\|desktop>.png` |
| Konsole / Netzwerk | `pilot-<portal>-devtools-<vor\|nach>-einwilligung-<seitentyp>.png` |
| AdSense-Report (Viewability) | `pilot-<portal>-adsense-<datum>.png` + `.csv` |
| Rollout je Stufe | `rollout-stufe<n>-<datum>.txt` (Prüflauf + `curl`-Ausgaben) |

Portal-Kürzel ist die Domain ohne TLD-Punkte, z. B. `unfallarzt-firmenfreund-de`.

## Was schon hier liegt

* `golive-check-2026-10-16-lokal.txt` — Prüflauf in der **lokalen** Umgebung,
  nicht auf der Produktion. Er belegt nur, dass das Werkzeug läuft und wie die
  globalen Zeilen aussehen. Die Portal-Tabelle darin ist **nicht** aussagekräftig:
  die lokale Tenant-DB ist gegenüber der Produktion verzogen (Themes, Domains,
  `ads_enabled`). Maßgeblich ist der Lauf auf der Produktion nach dem Deploy.
* `golive-check-2026-10-16.txt` — Prüflauf auf der **Produktion** nach dem
  Deploy (#38), Code `9bbe1dfc`: **0 FAIL**, 18 WARN, 16 von 23 Portalen
  `ads_enabled`. Das ist der maßgebliche Nachweis zu §2 Schritt 5.
* `legacy-takeover-trockenlauf-2026-10-16.txt` und
  `legacy-takeover-2026-10-16.txt` — Trockenlauf und echter Lauf von
  `ads:legacy-takeover` auf der Produktion (#38). 118 Alt-Slots gelesen, 111
  übernommen; geschrieben: 12 Werbemittel, 12 Belegungen, 96
  Portal-Zuordnungen, 18 Einstellungen.
* `pilot-protokoll.md` — das auszufüllende Protokoll für §3.
* `pilot-vorlauf-2026-10-16.md` — Vorlauf zum Pilot (#31): was ohne
  AdSense-Konto, ohne echtes Gerät und ohne drei Wartetage messbar war.
  Auslieferung und Einwilligungssperre auf der Produktion, CLS 0,000 auf fünf
  Seitentypen, Bewertung der Pilot-Portale. Rohdaten:
  `pilot-unfallarzt-firmenfreund-de-vor-einwilligung-2026-10-16.json`.
* `adstxt-sweep-2026-10-16.txt` — `/ads.txt` über alle 23 Portal-Domains,
  Lauf `bash scripts/ads-txt-sweep.sh`. Nach jeder Rollout-Stufe wiederholen.
* `cls-company-before-footer-2026-10-16.md` — Beleg zu #40: die beiden wegen
  #32 pausierten Belegungen auf `company_before_footer` stehen wieder auf
  `active`. CLS **0,000** auf dem Firmenprofil, lokal mit Einwilligung im
  laufenden Besuch und auf der Produktion vor der Einwilligung. Rohdaten:
  `cls-company-before-footer-2026-10-16.json` (lokal),
  `cls-company-before-footer-prod-2026-10-16.json` (Produktion).
* `golive-check-2026-10-16-nach-40.txt` — Prüflauf auf der Produktion nach dem
  Scharfschalten: weiter **0 FAIL**.
* `pilot-bodenlegerfinden-2026-10-16.md` — **der durchgeführte Pilot** (#31) auf
  `bodenlegerfinden.com` (27): Portalwahl mit Begründung, zwei neu angelegte
  Belegungen, CLS/LCP/CSP vorher und nachher in 360 px und 1366 px, Sichtbarkeit
  je Anker aus der eigenen Zählung, durchgesetzte Freigabeliste. Rohdaten:
  `pilot-bodenlegerfinden-com-{vorher-mobil,nachher-mobil,nachher-desktop,suche-wiederholungen,lcp-mobil,lcp-desktop,csp-konsole}-2026-10-16.json`.
* `golive-check-2026-10-16-nach-31.txt` — Prüflauf am Ende von #31: **0 FAIL**,
  18 WARN, 16 von 23 Portalen freigegeben.
* `adstxt-sweep-2026-10-16-nach-freigabeliste.txt` — `/ads.txt` über alle 23
  Domains nach der durchgesetzten Freigabeliste: 18 × 200 mit genau einer
  Google-Zeile, 5 × 404.
* `freigabe-sieben-portale-2026-10-16.md` — die sieben Portale ohne Altbestand
  (30, 49, 51, 54, 55, 57, 58): Schalter, Publisher-ID, `ads_txt_extra`,
  Alt-Slots und `/ads.txt` je Domain, die Entscheidung dazu (#48) und der
  Nachtrag der Publisher-ID für 49.

## Reihenfolge

1. ~~Deploy nach `docs/ad-slots-golive.md` §2~~ — erledigt am 16.10.2026 (#38).
2. ~~Prüflauf auf der Produktion hierher sichern, **kein FAIL**~~ — erledigt,
   `golive-check-2026-10-16.txt`.
3. ~~Pilot nach `pilot-protokoll.md`~~ — gelaufen am 16.10.2026 auf Portal 27
   (#31), Protokoll ausgefüllt.
4. ~~Rollout §4~~ — vom Takeover überholt, Nachweis über den gesamten
   freigegebenen Bestand (`golive-check-2026-10-16-nach-31.txt`).
