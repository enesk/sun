<?php

namespace App\Livewire\Verwaltung;

use App\Constants\InvitationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Models\Invitation;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class InvitationTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';

    public bool $showRevokeModal = false;
    public ?int $revokingId = null;
    public string $revokingEmail = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
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

    public function confirmRevoke(int $id, string $email): void
    {
        $this->revokingId = $id;
        $this->revokingEmail = $email;
        $this->showRevokeModal = true;
    }

    public function cancelRevoke(): void
    {
        $this->showRevokeModal = false;
        $this->revokingId = null;
        $this->revokingEmail = '';
    }

    public function revokeInvitation(): void
    {
        if (! $this->revokingId) {
            return;
        }

        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, Auth::user(), TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung.');
            $this->cancelRevoke();
            return;
        }

        $invitation = Invitation::where('id', $this->revokingId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $invitation) {
            $this->dispatch('toast', type: 'error', message: 'Einladung nicht gefunden.');
            $this->cancelRevoke();
            return;
        }

        $invitation->delete();
        $this->dispatch('toast', type: 'success', message: "Einladung an {$this->revokingEmail} wurde widerrufen.");
        $this->cancelRevoke();
    }

    public function render()
    {
        $tenant = tenant();

        $query = Invitation::where('tenant_id', $tenant->id)
            ->with(['user', 'team']);

        // Search
        if ($this->search) {
            $query->where('email', 'like', '%' . $this->search . '%');
        }

        // Status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderByDesc('created_at');

        $invitations = $query->paginate(20);

        // Enrich
        $invitations->getCollection()->transform(function ($invitation) {
            $invitation->is_expired = $invitation->expires_at && $invitation->expires_at->isPast();
            $invitation->status_label = match ($invitation->status) {
                InvitationStatus::PENDING->value => $invitation->is_expired ? 'Abgelaufen' : 'Offen',
                InvitationStatus::ACCEPTED->value => 'Angenommen',
                InvitationStatus::REJECTED->value => 'Abgelehnt',
                default => $invitation->status,
            };
            $invitation->status_color = match ($invitation->status) {
                InvitationStatus::PENDING->value => $invitation->is_expired ? 'warning' : 'info',
                InvitationStatus::ACCEPTED->value => 'success',
                InvitationStatus::REJECTED->value => 'error',
                default => 'warning',
            };
            $invitation->can_revoke = $invitation->status === InvitationStatus::PENDING->value && ! $invitation->is_expired;
            return $invitation;
        });

        $statusOptions = [
            InvitationStatus::PENDING->value => 'Offen',
            InvitationStatus::ACCEPTED->value => 'Angenommen',
            InvitationStatus::REJECTED->value => 'Abgelehnt',
        ];

        return view('livewire.verwaltung.invitation-table', compact('invitations', 'statusOptions'));
    }
}
