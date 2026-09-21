<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Guide\Assets\HeroImageGenerator;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Quality\RubricEvaluator;
use App\Guide\Support\BranchResolver;
use App\Guide\Writing\ChangelogWriter;
use App\Guide\Writing\MetaWriter;
use App\Guide\Writing\OutlineService;
use App\Guide\Writing\SectionWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seedet die Vorgaben des themengetriebenen Ratgebersystems (#7): System-Prompt
 * "Ratgeber-Redaktion", 16 Branchen-Styleguides, die zehn Stufen-Prompts, die
 * Qualitaets-Rubrik. #9 ergaenzt guide.assign_facts (Aenderungserkennung).
 * #10 hebt propose_outline, write_section, update_section, meta und
 * change_summary auf Version 2; deren Ausgabeschema kommt aus dem Code
 * (App\Guide\Writing\*::outputSchema()/writeSchema()/updateSchema()).
 * #11 hebt quality_rubric auf Version 2 (Update-Modus: nur geaenderte Teile,
 * Schema aus RubricEvaluator::outputSchema()) und ergaenzt guide.fix_section
 * fuer den Fix-Durchlauf des Qualitaetsgates.
 * #27 hebt deep_research auf Version 3: Block „Zu ersetzende Belege“
 * ({{replace_sources}}) fuer Fakten mit nicht erreichbarer Quelle.
 * #20 ergaenzt guide.image_alt (Alt-Text des Titelbilds, Schema aus
 * HeroImageGenerator::altSchema()).
 *
 * Schluessel tragen den Praefix `guide.` (docs/guide-system.md, §4), damit sie
 * neben den Templates der alten Pipeline (PromptTemplateSeeder) bestehen.
 * `prompt_templates` hat keine `branch`-Spalte: Branchen-Styleguides sind globale
 * Templates `guide.styleguide_<branch>`; die Zeile ist die Branche (branch = null
 * bei allen uebrigen Templates).
 *
 * Variablen: ausschliesslich die in docs/guide-prompts.md §2 dokumentierten.
 * Idempotent ueber (key, tenant_id, version). Eine inhaltliche Aenderung bekommt
 * eine hoehere VERSION-Zeile in `versions()`, die alte Zeile bleibt erhalten und
 * wird deaktiviert.
 */
class GuidePromptTemplateSeeder extends Seeder
{
    /** Dokumentierte Variablen (docs/guide-prompts.md §2). */
    public const ALLOWED_VARIABLES = [
        'branch', 'tenant_name', 'question', 'category', 'notes', 'outline', 'section',
        'current_facts', 'previous_facts', 'last_checked_at', 'today', 'sources',
        'styleguide', 'existing_section_html', 'changed_facts', 'links', 'fix_instructions',
        'replace_sources',
    ];

    private const VERSION = 1;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Du bist Redakteur:in der Ratgeber-Redaktion von {{tenant_name}}, einem deutschen
        Branchenportal für {{branch}}. Du schreibst sachliche, verlässliche Ratgebertexte
        für Verbraucher:innen und hältst dich strikt an diese Standards:

        - Sprache: Deutsch, Du-Ansprache, sachlicher und hilfsbereiter Ton. Die Du-Ansprache
          gilt auch dort, wo der Styleguide Beispielsätze in Sie-Form nennt.
        - Keine Superlative ("beste", "günstigste", "Nr. 1") und keine Werbesprache. Du
          empfiehlst keine einzelnen Firmen.
        - Keine Floskeln und Füllsätze ("in der heutigen Zeit", "nicht zu unterschätzen",
          "wie jeder weiß"). Konkrete Verben statt Nominalstil.
        - Jede Zahl, jeder Preis, jede Frist und jeder Fördersatz stammt aus dem
          Fakten-Set und nennt im Text die Quelle (Herausgeber und Stand, z. B. "laut
          Bundesnetzagentur, Stand 03/2026"). Was nicht im Fakten-Set steht, schreibst du
          nicht als Zahl. Du erfindest keine Zahlen, Quellen, Zitate oder Studien.
        - Ist ein Fakt im Fakten-Set nicht belegt oder offen, formulierst du ohne Zahl und
          sagst ehrlich, dass sich der Wert je nach Fall unterscheidet.
        - Kurze Absätze mit zwei bis vier Sätzen, aktive Sätze, eine Aussage je Satz.
          Die Antwort auf die Frage kommt zuerst, dann die Details.
        - Sicherheits-, gesundheits-, rechts- und finanzrelevante Aussagen (YMYL) bekommen
          den Pflicht-Disclaimer aus dem Styleguide. Du gibst keine Anleitung zu Arbeiten,
          die Fachbetrieben vorbehalten sind.
        - Du gibst nur den geforderten Output im vorgegebenen JSON-Schema zurück, ohne
          Vorrede und ohne Markdown-Umrandung.

        Branchen-Styleguide (Fachvokabular, Preisrahmen, Disclaimer, Verbotsliste):

        {{styleguide}}
        PROMPT;

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->definitions() as $definition) {
            $this->upsert($definition, $created, $skipped);
        }

