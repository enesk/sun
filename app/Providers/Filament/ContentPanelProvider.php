<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Guide\Filament\Pages\ArticleVersions;
use App\Guide\Filament\Pages\ConfirmOutlines;
use App\Guide\Filament\Pages\Costs;
use App\Guide\Filament\Pages\DailyRunMonitor;
use App\Guide\Filament\Pages\DailyRunSettings;
use App\Guide\Filament\Pages\ImportWizard;
use App\Guide\Filament\Pages\LegacyOverlaps;
use App\Guide\Filament\Pages\RunHistory;
use App\Guide\Filament\Pages\TenantGuideSettings;
use App\Guide\Filament\Resources\CategoryResource;
use App\Guide\Filament\Resources\PromptTemplateResource;
use App\Guide\Filament\Resources\ReviewRunResource;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Http\Controllers\ToggleReviewShortcuts;
use App\Guide\Http\Middleware\InitializeContentTenant;
use App\Guide\Livewire\OutlineEditor;
use App\Guide\Livewire\TenantSwitcher;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\ReviewShortcuts;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

/**
 * Ratgeber-Dashboard: Panel `content` unter <CENTRAL_DOMAIN>/content (#4, #14).
 *
 * Eigenes Panel, aber gewoehnlicher SaaSykit-Login: Guard 'web',
 * App\Models\User, keine eigene Benutzertabelle (docs/guide-system.md §7).
 * /content/login leitet auf den SaaSykit-Login weiter (routes/web.php,
 * Route content.login). Wer hinein darf, entscheidet
 * App\Models\User::canAccessPanel() ueber die Rolle guide_role
 * (owner/editor, Administratoren ohne Rolle gelten als owner); anlegen mit
 * `php artisan guide:user:create`.
 *
 * Navigation: Heute, Themen, Pruefung, Verlauf, Einstellungen
 * (design/guide-dashboard.md §1.2), alle aus app/Guide/Filament: Themen
 * (#15), Heute, Pruefung, Verlauf und Einstellungen (#16). Die Seiten der
 * alten Pipeline stehen nicht mehr in der Navigation.
 * Es werden ausdruecklich keine Ressourcen des Admin-Panels eingebunden:
 * discoverResources/discoverPages zeigen ausschliesslich auf app/Filament/Content.
 *
 * Gestaltung nach design/content-dashboard.md: Teal statt Admin-Violett,
 * dunkle Seitennavigation, warmer Seitenhintergrund. Die Token stehen in
 * resources/css/content/theme.css.
 */
class ContentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(config('content.panel.id', 'content'))
            ->path(config('content.panel.path', 'content'))
            ->authGuard(config('content.panel.guard', 'web'))
            ->brandName('Ratgeber')
            ->favicon(asset('images/favicon.ico'))
            ->colors([
                // Deckungsgleich mit --color-content-* aus resources/css/content/theme.css.
                'primary' => [
                    50 => '238, 251, 248',
                    100 => '211, 245, 238',
                    200 => '169, 235, 224',
                    300 => '114, 219, 205',
                    400 => '60, 194, 179',
                    500 => '23, 166, 152',
                    600 => '13, 133, 123',
                    700 => '13, 106, 99',
                    800 => '16, 84, 80',
                    900 => '17, 70, 67',
                    950 => '3, 41, 39',
                ],
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'gray' => Color::Stone,
            ])
            // Die Token in theme.css sind auf hellen Grund gerechnet; ein
            // Dunkelmodus muesste eigene Statusfarben bekommen (#30).
            ->darkMode(false)
            ->viteTheme('resources/css/content/theme.css')
            // Themen, Kategorien, Import, Gliederung bestaetigen (#15). Leben
            // unter app/Guide und werden deshalb ausdruecklich registriert;
            // Kategorien vor Themen, damit /themen/kategorien vor
            // /themen/{record} steht.
            ->resources([
                CategoryResource::class,
                TopicResource::class,
                // Pruefung, Prompts der Einstellungen (#16).
                ReviewRunResource::class,
                PromptTemplateResource::class,
            ])
            ->pages([
                ImportWizard::class,
                ConfirmOutlines::class,
                LegacyOverlaps::class,
                // Heute (Startseite), Verlauf, Kosten, Einstellungen (#16).
                DailyRunMonitor::class,
                // Verlauf › Laeufe und Einstellungen › Tageslauf (#33).
                RunHistory::class,
                DailyRunSettings::class,
                ArticleVersions::class,
                Costs::class,
                TenantGuideSettings::class,
            ])
            ->discoverResources(in: app_path('Filament/Content/Resources'), for: 'App\\Filament\\Content\\Resources')
            ->discoverPages(in: app_path('Filament/Content/Pages'), for: 'App\\Filament\\Content\\Pages')
            ->discoverWidgets(in: app_path('Filament/Content/Widgets'), for: 'App\\Filament\\Content\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Erst nach der Anmeldung: die Portalauswahl wird gegen die
                // Zuordnung des angemeldeten Kontos geprueft.
                InitializeContentTenant::class,
            ])
            // Einzeltasten der Pruefung abschaltbar (WCAG 2.1.4, #33): Schalter
            // im Nutzermenue, Wahl je Browser im Cookie (ReviewShortcuts).
            ->authenticatedRoutes(function (): void {
                Route::post('tastenkuerzel', ToggleReviewShortcuts::class)->name('review-shortcuts.toggle');
            })
            ->userMenuItems([
                Action::make('reviewShortcuts')
                    ->label(fn (): string => ReviewShortcuts::enabled() ? __('Einzeltasten abschalten') : __('Einzeltasten einschalten'))
                    ->icon('heroicon-o-command-line')
                    ->url(fn (): string => route('filament.'.config('content.panel.id', 'content').'.review-shortcuts.toggle'))
                    ->postToUrl(),
            ])
            // Globaler Portal-Umschalter in der Kopfzeile.
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('@livewire(\'content.tenant-switcher\')'),
            )
            ->sidebarCollapsibleOnDesktop();
    }

    public function boot(): void
    {
        // Benannte Statusfarben und die Marke (#30). Ohne diese Registrierung
        // faende ->color('status-review') bzw. ->color('content') keine Skala.
        FilamentColor::register(config('content.colors', []));

        // Prompt-Editor mit Platzhalterhervorhebung (#54). Eigener
        // CodeMirror-Bau: Quelle resources/js/content/prompt-code-editor.js,
        // gebaut mit `npm run build:content-assets`, veroeffentlicht mit
        // `php artisan filament:assets`. Wird erst beim Oeffnen des Editors
        // nachgeladen (x-load), nicht auf jeder Panel-Seite.
        FilamentAsset::register([
            AlpineComponent::make(
                'prompt-code-editor',
                base_path('resources/js/dist/content/prompt-code-editor.js'),
            ),
        ]);

        Livewire::component('content.tenant-switcher', TenantSwitcher::class);

        // Gliederungs-Editor im Thema-Detail (#15). Eigene Komponente, damit
        // das Polling des Laufbereichs die Bearbeitung nicht zuruecksetzt.
        Livewire::component('content.guide.outline-editor', OutlineEditor::class);

        // Themen und Kategorien aller Portale; ein Stand je Anfrage (#15).
        $this->app->scoped(TopicDirectory::class);
    }
}
