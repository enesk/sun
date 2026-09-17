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

## Handarbeit

- [ ] 1. Code aus #2 auf die Produktion bringen, dann dort ausführen:
      `sudo -u sanitaerfinden php artisan db:seed --class=PremiumPlansSeeder --force`
      (Der Seeder ist idempotent und lässt vorhandene PlanPrices und
      Stripe-Zuordnungen unverändert.)
- [ ] 2. Brutto-Endpreise festlegen. Danach im Stripe-Dashboard (Live-Modus) je
      einen Preis in EUR anlegen, inklusive Steuer:
      | Key                | Intervall | Referenz (brutto) |
      |--------------------|-----------|-------------------|
      | `pro_monthly`      | monatlich | 19,90 €           |
      | `pro_yearly`       | jährlich  | 199,00 €          |
      | `premium_monthly`  | monatlich | 39,90 €           |
      | `premium_yearly`   | jährlich  | 399,00 €          |
      | `featured_monthly` | monatlich | 29,90 €           |
- [ ] 3. Admin → Tenants → elektrikerportal.com (Prod-ID 30) → „Features & Analytics“
      → „Premium-Preise“: alle fünf `price_…`-IDs und die Bruttopreise in Cent
      eintragen.
- [ ] 4. Prüfen:
      ```
      sudo -u sanitaerfinden php artisan tinker --execute="var_dump(App\Models\Tenant::where('domain','elektrikerportal.com')->first()->premiumPricing()->isSaleEnabled());"
      ```
      Erwartet wird `bool(true)`. Liefert der Aufruf `false`, fehlt bei einem der
      vier Abo-Keys die ID oder der Betrag. `featured_monthly` fließt nur in
      `isFeaturedSaleEnabled()` ein.

## Subscription-Lifecycle (#5)

- [ ] Central-Migration `2026_09_17_000005_create_company_subscriptions_table` ausführen
      (`php artisan migrate --force`, nicht `tenants:migrate`).
- [ ] `.env`: `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=true`. Alle Betriebe eines Portals
      buchen im selben SaasyKit-Tenant; mit `false` lehnt `SubscriptionService::canCreateSubscription()`
      jede Buchung ab, sobald ein Betrieb des Portals ein Abo hat.
- [ ] Scheduler prüfen: `php artisan schedule:list | grep premium` →
      `tenants:run premium:process-expirations` täglich 01:15.
- [ ] Mit Stripe-Testkarten durchspielen: Buchung (`4242 4242 4242 4242`),
      Zahlungsausfall (`4000 0000 0000 0341`, danach Rechnung erneut fälligstellen),
      Planwechsel Pro ↔ Premium, Kündigung, Auto-Downgrade
      (`php artisan tenants:run premium:process-expirations --option="dry-run=1"`).

## Statistik-Dashboard und Monatsreport (#16)

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
