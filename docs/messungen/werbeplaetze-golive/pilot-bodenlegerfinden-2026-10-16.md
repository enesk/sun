# Pilot der Werbeplätze SUN-V2 auf `bodenlegerfinden.com` (Prod-ID 27)

**Datum:** 16.10.2026 · **Ticket:** #31 · **Codestand Produktion:** `abf578e7`
Gegenstand: `docs/ad-slots-golive.md` §3 (Pilot) und §4 (Rollout).

Dieses Dokument ist der Beleg zum **durchgeführten** Pilot — anders als der
Vorlauf (`pilot-vorlauf-2026-10-16.md`, der nur gemessen und nichts geschaltet
hat) sind hier auf der Produktion zwei Belegungen angelegt und die Freigabeliste
durchgesetzt worden.

Rohdaten in diesem Ordner:

| Datei | Inhalt |
| --- | --- |
| `pilot-bodenlegerfinden-com-vorher-mobil-2026-10-16.json` | 360 px, **vor** den beiden neuen Belegungen, mit Klick auf „Alle akzeptieren" |
| `pilot-bodenlegerfinden-com-nachher-mobil-2026-10-16.json` | 360 px, **nach** den Belegungen, mit Klick |
| `pilot-bodenlegerfinden-com-nachher-desktop-2026-10-16.json` | 1366 px, nach den Belegungen, mit Klick |
| `pilot-bodenlegerfinden-com-suche-wiederholungen-2026-10-16.json` | drei Wiederholungen der Suchseite (Ausreißer nachgeprüft) |
| `pilot-bodenlegerfinden-com-lcp-mobil-2026-10-16.json` | LCP/CLS ohne gegen mit Einwilligung, 3 Läufe je Zustand, 360 px |
| `pilot-bodenlegerfinden-com-lcp-desktop-2026-10-16.json` | dasselbe in 1366 px |
| `pilot-bodenlegerfinden-com-csp-konsole-2026-10-16.json` | CSP-Verstöße und Konsolenfehler, je Seitentyp ohne und mit Einwilligung |
| `adstxt-sweep-2026-10-16-nach-freigabeliste.txt` | `/ads.txt` über alle 23 Domains nach dem Abschalten von 43 |
| `golive-check-2026-10-16-nach-31.txt` | Prüflauf auf der Produktion am Ende dieses Tickets |

Werkzeuge: `scripts/ads-einwilligung-cls.mjs` (CLS beim Klick),
`scripts/ads-lcp-vergleich.mjs` (**neu**, LCP/CLS ohne gegen mit Einwilligung),
`scripts/csp-messung.mjs` (CSP und Konsole), `scripts/ads-txt-sweep.sh`.

---

## 1. Portalwahl (§3.1, B5)

Die bisherigen Vorschläge halten der Prüfung nicht stand: `unfallarzt.net`
existiert nicht, `unfallarzt.firmenfreund.de` (52) hat keinen Ratgeber-Artikel
und fährt Auto Ads, `sanitaerfinder.com` (49) hat keine Publisher-ID und keinen
belegten AdSense-Status. Gewählt ist **27 `bodenlegerfinden.com`**, weil es als
einziges Portal alle fünf Bedingungen aus §3.1 gleichzeitig erfüllt:

| Bedingung aus §3.1 | 27 | Beleg |
| --- | --- | --- |
| Theme `sun-v2`, Anker vorhanden | ja | `ads:golive:check`, Ankerkommentare im HTML |
| Seitentyp `guide` vorhanden | ja, 15 veröffentlichte Artikel | `/ratgeber`, Tenant-DB |
| Profile mit „Über die Firma" vorhanden | ja (z. B. `/12-parkett-loni`) | `company_after_about` steht im HTML |
| Auto Ads **aus** | ja, `adsense_auto_ads = false` und **kein** aktiver Alt-Slot `auto_ads` | `ad_settings`, Tenant-DB |
| AdSense liefert auf der Domain tatsächlich aus | ja | 6 aktive Alt-Slots mit `ca-pub-2563111817853634`, `/ads.txt` 200, Anzeigen werden eingesetzt |

