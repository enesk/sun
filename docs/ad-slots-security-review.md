# Security-Review des Werbeplatz-Systems SUN-V2

Status: **§1–§12 abgeschlossen; aus der Runde #39 (§13) ist nur noch der Leistungspunkt P1 offen; S3 ist mit #43, S4 mit #42 behoben, der dabei gefundene S6 ebenfalls mit #43**
Tickets: #11 (Runde 1, §1–§12), #39 (Runde 2: Impression-Zählkette, §13)
Prüfdatum: 2026-10-16 — Runde 1 am Stand der Arbeitskopie vor dem Einchecken
(`docs/ad-slots-golive.md` §0), Runde 2 am Stand, der seit dem Deploy vom
16.10.2026 auf der Produktion echt zählt
Umfang: `app/Ads/**`, `app/View/Components/Sun/**`, `app/Console/Commands/Ads*.php`,
`app/Http/Controllers/AdsTxtController.php`, `app/Livewire/Admin/AdPlacementPanel.php`,
`app/Filament/Admin/Pages/AdPlacementEditor.php`, `app/Filament/Admin/Pages/AdSettings.php`,
`app/Filament/Admin/Resources/AdUnits/**`, `app/Filament/Admin/Resources/AdPlacements/**`,
`config/ad-slots.php`, `config/csp.php`, die Routen `portal.ads.html`,
`portal.ads-txt`, `admin.ads.unit-preview`, die Brücken-Skripte
`resources/views/themes/sun-v2/js/ad-editor.js` und
`resources/js/filament/ad-editor-bridge.js`.
Architektur und Begründungen: `docs/ad-slots.md`. Durchführung: `docs/ad-slots-golive.md`.

Bewertung je Feld: **ok** / **Lücke** (Schweregrad niedrig / mittel / hoch).
Ein Punkt ist erst geschlossen, wenn er in §11 auf „behoben“ steht.

---

## 1. Editor-Token — ok

Geprüft: `app/Ads/EditorToken.php`, `app/Ads/Http/Middleware/AdEditorMode.php`,
`tests/Feature/Ads/EditorTokenTest.php`,
`tests/Feature/Ads/AdEditorModeMiddlewareTest.php`.

* **Signatur.** `hash_hmac('sha256', host|path|expires, APP_KEY)`, verglichen mit
  `hash_equals()` — kein `===` auf Hashes, also kein Timing-Orakel
  (`EditorToken.php:103, :150`).
* **Bindung.** Host **und** Pfad stehen im signierten Teil. Ein Token für
  `fliesenleger.io/firmen/muster` öffnet weder eine andere Seite noch ein anderes
  Portal. Der Host kommt aus `$request->getHost()`, nicht aus einem Header, den
  der Aufrufer wählt.
* **Pfad-Normalisierung** ist auf beiden Seiten dieselbe Funktion
  (`normalizePath()`), gerufen beim Erzeugen mit dem Panel-Pfad und beim Prüfen
  mit `getPathInfo()`. Damit gibt es keine Fassung des Pfades, die nur auf einer
  Seite stimmt.
* **Ablauf** 30 Minuten (`ad-slots.editor_token_minutes`), geprüft **vor** dem
  Signaturvergleich; `max(1, …)` schließt 0 und negative Werte aus.
* **Nicht signiert** sind die übrige Query und `ad_editor_page_type`. Begründet
  und tragbar: der Modus ist lesend, der Seitentyp steuert allein die Slot-Liste
  im Panel, und ein unbekannter Wert wird verworfen (`AdPageType::fromRoute()`
  hat Vorrang, siehe `layouts/sun.blade.php:112`).
* **Schreibende Zugriffe mit Token** werden mit 405 abgewiesen
  (`AdEditorMode.php:44`) — der Modus ist GET.
