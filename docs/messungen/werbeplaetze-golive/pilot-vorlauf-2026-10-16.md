# Pilot-Vorlauf auf der Produktion — was ohne AdSense-Konto messbar war

**Datum:** 16.10.2026 · **Ticket:** #31 · **Codestand Produktion:** `9bbe1dfc`
Gegenstand: `docs/ad-slots-golive.md` §3 (Pilot) und §4 (Rollout). Dieses
Dokument hält den Teil fest, der **ohne** AdSense-Konto, ohne echtes Gerät und
ohne drei Wartetage zu holen war. Was offen bleibt, steht am Ende.

Rohdaten:

* `pilot-unfallarzt-firmenfreund-de-vor-einwilligung-2026-10-16.json`
* `adstxt-sweep-2026-10-16.txt` (Lauf: `bash scripts/ads-txt-sweep.sh`)

---

## 1. Auslieferung am Pilot-Portal, vor der Einwilligung

Gemessen gegen die **Produktion** (`https://unfallarzt.firmenfreund.de`, Prod-ID
52) mit `scripts/ads-einwilligung-cls.mjs`, 360 px mobil, headless Chrome 155,
Cookies und localStorage je Lauf geräumt, 9 s Wartezeit, danach zweimal durch
die Seite gescrollt. **Ohne Klick auf den Cookie-Hinweis**
(`--klick='#kein-klick-im-pilot'`) — Begründung in §4.

| Seite | Anker-Kommentare im HTML | Container | CLS | offene Fragmente | offene Head-Vorlagen | `adsbygoogle` geladen | eingesetzte Anzeigen |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `/` (home) | 5 | 0 | 0,000 | 0 | 0 | nein | 0 |
| `/firmen` (search) | 5 | 0 | 0,000 | 0 | 0 | nein | 0 |
| `/1-herr-dr-med-ronny-langenhan` (company) | 5 | 4 | 0,000 | 4 | 1 | nein | 0 |
| `/staedte/cottbus` (city) | 4 | 0 | 0,000 | 0 | 0 | nein | 0 |
| `/ratgeber` (guide_index) | 2 | 0 | 0,000 | 0 | 0 | nein | 0 |

Aussage dieser Zeilen:

1. **Die Anker sind auf der Produktion da**, in allen fünf erreichbaren
   Seitentypen — als HTML-Kommentar ohne Geometrie, solange keine Belegung
   greift. Keine Seite ohne Belegung hat einen Container, das Layout ist dort
   also unverändert gegenüber vorher.
2. **Die Einwilligungssperre hält.** Auf der Profilseite warten vier Container
   mit `data-ad-consent="marketing"`, ihre vier Fragmente liegen ungeöffnet im
   `<template>`, der AdSense-Loader steckt in `<template data-ad-head>` und
   `window.adsbygoogle` ist nicht entstanden. Kein Request an Google vor der
   Zustimmung — derselbe Befund wie lokal in #25, jetzt am ausgelieferten Code.