Die letzte Zeile ist der Ersatz für den AdSense-Site-Status aus dem Konto
(B6, kein Kontozugang in dieser Umgebung): eine Domain, auf der das Altsystem
seit März 2026 AdSense mit der eigenen Publisher-ID ausspielt und die ihre
`ads.txt` mit genau dieser ID ausliefert, ist bei Google freigeschaltet —
sonst käme dort keine Anzeige zurück. Im Pilot ist das direkt nachgeprüft: nach
der Einwilligung werden Anzeigen eingesetzt (`eingesetzteAnzeigen` 2 bis 6 je
Seite), die Anker tragen also echte Werbung und nicht leere Rahmen.

## 2. Aufbau (§3.1)

Angelegt am 16.10.2026 um 12:40 UTC auf der Produktion, portalgenau für 27:

| Belegung | Anker | Seitentyp | Gerät | Format | Werbemittel | Prio |
| --- | --- | --- | --- | --- | --- | --- |
| #4 (aus dem Takeover) | `company_after_about` | company | all | `responsive` | #4 · Slot 9923316650 | 10 |
| **#13 (neu)** | `guide_in_article_1` | guide | all | `responsive` | #13 · Slot 3828015317 | 20 |
| **#14 (neu)** | `search_in_feed_3` | search | all | `responsive` | #13 · Slot 3828015317 | 20 |

Drei Entscheidungen dazu, mit Begründung:

1. **Format `responsive`, nicht `in_article`/`in_feed`.** Die fluiden Formate
   bestimmen ihre Höhe selbst (`AdSenseProvider::style()` lässt sie frei) und
   hätten die Reservierung von 280/250 px sprengen können; `in_feed` braucht
   zusätzlich einen `layout_key` aus dem Konto. `responsive` bekommt seit #32
   die Katalog-Höhe in den `style` — der Anker gibt die Höhe vor.
2. **Werbemittel #13 nutzt den AdSense-Slot 3828015317 doppelt.** Neue
   Anzeigenblöcke lassen sich nur im AdSense-Konto anlegen; genommen ist
   deshalb der Slot der einzigen Belegung, die netzweit auf `paused` stand
   (`company_before_footer`). Folge: der **AdSense-Report** kann die drei
   Pilot-Anker nicht auseinanderhalten. Die Zahlen je Anker kommen deshalb aus
   der eigenen Zählung (§5), die je `slot_key` trennt.
3. **Kein Banner-Anker im Pilot**, wie §3.1 es vorschreibt. (Dass die beiden
   `company_before_footer`-Belegungen während dieses Pilots von Ticket #40
   wieder scharf geschaltet wurden, ist dort gemessen:
   `cls-company-before-footer-2026-10-16.md`, CLS 0,000. Für den Pilot wurde
   nichts daran geändert.)

Nachweis der Auflösung (`php artisan ads:resolve 27 <anker> all`, Produktion):
alle drei Anker liefern genau diese Belegungen, Fragment
`<ins class="adsbygoogle …" style="display:block;width:100%;height:250px">`,
Head-Abhängigkeit „erst nach Einwilligung". Im ausgelieferten HTML sind die
Anker danach da: `guide_in_article_1` auf dem Ratgeber-Artikel,
`search_in_feed_3` auf `/firmen` — vorher fehlten sie, weil beide Einbauten im
Theme an `AdSlot::isActive()` hängen.

## 3. Abweichung von §3: drei Tage, Feldwerte, AdSense-Report

§3 verlangt drei Kalendertage, Feldwerte aus der Search Console und den
AdSense-Report. Keines der drei ist in dieser Umgebung zu holen (kein
Kontozugang, kein Wartefenster). Gemessen ist deshalb der Teil, der **ohne**
Konto und ohne Wartezeit dieselbe Aussage trägt — und zwar sauber getrennt:

| §3 verlangt | hier gemessen | trägt die Aussage? |
| --- | --- | --- |
| Labor vorher / nachher | zwei Läufe um die Belegung herum **plus** der A/B-Vergleich „ohne gegen mit Einwilligung" am selben Dokument | ja — ohne Einwilligung lädt kein AdSense, das ist der Zustand „ohne Werbung" |
| Feld (CrUX) vorher / nachher | **nicht gemessen** | nein, braucht Search Console und 28 Tage CrUX-Fenster |
| Konsole/Netzwerk vor und nach der Einwilligung | vollständig, je Seitentyp | ja |
| Viewability je Anker aus dem AdSense-Report | eigene Zählung je Anker (§5) | teilweise — absolute Sichtkontakte ja, Quote/CTR/RPM nein |

