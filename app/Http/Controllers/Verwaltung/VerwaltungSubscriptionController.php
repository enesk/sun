<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\SubscriptionService;
use App\Services\TenantPermissionService;

class VerwaltungSubscriptionController extends VerwaltungBaseController
{
    public function __construct(
        TenantPermissionService $permissionService,
        private SubscriptionService $subscriptionService,
    ) {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_SUBSCRIPTIONS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Abonnements'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.subscriptions.index', compact('navigationItems'));
    }

    public function show(string $uuid)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_SUBSCRIPTIONS);

        $subscription = $this->subscriptionService->findByUuidOrFail($uuid);

        // Verify tenant ownership
        if ($subscription->tenant_id !== $this->tenant()->id) {
            abort(403);
        }

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Abonnements', 'url' => route('verwaltung.subscriptions.index')],
            ['label' => $subscription->plan->name ?? 'Details'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.subscriptions.show', compact('navigationItems', 'subscription'));
    }

    public function cancel(string $uuid)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_SUBSCRIPTIONS);

        $subscription = $this->subscriptionService->findByUuidOrFail($uuid);

        if ($subscription->tenant_id !== $this->tenant()->id) {
            abort(403);
        }

        if (! $this->subscriptionService->canCancelSubscription($subscription)) {
            return redirect()->route('verwaltung.subscriptions.index')
                ->with('error', 'Dieses Abonnement kann nicht gekündigt werden.');
        }

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Abonnements', 'url' => route('verwaltung.subscriptions.index')],
            ['label' => 'Kündigen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.subscriptions.cancel', compact('navigationItems', 'subscription'));
    }
}
