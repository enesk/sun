# Premium-Modul: Preise für elektrikerportal.com freischalten (#20)

Offene Punkte aus der Definition of Done von #2. Sie brauchen echte Stripe-Preise
und eine Preisentscheidung. Deshalb bleiben sie Handarbeit und werden hier abgehakt.

## Stand (17.09.2026)

- **Eine Staging-Umgebung gibt es nicht.** Das einzige Ziel ist die Produktion
  (`ssh sun`, `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`), siehe
  `docs/messungen/produktionsumgebung-anleitung.md`. Auf dem Server laufen fremde
  Live-Projekte. `dep provision` und alle `provision:*`-Tasks sind dort verboten.
- Der Code aus #2 (`config/premium.php`, `app/Enums/PlanTier.php`,
  `app/Enums/PremiumFeature.php`, `app/Support/Tenancy/TenantPremiumPricing.php`,
  der neue `PremiumPlansSeeder`, das Formular „Premium-Preise“ im `TenantResource`)
  ist noch nicht committet und deshalb **nicht auf der Produktion**. Schritt 1 setzt
  voraus, dass dieser Stand dort ausgerollt ist.
- Lokal geprüft: `php artisan db:seed --class=PremiumPlansSeeder --force` läuft
  fehlerfrei durch („Premium-Products und -Plans angelegt bzw. aktualisiert.“).
  `premiumPricing()->isSaleEnabled()` liefert für das lokale Elektrikerportal
  (ID 19) `false`, weil noch keine Preise gepflegt sind. Das ist so gewollt.
- Die Brutto-Endpreise sind noch nicht festgelegt. Als Referenzwerte trägt der
  Seeder die Werte aus `config/premium.php` → `reference_prices_cents` ein. Diese
  Werte sind **keine** Freigabe.

## Produktions-Rollout 314b18c (#29)

Die Produktion steht auf `969a1f2`. Code, Migrationen (central 000005/000006,
tenant 000003–000015) und `config:cache` gehen nur gemeinsam live, sonst werfen
Stadt- und Kategorieseiten 500er. Der Ablauf steckt in `scripts/premium-rollout.sh`.

Befund `--pruefen` am 17.09.2026: Die `.env` weicht vom aktiven Config-Cache nur
bei `profile_descriptions.*` und dem Log-Kanal `profile-descriptions` ab (beide
neu, harmlos). `permission.cache.expiration_time` wird immer als geändert
gemeldet, weil der Wert ein Objekt ist. `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED`
hat noch keine Zeile. `--ausrollen` setzt sie. Auf `/home` sind 4,4 TB frei.

- [ ] Wartungsfenster festlegen und ankündigen.
- [ ] Datenbankänderungen freigeben.
- [ ] Freigeben, dass der Zwischenstand von #14 (Betriebsbereich) und #18 (Admin)
      live geht.
- [ ] `scripts/premium-rollout.sh --pruefen` direkt vor dem Fenster erneut
      ausführen und die Abweichungen abnicken.
- [ ] `scripts/premium-rollout.sh --sichern` ausführen und warten, bis im
      Sicherungsordner `FERTIG` liegt und kein Dump nur ~20 Byte groß ist.
- [ ] `FREIGABE=314b18c scripts/premium-rollout.sh --ausrollen` ausführen. Das
      Skript erledigt `down` mit Secret, `pull`, beide Migrationsläufe, `.env`,
      `composer`, `npm`, die Caches, den FPM-8.5-Reload, den Horizon-Neustart, `up`
      und den `PremiumPlansSeeder`. Hängt `npm run build` (Abbruch nach 15 Minuten),
      lokal auf `314b18c` bauen, `public/build/` per `rsync` hochladen, `chown`
      ausführen und die restlichen Schritte von Hand nachziehen.
- [ ] Stichprobe von Hand: je Portal eine Stadt-, Kategorie- und Profilseite
      (`/{id}-{slug}`) sowie `/premium` aufrufen. `--nachkontrolle` zeigt den
      Scheduler und die Log-Fehler (UTC).
- [ ] Im Fehlerfall `FREIGABE=969a1f2 scripts/premium-rollout.sh --rollback`
      ausführen. Neue Tabellen und Spalten bleiben stehen.

### Stand 17.09.2026 (#35)

