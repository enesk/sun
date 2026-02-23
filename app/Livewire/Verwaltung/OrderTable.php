<?php

namespace App\Livewire\Verwaltung;

use App\Constants\OrderStatus;
use App\Mapper\OrderStatusMapper;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class OrderTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $sortBy = 'updated_at';
    public string $sortDir = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'sortBy' => ['except' => 'updated_at'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'desc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $tenant = tenant();
        $statusMapper = app(OrderStatusMapper::class);

        $query = Order::where('tenant_id', $tenant->id)
            ->with(['currency', 'items.oneTimeProduct', 'transactions.currency', 'discounts', 'paymentProvider']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('uuid', 'like', '%' . $this->search . '%')
                    ->orWhereHas('items.oneTimeProduct', function ($q2) {
                        $q2->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // Status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Sort
        $allowedSorts = ['updated_at', 'total_amount', 'status'];
        $sortBy = in_array($this->sortBy, $allowedSorts) ? $this->sortBy : 'updated_at';
        $query->orderBy($sortBy, $this->sortDir);

        $orders = $query->paginate(15);

        // Enrich
        $orders->getCollection()->transform(function (Order $order) use ($statusMapper) {
            // Use transaction amount if available (more accurate after provider processing)
            if ($order->transactions->isNotEmpty()) {
                $tx = $order->transactions->first();
                $order->formatted_amount = money($tx->amount, $tx->currency->code);
            } else {
                $order->formatted_amount = money($order->total_amount_after_discount, $order->currency->code);
            }

            $order->status_label = $statusMapper->mapForDisplay($order->status);
            $order->status_color = $statusMapper->mapColor($order->status);

            return $order;
        });

        $statusOptions = [
            OrderStatus::SUCCESS->value => $statusMapper->mapForDisplay(OrderStatus::SUCCESS->value),
            OrderStatus::NEW->value => $statusMapper->mapForDisplay(OrderStatus::NEW->value),
            OrderStatus::PENDING->value => $statusMapper->mapForDisplay(OrderStatus::PENDING->value),
            OrderStatus::REFUNDED->value => $statusMapper->mapForDisplay(OrderStatus::REFUNDED->value),
            OrderStatus::FAILED->value => $statusMapper->mapForDisplay(OrderStatus::FAILED->value),
        ];

        return view('livewire.verwaltung.order-table', compact('orders', 'statusOptions'));
    }
}
