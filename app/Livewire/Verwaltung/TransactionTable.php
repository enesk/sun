<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TransactionStatus;
use App\Mapper\TransactionStatusMapper;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
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

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    public function downloadInvoice(int $transactionId): mixed
    {
        $tenant = tenant();
        $transaction = Transaction::where('id', $transactionId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $invoiceService = app(InvoiceService::class);

        if (! $invoiceService->canGenerateInvoices($transaction)) {
            $this->dispatch('toast', type: 'error', message: 'Für diese Transaktion kann keine Rechnung erstellt werden.');
            return null;
        }

        return redirect()->route('invoice.generate', ['transactionUuid' => $transaction->uuid]);
    }

    public function render()
    {
        $tenant = tenant();
        $statusMapper = app(TransactionStatusMapper::class);
        $invoiceService = app(InvoiceService::class);

        $query = Transaction::where('tenant_id', $tenant->id)
            ->where('amount', '>', 0)
            ->where('status', '!=', TransactionStatus::NOT_STARTED->value)
            ->with(['currency', 'plan', 'subscription.plan', 'order', 'paymentProvider']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('plan', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('subscription.plan', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        // Status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Sort
        $allowedSorts = ['created_at', 'amount', 'status'];
        $sortBy = in_array($this->sortBy, $allowedSorts) ? $this->sortBy : 'created_at';
        $query->orderBy($sortBy, $this->sortDir);

        $transactions = $query->paginate(15);

        // Enrich
        $transactions->getCollection()->transform(function (Transaction $tx) use ($statusMapper, $invoiceService) {
            $tx->formatted_amount = money($tx->amount, $tx->currency->code);
            $tx->status_label = $statusMapper->mapForDisplay($tx->status);
            $tx->status_color = $statusMapper->mapColor($tx->status);
            $tx->can_download_invoice = $invoiceService->canGenerateInvoices($tx);

            // Owner reference
            if ($tx->subscription_id) {
                $tx->owner_label = $tx->subscription?->plan?->name ?? '-';
                $tx->owner_type = 'subscription';
            } elseif ($tx->order_id) {
                $tx->owner_label = __('Bestellung anzeigen');
                $tx->owner_type = 'order';
                $tx->owner_uuid = $tx->order?->uuid;
            } else {
                $tx->owner_label = '-';
                $tx->owner_type = null;
            }

            return $tx;
        });

        $statusOptions = [
            TransactionStatus::SUCCESS->value => $statusMapper->mapForDisplay(TransactionStatus::SUCCESS->value),
            TransactionStatus::PENDING->value => $statusMapper->mapForDisplay(TransactionStatus::PENDING->value),
            TransactionStatus::FAILED->value => $statusMapper->mapForDisplay(TransactionStatus::FAILED->value),
            TransactionStatus::REFUNDED->value => $statusMapper->mapForDisplay(TransactionStatus::REFUNDED->value),
            TransactionStatus::DISPUTED->value => $statusMapper->mapForDisplay(TransactionStatus::DISPUTED->value),
        ];

        return view('livewire.verwaltung.transaction-table', compact('transactions', 'statusOptions'));
    }
}
