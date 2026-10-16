# Belege zum Go-Live der Werbeplätze SUN-V2

Ablage für §3 (Pilot), §4 (Rollout) und §6 (Abnahme) aus
`docs/ad-slots-golive.md`. Jede abgehakte Zeile dort braucht hier einen Beleg.

Stand: 2026-10-16 — Ordner angelegt, Pilot **nicht** gestartet (Ticket #31).

## Namensschema

| Art | Dateiname |
| --- | --- |
| Prüflauf `ads:golive:check` | `golive-check-<datum>.txt` (Produktion) bzw. `…-<datum>-lokal.txt` |
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
* `pilot-protokoll.md` — das auszufüllende Protokoll für §3.

## Reihenfolge

1. Deploy nach `docs/ad-slots-golive.md` §2 — `ads:legacy-takeover` **vor** dem
   ersten Aufruf der neuen `/ads.txt`.
2. Prüflauf auf der Produktion hierher sichern, **kein FAIL**.
3. Pilot nach `pilot-protokoll.md`.
4. Rollout §4, je Stufe ein Prüflauf.
