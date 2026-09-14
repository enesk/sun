<?php

/*
|--------------------------------------------------------------------------
| KI-Footprints in Firmenbeschreibungen (#2)
|--------------------------------------------------------------------------
|
| Muster fuer App\Services\Seo\AiFootprintDetector. Jede Regel wird Zeile fuer
| Zeile gegen den Text geprueft (Modifier iu, also case-insensitive und
| unicode-aware). Die Wirkung bestimmt 'scope':
|
|   tail     – ab dem Treffer bis zum Textende entfernen. Steht vor dem Treffer
|              auf derselben Zeile ein ganzer Satz, bleibt dieser stehen.
|   line     – nur die getroffene Zeile entfernen (Vorhandensein genuegt, auch mitten in der Zeile).
|   sentence – nur den Satz entfernen, in dem der Treffer steht; bleibt von der
|              Zeile nur eine Beschriftung ("Kontakt:") uebrig, faellt sie ganz weg.
|   trailing – nur, solange die Zeile die letzte nicht leere Zeile ist
|              (wiederholt, bis keine mehr passt).
|
| $start verankert Textmuster am Zeilen- oder Satzanfang und nimmt davor
| stehende Markdown-Zeichen, Klammern und Emojis mit — so trifft
| "💡 **SEO-Keywords:**" genauso wie "(Optimiert fuer Suchbegriffe ...)",
| aber nicht "... auch hier ist der Fachmann zur Stelle".
|
*/

$start = '(?:^|(?<=[.!?…]\s)|(?<=[.!?…]\*\s)|(?<=[.!?…]\*\*\s)|(?<=\|\s))[^\p{L}\p{N}]*';

