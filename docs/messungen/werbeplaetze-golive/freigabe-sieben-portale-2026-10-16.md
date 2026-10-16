# Die sieben Portale ohne Altbestand — Bestandsaufnahme und Entscheidung

Ticket #48, Punkt 4. Gemessen am 16.10.2026 auf der Produktion
(`ssh sun`, Central-DB `ad_settings` und Tenant-DBs `ad_slots`), `/ads.txt`
per `curl` von außen.

Gegenstand: 30, 49, 51, 54, 55, 57, 58 — die Portale, die der Takeover (#15)
nicht freigegeben hat, weil dort kein aktiver Alt-Slot lief. Sie stehen
fail-closed auf `ads_enabled = nein`.

## 1. Stand am 16.10.2026

| ID | Domain | `ads_enabled` | `adsense_publisher_id` | `ads_txt_extra` | Alt-Slots (gesamt / aktiv) | `/ads.txt` von außen |
| --- | --- | --- | --- | --- | --- | --- |
| 30 | `elektrikerportal.com` | nein | `ca-pub-1901800422608466` | eigene Google-Zeile derselben ID | 7 / 0 | 200, eine Google-Zeile `pub-1901800422608466` |
| 49 | `sanitaerfinder.com` | nein | **fehlte** → nachgetragen, siehe §3 | eigene Google-Zeile `pub-2563111817853634` | 0 / 0 | 200, eine Google-Zeile `pub-2563111817853634` |
| 51 | `firmenfreund.net` | nein | — | leer | 0 / 0 | 301 auf `solar-finden.de/ads.txt` |
| 54 | `klempner.firmenfreund.de` | nein | — | leer | 0 / 0 | 404 |
| 55 | `energieberaterportal.net` | nein | — | leer | 0 / 0 | 404 |
| 57 | `speditionportal.com` | nein | — | leer | 0 / 0 | 404 |
| 58 | `schluesseldienstportal.com` | nein | — | leer | 0 / 0 | 404 |

Zwei Dinge, die vorher nirgends festgehalten waren:

* **Das Netz fährt zwei AdSense-Konten.** Neben `ca-pub-2563111817853634`
  (Pilot-Portal 27, firmenfreund-Familie, 45, 49) steht
  `ca-pub-1901800422608466` auf 29, 30, 33, 37, 38, 43, 44, 46, 47
  (`adstxt-sweep-2026-10-16-nach-freigabeliste.txt`). Für 30 ist also das
  **zweite** Konto zu öffnen, nicht das des Piloten.
* **30 hat Vorgeschichte, 49 nicht.** 30 trägt sieben Alt-Slots, alle
  inaktiv — die Domain hat in der Vergangenheit mit
  `ca-pub-1901800422608466` ausgespielt. Das ist derselbe Schluss wie in
  `pilot-protokoll.md` §1 für 27 („abgeleitet"), aber schwächer: warum die
  Slots abgeschaltet wurden, steht nirgends. 49 hat nie Slots gehabt.

## 2. Entscheidung: alle sieben bleiben aus

Bestätigt am 16.10.2026. Dort lief nie Werbung — kein laufender Umsatz steht
auf dem Spiel, anders als beim Takeover der 16 Portale mit Altbestand. Ohne
Site-Status im Konto würde eine Freigabe nur den AdSense-Loader auf eine
Domain bringen, die Google möglicherweise nicht kennt. Auch 30 bleibt aus,
obwohl die Vorgeschichte für einen freigegebenen Site-Status spricht: die
inaktiven Alt-Slots können genauso eine Maßnahme des Kontos sein.

Vorgehen je Domain in der Kontositzung: `pilot-protokoll.md` §6.4.

## 3. Nachgetragen: Publisher-ID für 49

49 hatte keine Publisher-ID in `ad_settings` — und das war mehr als eine
Lücke im Panel: `AdsTxtBuilder` filtert die eigene Google-Zeile aus
`ads_txt_extra` nur gegen die **wirksame** Publisher-ID des Portals (#41).
Ohne ID gibt es keinen Vergleichswert, also hätte 49 die Zeile
`google.com, pub-2563111817853634, DIRECT, …` auch nach dem Deploy von #41
weiter ausgeliefert — ein Portal auf `ads_enabled = nein`, das den eigenen
Verkäufer autorisiert. `ADSENSE_PUBLISHER_ID` ist in der Produktions-`.env`
nicht gesetzt, der Rückfall aus `config('ad-slots.adsense.publisher_id')`
greift dort also nicht.

Gesetzt am 16.10.2026 auf der Produktion:
`ad_settings.adsense_publisher_id = ca-pub-2563111817853634` für Tenant 49,
`ads_enabled` unverändert **nein**, Tenant-Cache geleert.

Woher die ID: aus der eigenen `ads_txt_extra` derselben Domain (Takeover-Erbe
ihrer Alt-`ads.txt`); dieselbe ID fährt die firmenfreund-Familie.

Wirkung: heute keine — der Produktionsstand (`abf578e7`, Deploy zu #38) hat
den #41-Filter noch nicht, `/ads.txt` liefert die Zeile mit und ohne ID.
`AdSensePublisherId::isGoogleLineFor()` greift für genau diese Zeile und
diese ID (lokal geprüft), also fällt `/ads.txt` auf 49 mit dem nächsten
Deploy auf 404, solange der Schalter aus ist. Nachzuhalten:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://sanitaerfinder.com/ads.txt   # erwartet: 404
```

Für die Kontositzung ist der Sonderfall 49 damit erledigt — nur noch der
Schalter, falls der Site-Status „Bereit" ist.

## 4. Nachgehalten am 16.10.2026 (#54): Filter ist auf der Produktion

Der #41-Filter steht seit `50942c2d` auf der Produktion. Gegenprobe von
außen: **alle sieben** Portale ohne Freigabe liefern `/ads.txt` jetzt 404
(51 unverändert 301 auf `solar-finden.de`), die 16 freigegebenen
unverändert genau eine Google-Zeile mit der richtigen Kennung. Beleg:
`adstxt-sweep-2026-10-16-nach-41.txt` (16 × 200, 7 × 404).

30 und 49 standen nach dem Deploy noch auf 200: die fertige Datei lag im
Cache des jeweiligen Portals (eine Stunde, file-Store je Portal).
`AdCache::forgetTenant(30)` und `…(49)` auf der Produktion haben das
aufgelöst — Vorgehen jetzt in `docs/ad-slots-golive.md` §5.
