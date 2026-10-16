# Go-Live der Werbeplätze SUN-V2 — Checkliste, Pilot, Rollout, Abnahme

Status: **Maschinenanteil erledigt (16.10.2026). Offen sind §3 (Pilot, drei Tage),
§4 (Rollout) und §6 (Abnahme) — alle drei brauchen die Produktion, ein echtes
Gerät und das AdSense-Konto.**
Nachtrag 16.10.2026 (#31): §1.1 ist mit den aus der Produktion gelesenen Spalten
Theme und Altbestand vorbelegt, die Belegablage
`docs/messungen/werbeplaetze-golive/` steht samt Protokoll. Der Start hängt
weiterhin an B1 — das Modul ist nicht eingecheckt, auf der Produktion liegt kein
Code. Ohne Deploy nach §2 ist weder ein Prüflauf noch der Pilot möglich.
Ticket: #11 (Maschinenanteil: Security-Review, Prüfwerkzeug, diese Checkliste,
Slot-Empfehlung — erledigt; Durchführung und Abnahme: Enes)
Stand: 2026-10-16, Arbeitskopie — **das Modul ist noch nicht eingecheckt**
Architektur, Katalog und Begründungen: `docs/ad-slots.md`
Sicherheitsseite: `docs/ad-slots-security-review.md`
Pilot-Empfehlung je Anker: `docs/ad-slots.md` §13

Reihenfolge ist verbindlich: §0 und §1 sind Vorbedingungen, §2 der Deploy, §3 der
Pilot, §4 der Rollout über `ads_enabled`, §5 die Notbremse, §6 die Abnahme. Jede
Zeile wird mit Datum und Kürzel abgehakt (`[x] 2026-10-20 EN`) und gilt nur mit
Beleg (Befehlsausgabe, Screenshot, Link auf den AdSense-Report) als erledigt.
Belege gehören nach `docs/messungen/werbeplaetze-golive/`.

Produktion: `ssh sun` (man landet als root), Installation
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, **handgepflegt, kein Deployer**
(Ablauf `docs/messungen/produktionsumgebung-anleitung.md`). `php dep deploy` und
alle `provision:*`-Tasks dürfen dort nie laufen. Artisan immer als Eigentümer und
mit der PHP-Version des Schedulers:

```bash
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
sudo -u sanitaerfinden /usr/bin/php8.4 artisan <befehl>
```

---

## 0. Stand am 16.10.2026 — was den Start heute verhindert

