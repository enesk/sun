# SEO-Abnahme elektrikerportal.com (#14)

Abnahme des SEO-Hardening-Sets #2–#13 (plus #16, #17). Stand 14.09.2026.

Eine Staging-Umgebung gibt es für SUN nicht (siehe
`docs/messungen/produktionsumgebung-anleitung.md`). Die Staging-Abnahme lief
deshalb lokal gegen den Tenant „Elektrikerportal“ (lokal ID 19,
`elektriker.test`, Theme sun-v2). Aufbau: `APP_DEBUG=false php artisan serve
--port=8114` und `curl -H 'Host: elektriker.test'`. Auf Produktion heißt
dasselbe Portal ID 30.

## 1. Checkliste Staging (lokal)

| Punkt | Probe | Ergebnis |
| --- | --- | --- |
| Startseite ohne „None“ | `GET /`, Wortsuche `None` | 200, 0 Treffer ✅ |
| tel:-Links valide | 5 Profile (`/1-…` bis `/5-…`), `href="tel:…"` und `about:invalid` | alle E.164 (`tel:+493575230307` …), 0× `about:invalid` ✅ |
| Breadcrumb auf `/staedte/{slug}` | hamburg, stuttgart, berlin | sichtbare Brotkrume + `BreadcrumbList`, 0 Links auf `firmen?city=` ✅ |
| Filter-URLs noindex + Canonical | `/firmen?city=hamburg` | `noindex, follow`, Canonical `/staedte/hamburg` ✅ |
| | `/firmen?q=test&page=2` | `noindex, follow`, Canonical `/firmen` ✅ |
| | `/staedte/hamburg?page=2` | `noindex, follow`, Canonical `/staedte/hamburg` ✅ |
| | `/firmen`, `/staedte/hamburg` (ohne Parameter) | `index, follow`, Self-Canonical ✅ |
| Profil-JSON-LD | 5 Profile | `Electrician` mit `PostalAddress`, `AggregateRating`, bei 3 von 5 `OpeningHoursSpecification`, dazu `BreadcrumbList` ✅ |
| Städteseiten-Schema | 3 Städte | je `ItemList` (18 Einträge), `FAQPage` (4 Fragen), `BreadcrumbList` ✅ |
| Strukturprüfung JSON-LD | Skript: gültiges JSON, `@context`, Pflichtfelder je Typ (position/url, name/acceptedAnswer, name/address, ratingValue/reviewCount) | 0 Probleme ✅, Rohdaten: `docs/messungen/seo-abnahme-elektrikerportal-jsonld-2026-09-14.json` |
| Titles gemäß Template (#10) | Städte | „Die 362 besten Elektriker in Hamburg (2026) \| Empfehlungen“ usw. ✅ |
| | Profile | „Torsten Martin Elektroanlagen in Ruhland \| Elektrikerportal“ ✅ |
| Gastbewertung → pending → Freigabe | `SubmitReviewForm::submit()` ohne Login, Profil 1 | angelegt als `pending`, `user_id` NULL, nicht sichtbar, `reviewCount` 5 → nach `approve()` sichtbar, `reviewCount` 6 → Testbewertung gelöscht, wieder 5 ✅ |

**Rich Results Test (Google):** Das Werkzeug hat keine Schnittstelle, und ohne
öffentlich erreichbares Staging geht nur Weg B aus
`docs/messungen/rich-results-test-anleitung.md` (Reiter „Code“). Dafür je eine
Städteseite und ein Profil als HTML einfügen. Die lokale Strukturprüfung oben
ist fehlerfrei. Den Google-Lauf trägt ein Mensch in Abschnitt 5 ein; nach dem
Deploy geht er direkt mit der Live-URL.

## 2. Security-Review

### JSON-LD-Injection

**Befund:** 24 JSON-LD-Blöcke in den Themes `default`, `starter` und `sun-v2`
(Startseite, FAQ, Blog, Kategorien, Städte, Firmenliste, Jobs) haben mit
`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` kodiert, aber **ohne**
`JSON_HEX_TAG`. Damit wäre `</script>` in einem Firmen-, Stadt- oder Jobnamen
roh im `<script>` gelandet.

**Behoben:** Alle Blöcke tragen jetzt `JSON_HEX_TAG`. `<` und `>` werden damit
zu `\u003C` und `\u003E`. Das ist gleichwertig zu `<\/script>`, greift aber
zusätzlich bei `<!--`, und `JSON_UNESCAPED_SLASHES` kann es nicht aufheben.
Die zentrale Komponente `resources/views/components/seo/json-ld.blade.php`
(Profil, Städte, Brotkrumen) und die Ratgeber-Partials hatten das Flag schon.

**Tests** (`tests/Unit/Seo/JsonLdEscapingTest.php`, 2 Tests grün):
- rendert `x-seo.json-ld` mit dem Namen `Evil </script><script>alert(1)</script> GmbH`
  und einem Bewertungstext `<!-- </SCRIPT > x`. Geprüft wird: kein `<` im
  Script-Inhalt, JSON dekodiert wieder zum Originalnamen, Slashes in URLs
  bleiben unescaped.
- durchsucht alle Blade-Views mit `application/ld+json` und schlägt fehl, sobald
  irgendwo `JSON_UNESCAPED_SLASHES` ohne `JSON_HEX_TAG` steht (Regressionsschutz).

**End-to-End:** Firma 4 lokal kurz auf den präparierten Namen umbenannt und das
Profil dreimal abgerufen:
- im JSON-LD (`Electrician`, `BreadcrumbList`) steht der Name als `\u003C/script\u003E…`,
- im HTML (H1, Brotkrume, Dialog) steht er als `&lt;/script&gt;`,
- rohe Vorkommen von `<script>alert`: 0.

Der Name ist danach zurückgesetzt. Gastbewertung mit `</script><img onerror>`
in Name und Text: im HTML escaped, 0 rohe Vorkommen. Bewertungstexte gehen nicht
ins JSON-LD, dort steht nur `AggregateRating`.

Hinweis: Beim allerersten Abruf zählte die Probe zwei rohe Treffer. In drei
Wiederholungen danach war es nicht mehr nachzustellen, und die kompilierten
Views tragen alle `JSON_HEX_TAG`. Wahrscheinlich hatte ein Worker von
`artisan serve` noch einen alten kompilierten View im OPcache. Beim Deploy
deshalb zwingend `view:clear` und einen PHP-FPM-Reload ausführen, siehe
Abschnitt 4.

### Melden-Rate-Limit

`App\Livewire\Reviews\ReportReviewButton::report()` als Gast (Schlüssel
`report:127.0.0.1`) auf eine freigegebene Bewertung, sechsmal hintereinander,
in einer Transaktion mit Rollback:

| Versuch | Ergebnis |
| --- | --- |
| 1–5 | angenommen, Bewertung → `needs_review` |
| 6 | abgewiesen: „Zu viele Meldungen. Bitte versuch es in 60 Minuten noch einmal.“ ✅ |

Das Limit greift wie in `config/moderation.php` konfiguriert (5 je Stunde).
Die Bewertung steht nach dem Rollback wieder auf `approved`, der Zähler ist
geleert. Der Schlüssel ist nicht mandantengetrennt. Das ist hier gewollt
strenger: eine IP teilt sich das Limit über alle Portale.

## 3. Footprint-Grep Produktion

Gelesen am 14.09.2026 auf Produktion, **vor** dem Deploy: Das Set liegt dort
noch nicht, `seo:clean-ai-footprints` ist also nie gelaufen. Als Benutzer
`sanitaerfinden` per `artisan tinker` und nur mit `SELECT`: die Muster aus
`config/seo.php` (`ai_footprints.patterns`) über `description`,
`meta_description` und `short_description` aller Tenants. Laufzeit 26 s.

| Portal (Prod-ID) | Firmen | Firmen mit Treffer |
| --- | ---: | ---: |
| elektrikerportal.com (30) | 29.432 | **12.090** |
| kfzwerkstatt.io (44) | 48.256 | **22.679** |
| malerfinder.de (33) | 23.193 | **324** |
| geruestbauer.gmbh (37) | 4.444 | **13** |
| alle übrigen 19 Portale | – | 0 |

Das ist der Ausgangswert. Die Abnahme „keine Treffer nach dem Cleanup“ ist
Schritt 4.6 und steht aus (siehe Abschnitt 5).

## 4. Deploy-Ablauf Produktion

Die Installation ist handgepflegt, nicht Deployer-verwaltet. `dep provision` und
`provision:*` dürfen dort **nie** laufen, weil der Server fremde Projekte hostet.
Der Ablauf folgt Abschnitt 11 von
`docs/messungen/produktionsumgebung-anleitung.md`. Alles läuft als `root` über
`ssh sun`, jeder Artisan-Befehl als `sudo -u sanitaerfinden`, im Verzeichnis
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`.

**Vorbedingung:** Das Set #2–#13, #16, #17 und #14 liegt committet auf
`origin/main`. Am 14.09.2026 lag es noch unversioniert im lokalen Arbeitsbaum,
zusammen mit laufender Arbeit an #18. Produktion steht auf `0b7445e`.

1. **DB-Dump vor dem Deploy** (zentral + alle Tenants, 4,4 TB frei auf `/home`):
   ```bash
   D=/home/sanitaerfinden/backups/pre-seo-hardening-$(date +%Y%m%d%H%M); mkdir -p $D
   for db in sun $(mysql -N -e "show databases like 'tenant\_%'"); do
     mysqldump --single-transaction --quick --routines "$db" | gzip > "$D/$db.sql.gz"; done
   ls -la $D && du -sh $D
   ```
   Zusätzlich existiert die nächtliche CloudPanel-Sicherung unter
   `/home/sanitaerfinden/backups/databases/` (zuletzt 14.09.2026 03:15).
   Pfad, Größe und Uhrzeit ins Protokoll.
2. **Code:** `cp -p .env .env.bak-14-<zeit>`, `git pull --ff-only origin main`,
   `composer install --no-dev -o --no-scripts && php artisan package:discover`,
   `npm ci && npm run build`.
3. **Migrationen:** `php artisan migrate --force`, dann
   `php artisan tenants:migrate --force`. Erwartet sind je Tenant 5 Migrationen:
   - `2026_09_10_000016` ist schon heute auf Produktion ausstehend (Pretend vom
     14.09.2026),
   - dazu `2026_09_14_000001` bis `000004`.

   `000004` belegt `moderation_status` aus `is_approved`. Danach
   `tenants:migrate --pretend`, erwartet ist „Nothing to migrate“ für alle 23 Tenants.
4. **Seeder** (nur Vorlagen, idempotent): `SeoTemplateSeeder`,
   `CityContentTemplateSeeder`, `CityDistrictsSeeder` je Tenant.
5. **Caches:** `config:cache`, `view:clear`, `route:clear`, `filament:optimize`,
   Reload von PHP-FPM (OPcache), `horizon:terminate`.
6. **Cleanup elektrikerportal:**
   - `php artisan seo:clean-ai-footprints --tenants=elektrikerportal.com`
     (Trockenlauf) und den Bericht unter `storage/app/seo-cleanup` sichten,
   - dann `--apply`; Originale landen in `company_description_backups`,
   - danach den Grep aus Abschnitt 3 wiederholen. Soll: 0, sonst nur Datensätze
     aus dem Bericht „manuell prüfen“.
   - `php artisan companies:foreign:cleanup --tenant=30 --details`,
     dann `--write` (#16).
   - `php artisan moderation:scan-existing-reviews --tenants=30` (Trockenlauf),
     dann `--apply` (#13).
7. **Probe:** Checkliste aus Abschnitt 1 gegen `https://elektrikerportal.com`
   (dieselben curl-Proben ohne Host-Header). Zusätzlich `sanitaerfinden.com`,
   `malerfinder.de` und `firmenfreund.de` auf HTTP 200.
8. **Sitemap:** `php artisan tenants:generate-sitemap --tenant=30`, danach
   `https://elektrikerportal.com/sitemap.xml` auf 200 prüfen und auf keine
   `/firmen?`-URLs.
9. **Search Console (Handarbeit, Konto Enes):**
   - Property `elektrikerportal.com` → Sitemaps → `sitemap.xml` einreichen,
   - URL-Prüfung → „Indexierung beantragen“ für 5 Profile und 3 Städteseiten
     (Vorschlag: die fünf Profile mit den meisten Bewertungen sowie
     `/staedte/hamburg`, `/staedte/stuttgart`, `/staedte/berlin`),
   - Rich Results Test mit den Live-URLs.

   Ein Agentenlauf hat keinen GSC-Nutzerzugang; das Dienstkonto ist auf
   Produktion nicht eingerichtet (#120).

**Nachbeobachtung:** 4 Wochen ab Live-Gang (Search Console, wöchentlich):
- indexierte `/firmen?`-URLs nehmen ab (Seiten → „Durch noindex-Tag ausgeschlossen“ steigt),
- Rich-Result-Impressionen nehmen zu (Leistung → Darstellung in der Suche),
- keine manuellen Maßnahmen.

## 5. Protokoll

| Schritt | Datum/Uhrzeit | Ergebnis | Kürzel |
| --- | --- | --- | --- |
| Lokale Checkliste (Abschnitt 1) | 14.09.2026 | grün | Sebastian |
| Security-Review (Abschnitt 2) | 14.09.2026 | 1 Befund, behoben, Tests grün | Sebastian |
| Footprint-Ausgangswert Prod (Abschnitt 3) | 14.09.2026 | 12.090 Treffer elektrikerportal | Sebastian |
| Commit + Push | 14.09.2026 | `92aa436` auf `origin/main` | Dimitri |
| DB-Dump (Pfad, Größe) | 14.09.2026 15:44–16:04 | `/home/sanitaerfinden/backups/pre-seo-hardening-202609141544/`, 24 Dateien (sun + 23 Tenants), 602 MB, alle `OK`; zweiter, paralleler Dump `…-202609141546/` (736 MB) | Dimitri |
| Code auf Produktion | 14.09.2026 16:05 | `git pull --ff-only` auf `92aa436`, keine Composer-/npm-Änderungen | Dimitri |
| Migrationen alle Tenants | 14.09.2026 | zentral „No pending migrations“, `tenants:migrate --pretend` für alle 23 Tenants „Nothing to migrate“ | Dimitri |
| Seeder | 14.09.2026 | `SeoTemplateSeeder` 7 Templates, `CityContentTemplateSeeder` 1 Vorlage/4 FAQ, `CityDistrictsSeeder` 50 Städte (jeweils nur elektrikerportal) | Dimitri |
| Assets + Caches | 14.09.2026 16:20 | `public/build` lokal auf `92aa436` gebaut und per rsync übertragen (11 Manifest-Einträge), `config:cache`, `route:clear`, `view:clear`, `filament:optimize`, Reload von `php8.5-fpm`, Horizon neu gestartet | Dimitri |
| Footprint-Cleanup Tenant 30 | 14.09.2026 | Trockenlauf 22.160 geprüft, 14.995 betroffen, 0 manuell; Stichproben sauber; `--apply` 14.995 bereinigt, Originale in `company_description_backups` | Dimitri |
| Footprint-Grep nach Cleanup = 0 | 14.09.2026 | Tenant 30: 29.432 Firmen, **0 Treffer** über `description`, `meta_description`, `short_description` ✅ | Dimitri |
| Weitere Bereinigung Tenant 30 | 14.09.2026 | `companies:foreign:cleanup --write` 284 deaktiviert; `cities:state:repair --write` 9 korrigiert; `cities:placeholder:cleanup --write` Ort „None“ entfernt (212 Firmen gelöst) | Dimitri |
| Bewertungs-Scan Tenant 30 | 14.09.2026 | **nicht angewendet**, nur Trockenlauf: 956 Treffer, davon 559 nur „Text länger als 1500 Zeichen“ (echte ausführliche Bewertungen). Heuristik wird in einem eigenen Ticket nachgeschärft | Dimitri |
| Live-Probe elektrikerportal.com | 14.09.2026 16:25 | Startseite 200 ohne „None“; 5 Profile (meiste Bewertungen) 200, `tel:+49…`, 0× `about:invalid`, `Electrician` + `BreadcrumbList`, keine Footprints; `/staedte/hamburg`, `berlin`, `stuttgart` mit Title-Template, Brotkrume, `ItemList` + `FAQPage` + `BreadcrumbList`; Filter-URLs `noindex, follow` + Canonical wie in Abschnitt 1 ✅ | Dimitri |
| Schema-Prüfung Live (validator.schema.org) | 14.09.2026 | `/staedte/hamburg`: BreadcrumbList, ItemList, FAQPage, 0 Fehler/0 Warnungen; `/1280-mb-elektro-berlin`: Electrician, BreadcrumbList, 0/0 ✅ | Dimitri |
| Rich Results Test Google | | offen, Handarbeit (keine Schnittstelle) – Live-URLs siehe unten | |
| Andere Portale | 14.09.2026 | sanitaerfinden.com, malerfinder.de, firmenfreund.de, kfzwerkstatt.io, zahnarzt.firmenfreund.de: 200; Laravel-Log seit Migrationsende 0 Fehler | Dimitri |
| Sitemap generiert | 14.09.2026 16:22 | `tenants:generate-sitemap --tenant=30`: Index + `sitemap-misc.xml` (5.493) + `sitemap-companies-1.xml` (29.148), 0× `/firmen?` ✅ | Dimitri |
| Sitemap in Search Console eingereicht | | offen, Handarbeit Enes (kein Dienstkonto) | |
| URL-Prüfung 5 Profile + 3 Städte | | offen, Handarbeit Enes (Google bietet dafür keine API) | |

**URLs für die URL-Prüfung** (Profile mit den meisten Bewertungen):
- https://elektrikerportal.com/27684-blosfeld-telekommunikation-u-elektro
- https://elektrikerportal.com/1280-mb-elektro-berlin
- https://elektrikerportal.com/25348-elektro-ehrhardt-e-k
- https://elektrikerportal.com/15112-eckhard-schnepf
- https://elektrikerportal.com/20745-engels-f
- https://elektrikerportal.com/staedte/hamburg
- https://elektrikerportal.com/staedte/berlin
- https://elektrikerportal.com/staedte/stuttgart

**Hinweise aus dem Deploy:**
- Beim Pull (14:05 UTC) warf eine Städteseite im Default-Theme einmal `Undefined variable $cityHeading`,
  und bis zum Ende von `tenants:migrate` (14:13 UTC) fehlte `city_content_templates` (17 Fehler).
  Beides lag im Zeitfenster zwischen Code und Migration, danach trat kein Fehler mehr auf.
  Beim Rollout-Deploy anderer Stände deshalb: Pull, sofort migrieren, dann Caches.
- `php artisan view:cache` bricht lokal an `components/city/faq.blade.php` ab (`x-sun.icon`
  gibt es nur im Theme sun-v2). Das ist kein Laufzeitfehler, weil die Komponente nur von
  sun-v2 eingebunden wird. Auf Produktion deshalb `view:clear` statt `view:cache`.
