<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Models\Central\ContentUser;
use App\Content\Services\ContentTenantContext;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Gemeinsame Basis der fuenf Bereiche des Content-Panels.
 *
 * Die Bereiche selbst werden in #19 (Uebersicht, Produktion), #20 (Pruefung,
 * Einstellungen, Quellen-Monitor) und #25 (Leistung) mit Inhalt gefuellt.
 * Dieses Ticket liefert Panel, Guard, Navigation und Portalauswahl —
 * die Seiten stehen deshalb als Platzhalter mit korrekter Berechtigung
 * und korrektem Portalbezug.
 */
abstract class ContentPage extends Page
{
    protected string $view = 'filament.content.pages.placeholder';

    /**
     * Nummer des Tickets, das diesen Bereich ausbaut.
     */
    protected static string $followUpTicket = '#19';

    public static function getFollowUpTicket(): string
    {
        return static::$followUpTicket;
    }

    protected function contentUser(): ?ContentUser
    {
        $user = Filament::auth()->user();

        return $user instanceof ContentUser ? $user : null;
    }

    public function getSelectedPortalLabel(): string
    {
        $tenant = app(ContentTenantContext::class)->selected();

        return $tenant?->name ?? __('Alle Portale');
    }
}
