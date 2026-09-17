# Premium-Modul: Architektur-Review und Abnahmeplan (#19)

Gilt für das Premium-Modul aus #2–#18 (Commit `314b18c`).
Abnahmeportal: elektrikerportal.com.

## Rahmenbedingungen

- **Eine eigene Staging-Umgebung gibt es nicht** (siehe `docs/premium-golive.md`).
  „Staging“ heißt hier: elektrikerportal.com auf der Produktion
  (Prod-Tenant-ID 30) mit Stripe im **Test-Modus**. Voraussetzung ist #29:
  Der Stand ist ausgerollt, `tenants:migrate --force` und `config:cache` sind gelaufen.
- Die Stripe-Testschlüssel sind nur während der Abnahme aktiv. Nach der Abnahme
  werden die Live-Schlüssel wieder eingesetzt (#21, #26).
- Die Preise für elektrikerportal.com sind gepflegt (#20):
  `premiumPricing()->isSaleEnabled() === true`.
- Testbetriebe: Lege mindestens **fünf** Betriebe in derselben Stadt und derselben
  Branche an (A–E), jeder mit einem eigenen Owner-Login. Zusätzlich brauchst du
  einen Betrieb F auf einem zweiten Portal.

### Stripe-Testkarten

| Zweck | Kartennummer |
|---|---|
| Erfolg | `4242 4242 4242 4242` |
| Ablehnung (generic decline) | `4000 0000 0000 0002` |
| Karte wird hinterlegt, spätere Abbuchung schlägt fehl | `4000 0000 0000 0341` |
| 3-D Secure verlangt | `4000 0027 6000 3184` |

Für alle Karten gilt: Ablaufdatum in der Zukunft, CVC beliebig.

## 1. Architektur-Review (Code)

Stand 17.09.2026, geprüft am Arbeitsstand von `main`.

| Prüfpunkt | Ergebnis | Fundstelle |
|---|---|---|
| Keine direkte `plan_tier`-Abfrage zur Freischaltung außerhalb von `CompanyEntitlementService` | **OK** | `grep -rn plan_tier app resources routes` liefert nur die folgenden Stellen. Sie schreiben, zeigen an oder werten aus und entscheiden nie über eine Freischaltung: `Company` (Cast), `CompanyPlanService` / `PremiumAdminService` (Schreiben und Event-Payload), `ProcessPlanExpirations` (Auswahl der abgelaufenen Pläne), `PremiumRevenueService` (Zählung je Stufe), `Dashboard/CompanyResource` (Anzeige der gebuchten Stufe, Vorbelegung des Formulars, Herkunft). In Blade und Routen gibt es keine Treffer. Gleiche die Liste bei jedem Review erneut ab. |
| Webhook-Listener wechselt den Tenant sauber und beendet ihn wieder | **OK** | `SyncCompanyPlanFromSubscription` läuft als Queue-Job im Central-Kontext. `CompanyPlanService::runInTenant()` initialisiert den Tenant der Zuordnung (`company_subscriptions.tenant_id`). Im `finally` stellt die Methode den vorherigen Tenant wieder her oder ruft `tenancy()->end()` auf. Das gilt auch, wenn eine Exception auftritt. Add-on-Abos laufen über `FeaturedPlacementService::syncFromSubscription()` und dieselbe Methode. Die Mail bei Zahlungsausfall geht erst nach dem Tenant-Kontext raus. |
| Slot-Vergabe ist race-sicher | **OK** | `FeaturedPlacementService::book()` sperrt die aktiven Platzierungen mit einer Transaktion und `lockForUpdate()`. Zusätzlich gibt es den Unique-Index `fp_city_category_active_slot_unique` (`city_id`, `category_id`, `active_slot`). Ist die Menge leer, sperrt `FOR UPDATE` nichts. Dann greift der Unique-Index, und die zweite Buchung endet mit `noFreeSlot`. Bekannte Einschränkung: Ein Kollisionsverlierer bekommt „kein Slot frei“, obwohl ein höherer Slot frei wäre. Das ist selten und kann erneut gebucht werden, deshalb kein Bug. Webhook-Wiederholungen erkennt die Methode am `subscription_ref`. |
| Beacon-Endpoint ist rate-limitiert | **OK** | `POST /stats/beacon` → `throttle:120,1` (`routes/tenant.php`). Der Controller prüft `company_id` gegen `Company::active()` in der Tenant-DB und verwirft Bots. |
| Entitlement-Cache ist mandantengetrennt | **OK** | Schlüssel `premium:entitlements:{tenantKey}:{companyId}` |
| Lead-Kontingent ist atomar | **OK** | `LeadQuotaService::consume()` nutzt ein bedingtes `increment` und wirft bei 0 Zeilen `LeadQuotaExceededException`. |

## 2. Tenancy-Isolation

Technische Grundlage: `DatabaseTenancyBootstrapper`. `featured_placements`,
`company_leads`, `company_inquiries`, `company_events`, die Tagesaggregate und
`lead_quota_usages` liegen in der Tenant-DB. Nur `company_subscriptions` ist
eine Central-Tabelle und wird über `tenant_id` gebunden.

| # | Fall | Erwartung | Ergebnis |
|---|---|---|---|
| T1 | Betrieb A (elektrikerportal) bucht eine Top-Platzierung. Öffne auf Portal 2 dieselbe Stadt und dieselbe Branche. | Auf Portal 2 gibt es keinen „Empfohlen“-Eintrag von A. | ☐ |
| T2 | Schicke eine exklusive Anfrage an A. Öffne dann den Betriebsbereich von F auf Portal 2 und die Filament-Admin-Ansicht von Portal 2. | Die Anfrage von A erscheint nirgends. | ☐ |
| T3 | Prüfe die Statistik von A (`/firmenprofil/statistiken`) auf Portal 2 und im Admin von Portal 2. | Keine Zahlen von A sichtbar. Die Company-ID von A führt auf Portal 2 zu 404 oder zu einem fremden, leeren Betrieb. | ☐ |
| T4 | Sende `POST /stats/beacon` auf Portal 2 mit der `company_id` von A. | Kein Event in der DB von Portal 1. | ☐ |
| T5 | Rufe das Widget `/widget/{id-slug}` von A über die Domain von Portal 2 auf. | 404 | ☐ |
| T6 | Admin → Plan-Übersicht und Slot-Verwaltung (#18) auf Portal 2 | Nur Betriebe und Slots von Portal 2 sichtbar | ☐ |

## 3. Datenschutz-Check

| # | Prüfpunkt | Code-Befund | Manuelle Prüfung |
|---|---|---|---|
| D1 | Keine IPs in `company_events` | **OK:** Die Tabelle hat keine IP-Spalte. `session_hash` ist ein HMAC aus Session-ID oder IP und User-Agent. Der Schlüssel wechselt täglich und wird selbst nicht gespeichert. Rohdaten bleiben 14 Tage (`premium.stats.raw_retention_days`). | `SHOW COLUMNS FROM company_events` ☐ |
| D2 | Leads nach 12 Monaten löschen | **Teilweise:** `leads:purge-contacts` (täglich 01:30, je Tenant) entfernt bei `company_leads` Name, E-Mail, Telefon und die Freitext-Antworten. Die Metadaten bleiben für die Statistik. **Befund:** Für `company_inquiries` (Webhook, #31) gibt es keine Löschung, obwohl die Tabelle Kontaktdaten enthält → #32. | `php artisan tenants:run leads:purge-contacts --option="dry-run=1"` ☐ |
| D3 | Verifizierungsnachweise nach 90 Tagen löschen | **OK:** `premium:purge-verification-documents` (täglich 01:30, je Tenant) löscht die Datei von der Tenant-Disk und setzt `document_purged_at`. | Setze bei einem Nachweis `reviewed_at` auf vor 91 Tagen und führe den Befehl aus. Danach ist die Datei weg. ☐ |
| D4 | Widget setzt keine Cookies | **OK:** Die Widget-Routen laufen ohne `web`-Middleware, also ohne Session und ohne CSRF-Cookie. Der Controller setzt keine Cookies. Das Widget-Skript nutzt weder Cookies noch Storage. | Browser-DevTools auf einer externen Testseite: keine `Set-Cookie`-Header, kein Cookie für die Portal-Domain. ☐ |

## 4. Manuelle Testfälle

Status: ☐ offen · ✅ bestanden · ❌ Bug (Ticketnummer eintragen)

### Billing

| # | Fall | Schritte | Erwartung | Status |
|---|---|---|---|---|
| B1 | Pro monatlich buchen | Betrieb A → Preisseite → Pro monatlich → Karte `4242…` | Das Abo ist aktiv. `plan_tier=pro` und `plan_ends_at` sind gesetzt. Pro-Features sind freigeschaltet. Es gibt einen Eintrag in `company_subscriptions`. | ☐ |
| B2 | Pro jährlich mit 3DS | Betrieb B → Pro jährlich → Karte `4000 0027 6000 3184` → 3DS bestätigen | Wie B1, die Laufzeit beträgt 1 Jahr. Wird 3DS abgebrochen, wird kein Plan gesetzt. | ☐ |
| B3 | Buchung abgelehnt | Betrieb C → Pro → Karte `4000 0000 0000 0002` | Stripe zeigt eine Fehlermeldung. Der Betrieb bleibt Free, und es entsteht keine Zuordnung. | ☐ |
| B4 | Upgrade auf Premium | Betrieb A → Plan-Verwaltung → Premium | Die Stufe ist `premium`, Premium-Features sind sofort aktiv. Die Anteilsrechnung erscheint in Stripe. | ☐ |
| B5 | Add-on Top-Platzierung | Betriebe A, B und D buchen nacheinander das Add-on für Stadt X × Elektriker. | Die Slots 1–3 sind vergeben. Die Betriebe erscheinen in Stadt-, Kategorie- und Suchliste oben mit „Empfohlen“. | ☐ |
| B6 | 4. Buchung abgelehnt | Betrieb E versucht dasselbe Add-on. | Vor dem Checkout erscheint „kein Platz frei“. Wird die Buchung trotzdem bezahlt, storniert der Webhook das Add-on-Abo. Es entsteht kein 4. Slot. | ☐ |
| B7 | Zahlungsausfall, Grace Period, Auto-Downgrade | Betrieb D bucht Pro mit `4000 0000 0000 0341`. In Stripe die Verlängerung auslösen (Test Clock bzw. Rechnung fällig stellen). Danach `plan_ends_at` und `plan_grace_until` in die Vergangenheit setzen und `php artisan tenants:run premium:process-expirations` ausführen. | Die Mail „Zahlung fehlgeschlagen“ kommt an. In der Grace Period bleiben die Features aktiv, und ein Banner erscheint. Nach dem Befehl gilt `plan_tier=free`, die Platzierung ist freigegeben und die Features sind gesperrt. | ☐ |
| B8 | Kündigung zum Periodenende | Betrieb B → Plan-Verwaltung → Kündigen | Bis `plan_ends_at` bleibt Pro aktiv, der Hinweis „endet am …“ erscheint. Nach Ablauf und Expirations-Lauf ist der Betrieb Free. | ☐ |
| B9 | Manuelle Planvergabe | Admin → Betrieb C → „Plan manuell setzen“ → Premium bis Datum X, mit Grund | Die Stufe ist `premium` und die Herkunft „manuell“. Nach Datum X und Expirations-Lauf ist der Betrieb Free. Setzen auf Free beendet den Plan sofort. | ☐ |

### Features

| # | Fall | Schritte | Erwartung | Status |
|---|---|---|---|---|
| F1 | Exklusive Anfrage | Anfrage über das Profil von A (Pro) senden | Der Dialog hat kein Opt-in. Die Anfrage erreicht direkt A (Mail und Betriebsbereich). Der Zähler steht auf +1. | ☐ |
| F2 | Kontingent erschöpft und Fallback | Bei A `lead_quota_usages.used_count` auf das Kontingent setzen und eine weitere Anfrage senden. | Die Anfrage läuft über den Fallback widileads, nicht exklusiv an A. Der Betriebsbereich zeigt „Kontingent erschöpft“. Im Folgemonat wird der Zähler zurückgesetzt. | ☐ |
| F3 | Verifizierung freigeben | A lädt einen Nachweis hoch → Filament → Freigeben | Das Badge „Verifiziert“ erscheint auf Profil und Karte. Der Betrieb bekommt eine Mail. | ☐ |
| F4 | Verifizierung ablehnen | B lädt einen Nachweis hoch → Ablehnen mit Grund | Kein Badge. Der Grund ist im Betriebsbereich sichtbar, ein erneuter Upload ist möglich. | ☐ |
| F5 | Bewertungsantwort | A beantwortet eine Bewertung. | Die Antwort steht unter der Bewertung auf dem Profil. Ein Free-Betrieb sieht stattdessen ein Schloss mit Upsell. | ☐ |
| F6 | Widget extern | Widget-Snippet von A auf einer externen HTML-Seite einbinden (anderer Origin) | Das Widget rendert mit Portalfarbe und Bewertungen. Es setzt keine Cookies (D4). Für einen Free-Betrieb liefert es 404. | ☐ |
| F7 | Galerie-Limit beim Downgrade | Premium-Betrieb mit 30 Bildern → Downgrade auf Pro (20) → Free (1) | Öffentlich sind nur 20 bzw. 1 Bild sichtbar. Es wird nichts gelöscht, und der Betriebsbereich zeigt einen Hinweis. Beim Upgrade sind alle Bilder wieder sichtbar. | ☐ |
| F8 | Statistik Free und Pro | `/firmenprofil/statistiken` als Free-Betrieb und als Pro-Betrieb aufrufen (7/30/90 Tage) | Free: nur die Basis-Kennzahl bzw. Paywall. Pro: alle Kennzahlen und Charts. Die Klicks aus Beacon-Events erscheinen nach der Aggregation (00:45). | ☐ |
| F9 | Monatsreport | `php artisan tenants:run premium:send-monthly-reports --option="month=YYYY-MM"` (vorher `dry-run=1`) | Die Mail kommt im Premium-Layout an. Ein zweiter Lauf verschickt nichts. Der Opt-out in `/firmenprofil/einstellungen` wird beachtet. | ☐ |
| F10 | Werbefreies Profil | Profil von Pro-Betrieb A mit Free-Betrieb C vergleichen | A zeigt weder AdSense noch „Ähnliche Betriebe“, C zeigt beides. | ☐ |
| F11 | Preisseite auf zwei Portalen | Preisseite auf elektrikerportal.com und auf Portal 2 öffnen, mit abweichenden Preisen im TenantResource | Jedes Portal zeigt seine eigenen Bruttopreise. Der Checkout nutzt die `price_…`-ID des jeweiligen Portals. Ein Portal ohne Preise zeigt keine Buchungs-Buttons. | ☐ |

## 5. Befunde

| Ticket | Befund | Schwere |
|---|---|---|
| #32 | D2: Webhook-Anfragen (#31) behalten die Kontaktdaten unbegrenzt. | hoch (DSGVO) |

Trage Befunde aus dem manuellen Durchlauf hier mit Ticketnummer nach.

## 6. Go-Live-Freigabe

| Datum | Portal | Entscheidung | Wer | Bemerkung |
|---|---|---|---|---|
| 17.09.2026 | elektrikerportal.com | **nicht erteilt** | Steffen (Review) | Code-Review OK. Offen sind der manuelle Durchlauf (Abschnitte 2–4) und der Bug D2. Die Freigabe erfolgt, sobald alle Fälle ✅ oder als Bug erfasst sind **und** D2 behoben ist. |
| 17.09.2026 | elektrikerportal.com | **nicht erteilt** | Steffen/Sebastian/Dimitri (#33) | Durchlauf nicht begonnen, am 17.09. dreimal lesend geprüft (`ssh sun`), Stand unverändert: Die Produktion steht auf `969a1f2`, der Rollout aus #29 ist nicht ausgeführt, `TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED` fehlt in der Prod-`.env`. Stripe läuft trotz geschlossenem #21 weiter mit `sk_live`/`pk_live`. #32 (D2): Der Fix (`PurgeOldCompanyInquiries`, Migration `2026_09_17_000016_add_contact_purged_at_to_company_inquiries_table`) liegt lokal vor, ist aber weder committet noch ausgerollt (dritte Prüfung am 17.09., Dimitri). Die Abschnitte 2–4 bleiben ☐. **Vorsatz für den Menschen (Enes):** 1. Den #32-Fix committen, danach im Wartungsfenster `FREIGABE=<neuer Commit> scripts/premium-rollout.sh --ausrollen` ausführen (nicht mehr `314b18c`, denn dort fehlt der Fix). 2. Stripe-Testschlüssel setzen, dann `scripts/premium-rollout.sh --billing`. 3. Nach Behebung von #32 die Abschnitte 2–4 im Browser abarbeiten. 4. Hier freigeben und die Kandidaten für Welle 2 eintragen. 5. Live-Schlüssel zurücksetzen. |

### Rollout-Reihenfolge (mit Enes festzulegen)

1. elektrikerportal.com (Prod-Tenant 30): Freigabe laut Tabelle oben.
2. Zwei Portale mit viel Traffic: Auswahl nach GSC-Klicks der letzten 28 Tage.
   Achtung: firmenfreund.de-Subdomains teilen sich eine Domain-Property.
   Kandidaten: ______ und ______.
3. Alle übrigen Portale, sobald Preise gepflegt sind (`isSaleEnabled()`).

Je Portal: Preise pflegen (#20-Ablauf), F11 und B1 mit Testkarte durchführen,
dann auf Live-Schlüssel umstellen.
