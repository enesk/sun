<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\AntiSpam\BotQuarantine;
use App\AntiSpam\BotScorer;
use App\Turnstile\Support\BotProtectionAccess;
use App\Turnstile\Support\TurnstileAdminPortal;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Einstellungen › Verdächtige Accounts/Einträge (#10, docs/turnstile.md §7).
 *
 * Die Sichtung der Bestandsbereinigung: oben die markierten Konten aus der
 * zentralen `users` ueber alle Portale, darunter die markierten Eintraege des
 * gewaehlten Portals. Zwei Tabellen, weil die Datensaetze in verschiedenen
 * Datenbanken liegen — eine gemeinsame Tabelle waere nur zusammenkopiert.
 *
 * Markiert wird hier nichts. Das macht ausschliesslich `antispam:scan`; diese
 * Seite kennt nur die beiden Entscheidungen Freigeben und Löschen (soft), und
 * beide laufen ueber {@see BotQuarantine}.
 *
 * Die Portalauswahl ist dieselbe wie bei Bot-Schutz und Sicherheitspruefungen
 * ({@see TurnstileAdminPortal}): wer dort ein Portal waehlt, sieht es hier
 * wieder. Beim Wechsel wird die untere Tabelle neu aufgebaut (Schluessel im
 * Blade) — sie haengt an der Verbindung des Portals und laesst sich nicht
 * nachtraeglich umhaengen.
 */
class SuspectedBots extends Page
{
    protected string $view = 'filament.admin.pages.suspected-bots';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 82;

    protected static ?string $slug = 'verdaechtige-datensaetze';

    public ?int $tenantId = null;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Verdächtige Accounts/Einträge');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Verdächtige Accounts/Einträge');
    }

    public function getSubheading(): ?string
    {
        return __('Quarantäne aus "antispam:scan". Freigeben stellt den Zustand von vorher wieder her, Löschen setzt nur deleted_at. Ohne Freigabe werden markierte Datensätze nach :days Tagen automatisch soft-gelöscht.', [
            'days' => BotQuarantine::quarantineDays(),
        ]);
    }

    /** Wie die übrigen Bot-Schutz-Seiten: nur Betreiber. */
    public static function canAccess(): bool
    {
        return BotProtectionAccess::allowed();
    }

    public function mount(): void
    {
        $this->tenantId = TurnstileAdminPortal::currentId();
    }

    public function updatedTenantId(mixed $value): void
    {
        $this->tenantId = filled($value) ? (int) $value : null;

        TurnstileAdminPortal::select($this->tenantId);
    }

    /**
     * @return array<int, string>
     */
    public function portalOptions(): array
    {
        return TurnstileAdminPortal::options();
    }

    /**
     * Schwelle und Gewichte, damit neben der Liste steht, woraus ein Score
     * entsteht — die Werte stehen in config/antispam.php und sind kein Wissen,
     * das man sich aus den Spalten zusammenreimen soll.
     *
     * @return array<string, int>
     */
    public function weights(): array
    {
        return app(BotScorer::class)->weights();
    }

    public function threshold(): int
    {
        return BotScorer::threshold();
    }
}
