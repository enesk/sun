# Ratgebersystem — Prompts, Styleguides, Quellenlisten, Qualitäts-Rubrik

Status: **zur Review durch Uwe** (Rubrik und YMYL-Regeln, Abschnitte 6 und 7)
Ticket: SUN-RG-006 (#7)
Stand: 2026-09-21

Vorgaben, aus denen `claude-sonnet-5` im Ratgebersystem (`docs/guide-system.md`) die Texte
erzeugt. Dieses Dokument legt keine Architektur fest, sondern befüllt `prompt_templates`
und `tenant_guide_settings` mit Inhalt.

## 1. Wo die Daten liegen

| Datensatz | Ort | Seeder |
|---|---|---|
| System-Prompt, 10 Stufen-Prompts, Rubrik | `prompt_templates` (central), Schlüssel `guide.<stufe>` | `GuidePromptTemplateSeeder` |
| 16 Branchen-Styleguides | `prompt_templates`, Schlüssel `guide.styleguide_<branch>`; Quelle `docs/styleguides/<branch>.md` | `GuidePromptTemplateSeeder` |
| Quellen-Whitelist/-Blacklist je Branche | `tenant_guide_settings.source_whitelist_json` / `source_blacklist_json` | `GuideSourceListSeeder` |

```bash
php artisan db:seed --class=GuidePromptTemplateSeeder   # global, einmalig und bei neuer Version
php artisan db:seed --class=TenantGuideSettingSeeder    # Voraussetzung (#3)
php artisan db:seed --class=GuideSourceListSeeder       # je Tenant
```

Der Tenant-Seeder folgt dem Muster der übrigen Content-Seeder: `Tenant::query()->each()` mit
`$tenant->run()`, idempotent, nicht in `DatabaseSeeder` registriert. Er füllt nur **leere**
Listen; im Dashboard gepflegte Listen bleiben unangetastet. Die Branche kommt aus
`BranchResolver::resolve($tenant)`; ohne Treffer gelten nur die gemeinsamen Listen.

Die Templates sind über die bestehende Tabelle `prompt_templates` im Prompt-Editor (#16)
editierbar; `PromptTemplate::scopeResolve()` liefert je Schlüssel die aktive Zeile
(tenantspezifisch vor global).

### Abweichung von der Ticketformulierung: keine `branch`-Spalte

`prompt_templates` hat keine Spalte `branch` (siehe `docs/content-prompts.md` §2). Ein
Branchen-Styleguide ist deshalb ein eigenes globales Template mit Schlüssel
`guide.styleguide_<branch>`; „branch nullable" gilt sinngemäß (alle übrigen Templates sind
branchenneutral). Eine Migration wurde nicht angelegt, das gehört nicht in dieses Ticket.
Ebenso heißen die Stufen-Schlüssel wie im Ticket (`freshness_probe`, `write_section`, …), nicht
wie in `guide-system.md` §7 (`guide.probe`, `guide.section_write`, …); der Präfix `guide.` ist
geblieben. Umbenennung oder Alias gehört zu #8/#10, wenn dort andere Namen gebraucht werden.

## 2. Variablen

Nur diese Variablen sind erlaubt (`GuidePromptTemplateSeeder::ALLOWED_VARIABLES`). Der
Renderer (#5/#10) ersetzt `{{name}}`; unbekannte Variablen sind ein Fehler.

| Variable | Bedeutung |
|---|---|
| `{{branch}}` | Branchenbezeichnung des Portals (`tenant_guide_settings.branch`) |
| `{{tenant_name}}` | Portalname |
| `{{question}}` | Thema als Frage (`guide_topics.question`) |
| `{{category}}` | Name der Ratgeber-Kategorie |
| `{{notes}}` | Redaktionsnotizen des Themas (`guide_topics.notes`) |
| `{{outline}}` | Gliederung als JSON (`guide_topics.outline_json`) |
| `{{section}}` | Ein Gliederungspunkt als JSON: `id`, `level`, `heading`, `fact_keys` |
| `{{current_facts}}` | Aktuelles Fakten-Set als JSON (`guide_facts.is_current`): key, label, value, unit, valid_from, source_url |
| `{{previous_facts}}` | Vorherige Werte geänderter oder abgelöster Fakten, gleiche Felder |
| `{{last_checked_at}}` | Datum der letzten Prüfung (`YYYY-MM-DD`) |
| `{{today}}` | Datum des Laufs (`YYYY-MM-DD`) |
| `{{sources}}` | Whitelist (bevorzugt) und Blacklist (verboten) aus `tenant_guide_settings` plus die bekannten `guide_sources` |
| `{{styleguide}}` | Text des Branchen-Styleguides (`guide.styleguide_<branch>`) |
| `{{existing_section_html}}` | Bestehendes HTML. Bei `update_section` der Abschnitt, bei `change_summary` die geänderten Abschnitte, bei `quality_rubric` der **gesamte Artikel** |
| `{{changed_facts}}` | Geänderte Fakten als JSON: key, label, old_value, new_value, unit, valid_from, source_url |
| `{{links}}` | Interne Linkziele des Abschnitts als Liste `<a href="/pfad">Anker</a>` oder `keine` (#10) |
| `{{fix_instructions}}` | Befunde des Qualitätsgates für einen Abschnitt, eine Zeile je Anweisung (`guide.fix_section`, #11) |
| `{{replace_sources}}` | Zu ersetzende Belege: je aktuellem Fakt mit nicht erreichbarer Quelle (`guide_sources.broken_at`) eine Zeile mit key, bisherigem Wert und kaputter URL, sonst `keine` (`guide.deep_research`, #27) |

`{{tenant_name}}`, `{{branch}}` und `{{styleguide}}` stehen im System-Prompt und sind in jeder
Stufe gefüllt. Ein Styleguide wird als fertiger Text eingesetzt; verschachtelte Platzhalter
werden nicht ersetzt. Der Seeder ersetzt deshalb `{{fact_snippets}}` der Alt-Styleguides
durch „dem Fakten-Set".

## 3. System-Prompt „Ratgeber-Redaktion"

Schlüssel `guide.system_ratgeber_redaktion` (Referenzzeile), gleichlautend als `system_prompt`
in jeder Stufe. Kernregeln: Deutsch, **Du-Ansprache**, keine Superlative, keine Floskeln,
**jede Zahl mit Quelle und Stand aus dem Fakten-Set**, keine erfundenen Zahlen/Quellen/Zitate,
kurze Absätze (2 bis 4 Sätze), Antwort zuerst, YMYL-Disclaimer laut Styleguide, nur JSON
gemäß Schema. Die Du-Ansprache gilt auch, wo Styleguides Beispiele in Sie-Form nennen
(die Alt-Styleguides sind für die Sie-Form der SUN-RC-Pipeline geschrieben).

Der System-Prompt ist in allen Stufen identisch und wird gecacht (Prompt-Caching); Stufen
unterscheiden sich nur im User-Prompt.

## 4. Stufen und Ausgabeschemata

Alle Schemata sind strikt (`additionalProperties: false`, alle Felder `required`, optionale
Werte als `["string","null"]`).

| Schlüssel | Zweck | Ausgabe → Ziel |
|---|---|---|
| `guide.freshness_probe` | günstige Prüfung, ob sich etwas geändert hat | `changed` (bool), `reason`, `candidate_changes[]` (`key`, `old_value`, `new_value`, `source_url`, `published_at`), `confidence` (0 bis 1) → `guide_topic_runs.probe_json` |
| `guide.deep_research` | Fakten-Set und Quellen | `facts[]` (`key`, `label`, `value`, `unit`, `valid_from`, `source_url`) → `guide_facts`; `sources[]` (`url`, `title`, `publisher`, `published_at`, `trust_level` = `official\|trade\|press\|other`) → `guide_sources`; `open_points[]` → `research_json` |
| `guide.propose_outline` | Gliederung vorschlagen | `sections[]` (`id` `sN`, `level` 2, `heading`, `fact_keys`, `children[]` mit `id` `sN-M`, `level` 3) → `guide_topics.outline_json` |
| `guide.write_section` | Abschnitt der Erstfassung | `section_id`, `html`, `used_fact_keys` → `guide_article_versions.body_html` |
| `guide.update_section` | Abschnitt fortschreiben | `section_id`, `changed`, `html`, `edited_sentences[]` (`old`, `new`, `fact_key`) |
| `guide.faq` | Häufige Fragen | `faq[]` (`question`, `answer`, 4 bis 6) → `guide_article_details.faq_json` |
| `guide.short_answer` | Kurzantwort | `short_answer`, `used_fact_keys` → `guide_article_details.short_answer` |
| `guide.meta` | Meta-Angaben | `meta_title` (≤ 60), `meta_description` (100 bis 160), `primary_keyword` → `posts` |
| `guide.change_summary` | Changelog-Eintrag | `summary`, `changes[]` (`key`, `label`, `old_value`, `new_value`), `section_ids` → `changelog_json` (+ `date`, `version` vom System), `guide_article_versions.change_summary` = `summary` |
| `guide.assign_facts` | geänderte Fakten ohne Abschnitt dem thematisch nächsten Abschnitt zuordnen (#9, `ChangeDetector`) | `assignments[]` (`fact_key`, `section_id`, `reason`) → `guide_article_details.section_fact_map_json.assigned` |
| `guide.category_intro` | Kategorie-Einleitung | `intro_html`, `meta_title`, `meta_description` → `guide_categories` |
| `guide.quality_rubric` | Bewertung, Abschnitt 6 | `score`, `per_criterion[]`, `blocking_issues[]`, `fix_instructions[]` → `guide_topic_runs.quality_score`, `quality_report_json` |
| `guide.legacy_category` | Kategorie eines Bestandsartikels wählen (`guide:legacy:categorize --smart`, #19); eigener kurzer System-Prompt | `category_slug` (slug aus `{{category}}` oder `null` = „Allgemein“), `reason` → `posts.guide_category_id` |

Abgestimmt mit `docs/guide-system.md` §4 und der Migration `create_guide_tables`: `key` ≤ 128,
`value` ≤ 1000 Zeichen, `valid_from`/`published_at` als Datum (`format: date`), `trust_level`
wie `App\Guide\Enums\TrustLevel`. `facts[].source_url` wird beim Speichern über `sources[].url`
zu `guide_facts.source_id` aufgelöst; eine URL, die nicht in `sources` steht, verwirft der
Importer (#8).

### `update_section` — Wortgleichheit

Der Prompt verlangt: Unveränderte Aussagen bleiben **wortgleich** (Wortlaut, Reihenfolge,
Satzzeichen, HTML-Struktur); nur Sätze, die einen der `{{changed_facts}}` nennen oder
unmittelbar daraus folgen, werden angepasst. `edited_sentences` macht das prüfbar: Der Gate (#11)
kann den Rest des HTML gegen den Bestand vergleichen. Beispiel:
`docs/samples/guide-musterartikel-update.md`.

## 5. Branchen-Styleguides

Quelle: `docs/styleguides/<branch>.md` (16 Branchen: `fliesen`, `metallbau`, `geruestbau`,
`gartenbau`, `hoch-tiefbau`, `solar-pv`, `energieberatung`, `sanitaer`, `elektro`, `maler`, `kfz`,
`spedition`, `fahrschule`, `tierarzt`, `gutachter`, `medizin`; identisch mit
`BranchResolver::all()`). Jede Datei enthält Kurzprofil, Fachvokabular, typische Nutzerfragen,
Preisrahmen-Hinweise, Pflicht-Disclaimer und Verbotsliste. Die längste Datei hat rund 220
Wörter, das Limit von 1.200 ist deutlich unterschritten. Die bestehenden Styleguides der
SUN-RC-Pipeline wurden **übernommen, nicht neu geschrieben**, und im Seeder unter dem neuen
Schlüssel geführt, damit es eine einzige Fassung gibt. Änderungen an den Dateien wirken erst
mit einer neuen Seeder-Version (Abschnitt 9).

## 6. Qualitäts-Rubrik (Review Uwe)

Schlüssel `guide.quality_rubric`. Bewertet wird der ganze Artikel gegen Fakten-Set und
Styleguide; das Modell schreibt nicht um.

| Kriterium (`key`) | Punkte | Prüft |
|---|---|---|
| `belege` | 25 | jede Zahl, jeder Preis, jede Frist mit Quelle im Text |
| `faktentreue` | 20 | Werte entsprechen dem Fakten-Set |
| `aktualitaet` | 10 | keine Angabe älter als `valid_from` einer neueren Quelle; Stand genannt |
| `suchintention` | 10 | Frage zuerst und direkt beantwortet |
| `sprache` | 10 | Du-Ansprache, kurze Absätze, keine Floskeln |
| `neutralitaet` | 10 | keine Superlative, keine Werbesprache, keine Verbotswörter |
| `ymyl` | 10 | Pflicht-Disclaimer laut Styleguide, keine Fachbetriebsanleitung |
| `struktur` | 5 | Überschriften, Gliederung, FAQ und Kurzantwort |

Ausgabe: `score` (0 bis 100, Summe der Punkte), `per_criterion[]` (`key`, `points`,
`max_points`, `comment`), `blocking_issues[]` (`code`, `section_id`, `quote`, `explanation`),
`fix_instructions[]` (`section_id`, `instruction`).

**Blockierende Codes:**

| Code | Auslöser |
|---|---|
| `unbelegte_zahl` | Zahl, Preis oder Frist ohne Quelle im Text oder ohne Entsprechung im Fakten-Set |
| `faktenwiderspruch` | Wert weicht vom Fakten-Set ab |
| `veraltete_angabe` | Angabe beruht auf einem Stand, der älter ist als das `valid_from` einer neueren Quelle |
| `ymyl_disclaimer_fehlt` | Styleguide verlangt Pflicht-Disclaimer, er fehlt oder ist verwässert |
| `werbesprache` | Superlative, Werbeversprechen, Firmenempfehlung, Wort der Verbotsliste |
| `sonstiges` | weitere schwere Fälle (z. B. Selbstbauanleitung für Fachbetriebsarbeiten) |

Regeln für den Gate (#11), nicht für das Modell: Ein einziges `blocking_issue` verhindert die
Auto-Freigabe **unabhängig vom Score**; das Modell deckelt den Score dann auf höchstens 69. Die
Schwelle sonst: `tenant_guide_settings.auto_publish_threshold` (80, YMYL 90). Der Gate prüft
Zahlen und Fakten zusätzlich deterministisch (Fakten-Abgleich), die Rubrik ist die zweite
Instanz, nicht die einzige.

## 7. YMYL-Regeln (Review Uwe)

Aus den Styleguides, unverändert:

| Stufe | Branchen | Regel |
|---|---|---|
| Pflicht-Disclaimer in jedem Artikel | `medizin`, `tierarzt`, `gutachter`, `energieberatung` | Text laut Styleguide; fehlt er → `ymyl_disclaimer_fehlt` |
| Pflicht-Vorbehalt bei Förder-/Finanzaussagen | `solar-pv` | Hinweis auf sich ändernde Bedingungen, keine Renditeversprechen |
| Sicherheits-Hinweis | `elektro`, `geruestbau`, `kfz`, `fahrschule`, `hoch-tiefbau` | Arbeiten nur durch Fachkräfte/Fachbetriebe bzw. Verweis auf verbindliche Stelle |
| Verweis statt eigener Aussage | `sanitaer`, `fliesen`, `gartenbau`, `metallbau`, `maler`, `spedition` | auf Norm/Verordnung/Behörde verweisen |

Zu klären durch Uwe: (a) ob der Sicherheits-Hinweis bei `elektro`/`kfz`/`geruestbau` wie der
Pflicht-Disclaimer **blockierend** sein soll (jetzt: die Rubrik bewertet ihn im Kriterium `ymyl`,
blockiert aber nur bei Pflicht-Disclaimern); (b) ob `energieberatung` und `solar-pv` tatsächlich
`is_ymyl = true` (Schwelle 90) bekommen sollen; (c) die Formulierungen der Disclaimer.

## 8. Quellenlisten

`GuideSourceListSeeder` (Daten als statische Methoden, auch für Tests aufrufbar).

* **Whitelist-Eintrag:** `domain`, `publisher`, `type` (`behoerde`, `verband`, `kammer`,
  `foerderstelle`, `fachpresse`, `normung`, `wissenschaft`), `trust_level` (`official` für
  Behörde/Förderstelle/Normung/Wissenschaft, `trade` für Verband/Kammer, `press` für Fachpresse).
* **Blacklist-Eintrag:** `domain`, `reason` (`forum`, `social`, `content_farm`,
  `wettbewerber_verzeichnis`).
* **Gemeinsam für alle Branchen:** Bundesrecht, Statistik, Verbraucherzentrale, Stiftung Warentest,
  DIHK/IHK, ZDH/Handwerkskammern, DIN; Blacklist: Foren, Social-Media-Portale und die großen
  Verzeichnis-/Vermittlungsportale (Wettbewerber der Portale).
* **Je Branche:** Verbände, Behörden, Förderstellen (BAFA, KfW), Fachpresse; bei Bau-Gewerken
  zusätzlich Bau-Foren auf der Blacklist, bei Medizin die Bewertungsportale.

Die Domains stammen aus Fachwissen und wurden **nicht live geprüft** (kein Netzzugriff in
diesem Ticket). Vor Go-Live (#21) stichprobenhaft prüfen; Korrekturen im Dashboard oder in
der Seeder-Datei. Blacklist-Domains gelten samt Subdomains; die Whitelist ist eine
Bevorzugung, keine Ausschlussliste — belegte Angaben aus anderen seriösen Quellen sind
erlaubt, werden aber als `trust_level = other` geführt.

## 9. Versionierung

Templates werden nicht überschrieben. Der Seeder legt Zeilen idempotent über
`(key, tenant_id, version)` an; eine inhaltliche Änderung bekommt im Seeder eine höhere
Version (`'version' => 2` in der Definition), die bisherige Zeile wird deaktiviert und bleibt
erhalten. Im Dashboard erzeugt jede Bearbeitung ebenfalls eine neue Version (#16). Die Lauf-Daten
(`probe_json`, `quality_report_json`) sollen die verwendete Version festhalten; das setzen die
Folgetickets (#8, #10, #11) um.

### Versionen seit #8

`guide.freshness_probe` und `guide.deep_research` liegen in Version 2: beide nehmen
`{{notes}}` als verbindlichen Recherche-Auftrag, die Tiefenrecherche bekommt zusätzlich
`{{last_checked_at}}` und liefert bei widersprüchlichen Quellen je Quelle einen eigenen
Eintrag mit demselben `key` (die Auflösung macht `ResearchService`). `probe_json.meta` und
`research_json.meta` halten Schlüssel und Version des verwendeten Templates fest.

### Versionen seit #10

`guide.propose_outline`, `guide.write_section`, `guide.update_section`, `guide.meta` und
`guide.change_summary` liegen in Version 2. Ihr Ausgabeschema gehört dem Code
(`OutlineService::outputSchema()`, `SectionWriter::writeSchema()`/`updateSchema()`,
`MetaWriter::outputSchema()`, `ChangelogWriter::outputSchema()`); der Seeder referenziert es.
Änderungen: 4 bis 7 H2; `write_section` liefert `summary_sentence` (Ein-Satz-Fazit der H2) und
nimmt die neue Variable `{{links}}` (interne Linkziele des Abschnitts); `update_section` liefert
`used_fact_keys`; `meta_description` höchstens 155 Zeichen; `change_summary` liefert
`sentences[]` (`fact_key`, `sentence`), ein Satz je Änderung. Nach dem Deploy
`php artisan db:seed --class=GuidePromptTemplateSeeder` ausführen.

### Versionen seit #11

`guide.quality_rubric` liegt in Version 2: Beginnt der Artikeltext mit „[Nur geänderte Teile: …]“
(Update-Lauf), bewertet das Modell nur diese Teile. Das Ausgabeschema gehört dem Code
(`App\Guide\Quality\RubricEvaluator::outputSchema()`, inhaltlich unverändert). Neu ist
`guide.fix_section` (Fix-Durchlauf des Qualitätsgates, Variable `{{fix_instructions}}`, Schema
`SectionWriter::writeSchema()`). Nach dem Deploy
`php artisan db:seed --class=GuidePromptTemplateSeeder` ausführen.

### Versionen seit #27

`guide.deep_research` liegt in Version 3: Block „Zu ersetzende Belege“ (`{{replace_sources}}`)
mit der Anweisung, Fakten mit nicht erreichbarer Quelle anderweitig zu belegen und die
genannten URLs nicht zu verwenden (design/guide-dashboard.md §5.7.1). Liefert das Modell eine
kaputte URL trotzdem, verwirft `ResearchService` den Kandidaten (`research_json.rejected`,
Grund `broken`). Nach dem Deploy `php artisan db:seed --class=GuidePromptTemplateSeeder`
ausführen.

## 10. Musterartikel

* `docs/samples/guide-musterartikel.md` — Erstfassung nach `article_blueprint`.
* `docs/samples/guide-musterartikel-update.md` — Fassung 2 mit simuliertem Update, Wortgleichheit,
  Changelog-Eintrag, Stand-Zeile und Rubrik-Ergebnis.

Beide enthalten ausschließlich **Platzhalter**-Zahlen und sind keine Fakten.
