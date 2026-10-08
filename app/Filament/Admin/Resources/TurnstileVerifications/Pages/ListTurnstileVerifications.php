<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TurnstileVerifications\Pages;

use App\Filament\Admin\Resources\TurnstileVerifications\TurnstileVerificationResource;
use App\Filament\Admin\Widgets\BotProtectionStatsWidget;
use App\Filament\ListDefaults;
use Filament\Resources\Pages\ListRecords;

/**
 * Liste der Sicherheitspruefungen (#9). Nur lesend, deshalb ohne Kopfaktionen.
 * Ueber der Tabelle stehen die netzweiten Kennzahlen — sie sagen, ob eine
 * auffaellige Zeile ein Einzelfall oder gerade die Regel ist.
 */
class ListTurnstileVerifications extends ListRecords
{
    use ListDefaults;

    protected static string $resource = TurnstileVerificationResource::class;

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            BotProtectionStatsWidget::class,
        ];
    }
}
