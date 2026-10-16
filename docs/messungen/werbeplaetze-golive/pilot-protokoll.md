# Pilot-Protokoll Werbeplätze SUN-V2

Auszufüllen während des Pilots aus `docs/ad-slots-golive.md` §3. Jede Zeile mit
Datum und Kürzel. Belege in denselben Ordner, Namensschema in `README.md`.

Angelegt: 2026-10-16 (Ticket #31). **Noch nicht gelaufen.**

---

## 1. Aufbau (§3.1)

| Feld | Wert |
| --- | --- |
| Portal (Domain, Prod-ID) | ________________ |
| Theme | sun-v2 (Pflicht — ein Theme ohne Anker kann nur Auto Ads) |
| AdSense-Site-Status der Domain | ________________ |
| Startdatum | ________________ |
| Enddatum (Start + 3 volle Tage) | ________________ |
| Durchgeführt von | ________________ |

**Empfehlung aus dem Ticket und §3.1:** `unfallarzt.firmenfreund.de` (Prod-ID
52). Auf der Produktion fährt 52 das Theme `sun-v2` (geprüft 16.10.2026), hat
also Anker im Katalog, und bringt 7 aktive Alt-Slots mit — der Pilot läuft dort
in der Parallelphase, das Altsystem liefert daneben weiter aus. Der Vorschlag
`unfallarzt.net` aus #11 ist keine Domain des Netzes.

### Belegungen — nur diese drei, nichts sonst

| Anker | Seitentyp | Reservierung mobil/Desktop | Format | Status | angelegt am |
| --- | --- | --- | --- | --- | --- |
| `company_after_about` | company | 280 / 250 px | ______ | active | ______ |
| `guide_in_article_1` | guide | 280 / 250 px | ______ | active | ______ |
| `search_in_feed_3` | search | 280 / 250 px | ______ | active | ______ |

**Formatwahl — Befund zu #32 beachten.** `responsive` lieferte in der Messung zu
#25 eine Höhe von 250 px. Auf den drei Pilot-Ankern ist das gedeckt (280/250 px
reserviert; `company_after_header_card` mit derselben Reservierung maß CLS
0,024). Gebrochen wird die Zusage nur auf den Bannern mit 90/100 px Reservierung
— `home_after_hero` maß dort 0,136. Deshalb:

* Die drei Pilot-Anker dürfen `responsive` tragen.
* `home_after_hero` und die übrigen Banner-Anker werden im Pilot **nicht**
  belegt. Sie warten auf #32.
* Wer auf Nummer sicher gehen will, nimmt auf den drei Ankern das feste Format
  (`rectangle_300x250` bzw. `in_feed`/`in_article`) — es hält die Reservierung
  per `height` im `style` ein.

- [ ] Portal benannt und in `docs/ad-slots-golive.md` §1.1 eingetragen
- [ ] drei Anker belegt, Status `active`, Zeitraum offen
- [ ] `ads:resolve <portal> company_after_about all` zeigt genau diese Belegung
- [ ] `ads:resolve <portal> guide_in_article_1 all`
- [ ] `ads:resolve <portal> search_in_feed_3 all`
- [ ] Auto Ads **aus**, kein weiteres Portal freigegeben
- [ ] Startdatum protokolliert

---

## 2. Messung vorher (§3.2) — am Tag vor der Belegung

