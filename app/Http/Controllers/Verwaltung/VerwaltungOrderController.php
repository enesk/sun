<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Order;
use App\Services\TenantPermissionService;

class VerwaltungOrderController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_ORDERS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Bestellungen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.orders.index', compact('navigationItems'));
    }

    public function show(string $uuid)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_VIEW_ORDERS);

        $order = Order::where('uuid', $uuid)
            ->where('tenant_id', $this->tenant()->id)
            ->with(['items.oneTimeProduct', 'currency', 'transactions.currency', 'discounts', 'paymentProvider'])
            ->firstOrFail();

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Bestellungen', 'url' => route('verwaltung.orders.index')],
            ['label' => 'Bestellung #' . substr($order->uuid, 0, 8)],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.orders.show', compact('navigationItems', 'order'));
    }
}
