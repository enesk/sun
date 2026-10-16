# Security-Review des Werbeplatz-Systems SUN-V2

Status: **abgeschlossen, ohne offene Punkte im Modulumfang**
Ticket: #11
Prüfdatum: 2026-10-16, Stand Arbeitskopie (das Modul ist noch nicht eingecheckt,
siehe `docs/ad-slots-golive.md` §0)
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
* HTML-Werbemittel brauchen keine Einwilligung (`requiresConsent() === false`) —
  der Sandbox-Rahmen hat keinen Cookie- und keinen Speicherzugriff. Das ist eine
  Aussage über die Technik, nicht über den Inhalt: ein HTML-Werbemittel, das
  selbst einen Drittanbieter nachlädt, wäre eine Betreiberentscheidung und
  bräuchte die Einwilligung. Festgehalten als Betriebsregel in
  `docs/ad-slots-golive.md` §5.
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

Keine offenen Punkte im Modulumfang.

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
