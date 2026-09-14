<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Portal\Company;

/**
 * Prompt fuer die Firmenbeschreibungen (#3). Jede inhaltliche Aenderung am
 * System- oder Nutzerprompt bekommt eine neue VERSION — sie steht in jedem
 * Log-Eintrag des Generators, damit nachvollziehbar bleibt, welche
 * Beschreibungen mit welchem Prompt entstanden sind.
 *
 * Version 1: Prompt vor #3 (inline im Command, ohne System-Prompt).
 * Version 2: eigener System-Prompt, reiner Fliesstext ohne Meta-Kommentare und Markdown.
 */
final class CompanyDescriptionPrompt
{
    public const VERSION = 2;

    public function version(): int
    {
        return self::VERSION;
    }

    public function system(): string
    {
        return <<<'PROMPT'
Du bist ein sachlicher Texter fuer ein deutsches Branchenportal und schreibst Firmenbeschreibungen, die unveraendert auf der Profilseite erscheinen.

Ausgabe:
- Gib ausschliesslich den Fliesstext der Beschreibung aus, sonst nichts.
- Keine Einleitung, keine Rueckfrage, keine Erklaerung und keine Anmerkung zum Text, zu SEO, Keywords oder Suchmaschinen.
- Keine Keyword- oder Hashtag-Listen, keine Ueberschriften, keine Beschriftungen wie "Beschreibung:".
- Kein Markdown: keine Sternchen, keine Rauten, keine Aufzaehlungszeichen, keine nummerierten Listen, keine Links, keine Platzhalter in eckigen Klammern.
- Keine Anfuehrungszeichen um den gesamten Text, keine Emojis.
- Absaetze nur durch eine Leerzeile trennen.
PROMPT;
    }

    public function user(Company $company): string
    {
        $categories = $company->categories->pluck('name')->implode(', ') ?: 'Unbekannt';
        $city = $company->city?->name ?? 'Unbekannt';
        $address = trim("{$company->street} {$company->house_no}");
        $rating = $company->rating > 0 ? "{$company->rating}/5 Sternen bei {$company->rating_count} Bewertungen" : 'Keine Bewertungen';

        $reviewContext = '';
        $reviewTexts = $company->reviews
            ->filter(fn ($review) => ! empty($review->body))
            ->map(fn ($review) => "{$review->rating}/5: \"{$review->body}\"")
            ->take(3)
            ->implode("\n");

        if ($reviewTexts !== '') {
            $reviewContext = "\n\nKundenbewertungen (nur als Hintergrund, nicht zitieren):\n{$reviewTexts}";
        }

        return <<<PROMPT
Schreibe eine informative Firmenbeschreibung (2-4 Saetze, 150-300 Zeichen) fuer:

Firma: {$company->name}
Kategorie: {$categories}
Stadt: {$city}
Adresse: {$address}
Bewertung: {$rating}{$reviewContext}

Regeln:
- Sachlich-professionell, keine Werbesprache
- Erwaehne die Bewertung mit Sternen und Anzahl
- Wenn Kundenbewertungen vorhanden, paraphrasiere eine positive Aussage
- Erwaehne Branche und Standort
- Keine erfundenen Details (keine Telefonnummern, keine Preise, keine Leistungen, die nicht in den Daten stehen)
- Deutsch
PROMPT;
    }
}
