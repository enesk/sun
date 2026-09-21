<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\PromptTemplateResource\Pages;

use App\Filament\Content\Resources\PromptTemplates\Pages\ListPromptTemplates as ContentListPromptTemplates;
use App\Guide\Filament\Concerns\HasSettingsTabs;
use App\Guide\Filament\Resources\PromptTemplateResource;

/**
 * Liste der Ratgeber-Vorlagen, Reiter "Prompts" der Einstellungen (#16).
 */
class ListPromptTemplates extends ContentListPromptTemplates
{
    use HasSettingsTabs;

    protected static string $resource = PromptTemplateResource::class;
}
