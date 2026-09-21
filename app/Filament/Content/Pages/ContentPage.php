<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Guide\Services\ContentTenantContext;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Gemeinsame Basis der Seiten des Content-Panels.
 *
 * Die Navigation des Ratgebersystems (Heute, Themen, Pruefung, Verlauf,
 * Einstellungen) liegt unter app/Guide/Filament (#15, #16,
 * design/guide-dashboard.md §1.2).
 * Die Seiten der alten Pipeline direkt in diesem Verzeichnis stehen nicht mehr
 * in der Navigation, bleiben fuer Inhaber aber bis zum Rueckbau (#19) unter
 * ihrer URL erreichbar ($isLegacyPipelinePage).
 */
abstract class ContentPage extends Page
{
    protected string $view = 'filament.content.pages.placeholder';

    /**
     * Nummer des Tickets, das diesen Bereich ausbaut.
     */
    protected static string $followUpTicket = '#19';

    /**
     * Seite der alten SUN-RC-Pipeline: nie in der Navigation, nur fuer Inhaber.
     */
    protected static bool $isLegacyPipelinePage = false;

    public static function shouldRegisterNavigation(): bool
    {
        return ! static::$isLegacyPipelinePage && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return static::$isLegacyPipelinePage
            ? $user->canManageContentSettings()
            : $user->canAccessContentPanel();
    }

    public static function getFollowUpTicket(): string
    {
        return static::$followUpTicket;
    }

    protected function contentUser(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel() ? $user : null;
    }

    /**
     * Verweise auf die Seiten der alten Pipeline im Platzhalter; nur "Heute"
     * fuellt sie (#14).
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function getLegacyPipelineLinks(): array
    {
        return [];
    }

    /**
     * Hinweiszeile "Altartikel" (design/guide-dashboard.md §3.1 Punkt 5);
     * nur "Heute" fuellt sie (#25).
     *
     * @return array{text: string, url: string}|null
     */
    public function getLegacyOverlapHint(): ?array
    {
        return null;
    }

    public function getSelectedPortalLabel(): string
    {
        $tenant = app(ContentTenantContext::class)->selected();

        return $tenant?->name ?? __('Alle Portale');
    }
}