Beim Messen am Produktionsportal zuerst **beide** Einwilligungsschichten
wegklicken: erst den Google-CMP-Dialog (`button.fc-cta-consent`), danach den
eigenen Cookie-Hinweis (`[data-cookie-banner]`, Knopf „Alle akzeptieren" — der
erste Knopf ist „Einstellungen").

### 2.1 Feld (Search Console › Core Web Vitals / CrUX)

| Metrik | mobil | Desktop | Datum | Beleg |
| --- | --- | --- | --- | --- |
| CLS | ______ | ______ | ______ | ______ |
| LCP | ______ | ______ | ______ | ______ |
| INP | ______ | ______ | ______ | ______ |

### 2.2 Labor (Lighthouse, mobil, drei Läufe je Seitentyp)

Aufbau wie `docs/messungen/werbeplaetze-cls-2026-10-16.md` §1.

| Seitentyp | CLS L1 | CLS L2 | CLS L3 | LCP L1 | LCP L2 | LCP L3 | Beleg |
| --- | --- | --- | --- | --- | --- | --- | --- |
| company | ____ | ____ | ____ | ____ | ____ | ____ | ____ |
| guide | ____ | ____ | ____ | ____ | ____ | ____ | ____ |
| search | ____ | ____ | ____ | ____ | ____ | ____ | ____ |

### 2.3 Konsole und Netzwerk

| Seitentyp | vor Einwilligung | nach Einwilligung | Beleg |
| --- | --- | --- | --- |
| company | ______ | ______ | ______ |
| guide | ______ | ______ | ______ |
| search | ______ | ______ | ______ |

- [ ] Feldwerte vorher notiert
- [ ] Laborwerte vorher, drei Läufe je Seitentyp
- [ ] Konsole sauber: keine CSP-Verstöße, keine Fehler

---

## 3. Messung nachher (§3.3) — nach drei vollen Tagen

Dieselben Quellen, dieselbe Tageszeit, dasselbe Gerät.

| Metrik | vorher | nachher | Abweichung | bestanden? |
| --- | --- | --- | --- | --- |
| CLS Feld mobil | ____ | ____ | ____ | < 0,1 |
| CLS Feld Desktop | ____ | ____ | ____ | < 0,1 |
| CLS Labor | ____ | ____ | ____ | < 0,1 |
| LCP Feld mobil | ____ | ____ | ____ % | ≤ +10 % |
| LCP Feld Desktop | ____ | ____ | ____ % | ≤ +10 % |
| INP | ____ | ____ | ____ | unverändert |

- [ ] CLS unter 0,1 — Feld und Labor, mobil und Desktop
- [ ] LCP nicht schlechter als vorher, Toleranz 10 %
- [ ] keine CSP-Verstöße, keine Konsolenfehler — vor **und** nach der Einwilligung
- [ ] INP unverändert

**Bei Verschlechterung zuerst prüfen, bevor ein Anker entfernt wird:**
Lazy-Loading-Vorlauf (`initSlotAds()`, 200 px, `resources/js/ads.js`) und die
Mindesthöhen (`--ad-mh` / `--ad-mh-lg` aus `config/ad-slots.php`). Ein
ausgeliefertes Format, das höher ist als die Reservierung, ist die übliche
Ursache — siehe #32.

---

## 4. Sichtbarkeit je Anker (§3.4, Akzeptanzkriterium 3 von #11)

Aus dem AdSense-Report je Anzeigenblock, über die drei Pilottage.

| Anker | aktive Betrachtungszeit | Viewability | Klickrate | RPM | Empfehlung |
| --- | --- | --- | --- | --- | --- |
| `company_after_about` | ______ | ______ % | ______ | ______ | ______ |
| `guide_in_article_1` | ______ | ______ % | ______ | ______ | ______ |
| `search_in_feed_3` | ______ | ______ % | ______ | ______ | ______ |

Ein Anker unter **40 %** Viewability wird abgeschaltet, nicht „laufen gelassen".

- [ ] Viewability je belegtem Anker notiert
- [ ] `docs/ad-slots.md` §13.2 ausgefüllt, Empfehlung je Anker aktualisiert

---

## 5. Ergebnis

| Feld | Wert |
| --- | --- |
| Pilot bestanden (alle vier Aussagen aus §3.3) | ja / nein |
| Datum | ______ |
| Kürzel | ______ |
| Freigabe für Rollout Stufe 2 | ja / nein |
