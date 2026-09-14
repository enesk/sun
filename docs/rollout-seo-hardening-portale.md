# Rollout SEO-Hardening auf die übrigen Portale (#14)

Für Enes. Voraussetzung ist der Live-Gang auf elektrikerportal.com nach
`docs/seo-abnahme-elektrikerportal.md` Abschnitt 4. Stand 14.09.2026.

## Was mit dem Deploy automatisch für alle Portale live geht

Code- und Template-Änderungen wirken sofort auf allen 23 Portalen. Das gilt für
jedes Theme, weil die Views je Theme angepasst wurden:
- noindex und Canonical auf Filter-URLs (#7),
- Brotkrumen und Stadtlinks (#6),
- tel:-Normalisierung (#5),
- JSON-LD für Profile, Städte und Brotkrumen (#8, #9),
- Title-Templates (#10),
- Local Hub mit Vorlage (#11, #12),
- fail-closed-Bewertungsmoderation (#13, #17),
- das JSON-LD-Escaping (#14).

**Nicht** automatisch passieren die datenverändernden Bereinigungen. Sie laufen
je Portal einzeln, immer zuerst trocken.

## 1. Schema-Typ-Mapping (geprüft)

`config/tenant-schema-types.php` → `domains`: Jede der 23 Produktionsdomains ist
eingetragen. Abgeglichen am 14.09.2026 gegen `tenants.domain` auf Produktion,
kein Portal fällt auf `default` zurück.

| Prod-ID | Domain | Typ | Anmerkung |
| ---: | --- | --- | --- |
| 30 | elektrikerportal.com | Electrician | Pilot |
| 28 | sanitaerfinden.com | Plumber | |
| 49 | sanitaerfinder.com | Plumber | |
| 54 | klempner.firmenfreund.de | Plumber | |
| 33 | malerfinder.de | HousePainter | |
| 44 | kfzwerkstatt.io | AutoRepair | |
| 24 | firmenfreund.de | GeneralContractor | gemischtes Verzeichnis, prüfen ob `LocalBusiness` passender ist |
| 37 | geruestbauer.gmbh | GeneralContractor | |
| 27 | bodenlegerfinden.com | HomeAndConstructionBusiness | |
| 46 | fliesenleger.io | HomeAndConstructionBusiness | |
| 38 | metallbauer.io | HomeAndConstructionBusiness | |
| 47 | mjet.net | HomeAndConstructionBusiness | |
| 51 | firmenfreund.net | HomeAndConstructionBusiness | |
| 29 | fahrschulefinder.de | DrivingSchool | |
| 43 | tierarztportal.com | VeterinaryCare | |
| 50 | apotheke.firmenfreund.de | Pharmacy | |
| 53 | zahnarzt.firmenfreund.de | Dentist | YMYL |
| 52 | unfallarzt.firmenfreund.de | Physician | YMYL |
| 56 | arztfinder.firmenfreund.de | Physician | YMYL |
| 55 | energieberaterportal.net | ProfessionalService | |
| 45 | findegutachter.de | LocalBusiness | kein passender Untertyp |
| 57 | speditionportal.com | LocalBusiness | kein passender Untertyp |
| 58 | schluesseldienstportal.com | Locksmith | |

Probe je Portal nach dem Deploy: ein Profil abrufen und `"@type"` im
`<script type="application/ld+json">` ablesen.

## 2. Footprint-Cleanup je Portal

Ausgangswert auf Produktion, 14.09.2026, nur lesend (Regex-Treffer der Muster aus
`config/seo.php` in `description`, `meta_description`, `short_description`):

| Prod-ID | Portal | Firmen | Treffer | Welle |
| ---: | --- | ---: | ---: | --- |
| 30 | elektrikerportal.com | 29.432 | 12.090 | Pilot |
| 44 | kfzwerkstatt.io | 48.256 | 22.679 | 1 |
| 33 | malerfinder.de | 23.193 | 324 | 1 |
| 37 | geruestbauer.gmbh | 4.444 | 13 | 1 |
| übrige 19 | – | – | 0 | kein Cleanup nötig, Trockenlauf trotzdem als Beleg |

Die Regex-Zählung ist nicht der Trockenlauf des Befehls. Der Befehl hat
zusätzlich die Schutzregeln `min_length` 80 und `max_removed_ratio` 0,5 und
meldet solche Fälle als „manuell prüfen“. Den echten Trockenlauf kann erst der
Deploy liefern.

Ablauf je Portal:

```bash
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
sudo -u sanitaerfinden php artisan seo:clean-ai-footprints --tenants=<ID>          # Trockenlauf, Bericht in storage/app/seo-cleanup
# Bericht sichten: Anzahl bereinigt / manuell prüfen, 10 Stichproben vorher/nachher lesen
sudo -u sanitaerfinden php artisan seo:clean-ai-footprints --tenants=<ID> --apply  # Original in company_description_backups
# Rückweg bei Bedarf: seo:restore-ai-footprints --tenants=<ID>
```

Dazu je Portal ebenfalls erst trocken:
- `companies:foreign:cleanup --tenant=<ID> --details`, dann `--write` (#16).
  Portale mit weniger als 50 % deutschen PLZ überspringt der Befehl selbst.
- `moderation:scan-existing-reviews --tenants=<ID>`, dann `--apply` (#13).
- `cities:placeholder:cleanup --tenant=<ID>`, dann `--write` (#4).

Die Portal-ID ist sicherer als der Domainname, weil nicht jeder Befehl Domains
auflöst.

Nach dem Cleanup den Grep aus `docs/seo-abnahme-elektrikerportal.md`
Abschnitt 3 wiederholen. Soll: 0 Treffer, oder nur die als „manuell prüfen“
gemeldeten Datensätze.

## 3. Wellen

| Welle | Portale | Start | Kriterium für die nächste Welle |
| --- | --- | --- | --- |
| Pilot | elektrikerportal.com | Live-Gang | 7 Tage: keine manuellen Maßnahmen, keine neuen Fehler unter „Verbesserungen“ (Breadcrumbs, Rezensions-Snippets), Anteil indexierter Seiten nicht eingebrochen |
| 1 | kfzwerkstatt.io, malerfinder.de, geruestbauer.gmbh | Pilot + 7 Tage | wie oben, je Portal |
| 2 | übrige Handwerksportale (Sanitär ×3, Boden, Fliesen, Metall, mjet, firmenfreund.de/.net, Schlüsseldienst, Energieberater, Spedition, Gutachter, Fahrschule) | Welle 1 + 7 Tage | – |
| 3 | YMYL: zahnarzt, unfallarzt, arztfinder, apotheke, tierarzt | Welle 2 + 7 Tage | Bewertungsmoderation hier besonders sichten (Gesundheitsaussagen in Bewertungen) |

„Welle“ heißt hier Datenbereinigung, Sitemap-Neuaufbau
(`tenants:generate-sitemap --tenant=<ID>`), Einreichung in der Search Console
und URL-Prüfung von 3 Städteseiten. Der Code ist ab dem Deploy überall aktiv.

## 4. Vor dem Rollout je Portal zu klären

- **Stadt-Vorlage (Intro + FAQ):** `CityContentTemplateSeeder` enthält bisher
  nur eine Vorlage für `elektriker`. Alle anderen Portale haben ohne eigene
  Vorlage keinen Local Hub, also weder Intro noch FAQ oder FAQPage-Schema. Das
  ist unschädlich, aber der Zugewinn fehlt. Je Portal eine branchengerechte
  Vorlage in Filament (Dashboard → Stadt-Vorlagen) anlegen oder im Seeder
  ergänzen, bevor die Städteseiten zur Indexierung eingereicht werden. Das ist
  Redaktionsarbeit und gehört nicht zum Deploy.
- **Bewertungen:** Mit #13 und #17 sind neue Bewertungen `pending` und nur
  globale Admins moderieren. Für Portale mit vielen Bewertungen einplanen, wer
  die Queue abarbeitet. `reviews:approve-all` gibt nur noch `pending` ohne
  Heuristik-Treffer frei.
- **Bundesländer (#18):** Solange #18 offen ist, können `addressRegion` im
  Profil-JSON-LD und Bundesland-Filter falsche Regionen tragen.
- **Search Console:** Für jedes Portal braucht es einen Nutzerzugang von Enes
  auf die Property, weil das Dienstkonto auf Produktion fehlt (#120).

## 5. Nachbeobachtung (4 Wochen je Welle)

Wöchentlich je Portal in der Search Console:
1. Seiten → „Durch noindex-Tag ausgeschlossen“ steigt, indexierte `/firmen?`-URLs sinken.
2. Leistung → Darstellung in der Suche: Impressionen „Rezensions-Snippet“, „Breadcrumbs“, „FAQ“ steigen.
3. Sicherheit & manuelle Maßnahmen: leer.
4. Verbesserungen: keine neuen ungültigen Elemente.

| Woche | Portal | noindex-Ausschlüsse | Rich-Result-Impr. | Manuelle Maßnahmen | Kürzel |
| --- | --- | --- | --- | --- | --- |
| | | | | | |
