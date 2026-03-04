<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Http\Controllers\Controller;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;

/**
 * Base controller for all Verwaltung (Dashboard) controllers.
 *
 * Provides:
 * - Tenant/user resolution
 * - Permission checking helpers
 * - Breadcrumb management
 * - Navigation data for the sidebar
 */
abstract class VerwaltungBaseController extends Controller
{
    protected array $breadcrumbs = [];

    public function __construct(
        protected TenantPermissionService $permissionService,
    ) {}

    /**
     * Get the current tenant.
     */
    protected function tenant()
    {
        return tenant();
    }

    /**
     * Check if the current user has a specific tenant permission.
     */
    protected function hasPermission(string $permission): bool
    {
        $user = Auth::user();
        $tenant = $this->tenant();

        if ($user->isAdmin()) {
            return true;
        }

        return $this->permissionService->tenantUserHasPermissionTo($tenant, $user, $permission);
    }

    /**
     * Abort if the user doesn't have the required permission.
     */
    protected function requirePermission(string $permission): void
    {
        if (! $this->hasPermission($permission)) {
            abort(403, 'Keine Berechtigung für diese Aktion.');
        }
    }

    /**
     * Set breadcrumbs for the current page.
     *
     * @param array<array{label: string, url?: string}> $crumbs
     */
    protected function setBreadcrumbs(array $crumbs): void
    {
        $this->breadcrumbs = $crumbs;
        view()->share('breadcrumbs', $this->breadcrumbs);
    }

    /**
     * Build the sidebar navigation structure.
     * Shared with the layout via view composer in the base view.
     */
    protected function getNavigationItems(): array
    {
        $permissions = view()->shared('dashboardPermissions', []);

        return [
            [
                'group' => null,
                'items' => [
                    [
                        'route' => 'verwaltung.index',
                        'label' => 'Übersicht',
                        'icon' => 'squares-2x2',
                        'visible' => true,
                    ],
                ],
            ],
            [
                'group' => 'Portal',
                'items' => [
                    [
                        'route' => 'verwaltung.companies.index',
                        'label' => 'Firmen',
                        'icon' => 'building-office',
                        'visible' => $permissions['manage_companies'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.reviews.index',
                        'label' => 'Bewertungen',
                        'icon' => 'star',
                        'visible' => $permissions['manage_reviews'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.edit-suggestions.index',
                        'label' => 'Änderungsvorschläge',
                        'icon' => 'pencil-square',
                        'visible' => $permissions['manage_reviews'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.claims.index',
                        'label' => 'Claim-Anträge',
                        'icon' => 'shield-check',
                        'visible' => $permissions['manage_claims'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.categories.index',
                        'label' => 'Kategorien',
                        'icon' => 'tag',
                        'visible' => $permissions['manage_categories'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.cities.index',
                        'label' => 'Städte',
                        'icon' => 'map-pin',
                        'visible' => $permissions['manage_cities'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.statistics.index',
                        'label' => 'Statistiken',
                        'icon' => 'chart-bar',
                        'visible' => $permissions['manage_companies'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.jobs.index',
                        'label' => 'Stellenanzeigen',
                        'icon' => 'briefcase',
                        'visible' => $permissions['manage_companies'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.faqs.index',
                        'label' => 'FAQ',
                        'icon' => 'question-mark-circle',
                        'visible' => $permissions['update_settings'] ?? false,
                    ],
                ],
            ],
            [
                'group' => 'Finanzen',
                'items' => [
                    [
                        'route' => 'verwaltung.subscriptions.index',
                        'label' => 'Abonnements',
                        'icon' => 'credit-card',
                        'visible' => $permissions['view_subscriptions'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.transactions.index',
                        'label' => 'Zahlungen',
                        'icon' => 'banknotes',
                        'visible' => $permissions['view_transactions'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.orders.index',
                        'label' => 'Bestellungen',
                        'icon' => 'shopping-bag',
                        'visible' => $permissions['view_orders'] ?? false,
                    ],
                ],
            ],
            [
                'group' => 'Team',
                'items' => [
                    [
                        'route' => 'verwaltung.users.index',
                        'label' => 'Benutzer',
                        'icon' => 'users',
                        'visible' => $permissions['manage_team'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.teams.index',
                        'label' => 'Teams',
                        'icon' => 'user-group',
                        'visible' => $permissions['manage_team'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.roles.index',
                        'label' => 'Rollen',
                        'icon' => 'shield-check',
                        'visible' => $permissions['manage_roles'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.invitations.index',
                        'label' => 'Einladungen',
                        'icon' => 'envelope',
                        'visible' => $permissions['invite_members'] ?? false,
                    ],
                ],
            ],
            [
                'group' => 'Einstellungen',
                'items' => [
                    [
                        'route' => 'verwaltung.settings.general',
                        'label' => 'Allgemein',
                        'icon' => 'cog-6-tooth',
                        'visible' => $permissions['update_settings'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.settings.theme',
                        'label' => 'Theme',
                        'icon' => 'paint-brush',
                        'visible' => $permissions['update_settings'] ?? false,
                    ],
                    [
                        'route' => 'verwaltung.settings.legal',
                        'label' => 'Rechtliches',
                        'icon' => 'document-text',
                        'visible' => $permissions['update_settings'] ?? false,
                    ],
                ],
            ],
            [
                'group' => null,
                'items' => [
                    [
                        'route' => 'verwaltung.profile',
                        'label' => 'Profil',
                        'icon' => 'user-circle',
                        'visible' => true,
                    ],
                    [
                        'route' => 'verwaltung.referrals.index',
                        'label' => 'Empfehlungen',
                        'icon' => 'gift',
                        'visible' => true,
                    ],
                ],
            ],
        ];
    }
}