        foreach (BranchResolver::all() as $branch => $label) {
            $path = base_path("docs/styleguides/{$branch}.md");

            if (! File::exists($path)) {
                $this->command?->warn("Styleguide fehlt fuer Branche '{$branch}': {$path}");

                continue;
            }

            $this->upsert([
                'key' => "guide.styleguide_{$branch}",
                'name' => "Ratgeber-Styleguide: {$label}",
                'system_prompt' => null,
                'user_prompt' => $this->normalizeStyleguide(File::get($path)),
                'variables' => [],
                'schema' => null,
                'version' => self::VERSION,
            ], $created, $skipped);
        }

        $this->command?->info("Guide-Prompts: {$created} angelegt, {$skipped} bereits vorhanden.");
    }

    /**
     * Der Styleguide wird selbst als Wert von {{styleguide}} eingesetzt; verschachtelte
     * Platzhalter wuerden nicht ersetzt. Die alten Styleguides verweisen auf
     * {{fact_snippets}}, im Ratgebersystem heisst das Fakten-Set.
     */
    private function normalizeStyleguide(string $text): string
    {
        return str_replace(
            ['`{{fact_snippets}}`', '{{fact_snippets}}'],
            'dem Fakten-Set',
            $text,
        );
    }

    /**
     * @param  array{key: string, name: string, system_prompt: ?string, user_prompt: string, variables: array<int, string>, schema: ?array<string, mixed>, version: int}  $definition
     */
    private function upsert(array $definition, int &$created, int &$skipped): void
    {
        $exists = PromptTemplate::query()
            ->where('key', $definition['key'])
            ->whereNull('tenant_id')
            ->where('version', $definition['version'])
            ->exists();

        if ($exists) {
            $skipped++;

            return;
        }

        // Neue Version loest die bisher aktive ab; alte Zeilen bleiben erhalten.
        PromptTemplate::query()
            ->where('key', $definition['key'])
            ->whereNull('tenant_id')
            ->where('version', '<', $definition['version'])
            ->update(['is_active' => false]);

        PromptTemplate::create([
            'key' => $definition['key'],
            'tenant_id' => null,
            'version' => $definition['version'],
            'name' => $definition['name'],
            'system_prompt' => $definition['system_prompt'],
            'user_prompt' => $definition['user_prompt'],
            'variables_json' => $definition['variables'] === [] ? null : $definition['variables'],
            'output_schema_json' => $definition['schema'],
            'is_active' => true,
            'created_by' => null,
        ]);

        $created++;
    }

    /**
     * @return array<int, array{key: string, name: string, system_prompt: ?string, user_prompt: string, variables: array<int, string>, schema: ?array<string, mixed>, version: int}>
     */
    private function definitions(): array
    {
        $definitions = [
            [
                'key' => 'guide.system_ratgeber_redaktion',
                'name' => 'System-Prompt: Ratgeber-Redaktion',
                'system_prompt' => self::SYSTEM_PROMPT,
                'user_prompt' => 'Referenz, kein eigener LLM-Aufruf: Dieser System-Prompt steht in jeder Stufe unten als system_prompt (docs/guide-prompts.md §3).',
                'variables' => ['tenant_name', 'branch', 'styleguide'],
                'schema' => null,
            ],
            [
                'key' => 'guide.freshness_probe',
                'name' => 'Stufe: Aktualitäts-Probe',
                'user_prompt' => <<<'PROMPT'
                    Prüfe mit der Websuche, ob sich seit {{last_checked_at}} Angaben geändert haben,
                    die für den Ratgeber zur Frage "{{question}}" (Kategorie {{category}}) relevant
                    sind. Heute ist der {{today}}.

                    Bisheriges Fakten-Set:
                    {{current_facts}}

                    Bevorzugte Quellen: {{sources}}

                    Vorgehen:
                    1. Suche gezielt nach neuen Fassungen von Gesetzen, Verordnungen, Förderrichtlinien,
                       Fristen, Grenzwerten, Preisen und Gebühren, die zu den Fakten oben gehören.
                    2. Melde nur Änderungen, die eine Quelle mit Veröffentlichungsdatum nach
                       {{last_checked_at}} belegt oder deren Gültigkeitsbeginn (valid_from) nach diesem
                       Datum liegt. Meinungen, Werbung und Foren zählen nicht.
                    3. Liefere je Änderung den Schlüssel des betroffenen Fakts (key aus dem Fakten-Set),
                       den alten und den neuen Wert, die Quell-URL und das Veröffentlichungsdatum.
                    4. Gibt es keine belegte Änderung, setze changed auf false und lasse
                       candidate_changes leer.
                    5. confidence (0 bis 1) beschreibt, wie sicher dein Ergebnis belegt ist — auch
                       bei changed = false: gezielt gesucht und nichts Neueres gefunden ≥ 0,8;
                       Änderung durch amtliche Quelle mit Datum belegt ≥ 0,8; nur Fachpresse ≤ 0,6;
                       unklar oder kaum Treffer ≤ 0,3.
                    6. Quellen der Liste "Verboten" verwendest du nie. Gib source_url exakt so an, wie
                       sie in den Suchergebnissen steht.

                    Recherche-Auftrag der Redaktion (verbindlich, wenn nicht "keine"): {{notes}}
                    PROMPT,
                'variables' => ['question', 'category', 'notes', 'last_checked_at', 'today', 'current_facts', 'sources'],
                'schema' => $this->probeSchema(),
                // v2 (#8): confidence auch fuer "keine Aenderung", Notizen als Recherche-Auftrag.
                'version' => 2,
            ],
            [
                'key' => 'guide.deep_research',
                'name' => 'Stufe: Tiefenrecherche',
                'user_prompt' => <<<'PROMPT'
                    Recherchiere mit der Websuche das belastbare Fakten-Set für den Ratgeber zur Frage
                    "{{question}}" (Branche {{branch}}, Kategorie {{category}}). Heute ist der {{today}}.
                    Zuletzt geprüft: {{last_checked_at}}. Suche gezielt nach Neuerungen seit diesem Datum
                    (neue Fassungen, neue Sätze, neue Fristen) und bestätige die übrigen Werte.

                    Bereits bekannte Fakten (prüfen, bestätigen oder korrigieren):
                    {{current_facts}}

                    Zuvor geänderte oder ältere Werte: {{previous_facts}}

                    Erlaubte und bevorzugte Quellen sowie ausgeschlossene Quellen:
                    {{sources}}

                    Recherche-Auftrag der Redaktion (verbindlich, wenn nicht "keine"): {{notes}}

                    Zu ersetzende Belege: {{replace_sources}}
                    Diese Seiten sind nicht mehr erreichbar. Belege die genannten Fakten mit einer
                    anderen, erreichbaren Quelle neu. Verwende die genannten URLs nicht. Findest du
                    keinen Beleg, nenne den Fakt in open_points.

                    Regeln:
                    - Erfasse nur prüfbare Fakten: Zahlen, Preise, Fristen, Grenzwerte, Fördersätze,
                      gesetzliche Anforderungen, Zuständigkeiten. Je Fakt und Quelle genau ein Wert.
                    - key: stabiler snake_case-Schlüssel (z. B. foerderung_grundsatz_prozent), höchstens
                      128 Zeichen. Behalte vorhandene Schlüssel bei.
                    - label: verständliche Bezeichnung, value: der Wert als Text, unit: Einheit oder null,
                      valid_from: Datum, ab dem der Wert gilt (YYYY-MM-DD) oder null.
                    - Jeder Fakt braucht eine source_url, die auch in sources[] steht, exakt so, wie sie
                      in den Suchergebnissen steht. Keine URL aus dem Gedächtnis.
                    - published_at: Veröffentlichungs- oder Stand-Datum der Quelle (YYYY-MM-DD) oder null.
                    - Quellen, die älter als 24 Monate sind, nur verwenden, wenn keine neuere den Fakt belegt.
                    - Bevorzuge die Quellen der Whitelist. Quellen der Blacklist verwendest du nie.
                    - Widersprechen sich Quellen, liefere für denselben key je Quelle einen eigenen Eintrag
                      mit dem jeweiligen Wert; das System wählt nach Vertrauensstufe und Datum und
                      protokolliert den Konflikt. Nenne den Widerspruch zusätzlich in open_points.
                    - trust_level: official (Behörde, Gesetz, Förderstelle), trade (Verband, Kammer,
                      Norm-Institut), press (Fachpresse), other.
                    - Kein Seitentext, keine Zitate: nur Fakten, Titel, URL, Herausgeber und Datum.
                    - Was du nicht belegen kannst, erfindest du nicht, sondern nennst es in open_points.
                    PROMPT,
                'variables' => ['question', 'branch', 'category', 'notes', 'last_checked_at', 'today', 'current_facts', 'previous_facts', 'sources', 'replace_sources'],
                'schema' => $this->researchSchema(),
                // v2 (#8): last_checked_at, Recherche-Auftrag, ein Eintrag je Quelle bei Widerspruch.
                // v3 (#27): Block „Zu ersetzende Belege“ fuer nicht erreichbare Quellen.
                'version' => 3,
            ],
            [
                'key' => 'guide.propose_outline',
                'name' => 'Stufe: Gliederung vorschlagen',
                'user_prompt' => <<<'PROMPT'
                    Schlage die Gliederung für den Ratgeber zur Frage "{{question}}" vor
                    (Kategorie {{category}}, Branche {{branch}}, Portal {{tenant_name}}).

                    Fakten-Set (kann beim ersten Vorschlag leer sein): {{current_facts}}
                    Notizen der Redaktion: {{notes}}
                    Vorgegebene Gliederung (falls vorhanden, dann übernimmst du sie unverändert): {{outline}}

                    Vorgaben:
                    - Die feste Struktur eines Ratgebers: Antwort zuerst (Kurzantwort wird separat
                      erzeugt), dann 4 bis 7 H2-Abschnitte, optional bis zu 4 H3 je H2, am Ende
                      "Häufige Fragen" (wird separat erzeugt und ist nicht Teil der Gliederung).
                    - Jede Überschrift ist eine konkrete Aussage oder Frage, kein Schlagwort,
                      höchstens 70 Zeichen, und enthält das Hauptthema natürlich, ohne
                      Keyword-Stuffing. Keine Jahreszahl in Überschriften.
                    - Jeder Abschnitt hat eine id (s1, s2 … bzw. s1-1 für H3), eine level (2 oder 3)
                      und in fact_keys die Schlüssel der Fakten, die er verwenden soll. Die ids
                      vergibt das System beim Speichern neu.
                    - Kosten, Förderung und Fristen bekommen einen eigenen Abschnitt, wenn das Fakten-Set
                      oder die Notizen sie nahelegen.
                    - Keine Abschnitte, die nur Werbung für Firmen sind.
                    PROMPT,
                'variables' => ['question', 'category', 'branch', 'tenant_name', 'current_facts', 'notes', 'outline'],
                // Schema gehoert dem Code (#10), 4 bis 7 H2 aus config('guide.writing.outline').
                'schema' => OutlineService::outputSchema(),
                'version' => 2,
            ],
            [
                'key' => 'guide.write_section',
                'name' => 'Stufe: Abschnitt schreiben',
                'user_prompt' => <<<'PROMPT'
                    Schreibe einen Abschnitt des Ratgebers zur Frage "{{question}}".

                    Zu schreibender Abschnitt (id, Ebene, Überschrift, zugehörige Fakt-Schlüssel):
                    {{section}}

                    Gesamtgliederung (zur Abgrenzung, keine Wiederholung anderer Abschnitte):
                    {{outline}}

                    Fakten-Set (einzige erlaubte Quelle für Zahlen, Preise, Fristen und Fördersätze):
                    {{current_facts}}

                    Quellen: {{sources}}
                    Notizen der Redaktion: {{notes}}

                    Interne Links, die in diesen Abschnitt gehören (natürlich im Satz einbauen,
                    Linktext frei formulieren, URL exakt übernehmen):
                    {{links}}

                    Regeln:
                    - summary_sentence: Bei einer H2 (level 2) genau EIN Satz, der das Fazit des
                      Abschnitts zieht; er wird vom System als erster Absatz gesetzt und darf in
                      html nicht wiederholt werden. Bei einer H3 null.
                    - html: sauberes HTML ohne die Abschnittsüberschrift und ohne weitere
                      Überschriften. Erlaubt sind nur <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>,
                      <blockquote> und bei Vergleichen <table>, <thead>, <tbody>, <tr>, <th>, <td>.
                      Keine Attribute außer href.
                    - 120 bis 250 Wörter je H2-Abschnitt, 60 bis 150 je H3, Absätze mit zwei bis
                      vier Sätzen.
                    - Jede Zahl mit Quelle im Text ("laut <Herausgeber>, Stand <Monat Jahr>") und
                      exakt dem Wert aus dem Fakten-Set. Externe Links nur auf die source_url eines
                      Fakts.
                    - Preise als Spanne mit Bezugsgröße und als Richtwert kennzeichnen.
                    - Kein Fakt, keine Zahl außerhalb des Fakten-Sets. Fehlt ein Wert, schreibe
                      ohne Zahl und nenne, wovon der Wert abhängt.
                    - Gib in used_fact_keys alle verwendeten Fakt-Schlüssel zurück.
                    PROMPT,
                'variables' => ['question', 'section', 'outline', 'current_facts', 'sources', 'notes', 'links'],
                'schema' => SectionWriter::writeSchema(2),
                'version' => 2,
            ],
            [
                'key' => 'guide.update_section',
                'name' => 'Stufe: Abschnitt aktualisieren',
                'user_prompt' => <<<'PROMPT'
                    Aktualisiere einen bestehenden Abschnitt des Ratgebers zur Frage "{{question}}".
                    Heute ist der {{today}}.

                    Abschnitt (id, Ebene, Überschrift, bisher verwendete Fakt-Schlüssel):
                    {{section}}

                    Bestehendes HTML des Abschnitts:
                    {{existing_section_html}}

                    Geänderte Fakten (alter und neuer Wert, Quelle, Gültigkeit):
                    {{changed_facts}}

                    Vollständiges aktuelles Fakten-Set: {{current_facts}}

                    Zwingende Regeln:
                    1. Alle Aussagen, die nicht von den geänderten Fakten betroffen sind, bleiben
                       WORTGLEICH erhalten: gleicher Wortlaut, gleiche Reihenfolge, gleiche
                       Satzzeichen, gleiche HTML-Struktur. Formuliere nichts "nebenbei" um.
                    2. Nur die Sätze, die einen der geänderten Fakten nennen oder unmittelbar daraus
                       folgen (z. B. ein abgeleiteter Betrag), passt du an: neuer Wert exakt wie im
                       Fakten-Set, Quelle und Stand im Satz aktualisiert.
                    3. Der erste Absatz ist das Ein-Satz-Fazit des Abschnitts und bleibt ein
                       einzelner Satz. Keine Überschriften im HTML.
                    4. Keine neuen Absätze, außer ein geänderter Fakt macht einen Hinweis zwingend
                       nötig (z. B. neue Frist). Keine Streichungen, außer der Satz ist durch die
                       Änderung falsch geworden.
                    5. Wirkt eine Änderung auf keinen Satz des Abschnitts, gib das bestehende HTML
                       unverändert zurück und setze changed auf false.
                    6. Liste in edited_sentences jeden geänderten Satz mit altem und neuem Wortlaut
                       und in used_fact_keys alle Fakt-Schlüssel, die der Abschnitt danach nennt.
                    PROMPT,
                'variables' => ['question', 'today', 'section', 'existing_section_html', 'changed_facts', 'current_facts'],
                'schema' => SectionWriter::updateSchema(),
                'version' => 2,
            ],
            [
                'key' => 'guide.faq',
                'name' => 'Stufe: Häufige Fragen',
                'user_prompt' => <<<'PROMPT'
                    Erstelle den FAQ-Block für den Ratgeber zur Frage "{{question}}".

                    Gliederung des Artikels: {{outline}}
                    Fakten-Set: {{current_facts}}
                    Quellen: {{sources}}

                    Regeln:
                    - 4 bis 6 Fragen, die Leser:innen nach dem Lesen wirklich stellen, in der
                      Wortwahl der Suchanfragen. Keine Wiederholung der Haupt-Frage.
                    - Antwort: 40 bis 80 Wörter, erster Satz beantwortet direkt, danach eine
                      Einordnung. Als Klartext ohne HTML, weil der Block als FAQPage-Schema
                      ausgespielt wird.
                    - Zahlen nur aus dem Fakten-Set, mit Quelle im Text. Keine Frage ohne belegbare
                      Antwort.
                    - Kein Werbe-Ton, keine Empfehlung einzelner Firmen.
                    PROMPT,
                'variables' => ['question', 'outline', 'current_facts', 'sources'],
                'schema' => $this->faqSchema(),
            ],
            [
                'key' => 'guide.short_answer',
                'name' => 'Stufe: Kurzantwort',
                'user_prompt' => <<<'PROMPT'
                    Schreibe die Kurzantwort für den Ratgeber zur Frage "{{question}}". Sie steht
                    direkt unter der Überschrift und dient als Snippet-Antwort.

                    Fakten-Set: {{current_facts}}
                    Gliederung: {{outline}}

                    Regeln:
                    - Zwei bis drei Sätze, 40 bis 60 Wörter, Klartext ohne HTML.
                    - Der erste Satz beantwortet die Frage direkt. Es folgen die wichtigste
                      Einschränkung und der Verweis, wovon die Antwort abhängt.
                    - Zahlen nur aus dem Fakten-Set, mit Stand ("Stand 03/2026") im Text.
                    - Keine Einleitung ("In diesem Artikel …"), keine Superlative.
                    - Gib in used_fact_keys die verwendeten Fakt-Schlüssel zurück.
                    PROMPT,
                'variables' => ['question', 'current_facts', 'outline'],
                'schema' => $this->shortAnswerSchema(),
            ],
            [
                'key' => 'guide.meta',
                'name' => 'Stufe: Meta-Angaben',
                'user_prompt' => <<<'PROMPT'
                    Erstelle Titel und Meta-Description für den Ratgeber zur Frage "{{question}}"
                    (Portal {{tenant_name}}, Stand {{today}}).

                    Gliederung: {{outline}}
                    Fakten-Set: {{current_facts}}

                    Regeln:
                    - meta_title: Artikeltitel und Title-Tag zugleich, höchstens 60 Zeichen,
                      Hauptthema vorn, ohne Portalnamen (den hängt das Template an), kein Clickbait,
                      keine Großbuchstaben-Wörter. Eine Jahreszahl nur, wenn sie für die Suche
                      wichtig ist, und dann das laufende Jahr.
                    - meta_description: 100 bis 155 Zeichen, nennt Nutzen und den Aktualitätsbezug
                      ("Stand {{today}}" nur als Monat und Jahr), endet nicht mit "…".
                    - Zahlen nur aus dem Fakten-Set. Keine Superlative, keine Werbesprache.
                    - Gib zusätzlich primary_keyword an: das Hauptsuchbegriff-Paar, das du verwendet hast.
                    PROMPT,
                'variables' => ['question', 'tenant_name', 'today', 'outline', 'current_facts'],
                'schema' => MetaWriter::outputSchema(),
                'version' => 2,
            ],
            [
                'key' => 'guide.change_summary',
                'name' => 'Stufe: Änderungsvermerk',
                'user_prompt' => <<<'PROMPT'
                    Formuliere den Eintrag "Was ist neu?" für die Aktualisierung des Ratgebers zur
                    Frage "{{question}}". Letzte Prüfung: {{last_checked_at}}, heute: {{today}}.

                    Geänderte Fakten: {{changed_facts}}
                    Frühere Werte: {{previous_facts}}
                    Betroffene Abschnitte (HTML nach der Änderung): {{existing_section_html}}

                    Regeln:
                    - sentences: genau ein Satz je geändertem Fakt (fact_key = sein Schlüssel), für
                      Leser:innen verständlich, mit altem und neuem Wert und dem Datum, ab dem er
                      gilt. Kein Marketing, keine Bewertung ("endlich").
                    - Nichts erwähnen, was sich nicht geändert hat.
                    PROMPT,
                'variables' => ['question', 'last_checked_at', 'today', 'changed_facts', 'previous_facts', 'existing_section_html'],
                'schema' => ChangelogWriter::outputSchema(),
                'version' => 2,
            ],
            [
                'key' => 'guide.assign_facts',
                'name' => 'Stufe: Geänderte Fakten Abschnitten zuordnen',
                'user_prompt' => <<<'PROMPT'
                    Ordne geänderte Fakten des Ratgebers zur Frage "{{question}}" den Abschnitten der
                    gesperrten Gliederung zu. Die Fakten kommen bisher in keinem Abschnitt vor.

                    Gliederung (id, Ebene, Überschrift, bereits verwendete Fakt-Schlüssel):
                    {{outline}}

                    Geänderte Fakten ohne Abschnitt (key, label, alter und neuer Wert, Quelle):
                    {{changed_facts}}

                    Regeln:
                    - Für jeden Fakt genau eine Zuordnung: der Abschnitt, zu dessen Thema der Fakt
                      inhaltlich am besten passt. Ähnliche Fakt-Schlüssel eines Abschnitts sind ein
                      starker Hinweis.
                    - section_id ausschließlich aus der Gliederung; keine neuen Abschnitte.
                    - Passt ein Fakt zu keinem Abschnitt eindeutig, wähle den allgemeinsten
                      Abschnitt, der die Frage direkt beantwortet.
                    - reason: ein kurzer Satz, warum der Abschnitt passt.
                    PROMPT,
                'variables' => ['question', 'outline', 'changed_facts'],
                'schema' => $this->assignFactsSchema(),
            ],
            [
                'key' => 'guide.category_intro',
                'name' => 'Stufe: Kategorie-Einleitung',
                'user_prompt' => <<<'PROMPT'
                    Schreibe die Einleitung der Ratgeber-Kategorie "{{category}}" für {{tenant_name}}
                    (Branche {{branch}}).

                    Themen der Kategorie und Notizen: {{notes}}

                    Regeln:
                    - intro_html: 60 bis 100 Wörter in einem oder zwei <p>, erklärt, welche Fragen
                      Leser:innen hier beantwortet bekommen. Keine Zahlen, keine Firmen.
                    - meta_title: höchstens 60 Zeichen, meta_description: 120 bis 155 Zeichen.
                    - Keine Superlative, keine Werbesprache, keine Floskeln.
                    PROMPT,
                'variables' => ['category', 'tenant_name', 'branch', 'notes'],
                'schema' => $this->categoryIntroSchema(),
            ],
            [
                'key' => 'guide.legacy_category',
                'name' => 'Bestandsartikel: Kategorie wählen',
                // Ordnet nur zu und schreibt nichts, der Redaktions-System-Prompt passt nicht.
                'system_prompt' => <<<'PROMPT'
                    Du ordnest bestehende Ratgeberartikel von {{tenant_name}}, einem deutschen
                    Branchenportal für {{branch}}, einer vorhandenen Ratgeber-Kategorie zu. Du gibst nur
                    den geforderten Output im vorgegebenen JSON-Schema zurück.
                    PROMPT,
                'user_prompt' => <<<'PROMPT'
                    Welche der vorhandenen Kategorien passt am besten zum Artikel "{{question}}"?

                    Anriss des Artikels: {{notes}}

                    Vorhandene Kategorien (slug, name, description): {{category}}

                    Regeln:
                    - category_slug: genau ein slug aus der Liste oben, unverändert übernommen.
                    - Passt keine Kategorie inhaltlich, gib category_slug null zurück; der Artikel landet
                      dann in "Allgemein". Keine neue Kategorie erfinden.
                    - reason: ein kurzer Satz, warum.
                    PROMPT,
                'variables' => ['tenant_name', 'branch', 'question', 'notes', 'category'],
                'schema' => $this->legacyCategorySchema(),
            ],
            [
                'key' => 'guide.quality_rubric',
                'name' => 'Qualitäts-Rubrik',
                'user_prompt' => <<<'PROMPT'
                    Bewerte den folgenden Ratgeberartikel zur Frage "{{question}}" streng nach der
                    Rubrik. Heute ist der {{today}}. Du schreibst nichts um, sondern bewertest nur.

                    Beginnt der Artikel mit "[Nur geänderte Teile: …]", ist es eine Aktualisierung:
                    Bewerte und beanstande dann nur die gezeigten Teile. Kriterien, die sich auf den
                    ganzen Artikel beziehen (suchintention, struktur, ymyl), bewertest du nur danach,
                    ob die gezeigten Teile sie verschlechtern; ohne Befund volle Punktzahl.

                    Zu prüfender Artikel (HTML):
                    {{existing_section_html}}

                    Fakten-Set (einzige Wahrheit für Zahlen und Fristen):
                    {{current_facts}}

                    Quellen des Artikels: {{sources}}

                    Branchen-Styleguide (Verbotsliste, Pflicht-Disclaimer):
                    {{styleguide}}

                    Kriterien (Summe 100 Punkte), je Kriterium key, Punkte, kurze Begründung:
                    - belege (25): Jede Zahl, jeder Preis, jede Frist hat eine erkennbare Quelle im Text.
                    - faktentreue (20): Alle Werte entsprechen dem Fakten-Set; keine erfundenen Angaben.
                    - aktualitaet (10): Keine Angabe stammt aus einer Quelle, die älter ist als der
                      valid_from-Wert einer neueren Quelle im Fakten-Set; Stand ist genannt.
                    - suchintention (10): Die Frage wird zuerst und direkt beantwortet.
                    - sprache (10): Du-Ansprache, kurze Absätze, keine Floskeln, klare Sätze.
                    - neutralitaet (10): Keine Superlative, keine Werbesprache, keine Verbotswörter.
                    - ymyl (10): Pflicht-Disclaimer vorhanden, wenn der Styleguide ihn verlangt; keine
                      Anleitung zu Fachbetriebsarbeiten.
                    - struktur (5): Überschriften tragen, Gliederung logisch, FAQ und Kurzantwort da.

                    Blockierend (blocking_issues, Code genau so):
                    - unbelegte_zahl: eine Zahl, ein Preis oder eine Frist ohne Quelle im Text oder ohne
                      Entsprechung im Fakten-Set.
                    - faktenwiderspruch: ein Wert weicht vom Fakten-Set ab.
                    - veraltete_angabe: eine Angabe beruht auf einem Stand, der älter ist als das
                      valid_from einer neueren Quelle im Fakten-Set.
                    - ymyl_disclaimer_fehlt: der Styleguide verlangt einen Pflicht-Disclaimer und er fehlt
                      oder ist verwässert.
                    - werbesprache: Superlative, Werbeversprechen, Firmenempfehlung oder ein Wort aus der
                      Verbotsliste.
                    Weitere blockierende Fälle darfst du unter dem Code "sonstiges" melden (z. B.
                    Selbstbauanleitung für Fachbetriebsarbeiten).

                    Ausgabe:
                    - score: Summe der Punkte, 0 bis 100. Bei mindestens einem blocking_issue nicht
                      über 69.
                    - per_criterion: alle acht Kriterien.
                    - blocking_issues: je Fund Code, Fundstelle (section_id, wenn erkennbar), wörtliches
                      Zitat und Erklärung. Leer, wenn nichts blockiert.
                    - fix_instructions: konkrete Anweisung je Fund, adressiert an einen Abschnitt
                      (section_id oder "artikel"), so formuliert, dass ein Autor sie ohne Rückfrage
                      umsetzen kann. Keine neue Formulierung vorwegnehmen.
                    PROMPT,
                'variables' => ['question', 'today', 'existing_section_html', 'current_facts', 'sources', 'styleguide'],
                'schema' => RubricEvaluator::outputSchema(),
                'version' => 2,
            ],
            [
                'key' => 'guide.fix_section',
                'name' => 'Stufe: Abschnitt nach Qualitätsprüfung nachbessern',
                'user_prompt' => <<<'PROMPT'
                    Bessere einen Abschnitt des Ratgebers zur Frage "{{question}}" nach. Die
                    Qualitätsprüfung hat Mängel gefunden. Heute ist der {{today}}.

                    Abschnitt (id, Ebene, Überschrift, zugehörige Fakt-Schlüssel):
                    {{section}}

                    Bestehendes HTML des Abschnitts:
                    {{existing_section_html}}

                    Befunde der Qualitätsprüfung, die du beheben musst:
                    {{fix_instructions}}

                    Fakten-Set (einzige erlaubte Quelle für Zahlen, Preise, Fristen und Fördersätze):
                    {{current_facts}}

                    Interne Links, die in diesen Abschnitt gehören (URL exakt übernehmen):
                    {{links}}

                    Zwingende Regeln:
                    1. Behebe genau die genannten Befunde. Alles andere bleibt wortgleich: gleicher
                       Wortlaut, gleiche Reihenfolge, gleiche HTML-Struktur.
                    2. Eine Zahl, ein Datum oder eine Frist ohne Entsprechung im Fakten-Set streichst du
                       oder ersetzt sie durch den exakten Wert aus dem Fakten-Set samt Quelle und Stand.
                       Du erfindest keine neuen Zahlen.
                    3. summary_sentence: Bei einer H2 (level 2) genau EIN Satz als Fazit, bei einer H3
                       null. Der Satz steht nicht zusätzlich in html.
                    4. html ohne Überschriften; erlaubt sind nur <p>, <ul>, <ol>, <li>, <strong>, <em>,
                       <a>, <blockquote> und <table>, <thead>, <tbody>, <tr>, <th>, <td>. Keine
                       Attribute außer href.
                    5. Verlangt ein Befund den Pflicht-Disclaimer, übernimm ihn sinngemäß aus dem
                       Styleguide als eigenen Absatz.
                    6. Gib in used_fact_keys alle Fakt-Schlüssel zurück, die der Abschnitt danach nennt.
                    PROMPT,
                'variables' => ['question', 'today', 'section', 'existing_section_html', 'fix_instructions', 'current_facts', 'links'],
                'schema' => SectionWriter::writeSchema(2),
            ],
            [
                'key' => HeroImageGenerator::ALT_TEMPLATE,
                'name' => 'Titelbild: Alternativtext',
                // Beschreibt nur ein Bild, der Redaktions-System-Prompt passt nicht.
                'system_prompt' => <<<'PROMPT'
                    Du formulierst Alternativtexte (alt-Attribute) für Bilder auf {{tenant_name}},
                    einem deutschen Branchenportal für {{branch}}. Du gibst nur den geforderten
                    Output im vorgegebenen JSON-Schema zurück.
                    PROMPT,
                'user_prompt' => <<<'PROMPT'
                    Formuliere den Alternativtext für das Titelbild des Ratgebers zur Frage
                    "{{question}}".

                    Das Bild wurde mit dieser Bildbeschreibung erzeugt:
                    {{notes}}

                    Regeln:
                    - Höchstens 125 Zeichen, ein Satz, ohne Punkt am Ende.
                    - Beschreibe, was zu sehen ist, nicht was der Artikel behauptet.
                    - Beginne nicht mit "Bild von", "Foto von" oder "Darstellung von".
                    - Keine Keyword-Häufung, keine Werbesprache, keine Marken- oder Personennamen.
                    PROMPT,
                'variables' => ['tenant_name', 'branch', 'question', 'notes'],
                'schema' => HeroImageGenerator::altSchema(),
            ],
        ];

        return array_map(function (array $definition): array {
            $definition['system_prompt'] ??= self::SYSTEM_PROMPT;
            $definition['version'] ??= self::VERSION;

            return $definition;
        }, $definitions);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function object(array $properties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nullableString(?string $format = null): array
    {
        return ['type' => ['string', 'null']] + ($format !== null ? ['format' => $format] : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function probeSchema(): array
    {
        return $this->object([
            'changed' => ['type' => 'boolean'],
            'reason' => ['type' => 'string', 'maxLength' => 600],
            'candidate_changes' => [
                'type' => 'array',
                'items' => $this->object([
                    'key' => ['type' => 'string', 'maxLength' => 128],
                    'old_value' => $this->nullableString(),
                    'new_value' => ['type' => 'string', 'maxLength' => 1000],
                    'source_url' => ['type' => 'string', 'maxLength' => 2048],
                    'published_at' => $this->nullableString('date'),
                ]),
            ],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
        ]);
    }

    /**
     * Passt zu guide_facts (key, label, value, unit, valid_from) und guide_sources
     * (url, title, publisher, published_at, trust_level).
     *
     * @return array<string, mixed>
     */
    private function researchSchema(): array
    {
        return $this->object([
            'facts' => [
                'type' => 'array',
                'items' => $this->object([
                    'key' => ['type' => 'string', 'maxLength' => 128],
                    'label' => ['type' => 'string', 'maxLength' => 255],
                    'value' => ['type' => 'string', 'maxLength' => 1000],
                    'unit' => $this->nullableString(),
                    'valid_from' => $this->nullableString('date'),
                    'source_url' => ['type' => 'string', 'maxLength' => 2048],
                ]),
            ],
            'sources' => [
                'type' => 'array',
                'items' => $this->object([
                    'url' => ['type' => 'string', 'maxLength' => 2048],
                    'title' => ['type' => 'string', 'maxLength' => 255],
                    'publisher' => ['type' => 'string', 'maxLength' => 255],
                    'published_at' => $this->nullableString('date'),
                    'trust_level' => ['type' => 'string', 'enum' => ['official', 'trade', 'press', 'other']],
                ]),
            ],
            'open_points' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
    }

    /**
     * Passt zu guide_article_details.faq_json.
     *
     * @return array<string, mixed>
     */
    private function faqSchema(): array
    {
        return $this->object([
            'faq' => [
                'type' => 'array',
                'minItems' => 4,
                'maxItems' => 6,
                'items' => $this->object([
                    'question' => ['type' => 'string', 'maxLength' => 200],
                    'answer' => ['type' => 'string', 'maxLength' => 700],
                ]),
            ],
        ]);
    }

    /**
     * Passt zu guide_article_details.short_answer.
     *
     * @return array<string, mixed>
     */
    private function shortAnswerSchema(): array
    {
        return $this->object([
            'short_answer' => ['type' => 'string', 'minLength' => 120, 'maxLength' => 480],
            'used_fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
    }

    /**
     * Zuordnung geaenderter Fakten ohne Abschnitt (#9, ChangeDetector).
     *
     * @return array<string, mixed>
     */
    private function assignFactsSchema(): array
    {
        return $this->object([
            'assignments' => [
                'type' => 'array',
                'items' => $this->object([
                    'fact_key' => ['type' => 'string', 'maxLength' => 128],
                    'section_id' => ['type' => 'string'],
                    'reason' => ['type' => 'string', 'maxLength' => 300],
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryIntroSchema(): array
    {
        return $this->object([
            'intro_html' => ['type' => 'string', 'minLength' => 80],
            'meta_title' => ['type' => 'string', 'maxLength' => 60],
            'meta_description' => ['type' => 'string', 'minLength' => 100, 'maxLength' => 160],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyCategorySchema(): array
    {
        return $this->object([
            'category_slug' => $this->nullableString(),
            'reason' => ['type' => 'string'],
        ]);
    }
}
