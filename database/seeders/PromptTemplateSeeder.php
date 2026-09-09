<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Content\Generation\FixSectionsStep;
use App\Content\Jobs\ClusterLeadQuestionsJob;
use App\Content\Jobs\DiscoverTopicsJob;
use App\Content\Models\Central\PromptTemplate;
use App\Content\Quality\RubricEvaluator;
use App\Content\Support\BranchResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seedet die Redaktionsvorgaben, aus denen claude-sonnet-5 Ratgebertexte
 * erzeugt (#13): das System-Prompt „Ratgeber-Redakteur", die 16
 * Branchen-Styleguides, die neun Prompts je Pipeline-Stufe und die
 * Qualitäts-Rubrik fuer `QualityCheckJob` (#15).
 *
 * Alle Zeilen sind global (`tenant_id = null`); Details und die Begruendung
 * dafuer stehen in `docs/content-prompts.md`. Der Seeder ist idempotent ueber
 * `(key, tenant_id, version)`: ein erneuter Lauf mit unveraendertem Inhalt
 * legt nichts doppelt an. Ein inhaltlich geaenderter Eintrag braucht eine neue
 * `version` in diesem Seeder statt eines Updates der bestehenden Zeile, damit
 * alte Versionen nachvollziehbar erhalten bleiben.
 */
class PromptTemplateSeeder extends Seeder
{
    private const VERSION = 1;

    /**
     * Wird 1:1 als system_prompt in jede Schreib-Stufe uebernommen (siehe
     * docs/content-prompts.md §5). Nutzt ausschliesslich dokumentierte
     * Variablen (docs/content-prompts.md §4).
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Sie sind Redakteur:in für Ratgeberinhalte auf einem deutschen Branchenportal
        ({{tenant_name}}, Branche {{branch}}). Halten Sie sich strikt an diese
        Redaktionsstandards:

        - Schreiben Sie auf Deutsch, in der Sie-Form, in einem sachlichen,
          hilfsbereiten Ton.
        - Verwenden Sie keine Superlative ("beste", "günstigste", "Nr. 1") und
          keine Werbesprache.
        - Vermeiden Sie Floskeln und Füllsätze ("in der heutigen Zeit", "nicht zu
          unterschätzen", "wie jeder weiß").
        - Jede Zahl, jede Statistik und jede Preisangabe braucht eine erkennbare
          Quelle aus {{fact_snippets}} oder {{portal_data}}. Erfinden Sie keine
          Zahlen und keine Quellen.
        - Schreiben Sie kurze Absätze (2-4 Sätze), aktive Sätze, konkrete Verben
          statt Nominalstil.
        - Beantworten Sie die Suchintention ({{intent}}) zuerst, bevor Sie ins
          Detail gehen.
        - Nennen Sie Regionalbezüge ({{region_name}}) im Fließtext, nicht nur in
          Überschrift oder Meta-Angaben (kein Doorway-Muster).
        - Kennzeichnen Sie gesundheits-, sicherheits- oder rechtsrelevante
          Aussagen (YMYL) mit dem Pflicht-Disclaimer aus dem Styleguide.
        - Halten Sie sich an Fachvokabular, Verbotsliste und
          Preisrahmen-Hinweise aus diesem Styleguide:

        {{styleguide}}
        PROMPT;

    public function run(): void
    {
        $createdBy = null;
        $created = 0;
        $skipped = 0;

        $this->seedSystemPromptReference($createdBy, $created, $skipped);
        $this->seedStyleguides($createdBy, $created, $skipped);
        $this->seedStages($createdBy, $created, $skipped);
        $this->seedQualityRubric($createdBy, $created, $skipped);

        $this->command?->info("Prompt-Templates: {$created} angelegt, {$skipped} bereits vorhanden.");
    }

    private function seedSystemPromptReference(?int $createdBy, int &$created, int &$skipped): void
    {
        $this->upsert(
            key: 'system_ratgeber_redakteur',
            name: 'System-Prompt: Ratgeber-Redakteur',
            systemPrompt: null,
            userPrompt: 'Referenzdokument, kein eigener LLM-Aufruf. Der folgende Text wird '.
                '1:1 als system_prompt in jede Schreib-Stufe uebernommen (siehe '.
                "docs/content-prompts.md §5):\n\n".self::SYSTEM_PROMPT,
            variables: ['tenant_name', 'branch', 'fact_snippets', 'portal_data', 'intent', 'region_name', 'styleguide'],
            schema: null,
            createdBy: $createdBy,
            created: $created,
            skipped: $skipped,
        );
    }

    private function seedStyleguides(?int $createdBy, int &$created, int &$skipped): void
    {
        foreach (BranchResolver::all() as $key => $label) {
            $path = base_path("docs/styleguides/{$key}.md");

            if (! File::exists($path)) {
                $this->command?->warn("Styleguide-Datei fehlt fuer Branche '{$key}': {$path}");

                continue;
            }

            $this->upsert(
                key: "styleguide_{$key}",
                name: "Branchen-Styleguide: {$label}",
                systemPrompt: null,
                userPrompt: File::get($path),
                variables: [],
                schema: null,
                createdBy: $createdBy,
                created: $created,
                skipped: $skipped,
            );
        }
    }

    private function seedStages(?int $createdBy, int &$created, int &$skipped): void
    {
        foreach ($this->stageDefinitions() as $stage) {
            $this->upsert(
                key: $stage['key'],
                name: $stage['name'],
                systemPrompt: $stage['system_prompt'] ?? self::SYSTEM_PROMPT,
                userPrompt: $stage['user_prompt'],
                variables: $stage['variables'],
                schema: $stage['schema'],
                createdBy: $createdBy,
                created: $created,
                skipped: $skipped,
                version: (int) ($stage['version'] ?? self::VERSION),
            );
        }
    }

    /**
     * @return array<int, array{key: string, name: string, system_prompt?: string, user_prompt: string, variables: array<int, string>, schema: array<string, mixed>, version?: int}>
     */
    private function stageDefinitions(): array
    {
        return [
            [
                'key' => 'topic_discover',
                'name' => 'Pipeline-Stufe: Themenfindung',
                'user_prompt' => <<<'PROMPT'
                    Schlagen Sie 5 bis 10 Ratgeber-Themen für die Branche {{branch}} des
                    Portals {{tenant_name}} vor, mit Fokus auf {{region_scope}} ({{region_name}}).

                    Nutzen Sie als Anregung:
                    - Aktueller Saisonanlass: {{seasonal_hook}}
                    - Aktueller Nachrichtenanlass: {{news_hook}}
                    - Eigene Portaldaten: {{portal_data}}
                    - Belegte Fakten: {{fact_snippets}}
                    - Bereits vorhandene interne Ratgeber (nicht duplizieren): {{internal_link_targets}}

                    Jeder Vorschlag braucht ein klares Haupt-Keyword, eine erkennbare
                    Suchintention und eine kurze Begründung, warum das Thema jetzt relevant
                    ist. Vermeiden Sie Themen, die die vorhandenen internen Ratgeber nur
                    umformulieren.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'region_scope', 'region_name', 'seasonal_hook', 'news_hook', 'portal_data', 'fact_snippets', 'internal_link_targets'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['topics'],
                    'properties' => [
                        'topics' => [
                            'type' => 'array',
                            'minItems' => 5,
                            'maxItems' => 10,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['title', 'primary_keyword', 'secondary_keywords', 'intent', 'rationale'],
                                'properties' => [
                                    'title' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 160],
                                    'primary_keyword' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                                    'secondary_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'intent' => ['type' => 'string', 'enum' => ['informational', 'transactional', 'commercial', 'navigational']],
                                    'rationale' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 400],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // Version 2 (#47): Signalliste und Ausgaberegeln stehen als
                // Platzhalter im Text, statt vom Job an den fertigen Prompt
                // gehaengt zu werden — nur so zeigt die Vorschau im
                // Prompt-Editor denselben Prompt, den der Lauf abschickt.
                // Der System-Prompt ist der Planer-Text des Jobs, nicht der
                // Redakteurstext der Schreib-Stufen: die Themenfindung hat
                // weder Styleguide noch Faktenschnipsel.
                'key' => 'topic_discover',
                'name' => 'Pipeline-Stufe: Themenfindung',
                'version' => 2,
                'system_prompt' => DiscoverTopicsJob::SYSTEM_PROMPT,
                'user_prompt' => <<<'PROMPT'
                    Schlagen Sie bis zu {{max_candidates}} Ratgeber-Themen für die Branche
                    {{branch}} des Portals {{tenant_name}} vor.

                    {{signals}}

                    Bereits vorhandene interne Ratgeber (nicht duplizieren):
                    {{internal_link_targets}}

                    Jeder Vorschlag braucht ein klares Haupt-Keyword, eine erkennbare
                    Suchintention und eine kurze Begründung, warum das Thema jetzt relevant
                    ist. Vermeiden Sie Themen, die die vorhandenen internen Ratgeber nur
                    umformulieren.

                    {{output_rules}}
                    PROMPT,
                'variables' => ['tenant_name', 'branch', 'signals', 'internal_link_targets', 'max_candidates', 'output_rules'],
                'schema' => DiscoverTopicsJob::outputSchema(30),
            ],
            [
                'key' => 'outline',
                'name' => 'Pipeline-Stufe: Gliederung',
                'user_prompt' => <<<'PROMPT'
                    Erstellen Sie Titel, Slug und eine Gliederung (H2/H3) fuer einen
                    Ratgeberartikel der Branche {{branch}} auf {{tenant_name}}.

                    Haupt-Keyword: {{primary_keyword}}
                    Weitere Keywords: {{secondary_keywords}}
                    Suchintention: {{intent}}
                    Regionsbezug: {{region_scope}} ({{region_name}})
                    Von Nutzer:innen tatsächlich gestellte Fragen (People-Also-Ask): {{serp_paa}}
                    Ueberschriften der Top-Wettbewerber (zur Einordnung, nicht zum Kopieren): {{competitor_h2s}}
                    Belegte Fakten: {{fact_snippets}}
                    Interne Linkziele, die sinnvoll eingebaut werden sollen: {{internal_link_targets}}
                    Saisonanlass: {{seasonal_hook}}
                    Nachrichtenanlass: {{news_hook}}

                    Die Gliederung muss die Suchintention im ersten Abschnitt beantworten
                    und, falls {{region_scope}} nicht national ist, mindestens einen
                    Abschnitt mit echtem Regionalbezug im Text vorsehen (kein
                    Doorway-Muster).
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'primary_keyword', 'secondary_keywords', 'intent', 'region_scope', 'region_name', 'serp_paa', 'competitor_h2s', 'fact_snippets', 'internal_link_targets', 'seasonal_hook', 'news_hook'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'slug', 'outline'],
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 160],
                        'slug' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                        'outline' => [
                            'type' => 'array',
                            'minItems' => 4,
                            'maxItems' => 12,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['heading', 'level', 'key_points'],
                                'properties' => [
                                    'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                                    'level' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 3],
                                    'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'section_write',
                'name' => 'Pipeline-Stufe: Abschnitt ausformulieren',
                'user_prompt' => <<<'PROMPT'
                    Formulieren Sie den folgenden Abschnitt der Gliederung als
                    Fließtext-HTML (nur <p>, <ul>, <li>, <strong>) aus.

                    Gesamte Gliederung (Kontext): {{outline}}
                    Auszuformulierender Abschnitt: {{section}}
                    Branche: {{branch}}, Portal: {{tenant_name}}
                    Haupt-Keyword: {{primary_keyword}}
                    Weitere Keywords: {{secondary_keywords}}
                    Regionsbezug: {{region_scope}} ({{region_name}})
                    Belegte Fakten, die Sie zitieren duerfen: {{fact_snippets}}
                    Interne Linkziele: {{internal_link_targets}}

                    Halten Sie den Abschnitt bei 80 bis 220 Woertern, sofern die
                    Gliederung nichts anderes nahelegt. Nennen Sie keine Zahl, die nicht in
                    {{fact_snippets}} belegt ist.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'primary_keyword', 'secondary_keywords', 'region_scope', 'region_name', 'fact_snippets', 'internal_link_targets', 'outline', 'section'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['heading', 'html', 'word_count'],
                    'properties' => [
                        'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                        'html' => ['type' => 'string', 'minLength' => 20],
                        'word_count' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
            ],
            [
                // Version 2 (#14): Der Generator rendert `summary_sentence`
                // als ersten Absatz jeder H2 — ein eigenstaendiger Fazit-Satz
                // ist Abnahmekriterium. Version 1 bleibt zur Nachvollziehbarkeit
                // bestehen, `scopeResolve` nimmt die hoechste Version.
                'key' => 'section_write',
                'version' => 2,
                'name' => 'Pipeline-Stufe: Abschnitt ausformulieren (mit Fazit-Satz)',
                'user_prompt' => <<<'PROMPT'
                    Formulieren Sie den folgenden Abschnitt der Gliederung als
                    Fließtext-HTML (nur <p>, <ul>, <ol>, <li>, <strong>, <em>, <h3>, <a>)
                    aus.

                    Gesamte Gliederung (Kontext): {{outline}}
                    Auszuformulierender Abschnitt: {{section}}
                    Branche: {{branch}}, Portal: {{tenant_name}}
                    Haupt-Keyword: {{primary_keyword}}
                    Weitere Keywords: {{secondary_keywords}}
                    Regionsbezug: {{region_scope}} ({{region_name}})
                    Belegte Fakten, die Sie zitieren duerfen: {{fact_snippets}}
                    Interne Linkziele: {{internal_link_targets}}

                    Liefern Sie zuerst summary_sentence: einen eigenstaendigen Satz, der
                    die Frage der Überschrift beantwortet und auch ohne den folgenden Text
                    verständlich ist. Er wird als erster Absatz des Abschnitts gesetzt und
                    darf im übrigen HTML nicht wiederholt werden. Keine Einleitungsfloskel,
                    kein Rückbezug auf die Überschrift.

                    Halten Sie den Abschnitt im vorgegebenen Wortbudget. Nennen Sie keine
                    Zahl, keinen Preis und keine Frist, die nicht in {{fact_snippets}}
                    belegt ist, und nennen Sie die verwendeten Faktennummern.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'primary_keyword', 'secondary_keywords', 'region_scope', 'region_name', 'fact_snippets', 'internal_link_targets', 'outline', 'section'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['heading', 'summary_sentence', 'html', 'word_count', 'used_fact_ids', 'used_link_urls'],
                    'properties' => [
                        'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                        'summary_sentence' => ['type' => 'string', 'minLength' => 30, 'maxLength' => 300],
                        'html' => ['type' => 'string', 'minLength' => 120],
                        'word_count' => ['type' => 'integer', 'minimum' => 20, 'maximum' => 600],
                        'used_fact_ids' => ['type' => 'array', 'maxItems' => 12, 'items' => ['type' => 'integer']],
                        'used_link_urls' => ['type' => 'array', 'maxItems' => 6, 'items' => ['type' => 'string', 'maxLength' => 300]],
                    ],
                ],
            ],
            [
                'key' => 'faq',
                'name' => 'Pipeline-Stufe: FAQ',
                'user_prompt' => <<<'PROMPT'
                    Leiten Sie 3 bis 6 FAQ-Eintraege fuer einen Ratgeberartikel der Branche
                    {{branch}} zum Haupt-Keyword {{primary_keyword}} ab.

                    Nutzen Sie vorrangig echte Nutzerfragen aus {{serp_paa}} und beantworten
                    Sie sie kurz (2-4 Saetze) auf Basis von {{fact_snippets}} und der
                    Gliederung: {{outline}}. Keine Frage darf eine im Artikel unbelegte
                    Zahl in die Antwort einfuehren.
                    PROMPT,
                'variables' => ['branch', 'primary_keyword', 'serp_paa', 'fact_snippets', 'outline'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['faq'],
                    'properties' => [
                        'faq' => [
                            'type' => 'array',
                            'minItems' => 3,
                            'maxItems' => 6,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['question', 'answer'],
                                'properties' => [
                                    'question' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 200],
                                    'answer' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 600],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'meta',
                'name' => 'Pipeline-Stufe: Meta-Angaben',
                'user_prompt' => <<<'PROMPT'
                    Erzeugen Sie Meta-Title (bis 60 Zeichen) und Meta-Description (bis 155
                    Zeichen) fuer einen Ratgeberartikel der Branche {{branch}} auf
                    {{tenant_name}}.

                    Haupt-Keyword: {{primary_keyword}}
                    Weitere Keywords: {{secondary_keywords}}
                    Regionsbezug: {{region_name}}
                    Gliederung (Kontext): {{outline}}

                    Das Haupt-Keyword muss im Meta-Title vorkommen. Keine Superlative, keine
                    Anfuehrungszeichen, kein Clickbait.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'primary_keyword', 'secondary_keywords', 'region_name', 'outline'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['meta_title', 'meta_description'],
                    'properties' => [
                        'meta_title' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 60],
                        'meta_description' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 155],
                    ],
                ],
            ],
            [
                'key' => 'short_answer',
                'name' => 'Pipeline-Stufe: Kurzantwort',
                'user_prompt' => <<<'PROMPT'
                    Formulieren Sie eine Kurzantwort (2-3 Saetze, Featured-Snippet-tauglich)
                    auf die Suchintention {{intent}} zum Haupt-Keyword {{primary_keyword}}.

                    Stuetzen Sie sich ausschliesslich auf {{fact_snippets}} und die
                    Gliederung {{outline}}. Beantworten Sie die Frage direkt im ersten Satz.
                    PROMPT,
                'variables' => ['primary_keyword', 'intent', 'fact_snippets', 'outline'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['short_answer'],
                    'properties' => [
                        'short_answer' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 500],
                    ],
                ],
            ],
            [
                'key' => 'image_alt',
                'name' => 'Pipeline-Stufe: Alt-Text des Titelbilds',

                // Ein Alt-Text ist kein Redaktionstext: kein Styleguide, keine
                // Verbotsliste, nur eine Bildbeschreibung. Der volle
                // System-Prompt der Schreibstufen waere hier nur Eingabekosten.
                'system_prompt' => 'Sie beschreiben Bilder fuer Alternativtexte auf deutschen '
                    .'Ratgeberseiten: knapp, sachlich, in der dritten Person und ohne Werbesprache.',
                'user_prompt' => <<<'PROMPT'
                    Formulieren Sie den Alternativtext (alt-Attribut) fuer das Titelbild eines
                    deutschsprachigen Ratgeberartikels.

                    Artikel: {{title}}
                    Branche: {{branch}}
                    Region: {{region_name}}
                    Thema in Stichworten: {{primary_keyword}}
                    Bildbeschreibung der Quelle: {{image_description}}
                    Herkunft des Bildes: {{image_source}}

                    Regeln:
                    - Hoechstens {{max_chars}} Zeichen, ein Satz, ohne Punkt am Ende.
                    - Beschreiben Sie, was zu sehen ist, nicht was der Artikel behauptet.
                    - Beginnen Sie nicht mit "Bild von", "Foto von" oder "Darstellung von".
                    - Keine Keyword-Haeufung, keine Werbesprache, keine Marken- oder
                      Personennamen.
                    PROMPT,
                'variables' => ['title', 'branch', 'region_name', 'primary_keyword', 'image_description', 'image_source', 'max_chars'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['alt_text'],
                    'properties' => [
                        'alt_text' => ['type' => 'string', 'minLength' => 15, 'maxLength' => 125],
                    ],
                ],
            ],
            [
                'key' => 'regional_block',
                'name' => 'Pipeline-Stufe: Regionalblock',
                'user_prompt' => <<<'PROMPT'
                    Schreiben Sie einen Abschnitt (100-180 Woerter, HTML mit <p>) mit
                    echtem Regionalbezug fuer {{region_scope}} {{region_name}}, Branche
                    {{branch}}, Portal {{tenant_name}}.

                    Nutzen Sie Portaldaten ({{portal_data}}) und belegte Fakten
                    ({{fact_snippets}}), z. B. Anzahl Betriebe, Besonderheiten der Region
                    oder zustaendige Stellen. Der Regionalbezug muss im Fließtext stehen,
                    nicht nur andeutungsweise — kein Doorway-Muster. Interne Linkziele, die
                    Sie einbauen sollen: {{internal_link_targets}}.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'region_scope', 'region_name', 'portal_data', 'fact_snippets', 'internal_link_targets'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['region_scope', 'region_name', 'html'],
                    'properties' => [
                        'region_scope' => ['type' => 'string', 'enum' => ['national', 'state', 'city']],
                        'region_name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                        'html' => ['type' => 'string', 'minLength' => 40],
                    ],
                ],
            ],
            [
                // Version 2 (#14): Der Regionalblock liefert zusaetzlich
                // intro/outro als Klartext. Das Ratgeber-Template (#17)
                // rendert bei Stadt-Zuschnitt einen eigenen Baustein daraus;
                // die Faktenpaare des Blocks entstehen ohne Modellaufruf.
                'key' => 'regional_block',
                'version' => 2,
                'name' => 'Pipeline-Stufe: Regionalblock (mit Einstieg und Ueberleitung)',
                'user_prompt' => <<<'PROMPT'
                    Schreiben Sie einen Abschnitt mit echtem Regionalbezug fuer
                    {{region_scope}} {{region_name}}, Branche {{branch}}, Portal
                    {{tenant_name}}.

                    Portaldaten: {{portal_data}}
                    Belegte Fakten: {{fact_snippets}}
                    Gliederung des Artikels (Kontext): {{outline}}
                    Interne Linkziele: {{internal_link_targets}}

                    heading ist die Überschrift des Abschnitts und nennt die Region, ohne
                    den Ortsnamen bloß anzuhängen. intro ist ein Satz, der begründet, warum
                    die Lage in {{region_name}} anders ist als bundesweit. html sind 100 bis
                    180 Wörter in <p>-Absätzen ohne Überschrift, gestützt auf Portaldaten
                    und regionale Förderung oder Landesrecht. outro leitet in einem Satz zur
                    Betriebssuche im Portal über, ohne Werbesprache.

                    Der Regionalbezug muss im Fließtext stehen, nicht andeutungsweise —
                    kein Doorway-Muster. Keine geschätzten Betriebszahlen, keine erfundenen
                    Fördersätze.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'region_scope', 'region_name', 'portal_data', 'fact_snippets', 'internal_link_targets', 'outline'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['heading', 'intro', 'html', 'outro'],
                    'properties' => [
                        'heading' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 120],
                        'intro' => ['type' => 'string', 'minLength' => 40, 'maxLength' => 400],
                        'html' => ['type' => 'string', 'minLength' => 120],
                        'outro' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 300],
                    ],
                ],
            ],
            [
                'key' => 'refresh_update',
                'name' => 'Pipeline-Stufe: Refresh',
                'user_prompt' => <<<'PROMPT'
                    Der folgende, bereits veroeffentlichte Abschnitt zum Haupt-Keyword
                    {{primary_keyword}} (Branche {{branch}}) ist moeglicherweise veraltet:

                    {{section}}

                    Aktualisieren Sie ihn anhand neuer Fakten ({{fact_snippets}}) und eines
                    aktuellen Anlasses ({{news_hook}}). Aendern Sie nur, was durch neue
                    Fakten oder den Anlass tatsaechlich veranlasst ist, und behalten Sie
                    Struktur und Tonalitaet der Gliederung {{outline}} bei. Nennen Sie fuer
                    jede Aenderung kurz den Grund.
                    PROMPT,
                'variables' => ['branch', 'primary_keyword', 'fact_snippets', 'news_hook', 'outline', 'section'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['changes'],
                    'properties' => [
                        'changes' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['section_heading', 'updated_html', 'reason'],
                                'properties' => [
                                    'section_heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                                    'updated_html' => ['type' => 'string', 'minLength' => 20],
                                    'reason' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 300],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // Version 2 (#24): der Refresh laeuft abschnittsweise ueber
                // FixSectionsStep, nicht ueber den ganzen Artikel. Die
                // Vorlage beschreibt deshalb genau einen Abschnitt, und das
                // Ausgabeschema ist das des Schritts — dieselbe Arbeitsteilung
                // wie bei section_write und quality_rubric.
                'key' => 'refresh_update',
                'name' => 'Pipeline-Stufe: Refresh',
                'version' => 2,
                'user_prompt' => <<<'PROMPT'
                    Der folgende Abschnitt eines bereits veroeffentlichten Ratgebers zum
                    Haupt-Keyword {{primary_keyword}} (Branche {{branch}}, Portal
                    {{tenant_name}}) ist veraltet. Bringen Sie genau diesen Abschnitt auf den
                    aktuellen Stand — nicht mehr und nicht weniger.

                    Gliederung des Artikels (nur zur Orientierung):
                    {{outline}}

                    Abschnitt und Gruende fuer die Aktualisierung:
                    {{section}}

                    Regionsbezug: {{region_scope}} ({{region_name}})
                    Aktueller Nachrichtenanlass: {{news_hook}}
                    Belegte Fakten in ihrem heutigen Stand:
                    {{fact_snippets}}
                    Interne Linkziele:
                    {{internal_link_targets}}

                    Aendern Sie nur, was durch die genannten Gruende tatsaechlich veranlasst
                    ist. Ein Satz, den kein Grund betrifft, bleibt Wort fuer Wort stehen.
                    Nennen Sie in change_note in einem Satz, was Sie aktualisiert haben.
                    PROMPT,
                'variables' => ['branch', 'tenant_name', 'primary_keyword', 'region_scope', 'region_name', 'news_hook', 'fact_snippets', 'internal_link_targets', 'outline', 'section'],
                'schema' => FixSectionsStep::outputSchema(),
            ],
            [
                'key' => 'lead_question_cluster',
                'name' => 'Pipeline-Stufe: Themen-Fragencluster',
                'user_prompt' => <<<'PROMPT'
                    Bilden Sie ein Cluster verwandter Nutzerfragen rund um das Haupt-Keyword
                    {{primary_keyword}} (Branche {{branch}}), geeignet fuer interne
                    Verlinkung und zukuenftige Themenauswahl (#12).

                    Nutzen Sie {{secondary_keywords}} und {{serp_paa}} als Grundlage und
                    ordnen Sie jeder Frage eine Suchintention zu. Suchintention des
                    Hauptartikels: {{intent}}.
                    PROMPT,
                'variables' => ['branch', 'primary_keyword', 'secondary_keywords', 'serp_paa', 'intent'],
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['cluster_topic', 'questions'],
                    'properties' => [
                        'cluster_topic' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                        'questions' => [
                            'type' => 'array',
                            'minItems' => 3,
                            'maxItems' => 15,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['question', 'primary_keyword', 'suggested_intent'],
                                'properties' => [
                                    'question' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 200],
                                    'primary_keyword' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80],
                                    'suggested_intent' => ['type' => 'string', 'enum' => ['informational', 'transactional', 'commercial', 'navigational']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                // Version 2 (#75): Version 1 beschrieb ein Keyword-Cluster,
                // das kein Lauf angefordert hat, und ihr Schema wich vom
                // Ausgabevertrag des Jobs ab (kein `share`). Seit #75 ruft
                // ClusterLeadQuestionsJob die Vorlage tatsaechlich auf: der
                // ganze Prompt steht hier, die anonymisierten Eingaben gehen
                // als Variable `inputs` hinein. System-Prompt und Schema sind
                // woertlich die Konstanten des Jobs — der Auswertertext, nicht
                // der Redakteurstext der Schreib-Stufen.
                'key' => 'lead_question_cluster',
                'name' => 'Pipeline-Stufe: Themen-Fragencluster',
                'version' => 2,
                'system_prompt' => ClusterLeadQuestionsJob::SYSTEM_PROMPT,
                'user_prompt' => <<<'PROMPT'
                    Branche: {{branch}}

                    Bilde aus den folgenden Eingaben hoechstens {{max_questions}} Fragethemen.
                    Fasse inhaltlich Gleiches zusammen und ordne die Themen nach Haeufigkeit.

                    Eingaben:
                    {{inputs}}
                    PROMPT,
                'variables' => ['branch', 'max_questions', 'inputs'],
                'schema' => ClusterLeadQuestionsJob::outputSchema(),
            ],
        ];
    }

    private function seedQualityRubric(?int $createdBy, int &$created, int &$skipped): void
    {
        $userPrompt = <<<'PROMPT'
            Bewerten Sie den folgenden Artikelentwurf (Branche {{branch}}, Haupt-Keyword
            {{primary_keyword}}, Regionsbezug {{region_scope}} {{region_name}}) anhand der
            acht Kriterien mit ihren Gewichten:

            - Einzigartigkeit (15): kein Textbaustein deckungsgleich mit bestehenden
              Artikeln oder Wettbewerbern.
            - Faktenbelege (20): jede Zahl/Statistik hat eine Entsprechung in
              {{fact_snippets}}, kein Widerspruch dazu.
            - Suchintention (15): Artikel beantwortet die Suchintention bereits im ersten
              Abschnitt.
            - Struktur (10): folgt der Gliederung {{outline}}, sinnvolle Hierarchie, keine
              Duplikate.
            - Lesbarkeit (10): kurze Absaetze, aktive Sprache, keine Floskeln oder
              Superlative aus der Verbotsliste des Styleguides.
            - Interne Links (10): die Linkziele aus {{internal_link_targets}} sind sinnvoll
              eingebaut.
            - Regionalitaet (10): bei region_scope != national steht der Regionalbezug im
              Fließtext, nicht nur im Titel (Doorway-Test).
            - YMYL-Sicherheit (10): Pflicht-Disclaimer aus dem Styleguide vorhanden, keine
              unbedingten medizinischen/rechtlichen Handlungsanweisungen.

            Styleguide der Branche (fuer Verbotsliste und Disclaimer-Pflicht):
            {{styleguide}}

            Zu bewertender Artikeltext:
            {{section}}

            Jeder Abschnitt des Artikeltexts beginnt mit einer Kennung in eckigen
            Klammern ([s1], [s2], ...). Jede Anweisung in fix_instructions nennt in
            section_id genau diese Kennung des Abschnitts, der zu aendern ist, und in
            instruction eine konkrete Aenderung an diesem Abschnitt — keine allgemeine
            Kritik und keine Anweisung zum ganzen Artikel. Erfinden Sie keine Kennung:
            nur Kennungen, die im Artikeltext vorkommen, werden uebernommen.

            Melden Sie in blocking_issues mindestens jeden der folgenden Faelle, sofern
            zutreffend: unbelegte Zahl, Widerspruch zu einem fact_snippet, fehlender
            YMYL-Disclaimer, Superlativ-/Werbesprache, Doorway-Muster (Region nur im
            Titel, nicht im Inhalt). Ist blocking_issues nicht leer, ist der Artikel
            unabhaengig vom Score nicht freigabefaehig.
            PROMPT;

        $this->upsert(
            key: 'quality_rubric',
            name: 'Qualitäts-Rubrik (QualityCheckJob)',
            systemPrompt: self::SYSTEM_PROMPT,
            userPrompt: $userPrompt,
            variables: ['branch', 'primary_keyword', 'region_scope', 'region_name', 'fact_snippets', 'outline', 'internal_link_targets', 'styleguide', 'section', 'tenant_name', 'intent'],
            schema: RubricEvaluator::outputSchema(),
            createdBy: $createdBy,
            created: $created,
            skipped: $skipped,
            version: 2,
        );
    }

    /**
     * Idempotent ueber (key, tenant_id, version). Eine inhaltliche Aenderung
     * bekommt eine neue Version statt eines Updates, damit der Stand, mit dem
     * ein Artikel entstanden ist, nachvollziehbar bleibt. `$version` weicht
     * deshalb nur fuer einzelne, nachtraeglich geaenderte Stufen von
     * self::VERSION ab.
     *
     * @param  array<int, string>  $variables
     * @param  array<string, mixed>|null  $schema
     */
    private function upsert(
        string $key,
        string $name,
        ?string $systemPrompt,
        string $userPrompt,
        array $variables,
        ?array $schema,
        ?int $createdBy,
        int &$created,
        int &$skipped,
        int $version = self::VERSION,
    ): void {
        $exists = PromptTemplate::query()
            ->where('key', $key)
            ->whereNull('tenant_id')
            ->where('version', $version)
            ->exists();

        if ($exists) {
            $skipped++;

            return;
        }

        PromptTemplate::create([
            'key' => $key,
            'tenant_id' => null,
            'version' => $version,
            'name' => $name,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'variables_json' => $variables === [] ? null : $variables,
            'output_schema_json' => $schema,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);

        $created++;
    }
}