Der A/B-Vergleich ist der Kern: auf diesem Portal lädt der AdSense-Loader
**ausschließlich** nach der Einwilligung (belegt in §6). Derselbe Seitenaufruf
ohne Einwilligung ist damit genau die Vorher-Lage — gleiche Tageszeit, gleiche
Maschine, gleiche Inhalte, und ohne den Störfaktor „drei Tage älterer Datenbestand".

## 4. Messergebnis (§3.3)

### 4.1 CLS, 360 px mobil (Klick auf „Alle akzeptieren" im laufenden Besuch)

| Seite | vor Belegung | nach Belegung | eingesetzte Anzeigen (nachher) |
| --- | --- | --- | --- |
| `/12-parkett-loni` (company) | 0,0149 | **0,0000** | 4 |
| `/ratgeber/guten-handwerker-finden-tipps` (guide) | 0,0000 | **0,0000** | 2 |
| `/firmen` (search) | 0,0000 | 0,1298 → **0,0000** (3 Wiederholungen) | 2 bis 6 |

Die 0,0149 im Vorher-Lauf stammen vom **Google-CMP-Dialog**
(`div.fc-stacks`, `button.fc-faq-header`), nicht von einem Anker — derselbe
Befund wie in `docs/ad-slots.md` 9.5.

