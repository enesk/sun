# Spike: URL-Struktur der Firmenprofile (#15)

Stand 14.09.2026 · Analyse-Abschnitt 7 · Nur Entscheidungsgrundlage, keine Umsetzung.

## 1. Ausgangslage im Code

- Heutige kanonische Profil-URL: `/{id}-{slug}` (`routes/tenant.php`, Route `portal.companies.show`, letzte Route der Gruppe).
- Es gibt **bereits** ein zweites Schema je Tenant: `/{stadt}/{id}-{slug}` (`portal.companies.show.city`), umschaltbar in der Verwaltung (`settings.company_url_pattern`, `App\Services\CompanyUrlService`). Beide Routen sind parallel aktiv, die jeweils falsche leitet per 301 auf die kanonische um.
- Aufgelöst wird **ausschließlich über die ID** (`Company::findByUrlSlug()`); Slug- und Stadtteil sind kosmetisch und werden per 301 korrigiert. Deshalb bricht heute weder eine Umbenennung noch ein Stadtwechsel einen Link.
- Canonical (alle drei Themes), Sitemap (`GenerateSitemap`) und die meisten internen Links laufen über `$company->portal_url` → `CompanyUrlService::url()`. Eine Umstellung ist also an einer Stelle zentral.
- `companies.slug` ist je Tenant-DB `unique`; der Importer hängt bei Dubletten `-2`, `-3` an. Lokal tragen je nach Portal 1–24 % der Slugs ein Zahlensuffix (z. B. 6.669 von 28.181), das sind überwiegend Namensdubletten wie „Elektro Müller“.
- `/firma/{slug}/…` ist bereits für „Änderung vorschlagen“ und „Verifizierung“ belegt, `/firma/{slug}` selbst nicht.

## 2. Bewertung der Varianten

| | A: `/firma/{slug}` | B: `/staedte/{stadt}/{slug}` |
|---|---|---|
| Stabilität bei Umzug | stabil | URL ändert sich → 301-Kette |
| Stabilität bei Umbenennung | ändert sich (Slug) → 301 nötig | ändert sich → 301 nötig |
| Eindeutigkeit ohne ID | Slug je Tenant eindeutig (vorhanden), aber hässliche Suffixe `-2`, `-17` | eindeutig nur je Stadt; neuer Unique-Index `(city_id, slug)`, Suffixe seltener |
| Lookup | per Slug statt ID → Historientabelle zwingend | per Stadt + Slug → Historientabelle zwingend |
| Konflikt mit Stadtseite | keiner | `/staedte/{slug}` wird Elternpfad, Route-Reihenfolge und Reservierung von Stadt-Unterpfaden (z. B. FAQ-Anker, Paginierung) nötig |
| SEO-Signal | Keyword Firmenname, kein Ort | Ort im Pfad, klare Hierarchie zur Local-Hub-Seite |
| Risiko | mittel | hoch |

Der eigentliche Nachteil beider Varianten ist das **Weglassen der ID**: Heute ist jede je gebaute Profil-URL dauerhaft auflösbar, weil die ID nie wechselt. Ohne ID muss jede historische Slug-/Stadtkombination in einer Mapping-Tabelle leben, sonst gibt es 404.

