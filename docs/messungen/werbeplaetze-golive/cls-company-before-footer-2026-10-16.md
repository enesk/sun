# `company_before_footer` wieder scharf — CLS auf dem Firmenprofil

**Datum:** 16.10.2026 · **Ticket:** #40 (Nachzug zu #32) · **Codestand
Produktion:** `abf578e7`

Gegenstand: die beiden Belegungen auf dem Banner-Anker `company_before_footer`
(Reservierung 100 px mobil / 90 px Desktop), die der Takeover aus
`after_change_request` angelegt hatte und die wegen #32 auf `paused` standen.

Rohdaten:

* `cls-company-before-footer-2026-10-16.json` — lokale Messung **mit**
  Einwilligungsklick im laufenden Besuch
* `cls-company-before-footer-prod-2026-10-16.json` — Produktion, **ohne**
  Einwilligungsklick

---

## 1. Warum die Pause fallen kann

#32 hat Weg 2 gewählt: das `<ins>` bekommt keine Format-Angabe mehr, die Höhe
kommt aus der Reservierung des Ankers (`AdSenseProvider::reservedHeight()`,
kleinere der beiden Gerätehöhen). Für `company_before_footer` sind das 90 px.
Nachgesehen im Code, der auf der Produktion läuft:

```
$ php artisan ads:resolve 28 company_before_footer mobile --fresh
<ins class="adsbygoogle block w-full" style="display:block;width:100%;height:90px"
     data-ad-client="ca-pub-2563111817853634" data-ad-slot="3828015317" data-ads-push="true"></ins>

$ php artisan ads:resolve 33 company_before_footer mobile --fresh
<ins class="adsbygoogle block w-full" style="display:block;width:100%;height:90px"
     data-ad-client="ca-pub-1901800422608466" data-ad-slot="4148391584" data-ads-push="true"></ins>
```

Kein `data-ad-format="auto"`, kein `data-full-width-responsive` — genau der
Zustand, den `tests/Feature/Ads/AdSenseFormatClsTest.php` absichert.

## 2. Lokale Messung mit Einwilligung im laufenden Besuch

Aufbau wie #25/#32 (Chrome 155 headless, 360 × 800, Cookies und localStorage je
Lauf geräumt), aber über Herd statt `artisan serve`:

```bash
# Tenant 30 (elektrikerportal.com, sun-v2) voruebergehend auf eine Herd-Domain
# umgehaengt, ads_enabled=1, Publisher-ID ca-pub-1901800422608466
php artisan ads:resolve 30 company_before_footer mobile --fresh

"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t40_chrome

node scripts/ads-einwilligung-cls.mjs \
  --url="https://elektriker.test/15651-baalhorn-elektrotechnik" \
  --aus=docs/messungen/werbeplaetze-golive/cls-company-before-footer-2026-10-16.json
```

Belegt war **nur** `company_before_footer` — die übrigen Profil-Belegungen des
lokalen Abzugs hängen an Tenant 46. Der Anker wurde vor dem Klick in die
Bildmitte gescrollt (`ankerInBlick`, Vorgabe des Skripts), der Fall also
gemessen und nicht umgangen.

| Abschnitt | CLS | Containerhöhe | Fragment offen | `adsbygoogle` |
| --- | --- | --- | --- | --- |
| vor dem Klick | **0,000** | 130 px | 1 | nein |
| nach dem Klick | **0,000** | 130 px | 0 | ja |
| gesamt (nochmals durchgescrollt) | **0,000** | 130 px | 0 | ja |

Keine einzige gezählte Verschiebung (`verschiebungen: []` in allen drei
Abschnitten). 130 px = 90 px Reservierung + Zeile „Anzeige" + Innenabstand; der
Wert ist vor und nach der Einwilligung identisch, der Container wächst also
nicht. Zum Vergleich: derselbe Anker-Typ lieferte vor #32 einen Sprung von
130 auf 390 px und CLS 0,136.

**Grenze:** das Werbemittel trägt die Demo-Anzeigenblock-ID `1234567890` und
bekommt keine echte Füllung — belegt ist die Geometrie, nicht der Verkauf.
Dieselbe Einschränkung wie in `ads-cls-responsive-2026-10-16.md`.

Nach dem Lauf zurückgesetzt: Domain von Tenant 30, `ads_enabled=0`,
Publisher-ID `null`, Werbemittel der Probe-Belegung, Portal-Zuordnung,
`.env.messung` entfernt, Server und Chrome beendet, Cache geleert.

## 3. Produktion, ohne Einwilligungsklick

Nach dem Scharfschalten gemessen gegen `https://malerfinder.de` (Tenant 33,
Placement 8), 360 px mobil, `--klick='#kein-klick-nach-32'` — aus demselben
Grund wie im Pilot-Vorlauf: ein Klick aus Headless löst echte AdSense-Anfragen
auf einem Konto unter Beschränkung aus.

| Anker | wartet | sichtbar | Höhe | CLS |
| --- | --- | --- | --- | --- |
| `company_after_header_card` (mobil) | ja | ja | 310 px | — |
| `company_after_reviews` | ja | ja | 310 px | — |
| `company_sidebar_top` (Desktop) | ja | nein | 0 px | — |
| `company_sidebar_sticky` (Desktop) | ja | nein | 0 px | — |
| **`company_before_footer`** | **ja** | **ja** | **130 px** | — |
| gesamte Seite | | | | **0,000** |

Der Anker steht jetzt auf der Produktion, in Katalog-Höhe reserviert, mit
geschlossenem Fragment und ohne Request an Google vor der Zustimmung
(`offeneFragmente 5`, `adsbygoogleGeladen false`). Das Layout der Profilseite
ist gegenüber vorher unverändert — die 130 px waren schon vorher da, die
Belegung füllt sie nur.

## 4. Was offen bleibt

Der Nachher-Zustand auf der Produktion (echte Füllung in 90 px) gehört an ein
echtes Gerät und in die Abnahme von #31. Die Geometrie-Zusage ist mit §2
belegt, der Markup-Teil zusätzlich durch `AdSenseFormatClsTest`.
