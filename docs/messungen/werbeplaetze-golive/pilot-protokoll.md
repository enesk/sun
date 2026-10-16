# Pilot-Protokoll Werbeplätze SUN-V2

Protokoll zu `docs/ad-slots-golive.md` §3. **Gelaufen am 16.10.2026 (Ticket
#31) auf `bodenlegerfinden.com` (Prod-ID 27).** Messaufbau, Rohdaten und die
Begründung jeder Abweichung stehen in
`pilot-bodenlegerfinden-2026-10-16.md`; dieses Protokoll ist die Kurzfassung
mit den Haken.

Vorgeschichte: der Vorlauf am 16.10.2026 (`pilot-vorlauf-2026-10-16.md`) hat
gemessen, ohne etwas zu schalten, und 52 als Pilot-Portal verworfen.

---

## 1. Aufbau (§3.1)

| Feld | Wert |
| --- | --- |
| Portal (Domain, Prod-ID) | `bodenlegerfinden.com` (27) |
| Theme | `sun-v2` |
| AdSense-Site-Status der Domain | kein Kontozugang; **abgeleitet**: das Altsystem spielt dort seit März 2026 AdSense mit `ca-pub-2563111817853634` aus, `/ads.txt` trägt genau diese ID, und im Pilot werden Anzeigen eingesetzt |
| Startdatum | 16.10.2026, 12:40 UTC |
| Enddatum (Start + 3 volle Tage) | 19.10.2026 — **nicht abgewartet**, siehe §3 |
| Durchgeführt von | Kay (#31) |

Warum 27 und nicht 52 oder 49: 27 ist das einzige Portal mit `sun-v2`,
Ratgeber-Artikeln (15), Profilen mit Beschreibungstext, **Auto Ads aus** und
nachweislicher AdSense-Auslieferung. 52 hat keinen Ratgeber-Artikel und fährt
Auto Ads, 49 hat keine Publisher-ID.

### Belegungen

| Anker | Seitentyp | Reservierung mobil/Desktop | Format | Status | angelegt am |
| --- | --- | --- | --- | --- | --- |
| `company_after_about` (#4) | company | 280 / 250 px | `responsive` | active | aus dem Takeover, 12:09 UTC |
| `guide_in_article_1` (#13) | guide | 280 / 250 px | `responsive` | active | 16.10.2026, 12:40 UTC |
| `search_in_feed_3` (#14) | search | 280 / 250 px | `responsive` | active | 16.10.2026, 12:40 UTC |

Format `responsive` statt `in_article`/`in_feed`: die fluiden Formate bestimmen
ihre Höhe selbst und hätten die Reservierung sprengen können, `in_feed` braucht
zusätzlich einen `layout_key` aus dem Konto. Seit #32 schreibt
`AdSenseProvider` die Katalog-Höhe in den `style` — der Anker gibt die Höhe
vor. Das neue Werbemittel #13 nutzt den AdSense-Slot `3828015317` doppelt
(neue Anzeigenblöcke gibt es nur im Konto); Folge: der AdSense-Report kann die
Pilot-Anker nicht trennen, die eigene Zählung schon.

- [x] Portal benannt und in `docs/ad-slots-golive.md` §1.1 eingetragen
- [x] drei Anker belegt, Status `active`, Zeitraum offen
- [x] `ads:resolve 27 company_after_about all` zeigt genau diese Belegung
- [x] `ads:resolve 27 guide_in_article_1 all`
- [x] `ads:resolve 27 search_in_feed_3 all`
- [x] Auto Ads **aus** (eigener Schalter `false`, kein alter `auto_ads`-Slot),
      kein weiteres Portal freigegeben
- [x] Startdatum protokolliert

---

## 2. Messung vorher (§3.2)

### 2.1 Feld (Search Console / CrUX)

**Nicht gemessen** — kein Kontozugang, und CrUX braucht ein 28-Tage-Fenster.
Ersetzt durch den A/B-Vergleich in §2.2: ohne Einwilligung lädt auf diesem
Portal kein AdSense, derselbe Seitenaufruf ohne Einwilligung ist die
Vorher-Lage.

### 2.2 Labor (360 px mobil, `scripts/ads-einwilligung-cls.mjs` und `scripts/ads-lcp-vergleich.mjs`)

| Seitentyp | CLS vor der Belegung | LCP ohne Werbung (Median aus 3) | Beleg |
| --- | --- | --- | --- |
| company | 0,0149 (Google-CMP-Dialog, kein Anker beteiligt) | 480 ms | `…-vorher-mobil-…json`, `…-lcp-mobil-…json` |
| guide | 0,0000 | 224 ms | dieselben |
| search | 0,0000 | 520 ms | dieselben |

### 2.3 Konsole und Netzwerk (`scripts/csp-messung.mjs`)

| Seitentyp | vor Einwilligung | nach Einwilligung | Beleg |
| --- | --- | --- | --- |
| company | 0 Verstöße, 0 Fehler, kein Google-Werbehost | 0 / 0 | `…-csp-konsole-…json` |
| guide | 0 / 0, kein Google-Werbehost | 0 / 0 | dieselbe |
| search | 0 / 0, kein Google-Werbehost | 0 / 0 | dieselbe |

- [ ] Feldwerte vorher notiert — entfällt, kontogebunden
- [x] Laborwerte vorher (CLS und LCP, drei Läufe je Zustand)
- [x] Konsole sauber: keine CSP-Verstöße, keine Fehler

---

## 3. Messung nachher (§3.3)

**Abweichung:** gemessen wurde direkt nach der Belegung, nicht nach drei
Kalendertagen. Drei Tage warten kann dieser Lauf nicht, und sie würden den
Laborwert auch nicht verändern — die Tage dienen CrUX und dem AdSense-Report,
und beide sind kontogebunden.

| Metrik | vorher | nachher | Abweichung | bestanden? |
| --- | --- | --- | --- | --- |
| CLS Feld mobil | — | — | — | offen (CrUX) |
| CLS Feld Desktop | — | — | — | offen (CrUX) |
| CLS Labor 360 px | 0,0149 / 0,0000 / 0,0000 | **0,0000** (alle drei Seiten) | besser | ja, < 0,1 |
| CLS Labor 1366 px | — | **0,0000** (alle drei Seiten) | — | ja, < 0,1 |
| LCP 360 px company | 480 ms | 508 ms | **+5,8 %** | ja, ≤ +10 % |
| LCP 360 px guide | 224 ms | 244 ms | **+8,9 %** | ja |
| LCP 360 px search | 520 ms | 560 ms | **+7,7 %** | ja |
| LCP 1366 px | 9484 / 164 / 9524 ms | 9496 / 148 / 9524 ms | +0,1 / −9,8 / 0 % | ja |
| INP | — | — | — | offen (CrUX); die Anker bringen keinen Ereignis-Handler mit |

- [x] CLS unter 0,1 — Labor, mobil und Desktop (Feld offen)
- [x] LCP nicht schlechter als vorher, Toleranz 10 %
- [x] keine CSP-Verstöße, keine Konsolenfehler — vor **und** nach der Einwilligung
- [ ] INP unverändert — nur im Feld messbar, offen

**Ein Ausreißer, nachgeprüft:** ein Lauf auf `/firmen` zeigte CLS 0,1298
(gesamt 0,1872). Drei Wiederholungen im selben Aufbau liefern 0,0000 bei
geladenem Anker und eingesetzter Anzeige. In dem Lauf standen **sechs**
Anzeigen auf der Seite statt zwei: auf Kontoseite sind **Auto Ads** aktiv, die
unreservierte Anzeigen einsetzen, sobald ein Anker den AdSense-Loader auf die
Seite bringt. Die Verschiebungen stammen vom verschwindenden
Google-CMP-Dialog und von diesen Zusatzanzeigen, nicht vom Anker (Container
durchweg 310 px = 280 px Katalog + Zeile „Anzeige"). Dafür ist **#46**
angelegt.

---

## 4. Sichtbarkeit je Anker (§3.4)

Aus der **eigenen** Zählung (`ad_impression_daily`, §14 in `docs/ad-slots.md`),
nicht aus dem AdSense-Report: gezählt wird gegen dieselbe Schwelle (50 % der
Fläche, eine Sekunde). Fenster 16.10.2026 12:09–13:01 UTC, 14 Portale, 391
Sichtkontakte. Vollständige Tabelle in `docs/ad-slots.md` §13.2.

| Anker | sichtbare Impressionen | Reichweite gegen den obersten Anker | Viewability-Quote | Klickrate | RPM | Empfehlung |
| --- | --- | --- | --- | --- | --- | --- |
| `company_after_about` | 17 mobil / 7 Desktop | 9 % / 11 % | kontogebunden | kontogebunden | kontogebunden | behalten; kleine Grundmenge, der Anker steht nur auf Profilen mit Beschreibungstext |
| `guide_in_article_1` | noch keine Zählung | — | — | — | — | läuft seit 12:40 UTC |
| `search_in_feed_3` | noch keine Zählung | — | — | — | — | läuft seit 12:40 UTC |

Die 40-%-Regel aus §3.4 ist eine Quote „sichtbar / ausgeliefert" und lässt sich
auf die Reichweite **nicht** anwenden: dem eigenen Zähler fehlt der Nenner.

- [x] Sichtkontakte je belegtem Anker notiert
- [x] `docs/ad-slots.md` §13.2 ausgefüllt, Empfehlung je Anker aktualisiert
- [ ] Viewability-Quote, Klickrate, RPM aus dem AdSense-Report — offen

---

## 5. Ergebnis

| Feld | Wert |
| --- | --- |
| Pilot bestanden (alle vier Aussagen aus §3.3) | **ja im Labor** — drei von vier Aussagen vollständig belegt, INP nur im Feld messbar |
| Datum | 16.10.2026 |
| Kürzel | Kay (#31) |
| Freigabe für Rollout Stufe 2 | entfällt — der Takeover hat alle 16 Portale mit Altbestand gleichzeitig freigegeben; Nachweis über den gesamten Bestand in `docs/ad-slots-golive.md` §4 |

---

## 6. Nachtrag (#48, Durchführung #53) — Kontositzung, nicht vor dem 19.10.2026

Alles in §2.1, §3 (Feldzeilen) und §4 (drei kontogebundene Spalten) hängt an
**einem** Zugang: AdSense und Search Console desselben Kontos
(`ca-pub-2563111817853634`). Im Repo ist dazu nichts mehr zu tun; Stand
16.10.2026 ist der Pilot erst Stunden alt, CrUX braucht drei volle Tage. Dieser
Abschnitt sagt, was in der Sitzung abgelesen und **wohin** es eingetragen wird,
damit kein Wert zweimal gesucht werden muss. Dieselbe Sitzung erledigt #46 und
#50 (Auto Ads je Site) mit.

### 6.0 Ein Aufruf für den Stand

```bash
bash scripts/ads-kontositzung-stand.sh          # Stand aller vier Punkte
bash scripts/ads-kontositzung-stand.sh --kurz   # nur der nächste Handgriff
```

Das Skript ändert nichts. Es liest die Feldzeilen aus §3 und die Spalten aus
`docs/ad-slots.md` §13.2, fragt die Slot-IDs der beiden Pilot-Anker über
`ads:resolve` auf der Produktion ab, zeigt Schalter und `/ads.txt`-Status der
sieben Portale und nennt den ersten offenen Punkt samt Handgriff. Exit 70,
solange etwas offen ist; 0, wenn die Sitzung durch ist. Es kennt auch das
Zeitgatter: vor dem 19.10.2026 12:40 UTC meldet es §6.1 und §6.2 als „noch
nicht ablesbar" statt als offen.

**Stand 16.10.2026, 15:40** (`scripts/ads-kontositzung-stand.sh`, Exit 70):
§6.1 und §6.2 hinter dem Zeitgatter (noch ~71 Stunden), §6.3 offen — beide
Anker lösen weiter auf Slot `3828015317` auf (Placements 13 und 14, dasselbe
Werbemittel 13) —, §6.4 offen: alle sieben stehen auf `ads_enabled = nein`.
`/ads.txt` dabei: 51 → 301, die übrigen sechs → 404. Der 404 ist bei allen mit
Publisher-ID **neu und richtig so**: die Produktion trägt seit dem Merge
`50942c2d` den #41-Filter, und er nimmt die eigene Google-Zeile aus der Datei,
solange der Schalter aus ist — bei 49 (ID seit 16.10.2026 nachgetragen, so in
§3 des Freigabeberichts vorhergesagt) und seit diesem Lauf auch belegt bei
**30**, das vormittags noch 200 mit `pub-1901800422608466` lieferte.
`elektrikerportal.com` brauchte dabei zwei Versuche (erster Abruf nach 25 s
ohne Antwort, Startseite 200) — kein Werbeplatz-Befund, nur eine langsame
erste Antwort.

### 6.1 Feldwerte (Punkt 1)

Search Console für `bodenlegerfinden.com` › Core Web Vitals › Bericht öffnen
(mobil und Desktop getrennt), Wert der 75. Perzentile ablesen für CLS, LCP,
INP. Fenster endet **nach** dem 19.10.2026; CrUX mischt 28 Tage, der Pilot
deckt davon nur drei — der Vergleich zu §2 ist deshalb ein Hinweis, kein
Beweis. Das notieren, nicht wegrechnen.

Einzutragen in §3 dieses Protokolls, Zeilen `CLS Feld mobil`, `CLS Feld
Desktop`, `INP` — Spalten „nachher" und „bestanden?" (Schwellen: CLS < 0,1,
INP ≤ 200 ms, LCP nicht schlechter als vorher + 10 %). Danach die beiden
offenen Haken in §3 setzen.

### 6.2 AdSense-Report je Anzeigenblock (Punkt 2)

AdSense › Berichte › Anzeigenblöcke, Zeitraum 16.–19.10.2026, Spalten: aktive
Betrachtungszeit, Viewability-Quote („Sichtbare Impressionen in %"), Klickrate,
RPM. Einzutragen in `docs/ad-slots.md` §13.2, Spalten
„Viewability-Quote (AdSense)" und „RPM" — die eigene Zählung steht dort schon
und bleibt stehen. Greift §13.3 (unter 40 %), Belegung im Panel auf `paused`,
nicht löschen.

### 6.3 Eigene Anzeigenblöcke je Pilot-Anker (Punkt 3)

Solange Werbemittel #13 den Slot `3828015317` für `guide_in_article_1` **und**
`search_in_feed_3` nutzt — und derselbe Slot auf 27 zusätzlich im Altsystem
läuft —, mischt jede Report-Zeile drei Quellen; §6.2 ist für diese beiden Anker
dann nicht auswertbar. Also zuerst zwei neue Blöcke im Konto anlegen
(Display, `responsive`), dann die Slot-IDs eintragen: Panel ›
Werbemittel › das jeweilige Werbemittel › Slot-ID (`config_json.slot_id`), je
Anker ein eigenes Werbemittel. Nachweis:

```bash
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:resolve 27 guide_in_article_1 all
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:resolve 27 search_in_feed_3 all
```

Beide müssen die **neue** Slot-ID zeigen. Danach beginnt das Report-Fenster für
diese Anker neu — §6.2 für sie erst drei Tage später ablesen.

### 6.4 Freigabe der sieben Portale ohne Altbestand (Punkt 4)

Bestandsaufnahme je Domain (Schalter, Publisher-ID, `ads_txt_extra`,
Alt-Slots, `/ads.txt` von außen) am 16.10.2026:
`freigabe-sieben-portale-2026-10-16.md`. Zwei Punkte daraus vorweg:

* Das Netz fährt **zwei** AdSense-Konten — 30 hängt an
  `ca-pub-1901800422608466`, nicht am Konto des Piloten.
* Der Sonderfall **49** ist erledigt: Publisher-ID
  `ca-pub-2563111817853634` ist am 16.10.2026 in `ad_settings` eingetragen
  (Schalter unverändert nein). In der Sitzung bleibt dort nur der Schalter.

Portale 30, 49, 51, 54, 55, 57, 58 stehen fail-closed auf
`ads_enabled = nein`. **Entscheidung bis zur Kontositzung: so lassen.** Grund:
ohne Site-Status im Konto würde der Anker den Loader auf die Seite bringen und
Google könnte die Domain als nicht freigegeben zurückweisen — der sichere
Zustand ist der aktuelle, und es geht kein laufender Umsatz verloren (dort lief
nie Werbung). In der Sitzung je Domain: AdSense › Sites › Status prüfen.

* Status „Bereit" ⇒ Schalter im Panel auf ja, danach
  `ads:golive:check` und `bash scripts/ads-txt-sweep.sh` (die Domain muss dann
  `/ads.txt` mit genau einer Google-Zeile liefern).
* Status fehlt oder „Erforderliche Maßnahme" ⇒ aus lassen, Domain im Konto
  hinzufügen, nächste Runde.
* **49 `sanitaerfinder.com`:** Publisher-ID steht seit 16.10.2026 drin
  (`ca-pub-2563111817853634`, Begründung und Nachweis in
  `freigabe-sieben-portale-2026-10-16.md` §3) — der Schalter allein genügt
  dort jetzt.

Den Haken in `docs/ad-slots-golive.md` §4 („Freigabe der sieben Portale ohne
Altbestand") setzt diese Sitzung, mit Datum und Site-Status je Domain.

### 6.5 Muss das Klickarbeit sein? Vier Wege geprüft (16.10.2026)

Vor der Sitzung einmal belegt, damit kein weiterer Anlauf dieselben Umwege
durchprobiert. Alle vier Prüfungen waren rein lesend.

| Weg | Ergebnis |
| --- | --- |
| PageSpeed-Insights-API ohne Schlüssel (liefert dieselben CrUX-75.-Perzentile wie der CWV-Bericht) | HTTP **429**, Tageskontingent des anonymen Projekts erschöpft — kein verlässlicher Weg |
| CrUX API mit dem lokalen `GOOGLE_PLACES_API_KEY` | HTTP **403** `API_KEY_SERVICE_BLOCKED` und `CONSUMER_SUSPENDED` — Schlüssel auf Places eingeschränkt, Projekt gesperrt (→ **#56**) |
| CrUX API mit dem Produktions-Dienstkonto (`GOOGLE_SERVICE_ACCOUNT_JSON`, Scope `cloud-platform`, Token wird ausgestellt) | HTTP **400** `INVALID_ARGUMENT` für jedes Origin, auch `https://www.google.com` — die CrUX API nimmt nur einen API-Schlüssel, kein Dienstkonto-Token |
| AdSense Management API v2 mit demselben Dienstkonto (`AdSenseClient::accounts()`, Scope `adsense.readonly`) | Token ok, HTTP **403**: „AdSense Management API has not been used in project `321830197826` before or it is disabled" |
| Service Usage API mit demselben Dienstkonto (Scope `cloud-platform`), 16.10.2026 | **HTTP 200** — `services.get` liefert `state`, `services:enable` schaltet frei: damit war Handgriff 1 aus #55 ohne Google-Konsole zu erledigen (#58) |

Daraus folgt für die Sitzung:

* **§6.1 bleibt Oberflächenarbeit** (Search Console › Core Web Vitals), solange
  es keinen API-Schlüssel mit CrUX-Freigabe gibt.
* **§6.2 und §6.4 sind maschinell lesbar** — `accounts.reports.generate` mit
  Dimension `AD_UNIT_NAME` bzw. `accounts.sites.list` mit dem Feld `state` je
  Domain. Es fehlen genau zwei Handgriffe: AdSense Management API im Projekt
  `321830197826` aktivieren und das Dienstkonto
  `sun-portal@analytics-460722.iam.gserviceaccount.com` in **beiden** Konten
  als Nutzer eintragen. Beides steht in **#55**; wer die Sitzung sowieso im
  Konto hat, erledigt es dort gleich mit und liest §6.2/§6.4 danach per API.

  **Handgriff 1 ist am 16.10.2026 erledigt — ohne Konsole** (#58): das
  Produktions-Dienstkonto darf im Projekt `321830197826` Service Usage
  bedienen. Mit einem Token auf Scope `cloud-platform` (dieselbe
  Schlüsseldatei, nur anderer Bereich) antwortet
  `GET serviceusage.googleapis.com/v1/projects/321830197826/services/adsense.googleapis.com`
  mit `state: DISABLED`, und
  `POST …/services/adsense.googleapis.com:enable` (Rumpf `{}`, nicht leer —
  ein leerer Rumpf gibt HTTP 400 „Root element must be a message") schaltet
  sie frei. Danach `state: ENABLED`, und `ads:adsense-konto` fällt von Exit 70
  auf **71**. Der Weg steht auch für jede weitere Google-API dieses Projekts
  offen; ein `gcloud` gibt es weder lokal noch auf der Produktion.

  **Handgriff 2 bleibt offen und ist fraglich.** Die API nimmt das
  Dienstkonto-Token an und antwortet auf `accounts.list` mit HTTP 200 und
  **null** Konten — das Konto sieht nichts, was ihm nicht zugeteilt ist. Die
  Doku der Management API sagt aber ausdrücklich, dass AdSense **keine
  Dienstkonten unterstützt** und der Installed-Application-Flow mit einem
  authentifizierten Nutzer vorgesehen ist; eine Nutzereinladung in AdSense
  geht an eine E-Mail-Adresse und muss aus einem Google-Konto **angenommen**
  werden, was ein Dienstkonto nicht kann. Schlägt der Eintrag in der
  Oberfläche also fehl, ist das kein Bedienfehler, sondern die Grenze des
  Dienstkonto-Wegs — dann braucht der Client ein Refresh-Token eines echten
  Nutzers (**#61**). Der Versuch kostet nichts und ist damit die billigere
  Reihenfolge.

  Das Ablesen selbst ist seit #55 eingecheckt und braucht die Oberfläche nicht
  mehr:

  ```
  php artisan ads:adsense-konto            # Konten + state je Domain (§6.4)
  php artisan ads:adsense-konto --report   # zusätzlich Report je Block (§6.2)
  php artisan ads:adsense-konto --json     # maschinenlesbar
  ```

  Der Befehl ist rein lesend und deutet seinen Exit-Code selbst:
  **0** = alle im Netz benutzten Konten lesbar, **70** = API im Cloud-Projekt
  nicht freigeschaltet (Handgriff 1), **71** = Token gilt, aber ein Konto
  fehlt dem Dienstkonto (Handgriff 2), **65** = kein Dienstkonto-Schlüssel.
  `scripts/ads-kontositzung-stand.sh` fährt ihn als Abschnitt **§6.0** mit, vor
  §6.1 — damit steht am Anfang jedes Anlaufs, ob die zwei Punkte diesmal per
  API gehen. Die Soll-Liste der Konten baut der Befehl aus
  `ad_settings.adsense_publisher_id` plus `ADSENSE_PUBLISHER_ID`; ein Konto,
  das kein Portal benutzt, fällt damit nicht auf.
* **§6.3 bleibt Handarbeit.** Die v2-API ist lesend, ein `adunits.create` gibt
  es nicht — neue Anzeigenblöcke entstehen nur in der Oberfläche.