| Nr. | Blocker | Wer | Stand |
| --- | --- | --- | --- |
| B1 | Das Modul ist nicht eingecheckt — `app/Ads/**`, die vier Migrationen, `config/ad-slots.php`, die Filament-Flächen und die Brücke liegen nur in der Arbeitskopie des Zweigs `feature/portale-blockiert` | Entwicklung | offen |
| B2 | Vier Migrationen der **Central**-DB stehen aus (`ad_units`, `ad_placements`, `ad_placement_tenant`, `ad_settings` plus zwei Nachträge) | Deploy, §2 | offen |
| B3 | `ads:legacy-takeover` ist auf der Produktion nicht gelaufen. Bis dahin verlieren 13 Domains beim Deploy ihre `ads.txt` (Security-Review S2) | Deploy, §2 | offen |
| B4 | Keine Freigabeliste von Enes — welches Portal `ads_enabled` bekommen soll, steht nirgends. Der Seeder setzt überall `false`, der Takeover setzt für jedes Portal mit Altbestand `true` (§1) | Enes | **teilweise**: Theme und Altbestand je Portal am 16.10.2026 aus der Produktion eingetragen (§1.1); offen bleiben AdSense-Status und die Entscheidung je Portal |
| B5 | Pilot-Portal nicht benannt. Der Ticketvorschlag `unfallarzt.net` ist **keine** Portal-Domain des Netzes; die nächstliegende ist `unfallarzt.firmenfreund.de` (Prod-ID 52). Geprüft am 16.10.2026: 52 fährt auf der Produktion `sun-v2` und ist als Pilot technisch geeignet | Enes, §3 | offen |
| B6 | AdSense-Site-Status je Portal unbekannt (Kontobeschränkung). Ohne ihn ist eine Freigabe geraten | Enes, §4 | offen |
| B7 | Netzwerk-Mitschnitt vor/nach Einwilligung (#25) und der Datenschutzabschnitt „Werbung mit Google AdSense" (#26) sind nicht abgeschlossen. Beides muss **vor** der ersten echten Auslieferung stehen | #25, #26 | **erledigt** (beide Tickets abgeschlossen) |
| B8 | Aus der Messung zu #25 offen: #32 (Format `responsive` reißt auf 90/100-px-Bannern CLS 0,136) und #29 (Datenschutzabschnitt deckt den HtmlProvider nicht). #32 trifft die drei Pilot-Anker **nicht** (280/250 px reserviert), wohl aber `home_after_hero` und die übrigen Banner — die bleiben bis #32 unbelegt | #29, #32 | offen |

Was dagegen maschinell steht: Security-Review ohne offene Punkte
(`docs/ad-slots-security-review.md`), CLS-Messung mit allen 32 Ankern belegt
(`docs/messungen/werbeplaetze-cls-2026-10-16.md`, CLS 0.000 auf allen sechs
Seitentypen), 40 Tests grün, PHPStan ohne Befund, und das Prüfwerkzeug
`ads:golive:check`.

### 0.1 Der Rollout-Schalter

`ad_settings.ads_enabled` je Portal, **fail-closed**: fehlt die Zeile oder steht
der Schalter auf `false`, spielt der Resolver in diesem Portal nichts aus — egal
welche Belegungen netzweit aktiv sind — und `/ads.txt` verliert seine
Google-Zeile. Gepflegt wird er im Admin-Panel unter *Einstellungen › Werbung*
(Portal oben wählen). Änderungen wirken sofort: das Speichern vergisst über
`AdCacheInvalidator` die aufgelösten Karten **und** die gecachte `ads.txt`
dieses Portals. Kein Deployment, kein `cache:clear`.

Zwei weitere Schalter daneben, beide ohne Wirkung ohne den Hauptschalter:
`adsense_auto_ads` (Auto Ads, #18) und `adsense_publisher_id` (leer = Netz-Vorgabe
aus `config('ad-slots.adsense.publisher_id')`).

---

## 1. Vorbedingungen

### 1.1 Freigabeliste (Akzeptanzkriterium 4)

Enes trägt hier ein, welches Portal Werbung ausspielen darf. Maßgeblich ist der
**AdSense-Site-Status** je Domain: wegen der Kontobeschränkung ist eine Domain,
die bei Google nicht freigeschaltet ist, auch hier nicht freizugeben — sie würde
leere Anker und eine `ads.txt` ohne Wirkung zeigen.

Spalte „Theme" und „Alt-Slots aktiv" sind am 16.10.2026 direkt aus der
**Produktion** gelesen (Ticket #31) und müssen nicht mehr geraten werden:
21 Portale fahren `sun-v2`, nur 43 und 51 fahren `default` und haben damit keine
Anker im Katalog — sie können ausschließlich Auto Ads ausspielen (Entscheidung
#16). Offen bleiben die drei Spalten, die nur Enes beantworten kann:
AdSense-Status, `ads_enabled`, Auto Ads.

Achtung: der Prüflauf in der **lokalen** Umgebung zeigt eine andere Theme-Spalte
(dort steht bei den meisten Portalen `default`). Die lokale Tenant-DB ist
verzogen; maßgeblich ist allein der Lauf auf der Produktion.

| Prod-ID | Domain | Theme | Alt-Slots aktiv | AdSense-Status | `ads_enabled` | Auto Ads | abgehakt |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 24 | firmenfreund.de | sun-v2 | 7 | | | | |
| 27 | bodenlegerfinden.com | sun-v2 | 6 | | | | |
| 28 | sanitaerfinden.com | sun-v2 | 7 | | | | |
| 29 | fahrschulefinder.de | sun-v2 | 7 | | | | |
| 30 | elektrikerportal.com | sun-v2 | 0 | | | | |
| 33 | malerfinder.de | sun-v2 | 7 | | | | |
| 37 | geruestbauer.gmbh | sun-v2 | 7 | | | | |
| 38 | metallbauer.io | sun-v2 | 7 | | | | |
| 43 | tierarztportal.com | default, **nicht ausgeliefert** (#16) | 7 | — | nein | nein | |
| 44 | kfzwerkstatt.io | sun-v2 | 7 | | | | |
| 45 | findegutachter.de | sun-v2 | 7 | | | | |
| 46 | fliesenleger.io | sun-v2 | 7 | | | | |
| 47 | mjet.net | sun-v2 | 7 | | | | |
| 49 | sanitaerfinder.com | sun-v2 | 0 | | | | |
| 50 | apotheke.firmenfreund.de | sun-v2 | 7 | | | | |
| 51 | firmenfreund.net | default, **nicht ausgeliefert** (#16) | 0 | — | nein | nein | |
| 52 | unfallarzt.firmenfreund.de | sun-v2 | 7 | | | | |
| 53 | zahnarzt.firmenfreund.de | sun-v2 | 7 | | | | |
| 54 | klempner.firmenfreund.de | sun-v2 | 0 | | | | |
| 55 | energieberaterportal.net | sun-v2 | 0 | | | | |
| 56 | arztfinder.firmenfreund.de | sun-v2 | 7 | | | | |
| 57 | speditionportal.com | sun-v2 | 0 | | | | |
| 58 | schluesseldienstportal.com | sun-v2 | 0 | | | | |

Zwei Dinge, die beim Ausfüllen auffallen sollen:

1. **`elektrikerportal.com` (30) ist ein Sonderfall — die Falle ist aber
   entschärft.** Am 14.09.2026 wurden dort auf Wunsch alle sieben
   Alt-Anzeigenplätze abgeschaltet („erstmal keine AdSense"). Nachgezählt am
   16.10.2026: das Portal hat **0 aktive** Alt-Slots. `ads:legacy-takeover`
   schaltet nur frei, wenn aus dem Portal etwas übernommen wurde
   (`enableAds: $enableAds && ($tenantTaken > 0 || $autoAds)` in
   `LegacyAdTakeover::plan()`, inaktive Slots werden übersprungen) — 30 bleibt
   also von selbst auf `nein`. Der Lauf ist trotzdem gegenzuprüfen: ändert sich
   bis zum Deploy etwas am Altbestand, greift die Falle wieder.
2. **Der Takeover ist nicht neutral.** `ads:legacy-takeover` setzt `ads_enabled`
   für **jedes** Portal, aus dem etwas übernommen wurde — sonst wäre nach dem
   Umstieg netzweit Werbung aus, obwohl das Altsystem gerade ausliefert. Die
   Freigabeliste wird deshalb **nach** dem Takeover durchgesetzt, indem die
   nicht gewünschten Portale im Panel wieder abgeschaltet werden. Wer das
   umgekehrt will, nimmt `--keep-disabled` (§2, Schritt 3).

   Nach dem Stand vom 16.10.2026 trifft das **16 Portale** mit Altbestand
   (24, 27, 28, 29, 33, 37, 38, 43, 44, 45, 46, 47, 50, 52, 53, 56). Die
   übrigen sieben (30, 49, 51, 54, 55, 57, 58) haben 0 aktive Alt-Slots und
   bleiben nach dem Takeover auf `nein` — sie brauchen eine bewusste Freigabe
   im Panel, wenn sie Werbung ausspielen sollen.

- [ ] Liste vollständig ausgefüllt und gegen den AdSense-Site-Status abgeglichen
- [ ] `ads:golive:check` zeigt genau die Portale der Liste mit `ads_enabled = ja`

### 1.2 Rechtstext und Einwilligungsnachweis

- [ ] #26 abgeschlossen: Abschnitt „Werbung mit Google AdSense" steht in der
      Datenschutzerklärung **jedes** freigegebenen Portals (eine Stelle,
      `App\Constants\TenantLegalDefaults`, Nachzug über das Backfill-Kommando)
- [ ] #25 abgeschlossen: Netzwerk-Mitschnitt vor/nach Einwilligung in
      `docs/ad-slots.md` 9.5 eingetragen, kein Request an Google vor der Zustimmung

### 1.3 Umgebung

- [ ] `CSP_MODE=enforce` in der Produktions-`.env`
- [ ] `ADS_EDITOR_ORIGIN` auf die zentrale Domain (`https://sanitaerfinden.com`)
      oder leer, dann gilt `APP_URL`
- [ ] `ADSENSE_PUBLISHER_ID` leer lassen, solange es zwei AdSense-Konten im Netz
      gibt — die ID gehört je Portal in `ad_settings`
- [ ] `ADS_CONSENT_ON_DENIED=collapse`
- [ ] `php artisan ads:golive:check` → die globalen Zeilen alle OK

---

## 2. Deploy (B1–B3)

Reihenfolge ist wichtig: der Takeover muss **vor** dem ersten Aufruf von
`/ads.txt` aus der neuen Route gelaufen sein.

```bash
# 1. Code und Abhängigkeiten
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
sudo -u sanitaerfinden git pull
sudo -u sanitaerfinden composer install --no-dev --optimize-autoloader
sudo -u sanitaerfinden npm ci && sudo -u sanitaerfinden npm run build

# 2. Migrationen der CENTRAL-DB (keine Tenant-Migration in diesem Modul)
sudo -u sanitaerfinden /usr/bin/php8.4 artisan migrate --force
sudo -u sanitaerfinden /usr/bin/php8.4 artisan db:seed --class=AdSettingSeeder --force

# 3. Altbestand übernehmen — erst trocken, dann echt
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:legacy-takeover --dry-run
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:legacy-takeover

# 4. Caches neu, Konfiguration zuletzt
sudo -u sanitaerfinden /usr/bin/php8.4 artisan view:clear
sudo -u sanitaerfinden /usr/bin/php8.4 artisan config:cache
sudo -u sanitaerfinden /usr/bin/php8.4 artisan route:cache

# 5. Nachweis
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:golive:check
```

- [ ] Schritt 2: vier Tabellen angelegt, 23 Zeilen in `ad_settings`
- [ ] Schritt 3 trocken: die Tabelle „Nicht uebernommen" gelesen — sie ist die
      Abnahmeliste aus `docs/ad-slots.md` 11.1
- [ ] Schritt 5: **kein FAIL**. Insbesondere keine Zeile
      „ads.txt verliert … Zeile(n) aus der Tenant-DB" — das ist S2 und bedeutet,
      dass Schritt 3 fehlt oder unvollständig war
- [ ] `curl -s https://<domain>/ads.txt` für jedes freigegebene Portal: HTTP 200,
      die Google-Zeile mit der richtigen Publisher-ID, keine doppelten Zeilen
- [ ] Freigabeliste aus §1.1 durchgesetzt (nicht gewünschte Portale im Panel
      wieder auf `ads_enabled = aus`)

Nach Schritt 3 laufen Alt- und Neusystem **parallel**: das Altsystem liefert
weiter aus, die neuen Anker bleiben leer, solange keine Belegung für sie
existiert. `<x-sun.auto-ads />` hält sich zurück, solange der Alt-Slot `auto_ads`
im Portal aktiv ist — es gibt also nie zwei Loader.

---

## 3. Pilot — ein Portal, drei Tage (Akzeptanzkriterium 2)

### 3.1 Aufbau

* **Portal:** ein ausgeliefertes `sun-v2`-Portal mit hohem Verkehr und
  freigeschaltetem AdSense-Site-Status. Der Ticketvorschlag `unfallarzt.net`
  existiert nicht als Portal-Domain; nächstliegend ist
  `unfallarzt.firmenfreund.de` (52) — geprüft am 16.10.2026: 52 fährt auf der
  Produktion `sun-v2`, hat also Anker im Katalog, und bringt 7 aktive
  Alt-Slots mit (Parallelphase, der Takeover schaltet es selbst frei).
  Endgültige Wahl: Enes (B5).
* **Belegungen:** zwei bis drei **Content**-Anker, keine Seitenspalte, kein
  Haftband. Empfehlung und Begründung in `docs/ad-slots.md` §13.1 — für den
  Pilot: `company_after_about`, `guide_in_article_1`, `search_in_feed_3`.
* **Dauer:** drei vollständige Tage, damit CrUX und der AdSense-Report genug
  Daten haben. Keine Änderung an den Belegungen während des Laufs.
* **Alles andere bleibt aus:** Auto Ads nicht einschalten (sie platzieren
  außerhalb jeder Reservierung und verfälschen den CLS-Vergleich), kein weiteres
  Portal freigeben.
* **Banner-Anker bleiben unbelegt, solange #32 offen ist.** Die drei
  Pilot-Anker reservieren 280 px mobil / 250 px Desktop und sind von #32 nicht
  betroffen — `responsive` lieferte in der Messung zu #25 genau 250 px, und
  `company_after_header_card` mit derselben Reservierung maß CLS 0,024. Gerissen
  wird die Zusage nur auf den Ankern mit 90/100 px Reservierung, allen voran
  `home_after_hero` (gemessen 0,136). Die dürfen im Pilot nicht belegt werden,
  sonst scheitert §3.3 Aussage 1 schon am Aufbau.

Das auszufüllende Protokoll liegt unter
`docs/messungen/werbeplaetze-golive/pilot-protokoll.md`, die Ablageordnung für
die Belege daneben in `README.md`.

- [ ] Portal benannt und in §1.1 eingetragen
- [ ] zwei bis drei Content-Anker belegt, Status `active`, Zeitraum offen
- [ ] `ads:resolve <portal> company_after_about all` zeigt genau diese Belegung
      (Argumente: Portal, Anker-Schlüssel, Gerät)
- [ ] Startdatum protokolliert

### 3.2 Messung vorher (am Tag vor der Belegung)

| Messgröße | Quelle | Festhalten |
| --- | --- | --- |
| CLS, LCP, INP — Feld | Search Console › Core Web Vitals, CrUX, mobil **und** Desktop | Screenshot + Zahlen |
| CLS, LCP — Labor | Lighthouse, Aufbau wie in `docs/messungen/werbeplaetze-cls-2026-10-16.md` §1, drei Läufe mobil je Seitentyp | JSON nach `docs/messungen/werbeplaetze-golive/` |
| Konsole und Netzwerk | Chrome DevTools auf allen drei Seitentypen, vor **und** nach „Alle akzeptieren" | Screenshot |

Beim Messen am Produktionsportal zuerst **beide** Einwilligungsschichten
wegklicken, sonst fangen sie jeden Klick ab: erst den Google-CMP-Dialog
(`button.fc-cta-consent`, „Einwilligen"), danach den eigenen Cookie-Hinweis
(`[data-cookie-banner]`, Knopf „Alle akzeptieren" — der erste Knopf ist
„Einstellungen" und öffnet nur das Modal).

- [ ] Feldwerte vorher notiert (Datum, mobil/Desktop, je Metrik)
- [ ] Laborwerte vorher, drei Läufe je Seitentyp
- [ ] Konsole sauber: keine CSP-Verstöße, keine Fehler

### 3.3 Messung nachher (nach drei Tagen)

Dieselben Quellen, dieselbe Tageszeit, dasselbe Gerät. Bestanden ist der Pilot,
wenn **alle vier** Aussagen stimmen:

1. **CLS unter 0,1** im Feld und im Labor, mobil und Desktop.
2. **LCP nicht schlechter als vorher, Toleranz 10 %.**
3. **Keine CSP-Verstöße und keine Konsolenfehler** — vor und nach der
   Einwilligung.
4. **INP unverändert** (die Anker bringen kein eigenes Ereignis mit; eine
   Verschlechterung käme aus dem AdSense-Skript und wäre ein Grund, Auto Ads
   dauerhaft aus zu lassen).

- [ ] CLS nachher: ______ (Feld) / ______ (Labor)
- [ ] LCP nachher: ______ , Abweichung ______ %
- [ ] INP nachher: ______
- [ ] Konsole/Netzwerk nachher sauber

**Bei Verschlechterung zuerst zwei Dinge prüfen, bevor ein Anker entfernt wird**
(Vorgabe des Tickets):

* **Lazy-Loading-Vorlauf.** `initSlotAds()` setzt das Fragment 200 px vor dem
  Sichtbarwerden ein (`resources/js/ads.js`). Zu klein ⇒ die Anzeige erscheint
  erst im Blick und der Nutzer sieht ein Nachladen; zu groß ⇒ unnötige Requests.
* **Mindesthöhen.** `--ad-mh` / `--ad-mh-lg` auf `.ad-slot__area` kommen aus dem
  Katalog (`config/ad-slots.php`). Ist das ausgelieferte Format höher als die
  reservierte Höhe, wächst der Container — genau das ist CLS. Der
  Format-Hinweis im Konfigurationspanel meldet das vorher, sperrt aber nicht.

### 3.4 Sichtbarkeit je Anker (Akzeptanzkriterium 3)

Im AdSense-Report je Anzeigenblock ablesen und in `docs/ad-slots.md` §13.2
eintragen: **Aktive Betrachtungszeit**, **sichtbare Impressionen (Viewability)**,
Klickrate und RPM. Ein Anker unter 40 % Viewability trägt nicht und wird
abgeschaltet, statt ihn „laufen zu lassen" — er kostet Ladezeit und verwässert
den Kontowert.

- [ ] Viewability je belegtem Anker notiert
- [ ] §13.2 in `docs/ad-slots.md` ausgefüllt, Empfehlung je Anker aktualisiert

---

## 4. Rollout über `ads_enabled`

Erst nach bestandenem Pilot, und in Stufen — nicht alle 21 Portale an einem Tag.

| Stufe | Umfang | Wartezeit | Abbruchkriterium |
| --- | --- | --- | --- |
| 1 | Pilot-Portal (läuft schon) | 3 Tage | §3.3 |
| 2 | drei weitere `sun-v2`-Portale der Freigabeliste | 24 Stunden | CLS über 0,1 oder CSP-Fehler |
| 3 | die übrigen freigegebenen Portale | — | wie Stufe 2 |

Je Stufe:

```bash
# Schalter im Panel setzen (Einstellungen › Werbung, Portal oben wählen),
# danach Nachweis:
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:golive:check
curl -s https://<domain>/ads.txt
```

- [ ] Stufe 2 geschaltet, 24 h beobachtet, `ads:golive:check` ohne FAIL
- [ ] Stufe 3 geschaltet, `ads:golive:check` ohne FAIL
- [ ] `/ads.txt` für **jedes** freigegebene Portal HTTP 200 (Akzeptanzkriterium 4)
- [ ] Portale ohne Freigabe: `/ads.txt` ist 404 **oder** trägt nur die
      Zusatzzeilen fremder Vermarkter — beides richtig, eine leere Datei wäre
      falsch

### 4.1 Danach: Altsystem abschalten

Erst **nach** der Abnahme und nicht als Teil des Rollouts. Umfang und
Reihenfolge stehen in `docs/ad-slots.md` 11.1 („Abschalten des Altsystems").
Die Codes aus den Tenant-DBs vorher sichern.

---

## 5. Notbremse

```bash
# 1. ein Portal zeigt Mist: Schalter im Panel aus (wirkt sofort, Cache wird
#    mit vergessen). Ohne Panel-Zugang:
sudo -u sanitaerfinden /usr/bin/php8.4 artisan tinker --execute \
  "App\Ads\Models\AdSetting::query()->forTenant(52)->update(['ads_enabled' => false]); App\Ads\Support\AdCache::forgetTenant(52);"

# 2. eine einzelne Belegung stört: im Panel auf 'paused' — die Anker-Höhe
#    bleibt reserviert, es wird nur nichts ausgespielt.

# 3. netzweiter Vorfall: alle Portale aus
sudo -u sanitaerfinden /usr/bin/php8.4 artisan tinker --execute \
  "App\Ads\Models\AdSetting::query()->update(['ads_enabled' => false]);"
sudo -u sanitaerfinden /usr/bin/php8.4 artisan queue:work --once --queue=default  # FlushAdCache
```

Das Altsystem bleibt davon unberührt und liefert weiter aus — die Notbremse
nimmt nur das neue System heraus. Ein Rückbau der Migrationen ist dafür nie
nötig.

---

## 6. Abnahme

### 6.1 Was maschinell erledigt ist

| Akzeptanzkriterium | Stand |
| --- | --- |
| 1 — `docs/ad-slots-security-review.md` ohne offene Punkte | **erledigt**, S1 behoben, S2 als Verfahren in §2 |
| 2 — Pilot: CLS < 0,1, LCP im Rahmen, keine CSP-/Konsolenfehler | Labor **erledigt** (CLS 0.000 mit allen 32 Ankern belegt, `docs/messungen/werbeplaetze-cls-2026-10-16.md`); Feldwerte hängen am Pilot (§3) |
| 3 — Slot-Empfehlung in `docs/ad-slots.md` | **Gerüst und Vorab-Empfehlung erledigt** (§13); die Spalte Viewability füllt der Pilot |
| 4 — alle Tenants nach Freigabeliste, `ads.txt` erreichbar | Prüfwerkzeug **erledigt** (`ads:golive:check`); Liste und Schalter hängen an Enes (§1.1) |
| 5 — Abnahme durch Enes | offen |

### 6.2 Was am Menschen hängt

- [ ] Freigabeliste §1.1 ausgefüllt (AdSense-Site-Status je Domain)
- [ ] Pilot-Portal benannt, Pilot drei Tage gelaufen, §3.3 abgehakt
- [ ] Viewability je Anker im AdSense-Report abgelesen, §13.2 eingetragen
- [ ] Rollout Stufe 2 und 3 geschaltet
- [x] 2026-10-16 #25 und #26 abgeschlossen (beide Tickets erledigt; offen aus der Messung zu #25 bleiben #29 und #32, siehe B8)
- [ ] Abnahme erklärt: Datum, Kürzel

---

## 7. Belege

| Was | Wo |
| --- | --- |
| CLS-Messung mit allen Ankern (Labor, 16.10.2026) | `docs/messungen/werbeplaetze-cls-2026-10-16.md` + `-cls-2026-10-16/` |
| 360-px-Aufnahmen je Seitentyp | `docs/messungen/werbeplaetze-360/` |
| Pilot vorher/nachher | `docs/messungen/werbeplaetze-golive/` (angelegt 16.10.2026, Ablageordnung in `README.md`, Protokoll in `pilot-protokoll.md`) |
| Netzwerk-Mitschnitt Einwilligung | `docs/ad-slots.md` 9.5 (#25) |
| Prüfläufe `ads:golive:check` | `docs/messungen/werbeplaetze-golive/golive-check-<datum>.txt`; der lokale Lauf vom 16.10.2026 liegt dort als `…-lokal.txt` und ist nur ein Werkzeugnachweis |