Die Produktion steht weiter auf `969a1f2`. #29 und #21 wurden geschlossen, auf dem
Server ist aber nichts passiert. Der Rollout umfasst jetzt auch den #32-Fix
(`leads:inquiries:purge-contacts`, Tenant-Migration 000016). `--ausrollen` rollt
den **lokal ausgecheckten Commit** aus und bricht ab, wenn er nicht gleich
`origin/main` ist. `FREIGABE` muss diesen Kurz-Hash nennen.

- [ ] Arbeitsbaum prüfen und committen (Freigabe Enes), danach `git push`.
      Empfehlung: Den #32-Fix als eigenen Commit (`PurgeOldCompanyInquiries.php`,
      Tenant-Migration 000016, `routes/console.php`, `CompanyInquiry.php`).
      `TenantLegalDefaults.php` und `TenantDatenschutzExclusiveLeadsBackfillCommand.php`
      bleiben bis zur Rechtsfreigabe (#31/#36) draußen. Den Backfill-Command vorher
      **nicht** ausführen. Die übrigen geänderten Dateien vor dem Commit einzeln sichten.
- [ ] Die Schritte oben ab `--pruefen` mit
      `FREIGABE=$(git rev-parse --short HEAD) scripts/premium-rollout.sh --ausrollen`
      ausführen. `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=true`, der FPM-Reload und
      der Horizon-Neustart sind darin enthalten.
- [ ] `--nachkontrolle`: `leads:inquiries:purge-contacts` muss im Scheduler stehen.
- [ ] Stripe-Testschlüssel in die Prod-`.env` eintragen, `config:cache` ausführen,
      dann `scripts/premium-rollout.sh --billing`. Den Live-Secret-Key in Stripe
      rotieren (#33).
- [ ] Fertig, wenn `--billing` auf dem Server den neuen Commit meldet. Den Hash in
      #35 eintragen, danach sind #33/#34 frei.

## Handarbeit

- [ ] 1. Code aus #2 auf die Produktion bringen (Abschnitt „Produktions-Rollout“), dann dort ausführen:
      `sudo -u sanitaerfinden php artisan db:seed --class=PremiumPlansSeeder --force`
      (Der Seeder ist idempotent und lässt vorhandene PlanPrices und
      Stripe-Zuordnungen unverändert.)
- [x] 2. Brutto-Endpreise festgelegt (Enes/Uwe, 17.09.2026, #38), Quelle ist
      `config('premium.reference_prices_cents')`:
      | Key                | Intervall | Brutto   |
      |--------------------|-----------|----------|
      | `pro_monthly`      | monatlich | 49,00 €  |
      | `pro_yearly`       | jährlich  | 490,00 € |
      | `premium_monthly`  | monatlich | 79,00 €  |
      | `premium_yearly`   | jährlich  | 790,00 € |
      | `featured_monthly` | monatlich | 39,00 €  |
- [ ] 3. Preise setzen mit dem Seeder (#38), **erst nach dem Rollout (#35) und nur mit
      Freigabe von Enes für Live-Preise**. Die Produktion hat Stripe-Live-Schlüssel,
      ohne `--live` bricht der Seeder dort ab:
      ```
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan premium:pricing:seed --tenant=elektrikerportal.com --dry-run
      sudo -u sanitaerfinden /usr/bin/php8.4 artisan premium:pricing:seed --tenant=elektrikerportal.com --live
      ```
      Der Seeder legt je Key ein Stripe-Product `sun-<plan-slug>` und einen Price mit
      `lookup_key` `sun_<key>_<cent>` an (inkl. Steuer). Er schreibt die Price-ID und
      den Betrag in die Portal-Preise und setzt den SaasyKit-`PlanPrice` samt
      Stripe-Zuordnung auf denselben Price. Ein zweiter Lauf legt nichts doppelt an.
      Ohne `--tenant` gilt er für alle Portale mit sun-v2. Ohne Stripe-Schlüssel
      schreibt er nur die Beträge, die Pakete bleiben dann „Derzeit nicht buchbar“.
      Ein neuer Betrag in der Config ergibt einen neuen Stripe-Price. Den alten
      Price desselben Keys deaktiviert der Seeder, er löscht ihn nicht. Laufende Abos
      rechnen weiter über den alten Price ab. Läuft der Seeder ohne Schlüssel und der
      Betrag hat sich geändert, entfernt er die alte Preis-ID. Das Paket ist dann
      nicht buchbar, bis ein Lauf mit Schlüssel folgt.
      Handpflege im Admin bleibt möglich: Tenants → Bearbeiten → „Premium-Preise“.
      Das Feld „Bruttopreis“ erwartet **Euro** (29 für 29,00 €), gespeichert wird in Cent.
      Die Admin-Pflege ändert aber nur die Portal-Preise, nicht den `PlanPrice`, über den
      Stripe abrechnet. Beträge deshalb immer über Config und Seeder ändern.
- [ ] 4. Prüfen:
      ```
      sudo -u sanitaerfinden php artisan tinker --execute="var_dump(App\Models\Tenant::where('domain','elektrikerportal.com')->first()->premiumPricing()->isSaleEnabled());"
      ```
      Erwartet wird `bool(true)`. Liefert der Aufruf `false`, fehlt bei einem der
      vier Abo-Keys die ID oder der Betrag. `featured_monthly` fließt nur in
      `isFeaturedSaleEnabled()` ein.

## Subscription-Lifecycle (#5)

Stand 17.09.2026 (#21): Auf der Produktion ist noch nichts davon wirksam, weil sie
weiter auf `969a1f2` steht. Zuerst muss der Abschnitt „Produktions-Rollout“
abgehakt sein. `scripts/premium-rollout.sh --billing` prüft das ohne Eingriff.
Befund am 17.09.: Migration 000005 fehlt, der Scheduler-Eintrag fehlt,
`tenant_multiple_subscriptions_enabled = false`, und die Stripe-Schlüssel sind
**Live**-Schlüssel. Entscheidung aus #26 (Variante b): Die Testkarten-Matrix läuft
lokal mit `stripe login` und `stripe listen --forward-to <lokal>/stripe/webhook`.
Auf der Produktion folgt genau ein echter Kauf (Pro monatlich, eigener
Testbetrieb), der danach erstattet und gekündigt wird. Nach jedem Schritt zeigt
`--billing` die Zuordnungen, `plan_tier`, `plan_grace_until` und den Dry-Run des
Auto-Downgrades.

- [ ] Central-Migration `2026_09_17_000005_create_company_subscriptions_table` ausführen
      (`php artisan migrate --force`, nicht `tenants:migrate`).
- [ ] `.env`: `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=true`. Alle Betriebe eines Portals
      buchen im selben SaasyKit-Tenant; mit `false` lehnt `SubscriptionService::canCreateSubscription()`
      jede Buchung ab, sobald ein Betrieb des Portals ein Abo hat.
- [ ] Scheduler prüfen: `php artisan schedule:list | grep premium` →
      `tenants:run premium:process-expirations` täglich 01:15.
- [ ] Lokal mit Stripe-Testkarten (Voraussetzung: `migrate`,
      `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=true`, `stripe login`, `stripe listen`):
  - [ ] Buchung Pro monatlich / Pro jährlich / Premium monatlich / Premium jährlich
        (`4242 4242 4242 4242`), danach je eine Zeile in `company_subscriptions`.
  - [ ] Zahlungsausfall (`4000 0000 0000 0341`, Rechnung erneut fälligstellen):
        `plan_grace_until` ist gesetzt, und die Mail ist angekommen.
  - [ ] Rechnung nachzahlen: `plan_grace_until` ist wieder leer.
  - [ ] Planwechsel Pro ↔ Premium mit Proration (Stripe-Rechnung zeigt die Anteile).
  - [ ] Kündigung zum Periodenende: Der Plan bleibt bis `ends_at` erhalten.
  - [ ] Auto-Downgrade: `tenants:run premium:process-expirations --option="dry-run=1"`,
        danach ohne `dry-run`.
- [ ] Produktion: ein Live-Kauf Pro monatlich mit einem eigenen Testbetrieb, danach
      im Stripe-Dashboard erstatten und kündigen, dann `--billing` prüfen.

## Statistik-Dashboard und Monatsreport (#16)

Stand 17.09.2026 (#25): Eine Staging-Umgebung gibt es nicht. Lokal geprüft:
Die Tenant-Migration `000015` ist in 19 von 20 Tenants gelaufen.
`schedule:list` zeigt `0 7 1 * * tenants:run premium:send-monthly-reports`.
Der Probelauf mit `dry-run=1` läuft fehlerfrei (lokal 0 Pro-Betriebe).
Ausnahme ist nur der lokale Tenant `france.test`: Dort ist `create_cities_table` als gelaufen
eingetragen, die Tabelle `cities` fehlt aber, deshalb scheitert `000011` (FK `city_id`).
`tenants:migrate` bricht dort ab. Die übrigen Tenants deshalb mit `--tenants=<uuid>` migrieren.
Das lokale `public/build` enthält noch keinen Chart.js-Chunk, also vor der Abnahme neu bauen.
Rollout und Abnahme laufen **nach #29** auf der Produktion. Vorher dort prüfen:
`tenants:run` → `Schema::hasTable('cities')` in allen Tenants.

- [ ] Tenant-Migration `2026_09_17_000015_add_monthly_report_columns_to_companies_table`
      ausführen (`php artisan tenants:migrate --force`).
- [ ] Assets neu bauen (`npm ci && npm run build`, Deployer-Task `npm:build`) — Chart.js ist
      neue npm-Abhängigkeit und wird lokal gebundelt.
- [ ] Scheduler prüfen: `php artisan schedule:list | grep monthly` →
      `tenants:run premium:send-monthly-reports` am 1. um 07:00.
- [ ] Probelauf ohne Versand:
      `php artisan tenants:run premium:send-monthly-reports --option="dry-run=1"`.
      Ein zweiter echter Lauf im selben Monat verschickt nichts doppelt
      (`companies.monthly_report_sent_period`).
- [ ] Abnahme auf Staging (Rathana): `/firmenprofil/statistiken` als Basis- und als
      Pro-Betrieb (7/30/90 Tage, Kennzahl-Auswahl, mobil), Schalter „Monatsbericht“ in
      `/firmenprofil/einstellungen`, Report-Mail mit `--option="month=YYYY-MM"` an einen Testbetrieb.

## Top-Platzierung (#6, Abnahme #23)

Stand 17.09.2026: Eine Staging-Umgebung gibt es nicht, und die Produktion steht noch
nicht auf `314b18c` (Rollout #29). Lokal ist die Central-Migration `000006` noch
offen (`migrate:status` → Pending). Die Tenant-Migration `000004` legt
`featured_placements` an. Die Abnahme läuft deshalb erst **nach #29** auf der
Produktion im Stripe-Testmodus (siehe #21). Befehle als
`sudo -u sanitaerfinden /usr/bin/php8.4 artisan …`.

- [ ] 1. Migrationen (Teil von #29, Schritte 5/6): `migrate --force` →
      `2026_09_17_000006_add_featured_placement_to_company_subscriptions_table`,
      `tenants:migrate --force` → `featured_placements`. Prüfen:
      `migrate:status | grep 000006` meldet `Ran`.
- [ ] 2. `featured_monthly` (`price_…` + Bruttobetrag in Cent) für elektrikerportal.com
      (Prod-ID 30) pflegen, siehe Abschnitt „Handarbeit“ Schritt 3. Plan
      `featured-monthly` aktiv (PremiumPlansSeeder). Prüfen:
      `tinker --execute="var_dump(App\Models\Tenant::find(30)->premiumPricing()->isFeaturedSaleEnabled());"` → `bool(true)`.
- [ ] 3. Vier Premium-Betriebe derselben Stadt und Branche buchen nacheinander unter
      `/firmenprofil/premium` → „Top-Platzierung“ (Karte `4242 4242 4242 4242`).
      Die Buchungen 1–3 erhalten die Slots 1–3. Die Auswahl zeigt danach „Alle Plätze vergeben“.
      Die 4. Buchung wird vor dem Checkout mit „Alle Top-Plätze in Ihrer Stadt und
      Branche sind vergeben.“ abgelehnt (`FeaturedPlacementBooking`).
- [ ] 4. `/staedte/{slug}`: Die drei gebuchten Betriebe stehen in Slot-Reihenfolge oben
      (`Company::orderFeaturedFirst`), danach Premium, danach die bisherige Sortierung.
- [ ] 5. Kündigung des Add-ons im Betriebsbereich: Der Slot bleibt bis zum Periodenende
      aktiv (`featured_placements.ends_at`). Downgrade eines Betriebs auf Pro/Free
      (Admin oder `premium:process-expirations`): `releaseForCompany()` gibt den Slot
      sofort frei und kündigt das Add-on-Abo. Danach ist eine Buchung für den
      4. Betrieb möglich.
- [ ] 6. Log prüfen: `grep "Add-on bezahlt, Slot nicht vergeben" storage/logs/laravel*.log`.
      Jeder Treffer (Kontext `subscription_id`, `company_id`) bedeutet: Das Add-on ist bezahlt,
      aber es gibt keinen Slot. Die Zahlung muss in Stripe manuell erstattet werden.
      Nachstellen lässt sich der Fall so: Zwei Betriebe starten den Checkout gleichzeitig
      für den letzten freien Slot.

## Profil-Ausbau (#13, Abnahme #24)

Stand 17.09.2026: Es gilt dieselbe Lage wie bei #23. Eine Staging-Umgebung gibt es nicht, und die Produktion steht
noch nicht auf `314b18c`. Die Abnahme läuft deshalb **nach #29** auf der Produktion für
elektrikerportal.com (Prod-ID 30). Lokal sind die Tenant-Migrationen `000008`–`000010`
bewusst nicht ausgeführt. Befehle als `sudo -u sanitaerfinden /usr/bin/php8.4 artisan …`.
Den Plan des Testbetriebs setzt man im Admin (Plan-Übersicht, #18), ohne Stripe.

Grenzen laut Code: `config/premium.php` → `tiers.*.limits` (`gallery_photos` 1/20/50,
`job_postings_active` 1/3/unbegrenzt, `references` 0/0/20) und `profile.*`
(`reference_photos_max` 5, `video_hosts`). `ServiceCatalog` ist ab Pro freigeschaltet,
`VideoEmbed`/`References`/`JobHighlight` nur bei Premium.

- [ ] 1. Migrationen (Teil von #29): `tenants:migrate --force` →
      `2026_09_17_000008_create_company_references_table`,
      `2026_09_17_000009_create_company_services_table`,
      `2026_09_17_000010_add_video_url_to_companies_table`. Prüfen:
      `tenants:run "migrate:status" --tenants=<uuid von ID 30> | grep -E "00000(8|9)|000010"`
      meldet dreimal `Ran`. Den Tenant-Key bildet die UUID, nicht die ID.
- [ ] 2. Galerie `/firmenprofil/bearbeiten`: Bei Free / Pro / Premium lassen sich 1 / 20 / 50
      Fotos hochladen. Mehr Fotos werden verworfen, und es erscheint ein Hinweis
      (`CompanyProfileContentService::addGalleryPhotos`/`remainingGallerySlots`).
- [ ] 3. Downgrade: Premium mit 25 Fotos → Pro. In der Tabelle `media` stehen weiterhin 25 Einträge.
      Im Betriebsbereich sind die Fotos 21–25 als „Nicht sichtbar“ markiert. Das Profil mit dem Theme `default`
      zeigt nur 20 Fotos (`visibleGallery`).
- [ ] 4. Video: Das Feld erscheint nur bei Premium. `https://youtu.be/…` und `https://vimeo.com/<id>`
      werden angenommen. Ein anderer Host (z. B. `https://dailymotion.com/…`) wird mit
      „Bitte geben Sie einen Link zu einem YouTube- oder Vimeo-Video ein.“ abgelehnt.
- [ ] 5. Projektreferenzen: nur bei Premium. Die 21. Referenz und das 6. Foto einer Referenz werden
      abgelehnt (`canAddReference`, `referencePhotosMax`).
- [ ] 6. Leistungskatalog: ab Pro. Im Quelltext des Profils steht im JSON-LD `makesOffer`.
      Bei Free fehlt der Schlüssel (`StructuredDataService::offers` → `visibleServices` leer).
      Prüfen: `curl -s https://elektrikerportal.com/<id>-<slug> | grep -c makesOffer`.
- [ ] 7. Stellenanzeigen: Free / Pro / Premium erlauben 1 / 3 / beliebig viele aktive Anzeigen.
      Wer über dem Limit eine Anzeige anlegt oder reaktiviert, sieht den Upsell-Hinweis
      (`CompanyJobPostingService::canActivate`/`limitMessage`). Unter `/jobs` (Theme `default`)
      stehen Premium-Anzeigen oben (`Job` Top-Jobs zuerst) und tragen das Badge „Top-Job“.

## Profil-Sektionen und Betriebsbereich-Formulare (#14, Abnahme #28)

Stand 17.09.2026: Eine Staging-Umgebung gibt es nicht. Die Abnahme läuft auf der Produktion
für elektrikerportal.com (Prod-ID 30, Theme `sun-v2`). Voraussetzungen sind #29 (Stand `314b18c`
inkl. Tenant-Migrationen `000008`–`000010`) und ein frischer Asset-Build (`npm ci && npm run build`).
Die Testbetriebe (Free, Pro, Premium) bekommen ihren Plan im Admin (#18), ohne Stripe.

Im Code geprüft:
- `themes/sun-v2/views/pages/companies/show.blade.php` bindet die Sektionen in dieser
  Reihenfolge ein: Leistungen & Preise → Fotos → Referenzen → Video. Jede Sektion
  erscheint nur, wenn sie Inhalt hat. Die Freischaltung filtert der `CompanyController`.
- `portal/companies/partials/video.blade.php`: Das iframe steht in `<template x-if="playing">`.
  Das Vorschaubild ist ein eigenes Bild des Betriebs (Titelbild bzw. erstes Galeriefoto),
  kein Thumbnail des Anbieters.
- `portal/companies/partials/lightbox.blade.php`: `x-trap.inert.noscroll`, Escape und
  Pfeiltasten. Auf dem Handy gibt es **nur Vor-/Zurück-Knöpfe, keine Wischgeste**. Das deckt
  das Kriterium „Wischen bzw. Knöpfe“ ab. Wird Wischen gewünscht, ist das ein eigenes Ticket.

Handprüfung (Rathana), je mit einem Free-, Pro- und Premium-Testprofil:
- [ ] Profil: Die Reihenfolge stimmt. „Leistungen & Preise“ erscheint ab Pro, mobil als
      Karten und ab `md` als Tabelle. Referenzen und Video gibt es nur bei Premium. Ohne
      Inhalt fehlt die Sektion.
- [ ] Video: Im DevTools-Netzwerk-Tab (Filter `youtube|vimeo`) gibt es vor dem Klick keinen
      Request an `youtube-nocookie.com`, `vimeo.com` oder `vimeocdn.com`. Erst nach dem Klick
      lädt das iframe.
- [ ] Lightbox (Fotos und Referenzen): ←/→ blättern, Esc schließt, Tab bleibt im Dialog.
      Auf dem Handy funktionieren die Knöpfe, und die Seite scrollt im Hintergrund nicht mit.
- [ ] `/firmenprofil/bearbeiten`: Fotos lassen sich per Drag & Drop (Desktop) und per
      Pfeilknöpfen (Handy) sortieren. Die Reihenfolge bleibt nach dem Neuladen erhalten.
- [ ] Fotos über dem Limit (z. B. Pro mit 25 Fotos nach Downgrade) tragen das Schloss-Badge
      „Nicht sichtbar“ und den Hinweis mit „Freischalten“-Link.
- [ ] Free: Die Bereiche Leistungen, Referenzen und Video sind sichtbar, mit Schloss-Badge und
      „Freischalten“-Link auf `/firmenprofil/premium`.
- [ ] UI-Freigabe durch Rathana (Desktop und Handy).

## Admin-Planvergabe und Slot-Verwaltung (#18, Abnahme #30)

Stand 17.09.2026: Es gibt keine Staging-Umgebung, und die Produktion steht noch auf
`969a1f2`. Die Abnahme läuft deshalb **nach #29** auf der Produktion mit einem
Testbetrieb auf elektrikerportal.com (Prod-ID 30). Stripe ist dafür nicht nötig.
Befehle als `sudo -u sanitaerfinden /usr/bin/php8.4 artisan …`.

Im Code geprüft:
- `CompanyResource`: Der Tab „Plan“ zeigt Stufe, wirksame Stufe, Herkunft, Beginn, Laufzeit
  und Grace Period. Die Aktion „Plan manuell setzen“ ist nur für Admins sichtbar; Grund ist Pflicht,
  das Enddatum ab Pro ebenfalls (gilt bis Tagesende).
- `PremiumAdminService::setPlanManually()`: Leert `subscription_ref` und `plan_grace_until` und
  löst `CompanyPlanChanged` aus. `ForgetCompanyEntitlements` leert daraufhin den
  Entitlement-Cache, die Freischaltung wirkt also sofort. Bei Free beendet
  `ReleaseFeaturedPlacementsOnDowngrade` die Top-Platzierungen.
- `premium:process-expirations` stuft auch manuelle Pläne herunter. Die Zeile lautet
  `Auf Free gesetzt: #<id> <name> (<stufe>, manuell)`.
- `FeaturedPlacementResource`: „Slot manuell vergeben“ läuft über `FeaturedPlacementService::book()`
  (max. 3 Slots je Stadt×Branche), „Beenden“ über `end()`.
- Admin-Dashboard: `PremiumRevenueWidget` liest den Seitenfilter `tenant_id` („Portal“,
  leer = „Alle Portale“) und zählt `company_subscriptions` (Central-Migration `000005`).
- Log: Channel `premium-admin` (daily, 730 Tage) → `storage/logs/premium-admin-YYYY-MM-DD.log`
  im **zentralen** `storage/`. Der Pfad wird beim Laden der Config aufgelöst, deshalb
  greift `suffix_storage_path` hier nicht. Jede Zeile enthält `tenant`, `user_id`, `user` (E-Mail) und `note`.

Handprüfung:
- [ ] 1. `/dashboard` (Portal elektrikerportal.com) → Firmen → Testbetrieb bearbeiten → Tab
      „Plan“: Stufe Free, Herkunft „—“. „Plan manuell setzen“ → Pro, Enddatum morgen,
      Grund „Abnahme #30“. Danach zeigen Stufe und wirksame Stufe Pro, Herkunft „manuell“.
      Prüfen:
      `tenants:run tinker --tenants=30 --option="execute=dump(App\Models\Portal\Company::find(<id>)->only('plan_tier','plan_ends_at','subscription_ref'))"`
      → `subscription_ref` ist `null`. Das öffentliche Profil zeigt ohne Wartezeit die Pro-Funktionen
      (z. B. keine AdSense-Slots). Dasselbe mit Premium wiederholen.
- [ ] 2. Enddatum in die Vergangenheit ziehen (nur der Testbetrieb):
      `UPDATE companies SET plan_ends_at = NOW() - INTERVAL 1 DAY, plan_grace_until = NULL WHERE id = <id>;`
      in der Tenant-DB von ID 30. Danach
      `tenants:run premium:process-expirations --tenants=30 --option="dry-run=1"`: Die Ausgabe
      enthält `Auf Free gesetzt: #<id> … (premium, manuell)` und `[dry-run]`, die DB bleibt
      unverändert. Anschließend ohne `--option` ausführen: Der Betrieb steht auf Free, und die Laufzeit ist leer.
- [ ] 3. `/dashboard` → Top-Platzierungen → „Slot manuell vergeben“: Drei Premium-Testbetriebe
      bekommen dieselbe Stadt und Branche und erhalten die Slots 1–3. Ein vierter Versuch wird mit
      Fehlermeldung abgelehnt, und es entsteht kein Datensatz. „Beenden“ auf einem Slot → Status
      beendet, „Freie Slots“ steigt, und der vierte Betrieb lässt sich jetzt vergeben.
- [ ] 4. `/admin` → Dashboard: Das Widget „Premium-Umsatz“ ist sichtbar. Der Filter „Portal“ =
      elektrikerportal.com schränkt die Zahlen ein, leer zeigt alle Portale.
- [ ] 5. `grep -h "" storage/logs/premium-admin-*.log | tail -20`: Jede Aktion aus 1 und 3
      („Plan manuell gesetzt“, „Top-Platzierung manuell vergeben“, „Top-Platzierung
      beendet“) ist mit `user` und `note` vorhanden. Der Downgrade aus Schritt 2 läuft über
      `CompanyPlanService` und steht **nicht** in diesem Log.
- [ ] 6. Aufräumen: Die Testbetriebe über „Plan manuell setzen“ auf Free zurücksetzen (Grund „Abnahme
      #30 Ende“) und die Slots beenden.