* **Fauler oder abgelaufener Token ergibt keine Fehlerseite**, sondern die
  normale Seite. Das ist eine Produktentscheidung (#6, Akzeptanz 5) und kein
  Befund: der Modus verleiht keine Rechte, er nimmt nur welche weg (keine
  Werbung, kein Tracking, noindex).
* **Keine Benutzerbindung.** Wer die Adresse innerhalb von 30 Minuten hat, sieht
  die Vorschau. Getragenes Restrisiko **R1**: die Vorschau zeigt ausschließlich
  öffentliche Portalinhalte plus leere Anker-Rahmen; sie legt keine Belegung,
  keine Publisher-ID und keinen Anzeigenblock offen (im Editor-Modus rendert
  **kein** Provider, `Sun\AdSlot.php:154`). Eine Sitzung wäre der falsche Weg:
  Laravels Cookie ist `SameSite=lax` und kommt im fremden iframe nicht an
  (`EditorToken.php:21-30`).
* **APP_KEY fehlt** → `RuntimeException` beim Erzeugen *und* beim Prüfen, also
  kein stiller Rückfall auf einen leeren Schlüssel (`EditorToken.php:159-168`).
  Der Go-Live-Check prüft das Feld zusätzlich (`ads:golive:check`).

## 2. Origin-Check der postMessage-Brücke — ok

Geprüft: `resources/views/themes/sun-v2/js/ad-editor.js`,
`resources/js/filament/ad-editor-bridge.js`, `tests/Feature/Ads/AdSlotEditorFrameTest.php`.

| Richtung | Senden | Empfangen |
| --- | --- | --- |
| Portal → Panel | `window.parent.postMessage(…, origin)` mit `origin` aus `data-ad-editor-origin`; leer ⇒ es wird **nicht** gesendet (`ad-editor.js:74-80, 199`) | `event.origin !== origin` ⇒ verworfen, ohne Antwort (`ad-editor.js:154`) |
| Panel → Portal | `rahmen.contentWindow.postMessage(…, this.origin)`; `origin === null` ⇒ nicht gesendet (`ad-editor-bridge.js:142-153`) | `this.origin === null \|\| event.origin !== this.origin` ⇒ verworfen (`ad-editor-bridge.js:108`) |

* **Nie `'*'`** als `targetOrigin`, auf keiner der beiden Seiten.
* **Nie `*` als akzeptierte Herkunft**: fehlt die Herkunft, läuft die Brücke gar
  nicht an, statt sich zu öffnen — fail-closed.
* Die Herkunft wird aus `config('ad-slots.editor_origin')` über `parse_url()`
  auf Schema, Host und Port verengt (`EditorToken::origin()`); ein Pfad oder
  Müll im Wert ergibt `''` und damit „keine Brücke“.
* **Nutzlast wird nicht vertraut**: beide Seiten prüfen `typeof === 'object'`,
  den `type` gegen eine feste Liste und die `catalogVersion`. Aus der Nachricht
  entsteht nie Markup — nur `dataset`-Werte, `classList.toggle()` und
  `textContent` (`ad-editor.js:125-151`), also kein DOM-Injektionsweg.
* Panel-Seite: aus `slots:ready` werden ausschließlich `string`-Schlüssel
  übernommen (`ad-editor-bridge.js:134-136`), bevor sie als Livewire-Ereignis
  weitergehen.
* **Kein Inline-Skript.** Die Brücke ist ein registriertes Filament-Asset
  (`AppServiceProvider.php:108`) und eine Alpine-Komponente; `script-src` hat
  kein `'unsafe-inline'`.

Getragenes Restrisiko **R2**: `frame-ancestors` steht bewusst nicht in
`config/csp.php` (das einbettbare Bewertungs-Widget braucht `*`, siehe
`docs/ad-slots.md` 9.3). Eine Portalseite ist damit von jeder Herkunft
einbettbar — Zustand vor diesem Modul, nicht durch es verursacht. Für den
Editor-Modus ist das ohne Wirkung: Klicks im Rahmen werden abgefangen
(`event.preventDefault()`), und die Antwort auf einen Klick geht nur an die
konfigurierte Herkunft.

## 3. HtmlProvider-Sandbox — ok

Geprüft: `app/Ads/Providers/HtmlProvider.php`,
`app/Ads/Http/Controllers/AdHtmlController.php`,
`app/Ads/Http/Controllers/AdUnitPreviewController.php`.

* Fremdes Markup kommt **nie** in das DOM der Portalseite und nie in das DOM des
  Panels. Ausgegeben wird nur ein `<iframe>` (`HtmlProvider.php:144`).
* **Zwei unabhängige Grenzen, beide ohne `allow-same-origin`:**
  `sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"` als
  Attribut am Element **und** derselbe Wert als `Content-Security-Policy:
  sandbox …` im Antwort-Header der Zustellroute. Fällt eine aus, hält die
  andere. `allow-scripts` + `allow-same-origin` wäre gleichbedeutend mit „keine
  Sandbox“ und kommt in keiner der beiden Fassungen vor.
* **Kein `srcdoc`.** `srcdoc` erbt die CSP des Elterndokuments; das Markup liefe
  unter der Portal-CSP und bräuchte ein Nonce. Die eigene Route setzt ihre
  eigene CSP (`AdHtmlController.php:49`).
* **`frame-ancestors 'self'`** auf beiden HTML-Antworten: der Rahmen lässt sich
  nicht von einer fremden Domain einbinden.
* **Route ohne Session und ohne CSRF** (eigene Middleware-Gruppe in
  `routes/tenant.php:177-188`, nicht `web`): im Rahmen gibt es kein
  Session-Cookie, das das Markup lesen könnte. Dazu
  `Cross-Origin-Resource-Policy: same-site`, `Referrer-Policy: no-referrer`,
  `referrerpolicy="no-referrer"` am Element und `X-Robots-Tag: noindex, nofollow`.
* **Nur aktive HTML-Werbemittel** werden zugestellt (`provider = html` und
  `is_active`, `AdHtmlController.php:37-44`) — ein abgeschaltetes Werbemittel
  verschwindet auch aus Rahmen, die noch im Browser stehen.
* **Keine Filterung des Markups.** Bewusst: Sanitizing bricht legitime
  Werbemittel und erzeugt nur das Gefühl von Sicherheit. Die Grenze ist die
  Sandbox, der Zugang zu `ad_units` ist auf Betreiber beschränkt (§4).
  Obergrenze 20 kB, in **Bytes** gezählt (`HtmlProvider::MAX_BYTES`).
* **Feste Größe, keine Höhenverhandlung per postMessage** — der Rahmen kann das
  Layout nicht nachträglich verschieben; `width`/`height` sind Pflichtfelder
  mit `min:1|max:4000`.
* Die Panel-Vorschau (`admin.ads.unit-preview`) liegt auf der zentralen Domain,
  prüft `AdsAdminAccess::allowed()` und antwortet `Cache-Control: no-store,
  private`. Sie zeigt auch Entwürfe — deshalb ist sie, anders als die
  Portalroute, nicht öffentlich.

Getragenes Restrisiko **R3**: `portal.ads.html` ist öffentlich und liefert jedes
aktive HTML-Werbemittel unter jeder Portal-Domain aus, auch eines, das auf
diesem Portal nicht belegt ist. Das Werbemittel ist ohnehin für Besucher
bestimmt; eine Scope-Prüfung je Anfrage hieße, die Belegung bei jedem
Rahmen-Abruf neu aufzulösen. Gegen Massenabruf steht `throttle:240,1`.

## 4. Admin-only auf allen neuen Flächen — ok, S1 behoben

Hürde ist überall dieselbe wie beim Bot-Schutz: Administrator **und** Recht
`update settings` (`AdsAdminAccess::PERMISSION`). Das ist keine Formsache — wer
`ad_units` anlegen darf, trägt bei `provider = html` fremdes Markup ein.

| Fläche | Prüfung |
| --- | --- |
| Filament-Seite *Werbeplätze* | `AdPlacementEditor::canAccess()` |
| Filament-Seite *Einstellungen › Werbung* | `AdSettings::canAccess()` |
| Resource *Werbemittel* | `AdUnitResource::canAccess()` |
| Resource *Belegungen* | `AdPlacementResource::canAccess()` + `canCreate()` |
| Livewire *AdPlacementPanel* | `boot()` (jede Anfrage) + `speichern()`, `statusWechseln()`, `createAdUnit()` |
| Route `admin.ads.unit-preview` | `middleware('auth')` + `AdsAdminAccess::allowed()` im Controller |

**S1 (niedrig, behoben).** Das Konfigurationspanel prüfte den Zugang in
`mount()` und in den schreibenden Methoden, nicht aber in `bearbeiten()` und
`placements()`. `mount()` läuft genau einmal; ein Livewire-Update kommt mit
einem Schnappschuss und hätte die lesenden Methoden ohne Prüfung erreicht.
Behoben durch `AdPlacementPanel::boot()`, das bei **jeder** Anfrage prüft; die
Prüfungen in den schreibenden Methoden bleiben als zweite Grenze stehen.

* Keine der Werbe-Flächen hängt am Dashboard-Panel der Kunden — alles liegt im
  Admin-Panel auf der zentralen Domain.
* Die Portal-Auswahl im Panel (`AdsAdminPortal`) ist eine Sitzungseinstellung
  des Betreibers, kein Mandantenwechsel; sie verleiht keine Rechte.
* Das alte Backoffice `/verwaltung/werbung` (Gen 1, `VerwaltungAdController`)
  bleibt unverändert und ist nicht Teil dieses Moduls; es verschwindet mit dem
  Abschalten des Altsystems (`docs/ad-slots.md` 11.1).

## 5. Keine Tracking-Skripte und keine Anzeigen im Editor-Modus — ok

| Was | Wo abgeschaltet |
| --- | --- |
| Analytics / GA4 / `config('app.tracking_scripts')` | `layouts/sun.blade.php:87` (`@if(! $adEditor) @include('partials.analytics')`) |
| Cookie-Hinweis | `layouts/sun.blade.php:142` |
| Cloudflare Turnstile | `ResolvesTurnstileConfig.php:60` |
| Werbung des Altsystems (inkl. Auto Ads, Haftband) | `LegacyAdSlot::suppress()` in `AdEditorMode.php:53` |
| Auto Ads des neuen Systems | `Sun\AutoAds::allowedHere()` (`AutoAds.php:119`) |
| Jeder Provider (AdSense **und** HTML) | `Sun\AdSlot.php:154` — im Editor rendert der Anker nur seinen Rahmen |
| Indexierung | `X-Robots-Tag: noindex, nofollow` (Middleware) **und** `<meta name="robots">` mit Vorrang vor SeoService (`layouts/sun.blade.php:29-31`) |

* **Kein Impression auf einer Vorschauseite.** Das ist nicht nur Hygiene: ein
  Impression ohne echten Nutzer ist bei Google ein ungültiger Vorfall und
  gefährdet das Konto.
* Der Editor-Modus hängt per `scoped` am Container, nicht an der Sitzung
  (`Support/AdEditorMode.php:32`). Damit klebt er nicht über die Anfrage hinaus
  — in einem Langläufer (Octane, Queue-Worker, Smoketest) würde `instance`
  sonst jede folgende Anfrage im Editor-Modus rendern und ausgelieferte Seiten
  ohne Werbung und mit `noindex` zeigen.
* Das Editor-Bundle wird allein im Editor-Modus eingebunden
  (`layouts/sun.blade.php:92-94`); ein normaler Besucher lädt davon kein Byte.

Abgrenzung: dass `config('app.tracking_scripts')` im **Normalbetrieb** vor der
Einwilligung ausgeliefert wurde, war ein eigener Befund außerhalb dieses
Moduls und ist mit **#28** behoben — der Block läuft jetzt wie die Werbung
erst nach der Einwilligung (`App\Analytics\TrackingScriptsConsent`,
`resources/views/components/tracking-scripts.blade.php`,
`resources/js/consent-scripts.js`).

## 6. Content-Security-Policy — ok

Geprüft: `config/csp.php`, `app/Http/Middleware/ContentSecurityPolicy.php`,
`app/Services/Security/CspNonce.php`.

* Eine Stelle für alle Portale, Modus über `CSP_MODE` (`off|report|enforce`),
  Vorgabe inzwischen `enforce` (`config/csp.php:48`). `ads:golive:check` meldet
  alles außer `enforce` als WARN bzw. FAIL.
* Freigegeben sind genau die Google-Hosts, die die Browser-Messung gezeigt hat
  (`script-src`, `frame-src`, `connect-src`, dazu `img-src https:` für die
  Zählpixel) — keine Wildcard auf `*.google.com`.
* **Kein `'unsafe-inline'` in `script-src`.** `adsbygoogle.push({})` läuft nicht
  als Inline-Schnipsel, sondern im Modul `resources/js/ads.js` an den
  `data`-Attributen des Ankers (`AdSenseProvider`, Klassendoc).
* Der Loader wird mit `urlencode($publisherId)` zusammengesetzt, alle Attribute
  der ausgegebenen Tags laufen durch `e()` (`AbstractAdProvider::attributes()`);
  die Publisher-ID ist zusätzlich auf `^(ca-)?pub-\d{16}$` verengt, die
  Anzeigenblock-ID auf `^\d{6,20}$`. Damit gibt es keinen Weg, aus einem
  Werbemittel Markup oder ein Attribut auszubrechen.
* Die Middleware **überschreibt keinen vorhandenen** CSP-Header. Darum behalten
  `portal.ads.html` und `admin.ads.unit-preview` ihre eigene, strengere CSP —
  genau die Eigenschaft, auf der die Sandbox in §3 aufbaut.
* CSP-Berichte laufen auf `/csp-bericht` mit `throttle:60,1`; `report-uri` ist
  nicht Teil dieses Moduls.

## 7. Einwilligung — ok

Geprüft: `app/Ads/Support/AdConsent.php`, `app/View/Components/Sun/AdSlot.php`,
`resources/js/ads.js`, `resources/js/consent-adapter.js`,
`tests/Feature/Ads/ConsentCookieTest.php`.

* Vor der Einwilligung geht **kein** Request an Google: das Fragment liegt in
  einem `<template data-ad-fragment>`, der Loader in einem `<template
  data-ad-head>`. Ein `<template>` ist inert — der Browser lädt nichts daraus.
* Gelesen wird ausschließlich `request()->cookie()`, ohne Rückfall auf
  `$_COOKIE`; zwei Lesewege für denselben Wert hätten genau den Fehler verdeckt,
  den die `EncryptCookies`-Ausnahme aus #21 behebt.
* Drei Zustände (`granted` / `pending` / `denied`) statt zwei. `pending`
  reserviert die Höhe, weil die Einwilligung im selben Besuch noch kommen kann;
  erst `denied` klappt ein (`ad-slots.consent.on_denied`).
* HTML-Werbemittel galten hier zunächst als einwilligungsfrei
  (`requiresConsent() === false`) — Begründung: der Sandbox-Rahmen hat keinen
  Cookie- und keinen Speicherzugriff. **Das war der Befund S4 (#36) und ist
  korrigiert.** Die Sandbox trennt Origins, nicht Netzwerkziele: `allow-scripts`
  ist gesetzt, ein Skript im Rahmen darf jeden Host ansprechen und dabei
  IP-Adresse, Browser-/Gerätedaten und Zeitpunkt übertragen — einwilligungs-
  pflichtig nach § 25 Abs. 1 TDDDG, Art. 6 Abs. 1 lit. a DSGVO. Seit #36
  entscheidet der Schalter `config_json->third_party` je Werbemittel
  (Vorgabe und Antwort bei fehlender Angabe: Einwilligung nötig); nur ein
  ausdrücklich als drittanbieterfrei markiertes Eigenbanner lädt vor der
  Einwilligung (`docs/ad-slots.md` 5.2 Regel 8, 9.4).
* Der Nachweis „vorher/nachher“ als Netzwerk-Mitschnitt läuft als Ticket #25 und
  wird in `docs/ad-slots.md` 9.5 eingetragen; der Datenschutzabschnitt
  „Werbung mit Google AdSense“ als #26.

## 8. ads.txt — ok, mit Rollout-Befund S2

Geprüft: `app/Http/Controllers/AdsTxtController.php`,
`app/Ads/Support/AdsTxtBuilder.php`, `app/Ads/Support/AdSensePublisherId.php`.

* Inhalt ausschließlich aus `ad_settings` der **Central**-DB, je Portal gecacht;
  der Schlüssel wird mit den aufgelösten Belegungen vergessen
  (`AdCache::forgetTenant()`), eine Änderung im Panel greift also sofort.
* Zeilen aus `ads_txt_extra` werden auf Leerzeilen und Kommentare gefiltert und
  entdoppelt; ausgegeben wird `text/plain` — kein Markup-Weg.
* **404 statt leerer Antwort**, wenn nichts zu sagen ist. Eine leere `ads.txt`
  liest Google als „diese Domain hat keinen autorisierten Verkäufer“ und
  schaltet Anzeigen ab; eine fehlende Datei ist derselbe Zustand wie vor dem
  Umstieg.
* Die Route steht **vor** den Catch-all-Routen des Portals, sonst verschluckt
  die Stadtseite den Aufruf (`routes/tenant.php:130`).

**S2 (mittel, kein Code-Fehler — Rollout-Befund, Maßnahme in der
Go-Live-Checkliste).** `/ads.txt` kommt nach dem Deploy aus der Central-DB. Für
jedes Portal, dessen `ads_txt_content` noch in der **Tenant**-DB steht und
dessen `ads_txt_extra` zentral leer ist, verliert die Domain damit im Moment des
Deploys ihre Google-Zeile — ohne dass im Frontend etwas auffällt, während das
Altsystem dort weiter Anzeigen ausliefert. Gemessen am 16.10.2026 auf der
Spiegel-DB: **13 von 23 Portalen** betroffen. Maßnahme: `ads:legacy-takeover`
**vor** dem Deploy (er schreibt `ads_txt_content` → `ads_txt_extra`), danach
`ads:golive:check` — die Prüfung vergleicht beide Fassungen zeilenweise und
meldet jede verlorene Zeile als FAIL. Siehe `docs/ad-slots-golive.md` §2.

## 9. Cache, Mandantentrennung und Nebenwirkungen — ok

* Der Cache ist in diesem Projekt **nicht** mandantengetrennt. Deshalb steht der
  Tenant im Schlüssel (`ads:v{katalog}:{tenant}:{seitentyp}`,
  `AdCache::key()`); ohne ihn sähen Portale fremde Belegungen.
* Geleert wird in **beiden** Kontexten (zentral und per `$tenant->run()`), weil
  der file-Store je Portal wechselt (`AdCache::forgetTenant()`).
* Die Katalogversion steht im Schlüssel: eine Änderung an `config/ad-slots.php`
  entwertet alle Einträge, ohne dass irgendwo geleert werden muss.
* Im Editor-Modus wird weder gelesen noch geschrieben (`fresh`), sonst zeigte
  die Vorschau einen alten Stand.
* `AdCache::forgetAll()` ist teuer (ein Kontextwechsel je Portal) und läuft nur
  aus dem Job `FlushAdCache`, nie im Request.
* `ads_enabled` ist **fail-closed**: fehlt die Zeile in `ad_settings` oder steht
  der Schalter auf `false`, spielt der Resolver in diesem Portal nichts aus,
  egal welche Belegungen netzweit aktiv sind. Vorgabe des Seeders ist `false`.
* Die Werbemittel-ID in der Route ist auf `[0-9]+` verengt; alle
  Panel-Einstiegspunkte nehmen `int`, der Katalogschlüssel wird gegen
  `AdSlotCatalog::exists()` geprüft.

## 10. Was dieses Review ausdrücklich nicht abdeckt

* Das **Altsystem** (Gen 1): `App\View\Components\AdSlot`, `ad_slots` je
  Tenant-DB, `/verwaltung/werbung`, der Nonce-Weg über `CspNonce`. Es verschwindet
  mit dem Abschalten (`docs/ad-slots.md` 11.1) und wird dabei nicht verändert.
* `config('app.tracking_scripts')` ohne Einwilligung im Normalbetrieb → in **#28** behoben.
* Die Routen-Dimension des Katalogs (`guide_index_sidebar_sticky` auf
  `/ratgeber` stumm) → **#27**. Kein Sicherheitsthema.
* Googles eigene Auslieferung: welche Cookies AdSense setzt und was der
  Google-CMP tut, ist nicht prüfbar und nicht beeinflussbar. Die Grenze dieses
  Moduls ist „kein Request vor der Einwilligung“ (§7).
* Inhaltliche Prüfung eingetragener HTML-Werbemittel (§3, bewusst keine
  Filterung) — das ist eine Betriebsregel, keine technische Grenze.

## 11. Befunde und Stand

| Nr. | Befund | Schwere | Stand |
| --- | --- | --- | --- |
| S1 | `AdPlacementPanel` prüfte den Zugang nur in `mount()` und in den schreibenden Methoden | niedrig | **behoben** — `boot()` prüft jede Anfrage (§4) |
| S2 | `/ads.txt` aus der Central-DB kappt beim Deploy die Google-Zeile von 13 Portalen, deren Zeilen noch in der Tenant-DB stehen | mittel | **behoben durch Verfahren** — `ads:legacy-takeover` vor dem Deploy, `ads:golive:check` als Nachweis (§8, Go-Live §2) |
| R1 | Editor-Token ohne Benutzerbindung, 30 Minuten gültig | — | getragen, begründet (§1) |
| R2 | `frame-ancestors` netzweit offen (Bewertungs-Widget) | — | getragen, Zustand vor diesem Modul (§2) |
| R3 | `portal.ads.html` liefert jedes aktive HTML-Werbemittel unter jeder Portal-Domain | — | getragen, begründet (§3) |

Keine offenen Punkte aus Runde 1. Die Befunde der Impression-Zählkette
(Runde 2, #39) stehen in §13.6: **S3** offen, **S4** und **S5** behoben,
**P1** als Leistungspunkt offen.

## 12. Nachweis

```
php artisan ads:golive:check          # Globales, je Portal, ads.txt-Vergleich
vendor/bin/phpunit tests/Feature/Ads tests/Feature/Filament/Admin/Page/AdPlacementEditorTest.php
vendor/bin/phpstan analyse app/Ads app/Console/Commands/AdsGoLiveCheck.php
php scripts/secret-scan.php
```

Stand 16.10.2026: 40 Tests / 99 Assertions grün, PHPStan ohne Befund,
`ads:golive:check` mit 13× FAIL aus S2 (erwartet, solange `ads:legacy-takeover`
nicht gelaufen ist).

---

## 13. Impression-Zählkette — geprüft am 16.10.2026 (#39, DoD aus #12)

Nachgezogen aus der Definition of Done von #12, abgetrennt von #37 (dessen
Abnahme braucht sieben Tage echte Daten). Geprüft wurde der Zustand, der seit
dem Deploy vom 16.10.2026 auf der Produktion **echt zählt**
(`docs/messungen/werbeplaetze-golive/impression-abnahme-2026-10-16.txt`).

Umfang: `resources/js/ads.js`, die Route `portal.ads.impressions` in
`routes/tenant.php`, `app/Ads/Http/Controllers/AdImpressionController.php`,
`app/Ads/Support/AdImpressionRecorder.php`,
`app/Ads/Jobs/AggregateAdImpressions.php`,
`app/Console/Commands/AdsAggregateImpressionsCommand.php`,
`app/Ads/Support/AdImpressionStats.php`,
`app/Filament/Admin/Widgets/AdImpressionsWidget.php` und die beiden
Migrationen `ad_impression_daily` / `ad_impression_buffer`.
Architektur: `docs/ad-slots.md` §14.

### 13.1 Datenschutzaussage — trägt, Formulierung war zu weit (S5)

Die Schlussfolgerung „nichts Personenbezogenes in den Zähltabellen, deshalb
einwilligungsfrei" **hält**:

* Es gibt keine Tabelle mit einer Zeile je Ereignis. Die kleinste
  gespeicherte Einheit ist ein Zähler über eine Stunde, ein Portal, einen
  Anker, eine Belegung und eine Geräteklasse — aus 132 Impressionen in einer
  Stunde über 42 Zeilen ist kein Einzelbesuch ablesbar (Produktionsstand
  16.10.2026, 12:26 UTC).
* Der Rumpf des Beacons führt nur Katalogschlüssel, Belegungs-Id und
  `mobile|desktop`. Kein Wiedererkennungsmerkmal, kein Zeitstempel je
  Ereignis, kein Hash.
* Das Portal kommt aus der Domain (`tenant()`), nie aus dem Rumpf — eine
  Meldung kann kein fremdes Portal treffen.
* Die Route läuft ohne `'web'`: keine Session-Middleware, kein
  `EncryptCookies`, kein CSRF. Die Antwort trägt kein `Set-Cookie` (Nachweis
  in der Abnahme, Punkt 5, zwei Portale).
* Gezählt wird erst **nach** dem Einsetzen des Werbemittels
  (`setzeSlotEin()`), im Editor-Modus (Komponente **und** `body`-Flag) gar
  nicht, und bei `AdFree` entsteht kein Container.

Zwei Sätze in `docs/ad-slots.md` §14.2 waren trotzdem zu weit gefasst und mit
einem Blick in die DevTools widerlegbar:

* **„kein Cookie".** `navigator.sendBeacon()` schickt seinen Request nach
  Spezifikation mit credentials mode *include*. Der Browser legt also die
  Portal-Cookies bei (`cookie_consent_*`, auf Seiten mit Session auch deren
  Cookie). Richtig ist: die Anwendung **liest und speichert keines** — auf der
  Route steht keine Cookie- oder Session-Middleware, und die Antwort setzt
  keines.
* **„keine IP".** In den Zähltabellen steht keine, im nginx-Zugriffslog steht
  sie wie bei jedem Request (`/home/sanitaerfinden/logs/nginx`, 7 Tage,
  logrotate) — und `throttle` braucht sie im Schlüssel. Richtig ist: keine IP
  **in den Zähldaten**.

Beide Sätze sind in dieser Runde präzisiert (`docs/ad-slots.md` §14.2). Die
rechtliche Bewertung ändert sich dadurch nicht.

### 13.2 Manipulierbarkeit von außen — Lücke (S4, mittel) — behoben mit #42

Der Endpunkt ist offen, ohne CSRF und ohne Auth; das ist für ein Beacon
richtig. Geprüft wurde, was ein präparierter Aufruf anrichten kann:

* `slot_key` wird gegen den Code-Katalog geprüft (`AdSlotCatalog::exists()`),
  `device` gegen `mobile|desktop`, der Rumpf auf 8 kB, die Meldungen auf
  `max_entries` (20). Belegt: der Probe-Aufruf der Abnahme mit
  `abnahme_probe_37` landete in keiner Zeile.
* **Nicht** geprüft wird, ob die gemeldete `placement_id` überhaupt
  existiert, zu diesem Portal und zu diesem Anker gehört — und ob der Anker
  im Theme dieses Portals steht. Der Wert geht ungefiltert aus dem Rumpf in
  den Schlüssel des Zählers (`AdImpressionRecorder::normalize()`).
* Die Entdoppelung wirkt nur **innerhalb eines Beacons**. Mit dem Limit der
  Route sind damit 60 × 20 = **1 200 Impressionen je Minute und IP**
  erreichbar — und weil `placement_id` frei wählbar ist (0 … 2^64), ebenso
  **1 200 neue Zeilen je Minute** in Puffer und Tagesaggregat, die dort 400
  Tage stehen bleiben. Der Satz in §14.2 „ein präparierter Rumpf kann den
  Zähler nicht hochtreiben" stimmt so nicht; er ist korrigiert.
* Es gibt keinen Herkunftstest (`Origin`, `Sec-Fetch-Site`). Ohne CSRF kann
  eine fremde Seite per Formular-POST mit `text/plain` zählen — ohne
  Preflight, also ohne dass CORS es verhindert.

Das Projekt hat für genau diesen Fall ein Muster: `StatsBeaconController`
prüft die gemeldete Id mit `Company::active()->whereKey($id)->exists()`, bevor
er zählt. Der Recorder sollte dasselbe tun (Belegung existiert, ist dem Portal
zugeordnet, steht auf diesem Anker; unbekannt → `0`, was dort schon
„Belegung nicht mehr bekannt" heißt) und dazu einen Herkunftstest bekommen.
Das deckelt beides: die Werte **und** die Zeilenzahl.

Kein Datenschutzthema (es entstehen keine Personendaten), aber ein
Datenqualitätsthema: die Zahlen des Widgets sind die Grundlage für
`paused`-Entscheidungen nach §13.3.

**Behoben mit Ticket #42** (`docs/ad-slots.md` §14.2):

* `AdImpressionRecorder::normalize()` prüft die gemeldete `placement_id` gegen
  die Belegungen dieses Portals auf diesem Anker (`AdPlacement::active()` +
  `forTenant()`, gruppiert nach `slot_key`). Ein unbekannter Wert wird zu `0`
  statt zu einer eigenen Zeile — verworfen wird die Meldung nicht, weil eine
  Belegung zwischen Auslieferung und Beacon pausiert worden sein kann.
  Die Zeilenzahl ist damit auf Anker × Gerät × (echte Belegungen + 1)
  gedeckelt; mit dem Stand vom 16.10.2026 (12 aktive Belegungen) sind das
  Größenordnungen unter den vorher möglichen 1 200 neuen Zeilen je Minute.
* Die Liste der gültigen Ids liegt je Portal im Cache
  (`AdCache::impressionPlacementsKey()`,
  `ad-slots.impressions.placement_cache_ttl`, Vorgabe 300 s, und von
  `AdCache::forgetTenant()` mit vergessen). Der Beacon-Weg bleibt damit ein
  Statement plus — höchstens einmal je fünf Minuten und Portal — ein
  Index-Lookup. Absichtlich `active()` statt `deliverable()`: ein in derselben
  Sekunde abgelaufener Zeitraum darf eine echte Impression nicht zur
  Null-Belegung machen.
* `AdImpressionController::sameOrigin()` ist der Herkunftstest:
  `Sec-Fetch-Site` muss `same-origin` sein; fehlt der Header, muss der Host
  aus `Origin` die Portal-Domain sein; fehlen beide, wird verworfen. Die
  Antwort bleibt in jedem Fall `204`. Ein Formular-POST einer fremden Seite
  schickt `Sec-Fetch-Site: cross-site` und kommt damit nicht mehr durch.
  Nebeneffekt für die Nachmessung: ein `curl`-Aufruf von Hand braucht jetzt
  `-H 'Origin: https://<portal-domain>'`.

Nicht mit abgedeckt bleibt, ob der Anker im Theme dieses Portals überhaupt
steht — das ist eine Katalog-/Theme-Frage (§13.2, erster Punkt) und ohne
Wirkung auf Werte oder Zeilenzahl: eine Belegung muss trotzdem existieren und
auf genau diesem `slot_key` stehen.

### 13.3 `throttle:60,1` war hinter Cloudflare kein Limit je Besucher — behoben (S3, mittel)

Der Kommentar an der Route las das Limit als „je IP, je Besucher". Am
Prüftag war es das nicht:

* `real_ip_header X-Forwarded-For` war in `/etc/nginx/nginx.conf`
  **auskommentiert** (Zeile 28), und die Anwendung hat kein `TrustProxies`
  (bestätigt im Kommentar in
  `app/Http/Middleware/ContentSecurityPolicy.php:175`).
  `$request->ip()` war damit die Adresse, die nginx sieht.
* Im Zugriffslog standen für die Beacons Cloudflare-Adressen
  (`162.158.*`, `172.69/70/71.*`, `104.2*.*`). Der Zähl-Eimer galt also je
  **Cloudflare-Edge**, nicht je Besucher — und eine einzelne Edge-Adresse
  fuhr bis zu **242 Requests je Minute** gegen den Ursprung
  (`104.22.24.202`, 16.10.2026 13:31–13:38).
* Beacons lagen weit darunter (122 Stück in 14 Stunden, höchstens
  4 je IP und Minute) — bei 12 aktiven Belegungen. Die Reserve schrumpft mit
  jeder weiteren Belegung, und ein 429 ist unsichtbar: `sendBeacon` wertet
  keine Antwort aus, die Impressionen fehlen einfach.
* Einige Portale kommen nicht über Cloudflare (im Log echte Besucheradressen,
  z. B. `84.171.*`, `217.246.*`). Dort wirkte das Limit je Besucher. Die
  Bemessung war also netzweit uneinheitlich.

**Behoben mit #43 am 16.10.2026, 14:54 Uhr** — außerhalb dieses Moduls, weil
es auf jedes Limit und jede IP-Prüfung der Anwendung wirkt (Turnstile,
`BotTraffic`, Leads, `tracking_events`). Gewählt ist der nginx-Weg, nicht
`TrustProxies`: im äußeren 443-Block des Portal-vhosts stehen jetzt die
Cloudflare-Netze (`/etc/nginx/cloudflare-ips.conf`) und
`real_ip_header CF-Connecting-IP`; das vorhandene
`proxy_set_header X-Real-IP $remote_addr` trägt die echte Adresse an den
inneren Block und damit an PHP. Portale ohne Kante liefern weiter ihre
Absenderadresse — die Bemessung ist netzweit einheitlich. Ablauf, Proben und
Nebenwirkungen in **`docs/echte-besucher-ip.md`**, hergestellt von
`scripts/nginx-echte-besucher-ip.sh`.

Der Rest davon — wer selbst aus einem Cloudflare-Netz kommt, kann
`CF-Connecting-IP` weiter setzen — ist #45: host-gebundene Authenticated
Origin Pulls, `scripts/nginx-origin-pull-absichern.sh`, beschrieben in
`docs/echte-besucher-ip.md` §6. Scharf schalten kann nur Enes, weil die
Zone den Schalter braucht; eine Firewall auf die Cloudflare-Netze (Weg 1)
hilft gegen genau diesen Angreifer nicht, er sitzt in diesen Netzen.

Dabei ist ein **schwererer** Fehler aufgefallen, der in der Runde #39 nicht
gesehen wurde (S6, siehe §13.8).

### 13.4 Verdichtung und Puffer — ok

Lokal gegengefahren (MySQL 9.3, Puffer von Hand mit drei Stunden gefüllt):

* Verdichtet wird nur, was vor `startOfHour()` liegt: 11 von 18 Impressionen
  gingen ins Tagesaggregat, die laufende Stunde blieb liegen.
* Ein zweiter Lauf unmittelbar danach meldet `{"rows":0,"impressions":0}` —
  nichts verdoppelt.
* `insert … on duplicate key update impressions = impressions + values(…)`
  zählt hoch statt zu setzen; die Schlüsselspalten sind alle `not null`,
  die NULL-Falle im Unique-Index ist vermieden.
* Das Löschen je Gruppe (`id <= max_id`, ohne Tagesfilter) ist trotz der
  `limit CHUNK` sicher: für dieselbe Kombination aus Portal, Anker, Belegung
  und Gerät wächst die Id mit der Stunde, und die Gruppen werden nach
  `max_id` aufsteigend abgearbeitet — eine ältere Gruppe derselben
  Kombination liegt also immer **im selben** Durchgang und kann nicht
  übersprungen und dann mitgelöscht werden.
* `ShouldBeUnique` + `onOneServer` + `lockForUpdate()` auf der Auswahl;
  `select … group by … for update` wird von MySQL 9.3 und MariaDB 11.4
  angenommen.
* Produktionsstand 16.10.2026 12:26 UTC: alle 42 Pufferzeilen in der
  laufenden Stunde, `ad_impression_daily` leer — korrekt, der erste
  Scheduler-Lauf steht um 13:07 UTC. Queue ist `redis`, Horizon läuft unter
  Supervisor, der `Schedule::job()` wird also abgearbeitet.

Ein Hinweis ohne Befund: `VALUES()` auf der rechten Seite ist seit MySQL 8.0.20
abgekündigt (MariaDB 11.4, die Produktion, kennt nur diese Form). Beim Umzug
auf MySQL wird daraus die Alias-Form `as new … new.impressions`.

Zeitrechnung: `hour` und `day` sind UTC-Werte (`APP_TIMEZONE` ist auf der
Produktion nicht gesetzt, `config('app.timezone')` = `UTC`). Ein „Tag" im
Widget ist damit ein UTC-Tag, gegen die Wahrnehmung des Betreibers um zwei
Stunden verschoben. Für Anker-Vergleiche irrelevant, für
Tagesgenau-Vergleiche gut zu wissen.

### 13.5 Beobachter im Browser — ok

* Ein Beobachter für alle Anker, feste Stufen, Schwelle und Haltezeit aus
  `data-ad-track` (`config/ad-slots.php`) — eine Änderung der Messvorgabe
  braucht keinen Asset-Neubau.
* Aus dem Blick, bevor die Sekunde um ist → `clearTimeout`, nicht gezählt;
  nach dem Zählen `unobserve`.
* Je Seitenaufruf genau eine Meldung je `slot:placement` (`gemeldet`-Set).
  Das kann keine Impression verschlucken, weil die Portale keine
  SPA-Navigation verwenden (kein `wire:navigate` im Theme sun-v2, `initAds()`
  läuft nur beim Dokumentaufbau).
* Gebündelt mit 3 s Verzug, Rest beim `visibilitychange`/`pagehide`;
  `sende()` setzt die Frist zuerst zurück, ein Doppelversand ist nicht
  möglich. Ohne `IntersectionObserver` wird bewusst nicht gezählt.
* Gemeldet wird das **tatsächliche** Gerät (`matchMedia`), nicht die
  Gerätewahl des Ankers — nötig für Belegungen mit `device = all`.

### 13.6 Befunde

| Nr. | Befund | Schwere | Stand |
| --- | --- | --- | --- |
| S3 | `throttle:60,1` wirkte hinter Cloudflare je Edge-Adresse statt je Besucher (kein `real_ip_header`, kein `TrustProxies`); ein 429 verliert Impressionen unsichtbar | mittel | **behoben** (#43) — `real_ip_header CF-Connecting-IP` im äußeren vhost-Block, Netzliste eng; `docs/echte-besucher-ip.md` |
| S6 | `set_real_ip_from 0.0.0.0/0` bei auskommentiertem `real_ip_header`: nginx nahm die Vorgabe `X-Real-IP` und vertraute ihr von **jedem** Absender — `$request->ip()` war mit einem Header frei wählbar, netzweit | hoch | **behoben** (#43) — gefunden beim Umsetzen von S3, siehe §13.8 |
| S4 | `placement_id` (und der Anker-Bezug) kommen ungeprüft aus dem Rumpf: Zähler und Zeilenzahl sind von außen hochtreibbar, kein Herkunftstest | mittel | **behoben** (#42) — `placement_id` gegen die Belegungen des Portals geprüft (unbekannt → `0`), Liste je Portal gecacht, Herkunftstest über `Sec-Fetch-Site`/`Origin` (§13.2) |
| S5 | §14.2 behauptete „kein Cookie" und „keine IP" pauschal; `sendBeacon` legt Cookies bei, die IP steht im nginx-Log | niedrig | **behoben** — Formulierung präzisiert (§13.1) |
| P1 | Das Widget liest das 7-/30-Tage-Fenster ohne brauchbaren Index; bei 23 Portalen × 30 Tagen unkritisch, mit 400 Tagen Aufbewahrung wird daraus ein Full Scan | niedrig | **behoben** (#44) — Migration `…_add_covering_indexes_to_ad_impression_daily_table`: abdeckende Indizes `(day, slot_key, device, impressions)` und `(day, tenant_id, device, impressions)`, dafür `ad_impr_daily_day_tenant` (Präfix) und `ad_impr_daily_slot_day` (unbenutzt) weg (§13.7) |

### 13.7 Tragen die Indizes das Widget? — ja bei 23 × 30 Tagen, nicht bei 400 Tagen Aufbewahrung (P1)

Nachgemessen auf der lokalen zentralen DB (MySQL 9.3) mit synthetischen
Zeilen in der vollen Breite des Katalogs: 23 Portale × 32 Anker × 2 Geräte ×
2 Belegungen je Tag = 2 944 Zeilen je Tag. Gemessen sind die beiden Abfragen,
die `AdImpressionStats::build()` je Aufruf fährt (Gruppierung je Anker und je
Portal), danach wieder abgeräumt.

| Datenstand | je Anker, alle Portale, 30 Tage | je Portal, 30 Tage | ein Portal, 30 Tage | je Anker, 7 Tage |
| --- | --- | --- | --- | --- |
| 103 040 Zeilen (35 Tage) | 89 ms, Full Scan | 47 ms, Full Scan | 4,6 ms, Unique-Index | 27 ms |
| 558 041 Zeilen (190 Tage) | 165 ms, Full Scan | 129 ms, Full Scan | 5,0 ms, Unique-Index | 24 ms |
| 558 041 Zeilen, mit abdeckendem Index | **55 ms, Using index** | **52 ms, Using index** | 5,6 ms | **11 ms** |

Daraus:

* **Die Frage des Tickets ist mit Ja zu beantworten.** 23 Portale × 30 Tage
  sind rund 100 000 Zeilen; beide Abfragen laufen zusammen in gut 130 ms und
  sind 60 Sekunden gecacht. Der Portalfilter ist ohnehin schnell — dort
  greift der Unique-Index (`tenant_id, day, …`) als Präfix.
* Der Engpass ist nicht die Portalzahl, sondern die **Aufbewahrung**: keiner
  der beiden vorhandenen Indizes deckt die Abfrage ab, also wählt der
  Optimizer den Full Scan, sobald das Fenster einen nennenswerten Anteil der
  Tabelle ausmacht. Bei den vorgesehenen 400 Tagen (~1,18 Mio. Zeilen) sind
  das linear fortgeschrieben rund 350 ms + 280 ms je Widget-Aufbau.
* Ein abdeckender Index macht daraus einen Index-only-Scan:
  `(day, slot_key, device, impressions)` und
  `(day, tenant_id, device, impressions)`. Der zweite ersetzt
  `ad_impr_daily_day_tenant` (reines Präfix); `ad_impr_daily_slot_day`
  (`slot_key, day`) wird von keiner Abfrage des Moduls benutzt — die
  Anker-Auswertung filtert immer über `day` — und kann mit derselben
  Migration fallen.

Nichts davon ist heute dringend; eigenes Ticket, damit es vor dem ersten
Jahreswechsel der Daten passiert.

**Umgesetzt mit Ticket #44.** Die Migration
`2026_10_19_000009_add_covering_indexes_to_ad_impression_daily_table` legt
beide abdeckenden Indizes an (`ad_impr_daily_day_slot_cover`,
`ad_impr_daily_day_tenant_cover`; seit Ticket #47 um `deliveries` erweitert und
dadurch in `…_cover2` umbenannt — die Widget-Abfrage holt seitdem zusätzlich
`sum(deliveries)` und wäre über die alte Fassung nicht mehr Index-only
gelaufen) und lässt `ad_impr_daily_day_tenant` sowie
`ad_impr_daily_slot_day` fallen. `EXPLAIN` beider Widget-Abfragen nennt nach
der Migration den jeweils neuen Index und „Using index"; der Fremdschlüssel
auf `tenant_id` bleibt durch das Präfix des Unique-Index gedeckt, der Upsert
des Aggregations-Jobs trifft weiterhin `ad_impr_daily_unique`, das Pruning
(`where day < ?`) nutzt `day` als Präfix des neuen Slot-Index.

### 13.8 `X-Real-IP` war von jedem Absender vertrauenswürdig — behoben (S6, hoch)

Beim Umsetzen von S3 (#43) aufgefallen, in der Runde #39 nicht gesehen:
`real_ip_header` war in `/etc/nginx/nginx.conf` auskommentiert, nginx nahm
damit die **Vorgabe `X-Real-IP`** — und `set_real_ip_from 0.0.0.0/0` machte
den Header von **jedem** Absender vertrauenswürdig. Probe gegen den Ursprung
am 16.10.2026 14:51 Uhr:

```
curl --resolve elektrikerportal.com:443:88.198.64.145 \
     -H 'X-Real-IP: 203.0.113.77' https://elektrikerportal.com/robots.txt

203.0.113.77 - - [16/Oct/2026:14:51:07 +0200] "GET /robots.txt HTTP/2.0" 200 …
```

`$remote_addr` und `REMOTE_ADDR` standen auf dem gefälschten Wert. Damit war
nicht nur das Impression-Limit, sondern **jedes** `throttle`, jede
Turnstile-IP-Bindung, jede Netzprüfung in `BotTraffic` und das
`include /etc/nginx/blocked_ips` mit einem Header aushebelbar — netzweit und
für die fremden Projekte auf demselben Server mit.

Behoben in derselben Änderung: die `0.0.0.0/0`-Zeile ist weg,
`real_ip_header X-Real-IP` steht explizit da, vertraut wird nur noch dem
CloudPanel-Vorbau (`127.0.0.1`, `::1`). Gleiche Probe um 14:55 Uhr mit
`X-Real-IP` **und** `CF-Connecting-IP` gefälscht: im Log steht die echte
Absenderadresse. Nachprüfbar mit
`scripts/nginx-echte-besucher-ip.sh --abnahme`, Einzelheiten in
`docs/echte-besucher-ip.md`.
