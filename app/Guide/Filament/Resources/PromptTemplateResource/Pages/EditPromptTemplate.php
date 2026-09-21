<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\PromptTemplateResource\Pages;

use App\Filament\Content\Resources\PromptTemplates\Pages\EditPromptTemplate as ContentEditPromptTemplate;
use App\Guide\Filament\Concerns\HasSettingsTabs;
use App\Guide\Filament\Resources\PromptTemplateResource;

/**
 * Prompt-Editor fuer Ratgeber-Vorlagen (#16). Speichern legt die naechste
 * Version an, "Historie" zeigt alle Fassungen (geerbt).
 */
class EditPromptTemplate extends ContentEditPromptTemplate
{
    use HasSettingsTabs;

    protected static string $resource = PromptTemplateResource::class;
}
