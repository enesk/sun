# Content-Prompts — Templates, Styleguides, Rubrik, Saisonkalender, Keywords

Status: **verbindlich**
Ticket: SUN-RC-013 (#13)
Letzte Änderung: 2026-09-09

Dieses Dokument beschreibt die Redaktionsvorgaben, aus denen `claude-sonnet-5` zur
Laufzeit Ratgebertexte erzeugt: das System-Prompt „Ratgeber-Redakteur", die
Branchen-Styleguides, die Prompts je Pipeline-Stufe, die Qualitäts-Rubrik, den
Saisonkalender und die Branchen-Keywords. Es referenziert `docs/content-pipeline.md`
(#2/#3) und legt keine neue Architektur fest, sondern befüllt die dort eingefrorenen
Tabellen mit Inhalt.

---

## 1. Wo die Daten liegen

| Datensatz | Tabelle | Verbindung | Seeder |
|---|---|---|---|
| System-Prompt, Styleguides, Stufen-Prompts, Rubrik | `prompt_templates` | central | `database/seeders/PromptTemplateSeeder.php` |
| Saisonkalender | `seasonal_topics` | je Tenant | `database/seeders/SeasonalTopicSeeder.php` |
| Branchen-Keywords | `tenant_content_settings.branch_keywords_json` | je Tenant | `database/seeders/BranchKeywordSeeder.php` |

Ausführung:

```bash
php artisan db:seed --class=PromptTemplateSeeder
php artisan db:seed --class=SeasonalTopicSeeder
php artisan db:seed --class=BranchKeywordSeeder
```

Die beiden tenantbezogenen Seeder laufen über alle bestehenden Tenants (`Tenant::query()->each(...)`)
und sind idempotent: Sie legen keine Duplikate an, sondern überspringen bzw. aktualisieren
vorhandene Zeilen. Sie sind bewusst **nicht** in `database/seeders/DatabaseSeeder.php`
verdrahtet — genau wie `TenantContentSettingSeeder` (#3) laufen sie einmalig beim Rollout
bzw. beim Anlegen eines neuen Tenants, nicht bei jedem `db:seed`.

## 2. Abweichung von der Ticketformulierung: keine `branch`-Spalte

Ticket #13 nennt in den technischen Hinweisen eine Spalte `branch` auf `prompt_templates`.
Die von #3 tatsächlich angelegte und eingefrorene Tabelle (siehe `docs/content-pipeline.md` §4)
hat diese Spalte nicht — nur `tenant_id` (nullable = global), `key` und `version`
(`unique(key, tenant_id, version)`). Eine nachträgliche Spalte hätte eine neue Migration und
eine Ticket-#3-Abweichung bedeutet, die dieses Ticket nicht rechtfertigt.

**Entscheidung:** Branchen-Styleguides sind globale Templates (`tenant_id = null`) mit dem
Schlüssel `styleguide_<branch>`, z. B. `styleguide_sanitaer`. Das vermeidet 24-fache
Duplikation identischen Textes über alle Tenants einer Branche hinweg und passt zur
Anforderung „Prompt-Caching-tauglich" — der Styleguide-Text ändert sich pro Branche, nicht
pro Tenant. Ein Tenant kann sein Branchen-Template weiterhin überschreiben, indem später ein
tenantspezifisches Template mit demselben `key` und einer höheren `version` angelegt wird;
`PromptTemplate::scopeResolve()` bevorzugt automatisch tenantspezifisch vor global.

Ebenso referenziert das Ticket `article_drafts.template_versions_json` — diese Spalte
existiert nicht in der #3-Migration. Versionierung läuft ausschließlich über
`prompt_templates.version` (aufsteigend je `key`) plus `is_active`; alte Versionen bleiben
als inaktive Zeilen für die Nachvollziehbarkeit erhalten, statt gelöscht zu werden.

## 3. Branchen-Zuordnung der Tenants

Es gibt keine Spalte und keine Konfigurationsliste, die einen Tenant einer Branche
zuordnet — jeder Tenant ist ein Branchenportal für genau ein Gewerk, erkennbar am Namen
bzw. an der Domain. `App\Content\Support\BranchResolver::resolve(Tenant $tenant)` bildet das
über einen Slug-Abgleich ab (Name + Domain, kleingeschrieben, `Str::slug()`), analog zu
`TenantContentSettingSeeder::isYmyl()`. Ergebnis ist einer der folgenden 16
Branchen-Schlüssel oder `null` (Tenant ohne Treffer bekommt keine Styleguide-Zuordnung und
muss manuell nachgepflegt werden):

| Schlüssel | Branche | YMYL |
|---|---|---|
| `sanitaer` | Sanitär/Heizung | nein |
| `fliesen` | Fliesenleger | nein |
| `elektro` | Elektro | nein |
| `maler` | Maler/Lackierer | nein |
| `metallbau` | Metallbau | nein |
| `geruestbau` | Gerüstbau | nein |
| `gartenbau` | Garten- und Landschaftsbau | nein |
| `hoch-tiefbau` | Hoch- und Tiefbau | nein |
| `solar-pv` | Solar/Photovoltaik | nein |
| `energieberatung` | Energieberatung | ja |
| `kfz` | Kfz-Werkstatt | nein |
| `spedition` | Spedition/Logistik | nein |
| `fahrschule` | Fahrschule | nein |
| `tierarzt` | Tierarzt | ja |
| `gutachter` | Gutachter/Sachverständige | ja |
| `medizin` | Arzt/Zahnarzt/Unfallarzt/Apotheke | ja |

`{{branch}}` in den Prompts unten transportiert immer den Anzeigenamen aus dieser Tabelle
(`BranchResolver::label()`), nicht den Schlüssel.

## 4. Erlaubte Variablen

`PromptRenderer` (#6) ersetzt ausschließlich `{{var}}`-Platzhalter mit Buchstaben, Ziffern,
Unterstrich und Punkt und wirft, wenn eine im Template verwendete Variable fehlt. Jedes
Template in `PromptTemplateSeeder` verwendet **nur** die folgenden Variablen — keine
weiteren, keine Ausdruckslogik:

| Variable | Inhalt | Befüllt durch |
|---|---|---|
| `{{branch}}` | Anzeigename der Branche (siehe §3) | Aufrufer (#14) über `BranchResolver` |
| `{{tenant_name}}` | Name des Portals | `Tenant::$name` |
| `{{primary_keyword}}` | Haupt-Keyword des Artikels | Themenauswahl (#12) |
| `{{secondary_keywords}}` | Liste sekundärer Keywords | Themenauswahl (#12) / Keyword-Clustering |
| `{{intent}}` | Suchintention (informational, transactional, …) | Themenauswahl (#12) |
| `{{region_scope}}` | `national`, `state` oder `city` | Themenauswahl (#12) |
| `{{region_name}}` | Klartext-Regionsname, z. B. „Bayern" | Themenauswahl (#12) |
| `{{serp_paa}}` | People-Also-Ask-Fragen aus der SERP | Connector #10 |
| `{{competitor_h2s}}` | H2-Überschriften der Top-Wettbewerber | Connector #10 |
| `{{fact_snippets}}` | Belegte Fakten/Zahlen mit Quelle | Connectoren #11, `fact_snippets`-Tabelle |
| `{{portal_data}}` | Eigene Portaldaten (Anzahl Betriebe, Städte, Bewertungen) | Connector #11 (Portal-Eigendaten) |
| `{{seasonal_hook}}` | Aktueller Saisonanlass | `seasonal_topics` (dieses Ticket) |
| `{{news_hook}}` | Aktueller Nachrichtenanlass | Connector #11 (regionale News) |
| `{{internal_link_targets}}` | Interne Linkziele (Titel + URL) | Themenauswahl (#12); in `topic_discover` v2 die Titel vorhandener Ratgeber, sonst „keine" |
| `{{signals}}` | Nummerierte Rohsignale des Fensters; die Nummer ist die `source_items`-ID | `DiscoverTopicsJob::signalBlock()` (nur `topic_discover` v2) |
| `{{output_rules}}` | Ausgaberegeln der Themenfindung | `DiscoverTopicsJob::rulesBlock()` (nur `topic_discover` v2) |
| `{{max_candidates}}` | Obergrenze aus `config('content.topics.discover.max_candidates')` | `DiscoverTopicsJob` (nur `topic_discover` v2) |
| `{{styleguide}}` | Text des passenden `styleguide_<branch>`-Templates | Aufrufer lädt es separat und reicht es durch |
| `{{outline}}` | Bereits erzeugte Gliederung (JSON aus der `outline`-Stufe) | Vorherige Pipeline-Stufe |
| `{{section}}` | Ein einzelner Abschnitt bzw. der bisherige Volltext | Vorherige Pipeline-Stufe / Refresh-Loop |

Templates tragen die von ihnen benötigte Teilmenge zusätzlich in `variables_json`, damit
`PromptRenderer::missingVariables()` auch dann greift, wenn eine Variable (noch) nicht im
Text vorkommt, aber vom Aufrufer erwartet wird.

## 5. Templates in `PromptTemplateSeeder`

| `key` | Zweck | Braucht `output_schema_json` |
|---|---|---|
| `system_ratgeber_redakteur` | Referenztext der Redaktionsstandards (wird 1:1 als `system_prompt` in alle Schreib-Stufen unten übernommen) | nein — reines Referenzdokument, kein eigener LLM-Aufruf |
| `styleguide_<branch>` (16×) | Branchen-Styleguide, als `{{styleguide}}` in andere Prompts eingesetzt | nein |
| `topic_discover` | Themenvorschläge je Branche/Region ableiten | ja |
| `outline` | Gliederung (H2/H3) für ein gewähltes Thema | ja |
| `section_write` | Einen Abschnitt der Gliederung ausformulieren | ja |
| `faq` | FAQ-Block aus PAA + Fakten ableiten | ja |
| `meta` | Meta-Title und Meta-Description erzeugen | ja |
| `short_answer` | Kurzantwort (Featured-Snippet-tauglich) erzeugen | ja |
| `regional_block` | Regionalen Absatz mit Portal-/Regionaldaten erzeugen | ja |
| `refresh_update` | Veralteten Abschnitt anhand neuer Fakten aktualisieren | ja |
| `lead_question_cluster` | Anonymisierte Nutzereingaben zu Fragethemen bündeln (`ClusterLeadQuestionsJob`) | ja |
| `quality_rubric` | Qualitäts-Rubrik für `QualityCheckJob` (#15) | ja |

Jede Schreib-Stufe (`outline` bis `lead_question_cluster`) trägt denselben
`system_prompt`-Text wie `system_ratgeber_redakteur`. Ausnahmen sind `topic_discover` und `lead_question_cluster` ab
Version 2: die Themenfindung schreibt keinen Artikel, und der Redakteurstext verlangt
`{{styleguide}}` und `{{fact_snippets}}`, die es an dieser Stelle nicht gibt. Dort steht
der Planer-Text `App\Content\Jobs\DiscoverTopicsJob::SYSTEM_PROMPT` bzw. der
Auswertertext `App\Content\Jobs\ClusterLeadQuestionsJob::SYSTEM_PROMPT`, den der Seeder
wörtlich übernimmt. Das ist bewusst dupliziert statt über
eine Fremdreferenz gelöst: `LlmClient` liest `system_prompt` direkt von der aufgerufenen
Zeile, eine Indirektion über einen zweiten Datenbank-Lookup pro Aufruf wäre unnötige
Komplexität für Text, der sich nur bei einer neuen Version ändert (dann bekommen alle
Stufen-Templates dieselbe neue Version).

`output_schema_json` je Stufe ist so geschnitten, dass es direkt auf Spalten von
`article_drafts` (#3) abbildet:

| Stufe | Schema-Felder | Ziel-Spalte(n) |
|---|---|---|
| `outline` | `title`, `slug`, `outline[]` | `title`, `slug`, `outline_json` |
| `section_write` (v2) | `heading`, `summary_sentence`, `html`, `word_count`, `used_fact_ids`, `used_link_urls` | Baustein von `body_html`, Belegkette für `draft_sources` |
| `faq` | `faq[]` (`question`, `answer`) | `faq_json` |
| `meta` | `meta_title`, `meta_description` | `meta_title`, `meta_description` |
| `short_answer` | `short_answer` | `short_answer` |
| `regional_block` (v2) | `heading`, `intro`, `html`, `outro` | Baustein von `body_html` bzw. `outline_json.regional_*`, `region_scope`, `region_code` |
| `refresh_update` | `changes[]` (`section_heading`, `updated_html`, `reason`) | Aktualisierung von `body_html` |
| `lead_question_cluster` (v2) | `questions[]` (`question`, `share`, optional `keyword`) | `source_items` vom Typ `lead_question`, Eingabe für #12 (Scoring), nicht direkt `article_drafts` |
| `quality_rubric` | `score`, `per_criterion[]`, `blocking_issues[]`, `fix_instructions[]` | `quality_score`, `quality_report_json` |

Fünf Stufen liegen in Version 2 vor (#14, #47, #66, #75). Die Version wird je Vorlage geführt
(`upsert(..., version:)`); der Vorgabewert bleibt 1, damit eine Änderung an einer Vorlage
nicht 25 inhaltsgleiche Zeilen anlegt. Version 1 bleibt aktiv im Bestand, damit
nachvollziehbar bleibt, mit welchem Stand ein Artikel entstanden ist; `scopeResolve`
nimmt die höchste Version.

* `section_write` v2 verlangt `summary_sentence`. Der Satz wird als erster Absatz jeder
  H2 gerendert und ist Abnahmekriterium von #14. Das Ausgabeschema gehört dabei dem Code
  (`App\Content\Generation\SectionStep`), nicht dem Template: es ist der Vertrag zum
  `HtmlAssembler`, und eine Änderung im Prompt-Editor darf den Zusammenbau nicht brechen.
  Der Templatetext bleibt redaktionell editierbar.
* `regional_block` v2 liefert zusätzlich `intro` und `outro` als Klartext. Bei
  Stadt-Zuschnitt rendert das Ratgeber-Template (#17) daraus einen eigenen Baustein, bei
  Landes-Zuschnitt wandert das HTML als Abschnitt in den Fließtext. Die Faktenpaare des
  Blocks entstehen ohne Modellaufruf aus `fact_snippets` und den Portaldaten.

* `topic_discover` v2 trägt den ganzen Prompt der Themenfindung: Signalliste und
  Ausgaberegeln stehen als `{{signals}}` und `{{output_rules}}` im Text, statt vom Job
  angehängt zu werden. `variables_json` sind genau `tenant_name`, `branch`, `signals`,
  `internal_link_targets`, `max_candidates`, `output_rules`. `{{region_scope}}` und
  `{{region_name}}` entfallen: Über den Zuschnitt entscheidet der `RegionScopeResolver`
  nach dem Modellaufruf. Das Ausgabeschema gehört dem Code
  (`App\Content\Jobs\DiscoverTopicsJob::outputSchema()`); passt das Schema der Vorlage
  nicht, läuft der Aufruf mit dem Code-Schema weiter und protokolliert eine Warnung.

`topic_discover` schreibt nicht in `article_drafts`, sondern in `topic_candidates` (#12) —
es liegt trotzdem hier, weil es denselben Redaktionsrahmen braucht.

## 6. Qualitäts-Rubrik

`quality_rubric` bewertet acht gewichtete Kriterien (Summe der Gewichte = 100) und liefert
ein JSON mit `score` (0–100), `per_criterion[]`, `blocking_issues[]` und
`fix_instructions[]`:

| Kriterium | Gewicht | Prüft |
|---|---|---|
| Einzigartigkeit | 15 | Kein Textbaustein deckungsgleich mit bestehenden Artikeln des Tenants oder Wettbewerbern |
| Faktenbelege | 20 | Jede Zahl/Statistik hat eine Entsprechung in `{{fact_snippets}}`, keine widersprüchliche Angabe |
| Suchintention | 15 | Artikel beantwortet `{{intent}}` bereits im ersten Abschnitt |
| Struktur | 10 | Gliederung folgt `{{outline}}`, sinnvolle H2/H3-Hierarchie, keine Duplikate |
| Lesbarkeit | 10 | Kurze Absätze, aktive Sprache, keine Floskeln/Superlative aus der Verbotsliste |
| Interne Links | 10 | Mindestens die in `{{internal_link_targets}}` vorgesehenen Linkziele sind sinnvoll eingebaut |
| Regionalität | 10 | Bei `region_scope != national`: Regionalbezug im Fließtext, nicht nur im Titel (Doorway-Test) |
| YMYL-Sicherheit | 10 | Pflicht-Disclaimer aus dem Styleguide vorhanden, keine medizinischen/rechtlichen Handlungsanweisungen ohne Einschränkung |

`blocking_issues` erzwingt unabhängig vom Gesamtscore mindestens die Prüfung auf:
unbelegte Zahl, Widerspruch zu einem `fact_snippet`, fehlender YMYL-Disclaimer,
Superlativ-/Werbesprache, Doorway-Muster (Region nur im Titel). Ist `blocking_issues`
nicht leer, gilt der Artikel unabhängig vom Score als nicht freigabefähig — das entscheidet
`QualityCheckJob` (#15), nicht dieses Template. Die Score-Schwellen (`>= 85` auto-approve,
`70–84` Retry, `< 70` Prüf-Queue) stehen in `config('content.quality')` und werden hier
nicht dupliziert.

`fix_instructions` sind Objekte aus `section_id` und `instruction`, nicht reine Sätze:
der Fix-Durchlauf überarbeitet damit genau den genannten Abschnitt (`s1`, `s2`, … in
Reihenfolge der H2, vergeben von `FixSectionsStep::split()`) statt des ganzen Artikels.
Der Artikeltext im Prompt trägt diese Kennungen in eckigen Klammern; der Nutzer-Prompt
erklärt das seit Version 2 der Vorlage (#66). Wie überall gilt das Codeschema — der
`PromptTemplateSeeder` referenziert dafür `RubricEvaluator::outputSchema()`, statt das
Schema zu kopieren, sodass Anzeige und Wirklichkeit nicht auseinanderlaufen können.

## 7. Saisonkalender (`seasonal_topics`)

Pro Branche 20–40 Einträge mit `start_month`/`end_month` (1–12, Jahreswechsel erlaubt,
z. B. Heizungscheck 9–11), `lead_time_days` (Vorlauf bis zum Saisonstart) und optionalem
`region_scope`/`region_code` für Themen, die nur in bestimmten Bundesländern greifen (z. B.
Förderprogramme). `SeasonalTopicSeeder` bestimmt die Branche eines Tenants über
`BranchResolver` und seedet dessen Themenliste; Tenants ohne Branchentreffer bekommen keine
Einträge und werden in der Kommandozeilen-Ausgabe aufgelistet.

## 8. Branchen-Keywords (`tenant_content_settings.branch_keywords_json`)

30–80 Seed-Keywords je Branche, gruppiert nach Unterthema
(`["heizung" => ["wärmepumpe kosten", ...], "bad" => [...]]`), passend zum von
`TenantContext::branchKeywordGroups()` erwarteten Format (#7). `BranchKeywordSeeder`
aktualisiert die von `TenantContentSettingSeeder` bereits angelegte Zeile je Tenant, statt
neue Zeilen anzulegen, und überschreibt nur, wenn `branch_keywords_json` noch leer ist —
manuell im Content-Panel gepflegte Keywords bleiben damit erhalten.

## 9. Musterartikel

`docs/samples/musterartikel-national.md` (Sanitär, `region_scope = national`) und
`docs/samples/musterartikel-bundesland.md` (Solar/PV, `region_scope = state`,
Bayern) zeigen den vollständigen Aufbau: Meta-Title, Meta-Description, Kurzantwort,
Gliederung, Fließtext mit Regionalblock, FAQ und Key Facts mit Quellenangabe. Zahlen darin
sind ausdrücklich als Platzhalter gekennzeichnet — sie ersetzen keine echten
`fact_snippets` und dürfen nicht unverändert veröffentlicht werden. Prompts referenzieren
davon nur kurze Ausschnitte als Few-Shot-Beispiel (z. B. den FAQ-Block), nie den ganzen
Artikel — das hielte den System-Prompt klein und cache-freundlich.
