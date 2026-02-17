<?php

namespace App\Http\Middleware;

use App\Themes\TenantStyleInjector;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that resolves and activates the theme for the current tenant.
 *
 * Must run AFTER tenancy initialization (InitializeTenancyByDomain)
 * so that the tenant is available on the request.
 *
 * Flow:
 * 1. Read active_theme from tenant data (fallback: 'default')
 * 2. Activate theme via ThemeManager → registers view paths via ThemeViewFinder
 * 3. Inject CSS variables from tenant branding into view
 * 4. Share theme data with all views ($activeTheme, $themeOptions, $tenantStyles)
 */
class ResolveTheme
{
    public function __construct(
        private ThemeManager $themeManager,
        private ThemeViewFinder $themeViewFinder,
        private TenantStyleInjector $styleInjector,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            return $next($request);
        }

        // 1. Determine which theme to use
        $themeSlug = $this->themeManager->getTenantTheme($tenant);

        // 2. Resolve theme definitions
        $activeTheme = $this->themeManager->get($themeSlug);
        $defaultTheme = $this->themeManager->get(ThemeManager::DEFAULT_THEME);

        // Fallback to default if requested theme doesn't exist
        if (! $activeTheme) {
            $activeTheme = $defaultTheme;
            $themeSlug = ThemeManager::DEFAULT_THEME;
        }

        if (! $activeTheme) {
            // No themes available at all — let request proceed without theme
            return $next($request);
        }

        // 3. Set view paths via ThemeViewFinder (clean reset per request)
        $this->themeViewFinder->setThemePaths($activeTheme, $defaultTheme);

        // 4. Mark active theme on ThemeManager
        $this->themeManager->activate($themeSlug);

        // 5. Get theme options (merged with defaults from theme.json)
        $themeOptions = $this->themeManager->getTenantThemeOptions($tenant);

        // 6. Generate CSS variables from tenant branding
        $tenantStyles = $this->styleInjector->generateStyleTag($tenant);

        // 7. Share with all views
        View::share('activeTheme', $activeTheme);
        View::share('themeOptions', $themeOptions);
        View::share('tenantStyles', $tenantStyles);
        View::share('currentTenant', $tenant);

        return $next($request);
    }
}
