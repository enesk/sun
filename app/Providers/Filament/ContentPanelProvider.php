<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Content\Http\Middleware\InitializeContentTenant;
use App\Content\Livewire\ArticleList;
use App\Content\Livewire\EditorialCalendar;
use App\Content\Livewire\PipelineBoard;
use App\Content\Livewire\ReviewQueue;
use App\Content\Livewire\TenantSwitcher;
use App\Content\Models\Central\ContentUser;
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
use Illuminate\Auth\Events\Login;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

/**
 * Content-Panel der Ratgeber-Pipeline (#4).
 *
 * Eigenes Panel, eigener Guard, eigene Benutzertabelle — eine Admin-Session
 * gibt hier keinen Zugriff und umgekehrt (docs/content-pipeline.md, §6).
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
            ->authGuard(config('content.panel.guard', 'content'))
            ->authPasswordBroker(config('content.panel.password_broker', 'content_users'))
            ->brandName('SUN Content')
            ->favicon(asset('images/favicon.ico'))
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
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
                // Zuordnung des Redaktions-Accounts geprueft.
                InitializeContentTenant::class,
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

        // Board, Kalender und Artikelliste der Produktionsansicht (#19, #36).
        // Eigene Komponenten, damit das 15-Sekunden-Polling des Boards nur
        // seinen eigenen Bereich neu rendert und nicht die ganze Seite.
        Livewire::component('content.pipeline-board', PipelineBoard::class);
        Livewire::component('content.editorial-calendar', EditorialCalendar::class);
        Livewire::component('content.article-list', ArticleList::class);

        // Pruef-Queue (#20). Eigene Komponente, damit eine Entscheidung nur
        // die Warteschlange und das Pruefblatt neu rendert — die Vorschau im
        // Rahmen bleibt dabei stehen.
        Livewire::component('content.review-queue', ReviewQueue::class);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== config('content.panel.guard', 'content')) {
                return;
            }

            if ($event->user instanceof ContentUser) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