Der SEO-Gewinn ist gering: Google wertet Keywords im Pfad kaum, Ortsbezug und Hierarchie liefern bereits Title-Templates (#10), BreadcrumbList (#9) und die Local Hubs (#11/#12). Die ID im Pfad ist kein Ranking-Nachteil.

**Empfehlung: No-Go – nicht jetzt.**
Begründung: geringer Nutzen, reales Ranking-Risiko auf ~24 Portalen, und die P1–P3-Maßnahmen sind gerade erst live (#14); ein URL-Wechsel würde deren Messung überlagern. Falls später doch gewünscht, ist der risikoärmste Weg **nicht** A oder B, sondern die vorhandene Variante `/{stadt}/{id}-{slug}` bzw. `/staedte/{stadt}/{id}-{slug}` (ID bleibt, Umzug/Umbenennung sind weiter per 301 ohne Mapping-Tabelle abgedeckt). Zwischen A und B wäre A vorzuziehen (stabil bei Umzug).

## 3. Aufwandsschätzung (bei Go, Variante A bzw. B ohne ID)

Personentage, inkl. Code-Review.

| Paket | Backend | Frontend | QA |
|---|---|---|---|
| Routing beider Schemata parallel (neue Route, Altroute `/{id}-{slug}` + `/{stadt}/{id}-{slug}` bleiben dauerhaft als 301) | 1,5 | – | 1 |
| Slug-Eindeutigkeit je Tenant (A: bestehend, Suffix-Bereinigung optional; B: Index `(city_id, slug)`, Migration über alle Tenants, Kollisionsauflösung) | A 1 / B 2,5 | – | 1 |
| 301-Mapping-Tabelle `company_url_history` (Tenant-DB, `path_hash` unique, `company_id`), Befüllung für Bestand, Observer auf slug/city_id-Änderung, Kettenvermeidung | 3 | – | 1,5 |
| Sitemap-Umstellung (`CompanyUrlService`, Regenerierung aller Portale, Einreichung GSC) | 0,5 | – | 0,5 |
| Canonical-Umstellung (zentral über `portal_url`; JSON-LD-Cache leeren, hart gebaute Links in Content-Posts/Ratgebern prüfen) | 1 | 0,5 | 1 |
| Themes (default, starter, sun-v2): hart kodierte Profil-Links, Anfrage-Dialog `firmenprofil.url` | – | 1 | 0,5 |
| Monitoring (404-/301-Zähler je Portal, Log-Auswertung nginx, GSC-Abgleich Klicks/Impressionen, Alarm) | 1,5 | – | 1 |
| **Summe** | **A ≈ 8,5 / B ≈ 10** | **1,5** | **7** |

Gesamt: **A ≈ 17 PT, B ≈ 18,5 PT**, zzgl. 6–8 Wochen Beobachtung je Welle. Zum Vergleich: Variante mit ID im Pfad (s. Empfehlung) ≈ 5 PT, da Routing, 301 und Settings bereits existieren.

## 4. Risikoabschätzung

- **Erwarteter Sichtbarkeitsverlust:** bei sauberen 1-Hop-301 typischerweise 10–25 % Klicks auf Profilseiten für 4–8 Wochen, Erholung nach 2–3 Monaten; bei Ketten, 404-Lücken oder Soft-404 deutlich mehr und dauerhaft. Profile tragen den Long-Tail („Firmenname + Ort“) – genau der reagiert empfindlich.
- **Backlinks bestimmen das Risiko:** vor jeder Entscheidung GSC-Export „Links → Häufigste verlinkte Seiten“ je Portal ziehen. Anteil externer Links auf `/{id}-{slug}`: < 5 % der Profile → Risiko gering; > 20 % oder Top-Profile mit Verweisen von Branchenverbänden/Presse → No-Go bleibt.
- **Betriebsrisiken:** Crawl-Budget (bis 60.000 Profile je Portal lokal) wird für Umindexierung verbraucht; gemeinsamer vhost/TLS unberührt; Redis-/JSON-LD-Caches enthalten alte URLs.
- **Rollout-Reihenfolge:** (1) kleines Portal mit wenig Traffic und wenigen Backlinks als Pilot, (2) nach 6 Wochen ohne Abbruchkriterium ein mittleres Portal, (3) Rest in Wellen von 4–5 Portalen, elektrikerportal.com und die drei größten zuletzt.
- **Abbruchkriterien (Rollback = Pattern-Schalter zurück, Altroute wieder kanonisch, Mapping bleibt):**
  - Klicks auf Profilseiten des Pilots > 30 % unter Vorjahres-/Vorperiode nach 4 Wochen,
  - GSC „Seite mit Weiterleitung“/„Nicht gefunden (404)“ steigt nach 2 Wochen weiter statt zu fallen,
  - 404-Rate auf Profilpfaden im nginx-Log > 0,5 % der Profilaufrufe,
  - Anteil indexierter Profile fällt > 15 % unter Ausgangswert.

## 5. Go/No-Go-Vorlage für Enes

| Frage | Antwort |
|---|---|
| Vorgeschlagene Entscheidung | **No-Go (nicht jetzt)** |
| Wiedervorlage | frühestens Q1/2027, nach Auswertung P1–P3 (GSC-Daten ≥ 3 Monate) |
| Vorbedingung für Go | GSC-Backlink-Export je Portal liegt vor und erfüllt Schwelle (§4) |
| Variante bei späterem Go | ID behalten: `/staedte/{stadt}/{id}-{slug}` (≈ 5 PT); A/B ohne ID nur mit Mapping-Tabelle (≈ 17–19 PT) |
| Bei Go | eigenes Epic mit Paketen aus §3, Pilotportal benennen |
| Entscheidung Enes | ☐ Go (Variante: ____)  ☐ No-Go  ☐ Wiedervorlage am ____ |
| Datum / Kürzel | |