return [

    'ai_footprints' => [

        // Bleiben nach der Bereinigung weniger Zeichen uebrig, wird der
        // Datensatz nicht geschrieben, sondern als "manuell pruefen" gemeldet.
        'min_length' => 80,

        // Faellt mehr als dieser Anteil des Textes weg, ebenfalls "manuell pruefen":
        // Schutz gegen ein Muster, das mitten im eigentlichen Text greift.
        'max_removed_ratio' => 0.5,

        // Geprueft werden nur Spalten, die in der Tenant-Tabelle companies existieren.
        'columns' => ['description', 'meta_description', 'short_description'],

        'report_path' => 'app/seo-cleanup',

        'patterns' => [

            // Zeilenregeln zuerst: eine Ueberschrift "SEO-optimierte Beschreibung fuer ..."
            // am Textanfang darf nicht als Beginn eines Nachspanns gelten.
            'seo_beschreibung_ueberschrift' => [
                'scope' => 'line',
                'regex' => "/{$start}(?:SEO[-\s]*(?:optimierte\s+)?|optimierte\s+)(?:Firmen|Unternehmens)?beschreibung\b/iu",
            ],

            'hier_ist' => [
                'scope' => 'line',
                'regex' => "/{$start}Hier\s+(?:ist|sind|kommt|folgt)\s+(?:eine?|die|der|deine?|Ihre?)\b.{0,80}(?:Beschreibung|Text|Version|Entwurf|Profil)/iu",
            ],

            'gerne_erstelle_ich' => [
                'scope' => 'line',
                'regex' => "/{$start}Gerne\s+(?:erstelle|schreibe|passe|überarbeite|ueberarbeite|helfe|formuliere|optimiere)\s+ich\b/iu",
            ],

            // Meta-Title/-Description stehen meist ueber dem eigentlichen Text.
            'meta_beschreibung' => [
                'scope' => 'line',
                'regex' => "/{$start}Meta[-\s]*(?:Beschreibung|Description|Title|Titel)[*_\s]*:/iu",
            ],

            'optimiert_fuer_suchanfragen' => [
                'scope' => 'tail',
                'regex' => "/{$start}(?:SEO[-\s]*)?optimiert(?:e[nrs]?)?\s+(?:für|fuer|mit|auf)\s+(?:Such|SEO|lokale|relevante|Keywords|Fokus|Google)/iu",
            ],

            'fuer_suchanfragen_wie' => [
                'scope' => 'tail',
                'regex' => "/{$start}(?:perfekt|optimal|ideal|geeignet|relevant)\s+(?:für|fuer)\s+(?:Suchanfragen|Suchbegriffe|SEO)/iu",
            ],

            'seo_optimiert' => [
                'scope' => 'tail',
                'regex' => "/{$start}SEO[-\s]*optimiert(?:e[nrs]?)?\b/iu",
            ],

            // Beschriftete Keyword-Listen: "SEO-Keywords:", "Keywords fuer SEO:",
            // "Optimierte SEO-Keywords:", "Ihre Suchbegriffe:", "Schluesselwoerter fuer SEO:".
            'seo_keywords' => [
                'scope' => 'tail',
                'regex' => "/{$start}[^:\n]{0,40}?(?<!\p{L})(?:Keywords?|Schlüsselw(?:ö|oe)rter|Schluesselwoerter|Stichw(?:o|ö)rte|Suchbegriffe|Suchanfragen|SEO[-\p{L}]*)\b[^:\n]{0,30}:/iu",
            ],

            'hinweis_seo' => [
                'scope' => 'tail',
                'regex' => "/{$start}(?:Hinweis|Anmerkung|Tipp|Notiz)[*_\s]*:.{0,250}?(?:SEO|Keywords?|Suchmaschine|Conversion|Beschreibung)/iu",
            ],

            'fuer_suchmaschinen_optimiert' => [
                'scope' => 'line',
                'regex' => '/(?:für|fuer)\s+Suchmaschinen\s+optimiert/iu',
            ],

            'mit_dieser_beschreibung' => [
                'scope' => 'tail',
                'regex' => "/{$start}Mit\s+diese[rm]\s+(?:SEO[-\s]*optimierten\s+)?(?:Firmen)?(?:Beschreibung|Text)\b/iu",
            ],

            // Saetze ueber die eigene SEO-Wirkung: "Mit strategischen SEO-Keywords wie ...",
            // "Optimiere mit dieser SEO-Beschreibung ...", "Stark bei Google & SEO-optimiert".
            'seo_wirkung_satz' => [
                'scope' => 'tail',
                'regex' => "/{$start}[^.!?\n]{0,60}?(?<!\p{L})(?:SEO[-\s]*(?:Keywords?|Beschreibung|optimiert\p{L}*)|(?:strategischen|gezielten|relevanten)\s+[*_]*Keywords)/iu",
            ],

            'diese_beschreibung' => [
                'scope' => 'tail',
                'regex' => "/{$start}Diese[rs]?\s+(?:SEO[-\s]*optimierte[rs]?\s+|optimierte[rs]?\s+)?(?:Firmen|Unternehmens)?(?:Beschreibung|Text)\b/iu",
            ],

            'platzhalter_einfuegen' => [
                'scope' => 'sentence',
                'regex' => '/\[[^\]]{0,40}(?:einfügen|einfuegen|ergänzen|ergaenzen)\s*\]/iu',
            ],

            'hashtag_zeile' => [
                'scope' => 'line',
                'regex' => '/^[^\p{L}\p{N}#]*#[\p{L}\p{N}]+(?:[\s*_,]+#[\p{L}\p{N}]+){2,}[^\p{L}\p{N}]*$/iu',
            ],

            // Kursive Markdown-Zeile (*...*, nicht **fett**) am Textende.
            'markdown_kursiv_am_ende' => [
                'scope' => 'trailing',
                'regex' => '/^[^\p{L}\p{N}*]*\*(?!\*)[^*]+(?<!\*)\*[^\p{L}\p{N}*]*$/u',
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Generator fuer Firmenbeschreibungen (#3)
    |--------------------------------------------------------------------------
    |
    | App\Services\Ai\CompanyDescriptionGenerator ruft die Claude-CLI auf. Der
    | Prompt samt Version liegt in App\Services\Ai\CompanyDescriptionPrompt,
    | geprueft wird die Ausgabe mit den Mustern aus 'ai_footprints' oben.
    |
    */

    'description_generator' => [
        'cli_binary' => env('CLAUDE_CLI_BINARY', '/Users/enes/.local/bin/claude'),
        'model' => env('CLAUDE_CLI_MODEL', 'sonnet'),
        'timeout' => 60,
        // Wert in companies.description_source nach zwei verworfenen Versuchen.
        'failed_source' => 'ai_failed',
    ],

];