3. **Die Reservierung stimmt.** Die beiden sichtbaren Container messen je
   310 px (250 px Katalog-Höhe + Zeile „Anzeige" + Innenabstand), die zwei
   Desktop-Container sind mobil auf 0 px ausgeblendet.
4. **CLS 0,000 auf allen fünf Seiten**, keine einzige gezählte Verschiebung.
   Das ist der Zustand *vor* der Einwilligung und damit die Vorher-Messung aus
   §3.2 für den Laborteil.

## 2. `ads.txt` je Domain (§4, letzte zwei Zeilen)

Vollständiger Lauf über alle 23 Portal-Domains in
`adstxt-sweep-2026-10-16.txt`. Ergebnis: 18 × HTTP 200 mit **genau einer**
Google-Zeile und der erwarteten Publisher-ID, 5 × 404 (51, 54, 55, 57, 58),
keine Dublette, keine leere Datei.

Zwei Abweichungen gegenüber `docs/ad-slots-golive.md`:

* **30 `elektrikerportal.com` und 49 `sanitaerfinder.com` stehen auf
  `ads_enabled = nein`, liefern aber eine Google-Zeile aus.** Sie kommt aus
  `ad_settings.ads_txt_extra` (vom Takeover aus der Tenant-DB übernommen), nicht
  aus dem Hauptschalter. Der Satz „fehlt die Zeile oder steht der Schalter auf
  `false` … `/ads.txt` verliert seine Google-Zeile" (§0.1) gilt für diese beiden
  Portale nicht. Eigenes Ticket — für die Freigabeliste ist es kein Hindernis
  (ausgespielt wird dort nichts), für die Notbremse in §5 schon.
* **43 `tierarztportal.com` antwortet auf `/ads.txt` mit 200**, nicht mit 301.
  Die in §0 notierte Weiterleitung auf `pfotencheck.tierarztportal.com` trifft
  diesen Pfad nicht.

## 3. Pilot-Portal: 52 trägt zwei der drei empfohlenen Anker nicht

Die Empfehlung aus §3.1 lautet `company_after_about`, `guide_in_article_1`,
`search_in_feed_3`. Auf 52 geht das so nicht:

* **`guide_in_article_1` ist auf 52 nicht erreichbar.** Das Portal hat keinen
  einzigen veröffentlichten Ratgeber-Artikel; `/ratgeber` listet nur
  `/ratgeber/redaktion`. Der Seitentyp `guide` existiert dort also nicht.
  Gezählt am 16.10.2026 über die Ratgeber-Übersicht aller 21 `sun-v2`-Portale —
  Artikel haben nur: 24 `firmenfreund.de` (13), 27 `bodenlegerfinden.com` (15),
  28 `sanitaerfinden.com` (15), 49 `sanitaerfinder.com` (15),
  50 `apotheke.firmenfreund.de` (15), 30 `elektrikerportal.com` (4). Alle
  übrigen 15 Portale haben keinen.
* **`company_after_about` ist auf 52 schon belegt** (Takeover, Status `active`),
  rendert auf der geprüften Profilseite aber nicht: der Anker verlangt einen
  Beschreibungstext (`requires` im Katalog), und dieses Profil hat keinen.
* **`search_in_feed_3` ist netzweit nicht belegt.** Der Takeover hat
  ausschließlich Profil-Anker angelegt (12 Belegungen, alle `page_type`
  `company`): `company_after_header_card`, `company_after_reviews`,
  `company_after_about`, `company_sidebar_top`, `company_sidebar_sticky` je
  `active` und `company_before_footer` zweimal `paused` (#32).
* **Auto Ads laufen auf 52.** `ad_settings.adsense_auto_ads` ist dort `true`,
  und das Altsystem hat die Position `auto_ads` aktiv — die neue Komponente
  hält sich deswegen zurück, der alte Loader liefert aber. §3.1 verlangt für
  den Pilot ausdrücklich **Auto Ads aus**, weil sie außerhalb jeder
  Reservierung platzieren und den CLS-Vergleich verfälschen.

**Folge: ein sauberer Pilot nach §3.1 braucht ein anderes Portal.** Rein
maschinell passt **49 `sanitaerfinder.com`** am besten: Theme `sun-v2`, 15
Ratgeber-Artikel (also auch `guide`-Seiten), **0** aktive Alt-Slots (kein
Altsystem, keine alten Auto Ads), `ads_enabled` noch `nein` — der Vorher-Zustand
ist dort also noch messbar, und das Einschalten ist ein bewusster Pilotstart
statt einer Fortsetzung. Zwei Dinge fehlen dort: eine Publisher-ID in
`ad_settings` (steht auf `null`) und der AdSense-Site-Status der Domain. Beides
kann nur Enes beibringen. Zweite Wahl mit mehr Verkehr: 28 `sanitaerfinden.com`
— dort läuft aber das Altsystem mit sieben Slots inklusive Auto Ads mit.

## 4. Was hier bewusst nicht gemessen wurde

* **Kein Klick auf „Alle akzeptieren" gegen die Produktion.** Nach der
  Einwilligung würden die vier Fragmente der Profilseite echte
  AdSense-Anfragen mit der Publisher-ID des Portals stellen — Impressionen aus
  einem headless Chrome auf ein Konto, das laut §0 B6 ohnehin unter einer
  Beschränkung steht. Das Risiko für das Konto ist größer als der Messwert wert
  ist; der Fall „Einwilligung mitten im Besuch" ist in #25/#32 lokal gemessen
  (`docs/messungen/ads-einwilligung-2026-10-16.md`,
  `docs/messungen/ads-cls-responsive-2026-10-16.md`). Der Nachher-Zustand auf
  der Produktion gehört an ein echtes Gerät, mit echtem Besuch.
* **Keine Feldwerte.** CrUX und Search Console brauchen ein Konto und 28 Tage
  Rückschau.
* **Kein Lighthouse auf der Produktion.** Der Laborteil ist mit derselben
  Metrik (`PerformanceObserver`, `layout-shift`) gemessen, siehe §1 — für LCP
  und INP ist ein Lighthouse-Lauf nötig, der im Pilot ohnehin auf beiden
  Zuständen laufen muss.
* **Keine Belegung angelegt, kein Schalter umgelegt.** Begründung in
  `docs/ad-slots-golive.md` §3.0.
