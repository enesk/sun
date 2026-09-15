<?php

namespace App\Filament\Admin\Resources\TenantTexts\Concerns;

use App\Support\Translation\PortalTextCatalog;
use Filament\Notifications\Notification;

/**
 * Platzhalter-Warnungen blockieren das Speichern nicht, erscheinen danach
 * aber zusaetzlich als Hinweis (#13).
 */
trait WarnsAboutPlaceholders
{
    protected function notifyPlaceholderWarnings(): void
    {
        $record = $this->getRecord();

        $warnings = PortalTextCatalog::warnings($record->getAttribute('key'), $record->getAttribute('value'));

        if ($warnings === []) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(__('Gespeichert, aber bitte Platzhalter prüfen'))
            ->body(implode("\n", $warnings))
            ->persistent()
            ->send();
    }
}
