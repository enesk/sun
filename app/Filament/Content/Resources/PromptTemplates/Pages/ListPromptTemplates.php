<?php

declare(strict_types=1);

namespace App\Filament\Content\Resources\PromptTemplates\Pages;

use App\Filament\Content\Resources\PromptTemplates\PromptTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Liste der Prompt-Vorlagen (#20), Reiter "Prompts" der Einstellungen.
 *
 * Neue Schluessel kommen aus dem Seeder (#13); hier werden bestehende
 * Vorlagen weiterentwickelt. Deshalb gibt es bewusst keine Schaltflaeche
 * "Neu anlegen" — ein Schluessel, den keine Pipeline-Stufe aufruft, laege
 * sonst wirkungslos in der Tabelle.
 */
class ListPromptTemplates extends ListRecords
{
    protected static string $resource = PromptTemplateResource::class;

    public function getHeading(): string|Htmlable
    {
        return __('Prompt-Vorlagen');
    }

    /**
     * Wirkungsband aus §7. Hier bleibt es einsaetzig: die Liste zeigt alle
     * Vorlagen, der zweite Satz zum Code-Vertrag gilt nur je Vorlage. Er
     * steht deshalb im Editor ueber dem Formular
     * (content.partials.prompt-schema, §7b.1 Abschnitt 6).
     */
    public function getSubheading(): string|Htmlable|null
    {
        return __('Änderungen wirken auf alle Portale ab dem nächsten Lauf.');
    }
}
