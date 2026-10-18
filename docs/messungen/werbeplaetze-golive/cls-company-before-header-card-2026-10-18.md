# Messung company_before_header_card — 18.10.2026

Zu Ticket #68. Der neue Anker sitzt **vor** dem ersten Inhalt des Firmenprofils;
die Messung beantwortet die beiden Fragen aus dem Ticket: hält die Reservierung
(CLS), und was kostet der Platz an LCP.

## Aufbau

| | |
|---|---|
| Seite | `https://sun.test/22787-dithmarscher-haus-und-gartenservice` (Firmenprofil) |
| Portal | lokaler Tenant 47, für die Messung auf Theme `sun-v2` gestellt, danach zurück |
| Viewport | 360 × 800, `deviceScaleFactor` 2, mobil |
| Läufe | 3 je Zustand, dazu beide Einwilligungszustände des Skripts = 6 Werte je Zustand |
| Werkzeug | `scripts/ads-lcp-vergleich.mjs`, Median über die Läufe |
| Werbemittel | **eigenes HTML-Werbemittel 300 × 250 ohne jeden externen Inhalt** (HtmlProvider, `third_party=no`) |

**Warum kein AdSense:** die Vorgabe vom 16.10.2026 (CLAUDE.md) verbietet Aufrufe
fremder Dienste ohne Zustimmung von Enes. Gemessen wurde darum mit einer eigenen
Fläche in genau dem Format, das der Katalog für diesen Anker zulässt
(`rectangle_300x250`). Für die Layout-Aussage trägt das: ein AdSense-Creative
desselben festen Formats belegt dieselbe Box. Die **Netzlaufzeit** eines echten
AdSense-Requests ist damit **nicht** gemessen — der LCP-Wert unten ist die
Layout-Wirkung des Platzes, nicht die Wirkung des Anbieters.

Zur Absicherung lief Chrome mit
`--host-resolver-rules="MAP * ~NOTFOUND,EXCLUDE sun.test"`: jeder fremde Host
scheitert an der Namensauflösung. In allen sechs Läufen mit Anzeige:
`ins.adsbygoogle` 0, `adsbygoogle.loaded` false.

## Ergebnis

| Zustand | LCP (6 Werte, ms) | LCP Median | CLS max | Anker |
|---|---|---|---|---|
| **mit Anzeige** | 524 · 552 · 552 · 564 · 576 · 660 | **558 ms** | **0,0000** | gerendert, eingesetzt, 310 px hoch |
| **ohne Anzeige** (Belegung `paused`) | 524 · 524 · 544 · 548 · 584 · 592 | **546 ms** | **0,0000** | nicht vorhanden |

* **CLS: 0,0000** in allen zwölf Läufen — Zusage „unter 0,1" gehalten, mit
  deutlichem Abstand. Die Reservierung greift, weil das Format fest ist und die
  Höhe aus dem Katalog kommt (`--ad-mh:280px`).
* **LCP: +2,2 %** (558 gegen 546 ms) — Toleranz 10 % gehalten.

## Beobachtung zur Höhe

Der Container misst **310 px**, reserviert sind 280 px: 250 px Anzeige plus die
Zeile „Anzeige" darüber. Die 310 px stehen vom ersten Aufbau an, deshalb bleibt
CLS bei 0 — es wächst nichts nach. Die Reservierung im Katalog ist damit 30 px
knapper als die tatsächliche Fläche; sie ist die **Mindest**höhe und muss nicht
genau passen, solange sie nicht überschritten wird. Ein Format über 280 px
Anzeigenhöhe würde dagegen springen — genau darum steht hier nur
`rectangle_300x250`.

## Rohdaten

* `cls-company-before-header-card-2026-10-18-mit.json`
* `cls-company-before-header-card-2026-10-18-ohne.json`

## Was die Messung nicht abdeckt

* Keine Messung mit einem echten AdSense-Creative (siehe oben).
* Keine Feldwerte. Der Anker ist auf der Produktion noch nicht vorhanden —
  Einchecken und Deploy laufen über #69, belegt wird im Panel von Enes.
