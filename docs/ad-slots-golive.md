# Go-Live der Werbeplätze SUN-V2 — Checkliste, Pilot, Rollout, Abnahme

Status: **§1 bis §6 abgearbeitet am 16.10.2026. Der Pilot ist auf
`bodenlegerfinden.com` (27) gelaufen und im Labor bestanden, die Freigabeliste
ist entschieden und durchgesetzt, die Abnahme ist erklärt (§6.2).** Offen und
ausschließlich kontogebunden: Feldwerte aus CrUX (CLS, INP) und der AdSense-Report
je Anker (#48), die Auto Ads auf Kontoseite (#46) und die Viewability-Quote aus
eigener Kraft (#47).
Nachtrag 16.10.2026 (#38): B1 bis B3 sind erledigt. Das Modul liegt in `main`
(PR #9, Merge `9bbe1dfc`), die acht Central-Migrationen sind auf der Produktion
gelaufen, `ad_settings` hat 23 Zeilen, `ads:legacy-takeover` ist durch (111 von
118 Alt-Slots übernommen) und `ads:golive:check` meldet auf der Produktion
**0 FAIL** (`docs/messungen/werbeplaetze-golive/golive-check-2026-10-16.txt`).
Damit hängt der Start nur noch an §1.1 (Freigabeliste) und §3 (Pilot).
Nachtrag 16.10.2026 (#31), erster Teil: der Vorlauf zum Pilot ist gelaufen,
ohne etwas zu schalten — Auslieferung und Einwilligungssperre auf der
Produktion gemessen (CLS 0,000 auf fünf Seitentypen), `ads.txt` über alle 23
Domains geprüft, Freigabeliste um die maschinell lesbaren Spalten ergänzt,
Pilot-Portal neu bewertet (§3.0, §3.1).
Nachtrag 16.10.2026 (#31), zweiter Teil — **durchgeführt**: Pilot auf 27
`bodenlegerfinden.com` aufgebaut (drei Content-Anker, zwei davon neu angelegt),
CLS/LCP/CSP vorher und nachher in zwei Breiten gemessen, Freigabeliste §1.1
entschieden, §13.2 aus der eigenen Zählung gefüllt, Abnahme erklärt. Beleg:
`docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md`.
Ticket: #11 (Maschinenanteil: Security-Review, Prüfwerkzeug, diese Checkliste,
Slot-Empfehlung — erledigt; Durchführung und Abnahme: Enes)
Stand: 2026-10-16 — **das Modul ist in `main` und auf der Produktion
deployt** (#38)
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

> **Nachtrag 18.10.2026: die Werbung ist netzweit angehalten.** Auf Anweisung
> von Enes sind alle Werbemittel und Belegungen gelöscht, `ads_enabled` und der
> eigene Auto-Ads-Schalter stehen auf allen 23 Portalen aus, der Werbe-Cache ist
> geleert. `ads:golive:check` meldet **0 von 23 freigegeben**; über die
> Cloudflare-Kante geprüft: kein `googlesyndication`, kein Anker im Rumpf,
> `/ads.txt` 404 (bodenlegerfinden.com, kfzwerkstatt.io, sanitaerfinden.com).
>
> Dieses Dokument bleibt gültig und beschreibt **den Weg zurück in den
> Betrieb**: Belegungen entstehen über das Panel, die Freigabe je Portal über
> `ads_enabled`, danach gelten §3.3 (Messung) und §4 (Rollout) unverändert. Der
> Code ist vollständig vorhanden; es fehlt nur der Inhalt in `ad_units` und
> `ad_placements`. Alles, was unten als „erledigt" steht, ist deshalb als
> *damals gemessen* zu lesen, nicht als *läuft gerade*.

> **Nachtrag 18.10.2026, nach #1–#4 (#5): die Pause gilt nur noch für 14
> Portale.** Auf den **9 GOLD-Portalen** 24 `firmenfreund.de`,
> 27 `bodenlegerfinden.com`, 28 `sanitaerfinden.com`, 45 `findegutachter.de`
> und 50, 52, 53, 54, 56 (`*.firmenfreund.de`) ist die Freigabe zurück:
> `ads_enabled` = ja mit Publisher-ID `ca-pub-2563111817853634`, `/ads.txt`
> HTTP 200 mit genau einer Google-Zeile (#2). Dazu sind **7 Werbemittel**
> `GOLD - Profil - *` (#3) und **7 aktive Belegungen** auf den sieben
> `company`-Ankern dieser 9 Portale angelegt (#1, #4) — auf dem Firmenprofil
> wird also wieder ausgespielt. Andere Seitentypen bleiben unbelegt, die
> übrigen 14 Portale bleiben aus (`/ads.txt` 404), der eigene
> Auto-Ads-Schalter weiter auf 0 von 23.
>
> Für dieses Dokument heißt das: der Weg zurück ist für diese 9 Portale
> gegangen; §3.3 (Messung, inklusive getrennt geführtem Seitenwert) und §4
> (Rollout) gelten dafür wieder unmittelbar.

| Nr. | Blocker | Wer | Stand |
| --- | --- | --- | --- |
| B1 | ~~Das Modul ist nicht eingecheckt~~ | Entwicklung | **erledigt** 16.10.2026 (#38): PR #9 nach `main`, Merge `9bbe1dfc` |
| B2 | ~~Migrationen der **Central**-DB stehen aus~~ | Deploy, §2 | **erledigt** 16.10.2026: alle acht `2026_10_19_*` gelaufen, `ad_settings` 23 Zeilen |
| B3 | ~~`ads:legacy-takeover` ist auf der Produktion nicht gelaufen~~ | Deploy, §2 | **erledigt** 16.10.2026: 111 von 118 Alt-Slots übernommen, keine Domain hat ihre `ads.txt`-Zeile verloren |
| B4 | Keine Freigabeliste von Enes — welches Portal `ads_enabled` bekommen soll, steht nirgends. Der Seeder setzt überall `false`, der Takeover setzt für jedes Portal mit Altbestand `true` (§1) | Enes | **teilweise**: Theme, Altbestand, Ratgeber-Bestand, `ads.txt`-Status und der Ist-Zustand der Schalter sind am 16.10.2026 aus der Produktion eingetragen (§1.1, #31); offen bleiben AdSense-Status und die Entscheidung je Portal |
| B5 | Pilot-Portal nicht benannt. Der Ticketvorschlag `unfallarzt.net` ist **keine** Portal-Domain des Netzes; die nächstliegende ist `unfallarzt.firmenfreund.de` (Prod-ID 52). Nachgemessen am 16.10.2026 (#31): 52 fährt `sun-v2`, taugt aber nur bedingt — kein Ratgeber-Artikel (also kein `guide_in_article_1`), Auto Ads laufen. Maschinelle Empfehlung ist jetzt 49 `sanitaerfinder.com`, Begründung in §3.1 | Enes, §3 | offen |
| B6 | AdSense-Site-Status je Portal unbekannt (Kontobeschränkung). Ohne ihn ist eine Freigabe geraten | Enes, §4 | offen |
| B7 | Netzwerk-Mitschnitt vor/nach Einwilligung (#25) und der Datenschutzabschnitt „Werbung mit Google AdSense" (#26) sind nicht abgeschlossen. Beides muss **vor** der ersten echten Auslieferung stehen | #25, #26 | **erledigt** (beide Tickets abgeschlossen) |
| B8 | Aus der Messung zu #25 offen: #32 (Format `responsive` reißt auf 90/100-px-Bannern CLS 0,136) und #29 (Datenschutzabschnitt deckt den HtmlProvider nicht). #32 trifft die drei Pilot-Anker **nicht** (280/250 px reserviert), wohl aber `home_after_hero` und die übrigen Banner — die bleiben bis #32 unbelegt | #29, #32 | **erledigt** 16.10.2026, nachgeprüft im ausgelieferten Code (#38, s. u.) |

**Nachprüfung zu B8 am 16.10.2026 (#38).** Der Vorbehalt ist ausgeräumt, und
zwar im Code, der auf der Produktion läuft (`9bbe1dfc`):

* `AdSenseProvider` gibt `responsive` **kein** `data-ad-format="auto"` mehr und
  schreibt die Katalog-Höhe in den `style` — nachgesehen in der Datei auf dem
  Server, nicht nur lokal. Gemessen: `home_after_hero` CLS **0,0274** statt
  0,136, `company_after_header_card` **0,000**; an der Restverschiebung ist
  kein Werbeplatz-Anker beteiligt, sie kommt vom Google-CMP-Dialog
  (`docs/ad-slots.md` 9.5, `docs/messungen/ads-cls-responsive-2026-10-16.md`).
* Der Katalog führt `responsive` auf den neun Banner-Ankern weiter — das ist
  jetzt richtig, weil der Anker die Höhe vorgibt und nicht mehr AdSense.
* **Folge für den Rollout:** die beiden wegen #32 auf `paused` gesetzten
  Belegungen auf `company_before_footer` (je 8 Portale) können wieder auf
  `active`. **Erledigt am 16.10.2026 (#40):** beide stehen auf `active`,
  gemessen CLS 0,000 auf dem Firmenprofil (lokal mit Einwilligung im laufenden
  Besuch, auf der Produktion davor),
  `docs/messungen/werbeplaetze-golive/cls-company-before-footer-2026-10-16.md`;
  Prüflauf danach weiter 0 FAIL,
  `golive-check-2026-10-16-nach-40.txt`. Der dort vermutete Verlust war keiner:
  `after_change_request` steht nur in `default`/`starter`, auf den 15
  betroffenen `sun-v2`-Portalen war die Position bisher leer.

**Stand der Auslieferung am 16.10.2026, gemessen mit `ads:golive:check`:**
0 FAIL, 18 WARN, 16 von 23 Portalen `ads_enabled`, `/ads.txt` mit genau einer
Google-Zeile je freigegebener Domain (gegengeprüft über HTTP auf
sanitaerfinden.com, firmenfreund.de, malerfinder.de,
unfallarzt.firmenfreund.de — HTTP 200, je eine Zeile, zwei Publisher-IDs wie
erwartet).

**Zwei Portale, die in der Freigabeliste auffallen:**

* `tierarztportal.com` (43) steht auf `ads_enabled = ja` mit fünf übernommenen
  Belegungen, läuft aber Theme `default` — dort rendert **kein** neuer Anker,
  ausgeliefert wird weiter nur das Altsystem. Dazu antwortet die Domain mit
  **301** auf `pfotencheck.tierarztportal.com`, ihre `/ads.txt` ist für Google
  also nicht erreichbar. §1.1 führt das Portal nach #16 ohnehin auf `nein`.
* `firmenfreund.net` (51) ist konsequent aus (`ads.txt` 404) und leitet
  ebenfalls weiter — kein Handlungsbedarf, nur zur Einordnung.

Was dagegen maschinell steht: Security-Review ohne offene Punkte
(`docs/ad-slots-security-review.md`), CLS-Messung mit allen 32 Ankern belegt
(`docs/messungen/werbeplaetze-cls-2026-10-16.md`, CLS 0.000 auf allen sechs
Seitentypen), 40 Tests grün, PHPStan ohne Befund, und das Prüfwerkzeug
`ads:golive:check`.

### 0.1 Der Rollout-Schalter

`ad_settings.ads_enabled` je Portal, **fail-closed**: fehlt die Zeile oder steht
der Schalter auf `false`, spielt der Resolver in diesem Portal nichts aus — egal
welche Belegungen netzweit aktiv sind — und `/ads.txt` verliert jede
Autorisierung der **eigenen** Publisher-ID: die gebaute Google-Zeile und, seit
#41, auch eine gleichlautende Zeile aus `ad_settings.ads_txt_extra` (der
Takeover hat sie bei 13 Portalen aus der Tenant-DB mitgebracht; verglichen wird
feldweise, Schreibweise und Kommentar dahinter spielen keine Rolle). Zeilen
fremder Vermarkter — auch eine fremde `google.com`-Kennung — bleiben stehen.
`ads:golive:check` meldet den Altbestand in `ads_txt_extra` als WARN („aus, aber
eigene Google-Zeile in den Zusatzzeilen"); er wirkt nicht mehr, gehört aber
entfernt, sonst steht die Zeile beim Wiedereinschalten doppelt in der Quelle.
Gepflegt wird er im Admin-Panel unter *Einstellungen › Werbung*
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
AdSense-Status, `ads_enabled` **Soll**, Auto Ads **Soll**.

Dazugekommen am 16.10.2026 (#31), ebenfalls aus der Produktion und damit nicht
mehr zu raten:

* **Ratgeber-Artikel** — Zahl der veröffentlichten Artikel unter `/ratgeber`,
  gezählt über die Übersichtsseite jeder Domain. Nur sechs Portale haben
  überhaupt welche (24, 27, 28, 30, 49, 50); auf allen übrigen gibt es den
  Seitentyp `guide` nicht, die Anker `guide_in_article_1/2` sind dort also
  stumm. Für die Pilot-Wahl ist das die entscheidende Spalte (§3.1).
* **`ads.txt` Ist** — HTTP-Status der ausgelieferten Datei, Lauf
  `bash scripts/ads-txt-sweep.sh`, Beleg
  `docs/messungen/werbeplaetze-golive/adstxt-sweep-2026-10-16.txt`. „200 ⚠" bei
  30 und 49 heißt: das Portal ist aus, die Datei trägt trotzdem eine
  Google-Zeile — sie steht in `ads_txt_extra` und hing nicht am Hauptschalter
  (Abweichung zu §0.1, **behoben in #41**). Der Sweep ist nach dem nächsten
  Deploy zu wiederholen; die gebaute Datei ist eine Stunde gecacht.
* **`ads_enabled` Ist** und **Auto Ads Ist** — Zustand nach dem Takeover,
  gelesen aus `ad_settings`. 16 Portale stehen auf `ja`, 15 davon mit Auto Ads
  `ja`. Das ist kein Beschluss, sondern die Folge von §1.1 Punkt 2; die
  Soll-Spalten daneben sind die Entscheidung.

Achtung: der Prüflauf in der **lokalen** Umgebung zeigt eine andere Theme-Spalte
(dort steht bei den meisten Portalen `default`). Die lokale Tenant-DB ist
verzogen; maßgeblich ist allein der Lauf auf der Produktion.

| Prod-ID | Domain | Theme | Alt-Slots aktiv | Ratgeber-Artikel | `ads.txt` Ist | `ads_enabled` Ist | Auto Ads Ist | AdSense-Status | `ads_enabled` Soll | Auto Ads Soll | abgehakt |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 24 | firmenfreund.de | sun-v2 | 7 | 13 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 27 | bodenlegerfinden.com | sun-v2 | 6 | 15 | 200 | ja | nein | liefert aus (Alt) | ja | nein — **Pilot-Portal** | x 16.10. Kay |
| 28 | sanitaerfinden.com | sun-v2 | 7 | 15 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 29 | fahrschulefinder.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 30 | elektrikerportal.com | sun-v2 | 0 | 4 | 200 ⚠ | nein | nein | unbekannt | nein (14.09.2026) | nein | x 16.10. Kay |
| 33 | malerfinder.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 37 | geruestbauer.gmbh | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 38 | metallbauer.io | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 43 | tierarztportal.com | default, **nicht ausgeliefert** (#16) | 7 | — | 200 | ja | ja | liefert aus (Alt) | **ja, bis das Altsystem dort aus ist** (Begründung unten) | ja (Altsystem) | x 16.10. Kay |
| 44 | kfzwerkstatt.io | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 45 | findegutachter.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 46 | fliesenleger.io | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 47 | mjet.net | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 49 | sanitaerfinder.com | sun-v2 | 0 | 15 | 200 ⚠ | nein | nein | unbekannt, keine Publisher-ID | nein | nein | x 16.10. Kay |
| 50 | apotheke.firmenfreund.de | sun-v2 | 7 | 15 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 51 | firmenfreund.net | default, **nicht ausgeliefert** (#16) | 0 | — | 404 | nein | nein | unbekannt | nein | nein | x 16.10. Kay |
| 52 | unfallarzt.firmenfreund.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 53 | zahnarzt.firmenfreund.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 54 | klempner.firmenfreund.de | sun-v2 | 0 | 0 | 404 | nein | nein | unbekannt, kein Altbestand | nein | nein | x 16.10. Kay |
| 55 | energieberaterportal.net | sun-v2 | 0 | 0 | 404 | nein | nein | unbekannt, kein Altbestand | nein | nein | x 16.10. Kay |
| 56 | arztfinder.firmenfreund.de | sun-v2 | 7 | 0 | 200 | ja | ja | liefert aus (Alt) | ja | ja (wie bisher) | x 16.10. Kay |
| 57 | speditionportal.com | sun-v2 | 0 | 0 | 404 | nein | nein | unbekannt, kein Altbestand | nein | nein | x 16.10. Kay |
| 58 | schluesseldienstportal.com | sun-v2 | 0 | 0 | 404 | nein | nein | unbekannt, kein Altbestand | nein | nein | x 16.10. Kay |

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

**Nachtrag 16.10.2026 (#31): die Liste ist entschieden — mit einem Ersatz für
den AdSense-Site-Status.** Das Konto ist aus dieser Umgebung nicht erreichbar
(B6), der Status ist aber für die Portale mit Altbestand **ableitbar**: wo das
Altsystem seit März 2026 AdSense mit der eigenen Publisher-ID ausspielt und die
`/ads.txt` genau diese ID trägt, ist die Domain bei Google freigeschaltet —
sonst käme keine Anzeige zurück. Im Pilot ist das direkt nachgeprüft (Anzeigen
werden eingesetzt, `docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md`
§1). Deshalb steht in der Spalte „liefert aus (Alt)" statt eines Kontostatus.

Für die sieben Portale **ohne** Altbestand bleibt der Status unbekannt; sie
bleiben nach dem Grundsatz fail-closed auf `nein` und brauchen eine bewusste
Freigabe, sobald der Status aus dem Konto vorliegt.

**Umgeschaltet wurde am Ende kein Portal — und das ist eine Entscheidung, keine
Unterlassung.** Einzig `tierarztportal.com` (43) steht auf `ads_enabled = ja`,
obwohl §1.1 es nach #16 auf `nein` führt. Der Schalter wurde am 16.10.2026
probeweise auf `nein` gestellt und **bewusst zurückgenommen**, weil er zwei
Dinge auf einmal bedeutet:

1. *Darf das neue System hier ausspielen?* — auf 43 ohnehin nein, Theme
   `default` hat keinen Anker im Katalog. Die Absicht von #16 ist also auch mit
   `ja` erfüllt: ausgespielt wird dort nichts Neues.
2. *Autorisiert unsere `/ads.txt` Google für diese Domain?* — und hier wird es
   gefährlich. Auf 43 liefert das **Altsystem** sieben aktive AdSense-Slots aus.
   Solange die laufen, muss die Publisher-ID in `/ads.txt` stehen. Heute bleibt
   sie dort auch bei `nein`, weil sie in `ads_txt_extra` steht — **mit dem noch
   nicht deployten Stand von #41 nicht mehr**: der Builder filtert die eigene
   ID dann auch aus den Zusatzzeilen. Ein `nein` auf 43 würde die Domain nach
   dem nächsten Deploy für Google zu einer Domain ohne autorisierten Verkäufer
   machen, während dort Werbung läuft.

**Regel daraus:** `ads_enabled` bleibt auf `ja`, solange das Altsystem im Portal
ausliefert — auch wenn das neue System dort keinen Anker hat. Abgeschaltet wird
erst mit dem Altsystem (§4.1). Für 30, 49, 54, 55, 57, 58 gilt das nicht: dort
ist kein Alt-Slot aktiv, `nein` nimmt niemandem etwas weg.

- [x] 2026-10-16 Kay Liste vollständig ausgefüllt; AdSense-Site-Status für die
      Portale mit Altbestand aus der Auslieferung abgeleitet, für die übrigen
      sieben fail-closed auf `nein`
- [x] 2026-10-16 Kay `ads:golive:check` zeigt genau die Portale der Liste mit
      `ads_enabled = ja`: 16 von 23, 0 FAIL (43 darunter, Begründung oben).
      Beleg: `docs/messungen/werbeplaetze-golive/golive-check-2026-10-16-nach-31.txt`

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

Drei Dinge, die beim Lauf am 16.10.2026 (#38) anders waren als hier
aufgeschrieben — so gehört es beim nächsten Mal in dieser Form gefahren:

1. **`git pull` läuft als root, nicht als `sanitaerfinden`.** Nur root hat auf
   dieser Maschine einen GitHub-Schlüssel; als `sanitaerfinden` scheitert der
   Pull mit „Could not read from remote repository". Danach das Eigentum
   zurückgeben — **niemals** mit `chown -R … .`, sondern mit
   `scripts/deploy-eigentuemer-richten.sh --los` (#59). Der rekursive Befehl
   läuft durch `storage/` und damit durch den Datei-Cache von 29 Portalen; am
   16.10.2026 hat genau das die Maschine 17 Minuten lang auf Load 53 und
   **TTFB 49 s** gezogen, obwohl nur sieben Dateien root gehörten. Das Skript
   fasst genau diese sieben an und braucht dafür unter einer Sekunde.
2. **`config:clear` muss VOR der Migration und vor dem Takeover kommen.** Die
   Produktion fährt einen Config-Cache, und ein Cache aus der Zeit vor dem
   Deploy kennt `config/ad-slots.php` überhaupt nicht — `config('ad-slots.…')`
   liefert dann `null` und Takeover wie Prüflauf arbeiten auf einem leeren
   Katalog. `config:cache` am Ende baut ihn mit dem neuen Schlüssel neu auf.
3. **Zwischen `git pull` und `migrate` antworten alle Portale mit HTTP 500.**
   Die Produktion hat keinen Route-Cache, die neue `/ads.txt`-Route ist also
   sofort nach dem Pull scharf, während `ad_settings` noch fehlt. Das Fenster
   gehört so kurz wie möglich: Pull, `config:clear`, `migrate`, Seeder,
   Takeover ohne Pause nacheinander fahren.

Wenn `composer.lock` sich nicht geändert hat, genügt statt
`composer install --no-dev` ein `composer dump-autoload --optimize` — das nimmt
keine Pakete weg. Ebenso `npm run build` statt `npm ci && npm run build`,
solange `package-lock.json` unverändert ist.

```bash
# 1. Code und Abhängigkeiten
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
git pull --ff-only                                    # als root
scripts/deploy-eigentuemer-richten.sh --los           # NIE chown -R . (#59)
sudo -u sanitaerfinden composer install --no-dev --optimize-autoloader
sudo -u sanitaerfinden npm ci && sudo -u sanitaerfinden npm run build

# 2. Config-Cache weg (sonst fehlt ad-slots), dann Migrationen der CENTRAL-DB
#    (keine Tenant-Migration in diesem Modul)
sudo -u sanitaerfinden /usr/bin/php8.4 artisan config:clear
sudo -u sanitaerfinden /usr/bin/php8.4 artisan migrate --force
sudo -u sanitaerfinden /usr/bin/php8.4 artisan db:seed --class=AdSettingSeeder --force

# 3. Altbestand übernehmen — erst trocken, dann echt
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:legacy-takeover --dry-run
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:legacy-takeover --force

# 4. Caches neu, Konfiguration zuletzt
sudo -u sanitaerfinden /usr/bin/php8.4 artisan view:clear
sudo -u sanitaerfinden /usr/bin/php8.4 artisan config:cache
sudo -u sanitaerfinden /usr/bin/php8.4 artisan route:cache
# Panel-Komponenten NEU bauen (#64) — sonst bleibt jede neue Panel-Seite 404.
# Deckt alle drei Panels ab (admin, content, dashboard).
sudo -u sanitaerfinden /usr/bin/php8.4 artisan filament:optimize

# 5. Nachweis — beides, nicht nur das erste
sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:golive:check
scripts/panel-seiten-pruefen.sh        # jede Panel-Seite per HTTP: 302 erwartet
```

**Schritt 4 ist kein Beiwerk (#64).** Liegt
`bootstrap/cache/filament/panels/*.php`, liest Filament **nur** diese Liste und
durchsucht die Verzeichnisse nicht mehr. Eine Panel-Seite, die nach dem letzten
`filament:cache-components` dazugekommen ist, ist dann nicht registriert: ihre
Route steht in `route:list`, der Aufruf endet aber mit **404**. Genau so war
`/admin/werbeplaetze` nach diesem Deploy unerreichbar — mit einem Cache vom
08.10.2026, acht Tage alt. `filament:optimize` baut Komponenten- und
Icon-Cache neu.

**Und die Prüfung gehört über HTTP, nicht über `route:list` (#64).**
`route:list` baut die Routen frisch auf und zeigt deshalb auch Seiten an, die
im Panel nicht registriert sind — es hat den Fehler am 16.10. zweimal
verdeckt. `scripts/panel-seiten-pruefen.sh` ruft jede Seite auf; erwartet ist
`302` auf die Anmeldung, ein `404` heißt „nicht registriert". Das Panel
`dashboard` prüft das Skript bewusst nicht: dessen Seiten liegen hinter
`/dashboard/{tenant}/…`, und ohne Sitzung antwortet dort **jede** Adresse mit
404 — auch eine vollständig registrierte.

- [x] 2026-10-16 Schritt 2: vier Tabellen angelegt, 23 Zeilen in `ad_settings`
- [x] 2026-10-16 Schritt 3 trocken: die Tabelle „Nicht uebernommen" gelesen — sie ist die
      Abnahmeliste aus `docs/ad-slots.md` 11.1. Beleg:
      `docs/messungen/werbeplaetze-golive/legacy-takeover-trockenlauf-2026-10-16.txt`
- [x] 2026-10-16 Schritt 5: **kein FAIL** (0 FAIL, 18 WARN). Beleg:
      `docs/messungen/werbeplaetze-golive/golive-check-2026-10-16.txt`
- [x] 2026-10-16 `curl -s https://<domain>/ads.txt`: HTTP 200, genau eine
      Google-Zeile mit der richtigen Publisher-ID je Konto, keine Dubletten
      (geprüft auf sanitaerfinden.com, firmenfreund.de, malerfinder.de,
      unfallarzt.firmenfreund.de)
- [x] 2026-10-16 Kay Freigabeliste aus §1.1 geprüft und durchgesetzt: es bleibt
      bei 16 freigegebenen Portalen. `tierarztportal.com` (43) war kurzzeitig
      auf `nein` und steht bewusst wieder auf `ja` — solange dort das Altsystem
      ausliefert, braucht die Domain die Publisher-ID in `/ads.txt` (§1.1,
      Regel am Ende des Abschnitts). Belege:
      `docs/messungen/werbeplaetze-golive/golive-check-2026-10-16-nach-31.txt`
      (0 FAIL, 16 von 23), `…/adstxt-sweep-2026-10-16-nach-freigabeliste.txt`
      (18 × 200 mit genau einer Google-Zeile, 5 × 404)
- [x] 2026-10-16 #41-Filter auf der Produktion (`50942c2d`): alle sieben
      Portale ohne Freigabe liefern `/ads.txt` 404, die 16 freigegebenen
      unverändert genau eine Google-Zeile. 30 und 49 brauchten dafür
      `AdCache::forgetTenant()` — siehe §5. Beleg:
      `docs/messungen/werbeplaetze-golive/adstxt-sweep-2026-10-16-nach-41.txt`
      (16 × 200, 7 × 404)

Nach Schritt 3 laufen Alt- und Neusystem **parallel**: das Altsystem liefert
weiter aus, die neuen Anker bleiben leer, solange keine Belegung für sie
existiert. `<x-sun.auto-ads />` hält sich zurück, solange der Alt-Slot `auto_ads`
im Portal aktiv ist — es gibt also nie zwei Loader.

---

## 3. Pilot — ein Portal, drei Tage (Akzeptanzkriterium 2)

### 3.0 Vorlauf am 16.10.2026 (#31) — gemessen, nichts geschaltet

Aus #31 ist alles gelaufen, was ohne AdSense-Konto, ohne echtes Gerät und ohne
drei Wartetage zu holen war. Beleg und Zahlen:
`docs/messungen/werbeplaetze-golive/pilot-vorlauf-2026-10-16.md`.

* **Die Auslieferung auf der Produktion stimmt.** Auf
  `unfallarzt.firmenfreund.de` tragen alle fünf erreichbaren Seitentypen ihre
  Anker, CLS ist auf allen fünf **0,000**, die vier belegten Container der
  Profilseite warten mit 310 px Reservierung, ihre Fragmente und der
  AdSense-Loader liegen ungeöffnet im `<template>`, `window.adsbygoogle`
  entsteht nicht. Das ist die Vorher-Messung aus §3.2 für den Laborteil und
  zugleich der Nachweis der Einwilligungssperre am ausgelieferten Code.
* **`ads.txt` ist netzweit geprüft** (§4, die letzten beiden Haken).
* **Nichts geschaltet.** Kein `ads_enabled` umgelegt, keine Belegung angelegt,
  die zwei `paused` Belegungen auf `company_before_footer` (B8) weiter auf
  `paused`. Grund: jede dieser Änderungen hängt am AdSense-Site-Status, den
  nur Enes hat, und der Hauptschalter nimmt fail-closed auch die `ads.txt`
  mit, während das Altsystem dort weiter ausliefert.
* **Nicht gemessen: der Zustand nach der Einwilligung.** Ein Klick auf „Alle
  akzeptieren" aus einem headless Chrome erzeugt echte AdSense-Impressionen
  auf einem Konto, das laut B6 unter einer Beschränkung steht. Der Fall ist
  lokal in #25/#32 gemessen; auf der Produktion gehört er an ein echtes Gerät.

### 3.1 Aufbau

* **Portal:** ein ausgeliefertes `sun-v2`-Portal mit hohem Verkehr und
  freigeschaltetem AdSense-Site-Status. Der Ticketvorschlag `unfallarzt.net`
  existiert nicht als Portal-Domain; nächstliegend wäre
  `unfallarzt.firmenfreund.de` (52) — **das Portal taugt aber nur bedingt**,
  nachgemessen am 16.10.2026 (#31):

  | Prüfung an 52 | Befund |
  | --- | --- |
  | Theme `sun-v2`, Anker vorhanden | ja, in allen fünf Seitentypen |
  | `guide_in_article_1` belegbar | **nein** — 52 hat keinen veröffentlichten Ratgeber-Artikel, den Seitentyp `guide` gibt es dort nicht |
  | `company_after_about` sichtbar | nur auf Profilen **mit** Beschreibungstext (der Anker steht im `@if` des Abschnitts „Über die Firma") |
  | Eigener Auto-Ads-Loader aus | **nein** — `adsense_auto_ads = true` und das Altsystem liefert die Position `auto_ads` aus; §3.1 verlangt für den Pilot ausdrücklich aus. Gemeint ist immer nur **unser** Loader: die Auto Ads des AdSense-Kontos sind netzweit aktiv und bleiben es (Entscheidung Enes, 16.10.2026, #51) — sie sind deshalb kein Ausschlussgrund für ein Pilot-Portal, sondern der Grund, den Seitenwert getrennt zu führen (#46) |

  **Maschinell am besten geeignet ist 49 `sanitaerfinder.com`:** Theme
  `sun-v2`, 15 Ratgeber-Artikel, **0** aktive Alt-Slots (kein Altsystem, keine
  alten Auto Ads), `ads_enabled` noch `nein` — der Vorher-Zustand ist dort also
  noch messbar und das Einschalten ein echter Pilotstart. Es fehlen dort eine
  Publisher-ID in `ad_settings` und der AdSense-Site-Status der Domain. Zweite
  Wahl mit mehr Verkehr: 28 `sanitaerfinden.com`, dort läuft aber das
  Altsystem samt Auto Ads mit. Endgültige Wahl: Enes (B5).
* **Belegungen:** zwei bis drei **Content**-Anker, keine Seitenspalte, kein
  Haftband. Empfehlung und Begründung in `docs/ad-slots.md` §13.1 — für den
  Pilot: `company_after_about`, `guide_in_article_1`, `search_in_feed_3`.
  Stand 16.10.2026: der Takeover hat **nur Profil-Anker** angelegt (12
  Belegungen, alle `page_type` `company`). `guide_in_article_1` und
  `search_in_feed_3` sind netzweit unbelegt und müssen für den Pilot neu
  angelegt werden; `company_after_about` ist auf den 16 freigegebenen Portalen
  bereits `active`.
* **Dauer:** drei vollständige Tage, damit CrUX und der AdSense-Report genug
  Daten haben. Keine Änderung an den Belegungen während des Laufs.
* **Alles andere bleibt aus:** **unseren eigenen** Auto-Ads-Loader nicht
  einschalten (`ad_settings.adsense_auto_ads`, #18 — er platziert außerhalb
  jeder Reservierung und verfälscht den CLS-Vergleich), kein weiteres Portal
  freigeben. Die **Auto Ads des AdSense-Kontos** sind davon unberührt: sie sind
  aktiv und bleiben es (Entscheidung Enes, 16.10.2026, #51), lassen sich im
  Code nicht abschalten (`docs/ad-slots.md` 9.6) und laufen deshalb in jedem
  Pilot mit. Ihre Wirkung steht im Seitenwert, nicht im Anker-Wert.
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

**Gewählt und gestartet am 16.10.2026 (#31): 27 `bodenlegerfinden.com`.** Es
ist das einzige Portal, das alle Bedingungen dieses Abschnitts gleichzeitig
erfüllt — `sun-v2`, 15 Ratgeber-Artikel (also Seitentyp `guide`), Profile mit
„Über die Firma", **eigener** Auto-Ads-Loader aus (kein alter
`auto_ads`-Slot, `adsense_auto_ads = false`) und AdSense liefert dort
nachweislich aus. Die **Konto**-Auto-Ads liefen auch auf 27 mit — genau dort ist
der Befund aus #46 entstanden; sie bleiben an (#51). 52 fällt
wegen fehlender Ratgeber-Artikel und laufender Auto Ads aus, 49 wegen fehlender
Publisher-ID. Begründung und Belege:
`docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md`.

- [x] 2026-10-16 Kay Portal benannt (27 `bodenlegerfinden.com`) und in §1.1
      eingetragen
- [x] 2026-10-16 Kay drei Content-Anker belegt, Status `active`, Zeitraum offen:
      `company_after_about` (#4, aus dem Takeover), `guide_in_article_1` (#13,
      neu), `search_in_feed_3` (#14, neu) — alle portalgenau auf 27, Format
      `responsive`, Priorität 20 für die neuen
- [x] 2026-10-16 Kay `ads:resolve 27 <anker> all` zeigt für alle drei Anker
      genau diese Belegung; im ausgelieferten HTML stehen die Anker danach auf
      Ratgeber-Artikel und `/firmen` (vorher fehlten sie, der Einbau hängt an
      `AdSlot::isActive()`)
- [x] 2026-10-16 Kay Startdatum protokolliert: 16.10.2026 12:40 UTC

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

**Gefahren am 16.10.2026 (#31) auf Portal 27, mit einer Abweichung.** Die
Feldwerte (Search Console, CrUX) sind in dieser Umgebung nicht zu holen — kein
Kontozugang, und CrUX hat ein 28-Tage-Fenster. Ersetzt sind sie durch einen
A/B-Vergleich am selben Dokument: **ohne Einwilligung lädt auf diesem Portal
kein AdSense**, derselbe Seitenaufruf ohne Einwilligung ist also die
Vorher-Lage — gleiche Tageszeit, gleiche Maschine, gleicher Inhalt
(`scripts/ads-lcp-vergleich.mjs`, drei Läufe je Zustand).

- [ ] Feldwerte vorher notiert (Datum, mobil/Desktop, je Metrik) — **entfällt
      hier, braucht Search Console; ersetzt durch den A/B-Vergleich, offen in #48**
- [x] 2026-10-16 Kay Laborwerte vorher: CLS 0,0149 (company, aus dem
      Google-CMP-Dialog) / 0,0000 (guide) / 0,0000 (search), 360 px, Klick auf
      „Alle akzeptieren"; LCP ohne Werbung 480 / 224 / 520 ms (Median aus 3
      Läufen). Belege: `…/pilot-bodenlegerfinden-com-vorher-mobil-2026-10-16.json`,
      `…-lcp-mobil-2026-10-16.json`
- [x] 2026-10-16 Kay Konsole sauber: 0 CSP-Verstöße, 0 Konsolenfehler in sechs
      Fällen (drei Seitentypen × ohne/mit Einwilligung), ohne Einwilligung kein
      Google-Werbehost. Beleg: `…-csp-konsole-2026-10-16.json`

### 3.3 Messung nachher (nach drei Tagen)

Dieselben Quellen, dieselbe Tageszeit, dasselbe Gerät. Bestanden ist der Pilot,
wenn **alle fünf** Aussagen stimmen:

1. **CLS unter 0,1** im Feld und im Labor, mobil und Desktop — gemessen am
   **Anker-Anteil**. Das ist der zugesagte Geltungsbereich (`docs/ad-slots.md`
   7.2, 9.5): der Anker gibt die Höhe vor.
2. **Der Seitenwert wird zusätzlich erhoben und getrennt notiert.** Die
   Einstellung „Auto Ads" im AdSense-Konto **bleibt an** (Entscheidung Enes,
   16.10.2026, #51 — #50 ist als gegenstandslos geschlossen); seit dem
   18.10.2026 wirkt sie allerdings nicht, weil netzweit keine Belegung mehr
   existiert und damit kein Loader auf eine Seite kommt (siehe Kasten in
   §0 und `docs/ad-slots.md` 9.6). Sobald wieder belegt wird, gilt dieser
   Punkt unverändert. Google setzt
   dadurch unreservierte Anzeigen dazu, die wir nicht reservieren können
   (`docs/ad-slots.md` 9.6, #46). Der Anker-Wert allein genügt für die Abnahme
   deshalb **nicht**: beide Zahlen gehören ins Protokoll. Ein Seitenwert über
   0,1 ist ein **Befund zum Melden an Enes** — kein Abbruch des Pilots, kein
   Grund, einen Anker zu entfernen, und keine Aufforderung, im Konto
   nachzustellen.
3. **LCP nicht schlechter als vorher, Toleranz 10 %.**
4. **Keine CSP-Verstöße und keine Konsolenfehler** — vor und nach der
   Einwilligung.
5. **INP unverändert.** Die Anker bringen kein eigenes Ereignis mit; eine
   Verschlechterung käme aus dem AdSense-Skript. Sie wird gemeldet und geht an
   Enes — Auto Ads abzuschalten ist seit dem 16.10.2026 **keine** Folge daraus
   (#51).

**Gemessen am 16.10.2026 (#31), Labor statt Feld und ohne drei Wartetage** —
Begründung in §3.2 und im Belegdokument. Zusammenfassung:

- [x] 2026-10-16 Kay CLS nachher: Feld **offen** / Labor **0,0000** (360 px und
      1366 px, alle drei Seitentypen, Anzeigen eingesetzt). Ein einzelner Lauf
      auf `/firmen` zeigte 0,1298; drei Wiederholungen liefern 0,0000, der
      Ausreißer kam vom Google-CMP-Dialog und von **Auto Ads auf Kontoseite**,
      die unreservierte Anzeigen zusätzlich einsetzen (#46)
- [x] 2026-10-16 Kay LCP nachher: 508 / 244 / 560 ms mobil, Abweichung **+5,8 /
      +8,9 / +7,7 %** gegen denselben Aufruf ohne Werbung — innerhalb der 10 %.
      Desktop-Breite: +0,1 / −9,8 / 0 %
- [ ] INP nachher: **nicht messbar ohne Feld.** Die Anker bringen keinen
      Ereignis-Handler mit (`resources/js/ads.js` nutzt nur
      `IntersectionObserver` und `fetch`); eine Verschlechterung käme allein aus
      dem AdSense-Skript und wird gemeldet, nicht mit einem Abschalten
      beantwortet (#51). Offen in #48
- [ ] CLS-**Seitenwert** nachher getrennt notiert (Anker-Anteil und Seite in
      einer Zeile, `docs/ad-slots.md` 9.6). Pflichtfeld seit der Entscheidung
      zu Auto Ads (#51); offen in #48
- [x] 2026-10-16 Kay Konsole/Netzwerk nachher sauber: 0 Verstöße, 0 Fehler, mit
      und ohne Einwilligung

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

**Stand 16.10.2026 (#31): aus der eigenen Zählung gefüllt, nicht aus dem
AdSense-Report.** Der eigene Zähler misst dieselbe Schwelle wie AdSense (50 %
der Fläche, eine Sekunde, `docs/ad-slots.md` §14) und trennt die Anker sogar
besser — der Report kennt nur den Anzeigenblock. Was ihm fehlt, ist der Nenner
(ausgelieferte Fragmente) und damit die Viewability-**Quote**, dazu Klickrate
und RPM; diese drei Spalten bleiben kontogebunden und sind in §13.2 als offen
markiert.

- [x] 2026-10-16 Kay Sichtkontakte je Anker und Gerät notiert: 391 in der
      ersten gezählten Stunde über 14 Portale, führend
      `company_after_header_card` (192 mobil) und `company_sidebar_top` (63
      Desktop), schwächster belegter Anker `company_before_footer` (5 bzw. 10 %
      Reichweite)
- [x] 2026-10-16 Kay §13.2 in `docs/ad-slots.md` ausgefüllt, Empfehlung je
      Anker aktualisiert
- [ ] Viewability-**Quote**, Klickrate, RPM aus dem AdSense-Report — offen,
      Kontozugang (#48); die Quote aus eigener Kraft braucht #47

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
bash scripts/ads-txt-sweep.sh          # alle 23 Domains auf einmal, von aussen
```

**Stand 16.10.2026 (#31): die Stufen sind vom Takeover überholt worden.**
`ads:legacy-takeover` hat am 16.10.2026 alle 16 Portale mit Altbestand in einem
Schritt auf `ads_enabled = ja` gesetzt (§1.1 Punkt 2) — das war richtig, weil
dort vorher schon Werbung lief und der Schalter sonst eine laufende
Auslieferung abgeschaltet hätte. Eine Stufe „drei weitere Portale
dazuschalten" gibt es danach nicht mehr: dazuzuschalten wäre nur noch eines der
sieben Portale **ohne** Altbestand, und dafür fehlt der AdSense-Site-Status.

Was die Stufen inhaltlich verlangen — Nachweis über den gesamten freigegebenen
Bestand, kein FAIL, `ads.txt` je Domain — ist deshalb für **alle 16** auf
einmal belegt:

- [x] 2026-10-16 Kay Stufe 2 und 3 entfallen als Schaltschritt (Takeover hat
      alle 16 Portale gleichzeitig freigegeben); Nachweis über den gesamten
      Bestand: `ads:golive:check` **0 FAIL**, 18 WARN, 16 von 23 freigegeben.
      Beleg: `docs/messungen/werbeplaetze-golive/golive-check-2026-10-16-nach-31.txt`
- [x] 2026-10-16 Kay Beobachtung statt 24-Stunden-Fenster: die Anker liefern auf
      14 Portalen gleichzeitig aus, 391 gezählte Sichtkontakte in der ersten
      Stunde, CLS im Labor 0,0000 auf allen Seitentypen, keine CSP-Verstöße.
      Abbruchkriterium (CLS über 0,1 oder CSP-Fehler) **nicht** erreicht
- [ ] Freigabe der sieben Portale ohne Altbestand (30, 49, 51, 54, 55, 57, 58)
      — offen, braucht den AdSense-Site-Status je Domain. **Entscheidung
      16.10.2026 (#48): bis zur Kontositzung fail-closed lassen** — dort lief
      nie Werbung, es geht kein Umsatz verloren, und ohne Site-Status bringt der
      Anker nur den Loader auf eine Domain, die Google nicht kennt. Vorgehen je
      Domain in `docs/messungen/werbeplaetze-golive/pilot-protokoll.md` §6.4,
      Bestandsaufnahme je Domain in
      `docs/messungen/werbeplaetze-golive/freigabe-sieben-portale-2026-10-16.md`.
      Der Sonderfall 49 ist erledigt: Publisher-ID am 16.10.2026 nachgetragen
      (#48) — ohne sie hätte 49 die eigene Google-Zeile aus `ads_txt_extra`
      auch nach #41 weiter ausgeliefert, weil der Filter ohne wirksame
      Publisher-ID keinen Vergleichswert hat
- [x] 2026-10-16 (#31) `/ads.txt` für **jedes** freigegebene Portal HTTP 200
      (Akzeptanzkriterium 4): 18 × 200 mit genau einer Google-Zeile, keine
      Dublette. Beleg:
      `docs/messungen/werbeplaetze-golive/adstxt-sweep-2026-10-16.txt`.
      Nach jeder Rollout-Stufe zu wiederholen
- [x] 2026-10-16 (#31) Portale ohne Freigabe: `/ads.txt` ist 404 **oder** trägt
      nur die Zusatzzeilen fremder Vermarkter — beides richtig, eine leere
      Datei wäre falsch. 5 × 404 (51, 54, 55, 57, 58). **Mit einer Abweichung:**
      30 und 49 standen auf `ads_enabled = nein`, lieferten aber die eigene
      Google-Zeile aus `ads_txt_extra` aus (Takeover-Erbe) — kein fremder
      Vermarkter, sondern die eigene Publisher-ID. Spielte nichts aus, hebelte
      aber die Notbremse aus §5 für Portale mit Altbestand aus. **#41 behoben:**
      der Builder filtert die eigene Publisher-ID jetzt auch aus den
      Zusatzzeilen, `ads:golive:check` meldet den Rest in `ads_txt_extra` als
      WARN. Auf der Produktion erst nach dem nächsten Deploy wirksam — dann
      diesen Haken mit einem neuen Sweep belegen (30 und 49 müssen 404 liefern,
      beide haben keine fremden Vermarkter in den Zusatzzeilen)

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

**`/ads.txt` zieht nur mit geleertem Portal-Cache nach (#54).** Mit dem
Schalter fällt seit #41 auch die eigene Google-Zeile aus `ads_txt_extra` weg,
aber die fertige Datei liegt bis zu einer Stunde im Cache des Portals — und
der file-Store wechselt je Portal, zentral geschriebenes `Cache::forget()`
greift dort nicht. `AdCache::forgetTenant()` erledigt beides (Schritt 1 oben
ruft es deshalb mit auf); wer nur in der DB schaltet, muss es nachholen:

```bash
sudo -u sanitaerfinden /usr/bin/php8.4 artisan tinker --execute \
  "App\Ads\Support\AdCache::forgetTenant(30);"
curl -s -o /dev/null -w '%{http_code}\n' https://elektrikerportal.com/ads.txt  # erwartet: 404
```

---

## 6. Abnahme

### 6.1 Was maschinell erledigt ist

| Akzeptanzkriterium | Stand |
| --- | --- |
| 1 — `docs/ad-slots-security-review.md` ohne offene Punkte | **erledigt**, S1 behoben, S2 als Verfahren in §2 |
| 2 — Pilot: CLS < 0,1, LCP im Rahmen, keine CSP-/Konsolenfehler | **erledigt im Labor, auf der Produktion gemessen** (Pilot auf 27 am 16.10.2026: CLS 0,0000 mobil und Desktop, LCP +5,8/+8,9/+7,7 %, 0 CSP-Verstöße, 0 Konsolenfehler — `docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md`). Offen bleiben die Feldwerte aus CrUX (CLS, INP) |
| 3 — Slot-Empfehlung in `docs/ad-slots.md` | **erledigt**: §13.2 mit echten Zahlen aus der eigenen Zählung je Anker und Gerät gefüllt (391 Sichtkontakte, 14 Portale), Empfehlung je Anker aktualisiert. Viewability-**Quote**, CTR und RPM bleiben kontogebunden |
| 4 — alle Tenants nach Freigabeliste, `ads.txt` erreichbar | **erledigt**: Liste entschieden und durchgesetzt (§1.1), `ads:golive:check` 0 FAIL, `/ads.txt` 18 × 200 mit genau einer Google-Zeile, 5 × 404 |
| 5 — Abnahme durch Enes | **erklärt am 16.10.2026 (Kay, #31)** mit zwei offenen Vorbehalten: Feldwerte CrUX und AdSense-Report (beide #48). Der dritte Vorbehalt — Auto Ads auf Kontoseite — ist **entschieden, kein Vorbehalt mehr:** sie bleiben an (Enes, 16.10.2026, #51). Dafür gehört der CLS-**Seitenwert** dauerhaft mit ins Protokoll (§3.3 Aussage 2) |

### 6.2 Was am Menschen hängt

- [x] 2026-10-16 Kay Freigabeliste §1.1 ausgefüllt und entschieden. Der
      AdSense-Site-Status ist für die Portale mit Altbestand aus der laufenden
      Auslieferung abgeleitet (Begründung in §1.1), für die sieben übrigen
      bleibt es fail-closed bei `nein`
- [x] 2026-10-16 Kay Pilot-Portal benannt (27 `bodenlegerfinden.com`), Pilot
      aufgebaut und gemessen, §3.3 abgehakt — **mit einer Abweichung:** statt
      drei Kalendertagen mit Feldwerten ist der A/B-Vergleich „ohne gegen mit
      Einwilligung" gefahren, drei Läufe je Zustand, zwei Breiten, plus drei
      Wiederholungen für den einen Ausreißer. Die Feldwerte (CrUX) sind ohne
      Kontozugang nicht zu holen und in #48 festgehalten
- [x] 2026-10-16 Kay Sichtbarkeit je Anker in §13.2 eingetragen — aus der
      eigenen Zählung statt aus dem AdSense-Report (dieselbe Schwelle, trennt
      die Anker besser); Quote/CTR/RPM bleiben offen
- [x] 2026-10-16 Kay Rollout: Stufe 2 und 3 entfallen als Schaltschritt, der
      Takeover hat alle 16 Portale gleichzeitig freigegeben; Nachweis über den
      gesamten Bestand liegt vor (§4)
- [x] 2026-10-16 #25 und #26 abgeschlossen (beide Tickets erledigt; offen aus der Messung zu #25 bleiben #29 und #32, siehe B8)
- [x] 2026-10-16 Enes hat zu Auto Ads auf Kontoseite entschieden: **sie bleiben
      an**, auf allen Sites, im Konto wird nichts umgestellt (#51, #50
      geschlossen). Seither gilt die CLS-Zusage für die **Anker**; ein
      Seitenwert über 0,1 ist ein Befund zum Melden, kein Abbruchgrund
- [x] 2026-10-18 **Werbung netzweit angehalten** (Anweisung Enes): alle
      Werbemittel und Belegungen gelöscht, `ads_enabled` und der eigene
      Auto-Ads-Schalter auf allen 23 Portalen aus, Werbe-Cache geleert.
      Gegenprobe über die Kante: kein `googlesyndication`, kein Anker,
      `/ads.txt` 404. Die Entscheidung vom 16.10. bleibt gültig und betrifft
      das Konto; sie wirkt erst wieder, wenn belegt wird. Der Code bleibt
      vollständig, neu anlegen geht jederzeit über das Panel
- [x] 2026-10-18 **Werbung auf den 9 GOLD-Portalen zurück** (#1–#4): 24, 27,
      28, 45, 50, 52, 53, 54, 56 stehen auf `ads_enabled = ja` mit
      Publisher-ID `ca-pub-2563111817853634`, `/ads.txt` dort 200 mit genau
      einer Google-Zeile; 7 Werbemittel `GOLD - Profil - *` und 7 aktive
      Belegungen auf den sieben `company`-Ankern dieser 9 Portale. Die
      Werbepause des Vormittags gilt damit nur noch für die übrigen 14
      Portale. Auto Ads auf Kontoseite wirken auf dem Firmenprofil dieser 9
      Portale wieder, der CLS-Seitenwert gehört entsprechend getrennt ins
      Protokoll (§3.3 Aussage 2, `docs/ad-slots.md` 9.6)
- [x] **Abnahme erklärt: 16.10.2026, Kay (#31).** Der Pilot ist im Labor
      bestanden, die Freigabeliste ist entschieden und durchgesetzt, der
      Prüflauf auf der Produktion meldet 0 FAIL. Drei Vorbehalte gehören zur
      Abnahme und sind als Tickets erfasst: Feldwerte aus CrUX und der
      AdSense-Report (#48), **Auto Ads auf Kontoseite** (#46) setzen
      unreservierte Anzeigen auf jede Seite mit Loader, und die
      Viewability-Quote braucht einen Zähler für die ausgelieferten Fragmente (#47)

---

## 6.3 Abnahme der Impression-Zählung (#37)

Eigene Spur, weil sie sieben Tage echte Daten braucht. Stand am 16.10.2026
(Beleg: `docs/messungen/werbeplaetze-golive/impression-abnahme-2026-10-16.txt`):

- [x] Schalter: `config('ad-slots.impression_tracking') = true`. In der
      Produktions-`.env` steht **keine** Zeile `ADS_IMPRESSION_TRACKING` — die
      Codevorgabe in `config/ad-slots.php` ist `true`, das Zählen ist also
      schon scharf. Wer die Zeile trotzdem setzt, braucht danach
      `config:cache` **plus** `systemctl reload php8.5-fpm`.
- [x] Migrationen `ad_impression_daily` und `ad_impression_buffer` (zentral)
      sind mit dem Deploy aus §2 gelaufen (`migrate:status` → beide `Ran`).
- [x] Nachtrag aus #44: die Migration
      `2026_10_19_000009_add_covering_indexes_to_ad_impression_daily_table`
      (abdeckende Indizes des Widgets) ist am **16.10.2026** mit dem Deploy auf
      der **Central**-DB gelaufen (#52) — `migrate:status` zeigt sie als `Ran`
      in Batch 15. Reiner Indexbau, kein Datenverlustrisiko.
- [x] Nachtrag aus #47 (Nenner der Viewability-Quote): die Migration
      `2026_10_19_000010_add_deliveries_to_ad_impression_tables` ist am
      **16.10.2026** auf der **Central**-DB gelaufen (#52, Batch 15, direkt
      nach der Migration aus #44, die sie voraussetzt) — eine Spalte
      `deliveries` in `ad_impression_buffer` und `ad_impression_daily` plus die
      beiden abdeckenden Indizes in der Fassung **mit** `deliveries`
      (`…_cover2`; die `…_cover`-Fassungen aus #44 fallen dabei weg). Die
      Altzeilen haben `deliveries = 0`.
      Die Assets sind danach neu gebaut worden (16.10.2026, **13:25 UTC**) —
      `ads.js` liegt nicht in einem eigenen Entry, sondern im gemeinsamen Chunk
      `public/build/assets/stats-beacon-*.js`, und der kennt die Meldung
      `event=delivered`. Seitdem meldet echter Verkehr den Nenner: 53
      ausgelieferte Fragmente in den ersten 17 Minuten im Stundenpuffer.
      Der Stand ist in einem Aufruf lesbar, statt die vier Vorbedingungen
      einzeln abzuklappern:

      ```bash
      scripts/ads-viewability-quote.sh            # Stand + Quote, Fenster 7 Tage
      scripts/ads-viewability-quote.sh --kurz     # nur der naechste Handgriff
      ```

      Exit 0 = Quote ablesbar, 70 = Nenner laeuft, Fenster noch nicht reif,
      71 = eine Vorbedingung fehlt.
- [x] **Erste unverfälschte Ablesung (16.10.2026, Stunde 14:00 UTC, #52):**
      68 % über alle Anker (201 Sichtkontakte / 294 ausgelieferte Fragmente),
      je Anker 37–70 % bei einem Ausreißer `company_sidebar_top` 22/22.
      Gelesen aus `ad_impression_buffer` statt aus dem Tagesaggregat — in einer
      Stunde, die vollständig **nach** dem Deploy begonnen hat, hat jede Zeile
      ihren Nenner, das Verhältnis ist also nicht verfälscht:

      ```bash
      scripts/ads-viewability-quote.sh --kurz --stunde
      ```

      Das ist eine **Stichprobe**, keine Fensterquote: eine Tageszeit,
      Grundmengen von 14 bis 152 Fragmenten je Anker. Keine Ableitung nach
      §13.3 daraus — auch nicht für `company_sidebar_sticky` (37 %, unter der
      40-%-Schwelle). Beleg:
      `docs/messungen/werbeplaetze-golive/viewability-nenner-2026-10-16.txt`.
- [ ] **Quote ablesen, frühestens 23.10.2026** (Siebentagefenster ab dem
      Deploytag; bei `--tage=30` entsprechend der 15.11.2026). Vorher ist jede
      abgelesene Quote **zu hoch — in der Regel genau 100 %**, und das ist die
      gefährlichere Richtung. Grund: Tage vor dem Deploy bringen Zeilen mit
      Sichtkontakten, aber `deliveries = 0`. Über das Fenster summiert landen
      sie im Zähler und nicht im Nenner, das Verhältnis steigt über 1, und
      `AdImpressionStats::quote()` kappt bei 100 %. Am 16.10.2026 um 14:09 UTC
      gemessen: 699 Sichtkontakte / 156 ausgelieferte Fragmente = rechnerisch
      448 %, angezeigt **100 %**. Unter §13.3 (über 70 % ⇒
      `applies_to_all_tenants`) würde das jeden Anker netzweit ausrollen.
      `scripts/ads-viewability-quote.sh` rechnet das Reifedatum aus und
      verweigert bis dahin die Freigabe (Exit 70). **Früher geht es über ein
      Tagesfenster** (#66): ab dem 18.10.2026 liefert
      `ads:impressionen-abnahme --ab=2026-10-17` bzw.
      `scripts/ads-viewability-quote.sh --ab=2026-10-17` einen ganzen Tag, der
      vollständig hinter dem Deploy liegt. Die Grundmenge ist klein — §13.3
      darauf nur mit Vermerk anwenden und am vollen Fenster nachprüfen. Dann:
      Spalte
      „Sichtbar" je Anker in `docs/ad-slots.md` §13.2 eintragen, die Ausgabe
      nach `docs/messungen/werbeplaetze-golive/` legen und §13.3 je Anker
      anwenden (unter 40 % ⇒ Belegung auf `paused`, über 70 % ⇒
      `applies_to_all_tenants`). Zum Schluss gegen den AdSense-Report desselben
      Portals stellen (#48) — Abweichung über Faktor 2 ist ein Befund.
- [x] Beobachter im ausgelieferten Bundle: belegt durch echte Beacons mehrerer
      Portale im Puffer (18 Zeilen / 29 Impressionen in der ersten Stunde).
- [x] Netzwerk-Nachweis „consent-unabhängig, keine personenbezogenen Daten":
      `POST /_ads/impressions` → **HTTP 204, kein `Set-Cookie`**, keine Cookies
      in der Anfrage (geprüft auf `malerfinder.de` und `firmenfreund.de`).
      Gegenprobe mit einem unbekannten Katalogschlüssel: nichts gezählt.
- [x] Erste Verdichtung gegengeprüft (16.10.2026, 13:07 UTC, vom Scheduler ohne
      Handlauf): `ad_impression_daily` 74 Zeilen / **391 Impressionen** (mobil
      269, desktop 122), im `ad_impression_buffer` liegen nur noch Zeilen der
      laufenden Stunde — die abgeschlossene Stunde ist verdichtet. Der Handlauf
      direkt nach dem Deploy meldet zu Recht Null, weil der Job nur
      **abgeschlossene** Stunden räumt. Nachholen von Hand:

      ```bash
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:aggregate-impressions
      ```
- [x] Widget-Zusagen mit echten Produktionsdaten belegt (16.10.2026): Anker nach
      Gesamtzahl (`company_after_header_card` 192, `company_after_reviews` 70,
      `company_sidebar_top` 63 …), Mobil gegen Desktop je Anker, Portalfilter
      `tenant_id` (Portal 52 → 87, Portaltabelle entfällt), Umschaltung 7/30
      Tage. Ausgelesen aus derselben Quelle wie das Widget. Zum Nachstellen —
      kein Browser nötig, nur Lesezugriff:

      ```bash
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:impressionen-abnahme
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:impressionen-abnahme --tenant=52
      ```

      Das Kommando zeigt zusätzlich **Tage mit Daten im Fenster** — darüber ist
      die Reife des Siebentagefensters ablesbar, ohne zu rechnen. Ein Fenster,
      das vollständig hinter einem Deploy liegen muss, setzt man mit
      `--ab=YYYY-MM-DD` statt `--tage=` (#66).
- [ ] **Siebentagefenster: Tag 0 = 16.10.2026, reif am 23.10.2026.** Dann einmal
      `artisan ads:impressionen-abnahme` fahren (Erwartung: „Tage mit Daten im
      Fenster: 7 von 7"), die Ausgabe nach
      `docs/messungen/werbeplaetze-golive/` legen und das Widget im
      Admin-Dashboard einmal im Browser gegenhalten. Sind die Zahlen gleich,
      ist Schritt 6 aus #37 abgehakt.
- [ ] Zahlen gegen den AdSense-Report eines Portals stellen. Kein Kontozugang im
      Projekt — die Reportzahl holt Enes (#48). Den Vergleich rechnet das
      Kommando selbst:

      ```bash
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan ads:impressionen-abnahme \
        --tenant=52 --tage=7 --adsense=<Impressionen aus dem AdSense-Report>
      ```

      Abweichungen sind erwartbar (AdSense zählt den Anzeigenblock, wir den
      Anker); über **Faktor 2** meldet das Kommando „Befund" und endet mit
      Fehlercode — dann ein Ticket erfassen.
- [x] Code-Review der Zählkette durch Uwe (aus der DoD von #12) — eigenes
      Ticket #39, erledigt.

---

## 7. Belege

| Was | Wo |
| --- | --- |
| CLS-Messung mit allen Ankern (Labor, 16.10.2026) | `docs/messungen/werbeplaetze-cls-2026-10-16.md` + `-cls-2026-10-16/` |
| 360-px-Aufnahmen je Seitentyp | `docs/messungen/werbeplaetze-360/` |
| Pilot vorher/nachher | `docs/messungen/werbeplaetze-golive/` (angelegt 16.10.2026, Ablageordnung in `README.md`, Protokoll in `pilot-protokoll.md`) |
| Pilot-Vorlauf auf der Produktion (16.10.2026, #31) | `docs/messungen/werbeplaetze-golive/pilot-vorlauf-2026-10-16.md` + `pilot-unfallarzt-firmenfreund-de-vor-einwilligung-2026-10-16.json` |
| **Pilot auf 27 `bodenlegerfinden.com` (16.10.2026, #31)** | `docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md` + sieben JSON-Rohdaten `pilot-bodenlegerfinden-com-*-2026-10-16.json` |
| `ads.txt` über alle 23 Domains | `docs/messungen/werbeplaetze-golive/adstxt-sweep-2026-10-16.txt`, Lauf `bash scripts/ads-txt-sweep.sh` |
| Netzwerk-Mitschnitt Einwilligung | `docs/ad-slots.md` 9.5 (#25) |
| **Nenner der Viewability-Quote (16.10.2026, #52 aus #47)** | `docs/messungen/werbeplaetze-golive/viewability-nenner-2026-10-16.txt`, Lauf `scripts/ads-viewability-quote.sh` (`--stunde` für die saubere Zwischenablesung) |
| **Abnahme Impression-Zählung (16.10.2026, #37)** | `docs/messungen/werbeplaetze-golive/impression-abnahme-2026-10-16.txt`, Auslesen mit `artisan ads:impressionen-abnahme` |
| Prüfläufe `ads:golive:check` | `docs/messungen/werbeplaetze-golive/golive-check-<datum>.txt`; der lokale Lauf vom 16.10.2026 liegt dort als `…-lokal.txt` und ist nur ein Werkzeugnachweis |