Der eine Lauf mit **0,1298** (gesamt 0,1872) auf der Suchseite ist nachgeprüft
und **nicht reproduzierbar**: drei Wiederholungen derselben Seite im selben
Aufbau liefern 0,0000, bei geladenem Anker und eingesetzter Anzeige. In dem
Ausreißer waren **sechs** Anzeigen auf der Seite statt zwei, und alle
Verschiebungen bis auf eine haben als Quelle „(entfernt)" — Knoten, die zur
Lesezeit nicht mehr im DOM waren, also der verschwindende CMP-Dialog. Die eine
Verschiebung mit Anker-Bezug (0,0339) nennt neben `div.ad-slot
@search_in_feed_3` auch zwei Treffer-Karten: dort ist die ganze Liste nach oben
gerutscht, als der Dialog ging, nicht der Anker gewachsen (Container durchweg
310 px = 280 px Katalog + Zeile „Anzeige").

**Befund daraus — nicht vom Modul verursacht, aber für die Zusage relevant:**
auf diesem Konto sind **Auto Ads auf Kontoseite** aktiv. Sobald irgendein Anker
den AdSense-Loader auf die Seite bringt, setzt Google zusätzlich eigene,
**nicht reservierte** Anzeigen ein (im Ausreißer vier zusätzliche). Vor den
Belegungen passierte das nicht, weil ohne Anker kein Loader auf die Seite kommt
(`/firmen` und `/ratgeber/…` hatten vorher 0 Anzeigen). Der Schalter
`adsense_auto_ads` in `ad_settings` steuert das **nicht** — er steuert nur
unseren eigenen Auto-Ads-Loader. Dafür ist **#46** angelegt.

### 4.2 CLS, 1366 px (Desktop-Breite)

Alle drei Seiten **0,0000**, sämtliche Anker geladen, inklusive der beiden
Seitenspalten-Anker (286 px bzw. 636 px) und `company_before_footer` (126 px).

### 4.3 LCP ohne gegen mit Einwilligung (Median aus 3 Läufen je Zustand)

360 px mobil (`…-lcp-mobil-…json`):

| Seite | LCP ohne Werbung | LCP mit Werbung | Abweichung | CLS ohne / mit |
| --- | --- | --- | --- | --- |
| company | 480 ms | 508 ms | **+5,8 %** | 0,0000 / 0,0000 |
| guide | 224 ms | 244 ms | **+8,9 %** | 0,0000 / 0,0000 |
| search | 520 ms | 560 ms | **+7,7 %** | 0,0000 / 0,0000 |

1366 px (`…-lcp-desktop-…json`):

| Seite | LCP ohne Werbung | LCP mit Werbung | Abweichung |
| --- | --- | --- | --- |
| company | 9484 ms | 9496 ms | **+0,1 %** |
| guide | 164 ms | 148 ms | **−9,8 %** |
| search | 9524 ms | 9524 ms | **0 %** |

Die 9,5 s auf company und search sind **kein** Feldwert: in Desktop-Breite
findet das Durchscrollen des Skripts weit unten ein größeres
LCP-Kandidatenelement (Kartenbild bzw. Trefferliste), und der letzte Kandidat
gewinnt. Für die Aussage zählt nur der Vergleich beider Zustände am gleichen
Ablauf — und der ist auf null.

Die Toleranz aus §3.3 Aussage 2 ist 10 % — alle drei Seitentypen liegen
darunter. Der Aufschlag ist erwartbar: nach der Einwilligung lädt zusätzlich
`adsbygoogle.js`, und das LCP-Element ist auf diesen Seiten Text, der danach
noch einmal neu bewertet wird.

### 4.4 CSP und Konsole, je Seitentyp ohne und mit Einwilligung

`scripts/csp-messung.mjs`, sechs Fälle (drei Seiten × zwei Zustände):

| Fall | CSP-Verstöße | Konsolenfehler | Google-Hosts angesprochen |
| --- | --- | --- | --- |
| company ohne Einwilligung | 0 | 0 | keiner |
| company mit Einwilligung | 0 | 0 | `pagead2.googlesyndication.com`, `fundingchoicesmessages.google.com`, … |
| guide ohne / mit | 0 / 0 | 0 / 0 | keiner / wie oben |
| search ohne / mit | 0 / 0 | 0 / 0 | keiner / wie oben |

Damit ist zugleich die Einwilligungssperre auf allen drei Pilot-Seitentypen am
ausgelieferten Code belegt: ohne Einwilligung **kein** Request an einen
Google-Werbehost, `adsbygoogleGeladen = false`, Fragmente im `<template>`.

### 4.5 Bewertung der vier Aussagen aus §3.3

| Aussage | Ergebnis |
| --- | --- |
| 1. CLS unter 0,1, mobil und Desktop | **erfüllt im Labor** (0,0000 in beiden Breiten, Wiederholungen bestätigt); Feldwert offen |
| 2. LCP nicht schlechter als +10 % | **erfüllt** (+5,8 / +8,9 / +7,7 %) |
| 3. keine CSP-Verstöße, keine Konsolenfehler, vor **und** nach der Einwilligung | **erfüllt** (0/0 in sechs Fällen) |
| 4. INP unverändert | **nicht messbar ohne Feld.** Die Anker bringen keinen Ereignis-Handler mit (`resources/js/ads.js` nutzt nur `IntersectionObserver` und `fetch`), eine Verschlechterung käme allein aus dem AdSense-Skript. Feldwert offen |

Pilot damit **im Labor bestanden**, mit zwei offenen Feldwerten (CLS/INP aus
CrUX) und dem Befund zu den Auto Ads auf Kontoseite.

## 5. Sichtbarkeit je Anker aus der eigenen Zählung (§3.4)

Quelle ist die eigene Impressionszählung (#12): gezählt wird erst, wenn der
Anker den Sichtbarkeitsanteil aus dem Katalog für die vorgegebene Dauer hält
(`resources/js/ads.js`, `IntersectionObserver` + Frist) — also schon ein
**Sichtkontakt**, keine bloße Auslieferung.

Stand 16.10.2026, Fenster 12:09–13:01 UTC (erste gezählte Stunde des Netzes),
14 Portale, **391 Sichtkontakte**, verdichtet in `ad_impression_daily`:

| Anker | Gerät | Sichtkontakte | Portale | Reichweite gegen den obersten Anker derselben Seite |
| --- | --- | --- | --- | --- |
| `company_after_header_card` | mobil | 192 | 14 | 100 % (Bezug) |
| `company_after_reviews` | mobil | 50 | 13 | 26 % |
| `company_after_about` | mobil | 17 | 4 | 9 % · nur Profile mit Beschreibungstext |
| `company_before_footer` | mobil | 10 | 5 | 5 % |
| `company_sidebar_top` | Desktop | 63 | 14 | 100 % (Bezug Desktop) |
| `company_sidebar_sticky` | Desktop | 26 | 9 | 41 % |
| `company_after_reviews` | Desktop | 20 | 8 | 32 % |
| `company_after_about` | Desktop | 7 | 3 | 11 % |
| `company_before_footer` | Desktop | 6 | 4 | 10 % |

Nebenbei belegt dieser Lauf die offene Zeile aus §6.3 (#37): die erste
Verdichtung hat gegriffen, sobald die Stunde abgeschlossen war — 74
Aggregatzeilen, 391 Impressionen, der Puffer der Stunde `12:00` ist leer.

**Was diese Spalte ist und was nicht.** Die Quote ist die **Reichweite** eines
Ankers: wie viele Besucher ihn überhaupt zu sehen bekommen, bezogen auf den
obersten Anker derselben Seite. Die Viewability-Quote aus §3.4 („sichtbare
Impressionen / ausgelieferte Impressionen", Schwelle 40 %) ist das **nicht** —
dafür fehlt der Nenner: die eigene Zählung schreibt nur den Sichtkontakt, nicht
die Auslieferung. Die 40-%-Regel darf auf diese Spalte deshalb nicht angewendet
werden. Für die echte Quote braucht es entweder den AdSense-Report (Konto) oder
einen zweiten Zähler für die ausgelieferten Fragmente (#47).

Ablesbar ist die Reihenfolge trotzdem, und sie bestätigt die Vorab-Empfehlung
aus `docs/ad-slots.md` §13.1: der Anker direkt unter der Kopf-Karte und der
obere Seitenspalten-Anker tragen das Meiste, `company_before_footer` erreicht
unter 10 % der Besucher einer Profilseite. Die beiden Pilot-Anker
`guide_in_article_1` und `search_in_feed_3` haben in diesem Fenster noch keine
Zählung (angelegt um 12:40 UTC, Verdichtung läuft stündlich).

## 6. Freigabeliste durchgesetzt (§1.1, §2 letzte Zeile)

Entschieden nach der Regel aus §1 und dem Ersatzkriterium aus §1 dieses
Dokuments:

* **`ads_enabled = ja`** bleibt auf den 15 `sun-v2`-Portalen mit Altbestand —
  dort liefert AdSense nachweislich aus, die Domains sind freigeschaltet, und
  ein Abschalten würde laufende Werbung wegnehmen.
* **`tierarztportal.com` (43) bleibt auf `ja`** — nach einer Probe und einer
  Rücknahme. Das Portal fährt Theme `default` und hat keinen Anker im Katalog
  (#16), §1.1 führt es deshalb auf `nein`. Um 12:44 UTC auf `nein` geschaltet
  (mit `adsense_auto_ads = false`, Portal-Cache vergessen), um 13:05 UTC
  **zurückgenommen**: der Schalter steuert nicht nur die Auslieferung, sondern
  auch die Google-Zeile in `/ads.txt`, und auf 43 liefert das **Altsystem**
  sieben aktive AdSense-Slots aus. Heute bleibt die Zeile bei `nein` noch, weil
  sie in `ads_txt_extra` steht; mit dem Stand von #41 (noch nicht deployt)
  fällt sie weg — dann stünde dort laufende Werbung ohne autorisierten
  Verkäufer. Regel daraus, in §1.1 eingetragen: `ads_enabled` bleibt `ja`,
  solange das Altsystem im Portal ausliefert; abgeschaltet wird erst mit dem
  Altsystem (§4.1). Nachgeprüft in beiden Zuständen:
  `https://pfotencheck.tierarztportal.com/ads.txt` HTTP 200, eine Zeile.
* **`ads_enabled = nein`** bleibt für 30 (bewusst abgeschaltet am 14.09.2026),
  51 (Theme `default`) und 49, 54, 55, 57, 58 (kein Altbestand, kein belegter
  AdSense-Status, keine Publisher-ID) — fail-closed.

`/ads.txt` danach über alle 23 Domains: 18 × HTTP 200 mit genau einer
Google-Zeile, 5 × 404, keine Dublette
(`adstxt-sweep-2026-10-16-nach-freigabeliste.txt`). Prüflauf am Ende:
**0 FAIL**, 18 WARN, 16 von 23 Portalen freigegeben
(`golive-check-2026-10-16-nach-31.txt`).

## 7. Was offen bleibt — und wo es hängt

1. **Feldwerte CLS/INP/LCP (CrUX, Search Console)** — braucht Kontozugang und
   das 28-Tage-Fenster. Nachzutragen in §3.3 des Protokolls.
2. **AdSense-Report je Anker**: aktive Betrachtungszeit, Viewability-Quote,
   Klickrate, RPM. Braucht das Konto; zusätzlich kann der Report die drei
   Pilot-Anker erst dann trennen, wenn jeder Anker seinen eigenen
   Anzeigenblock hat (§2, Punkt 2).
3. **Auto Ads auf Kontoseite** setzen unreservierte Anzeigen auf jede Seite,
   auf der ein Anker den Loader lädt — der einzige gemessene Lauf über CLS 0,1
   kam daher.
4. **Viewability-Quote aus eigener Kraft** braucht einen zweiten Zähler
   (ausgelieferte Fragmente je Anker).
