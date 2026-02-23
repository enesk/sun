<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RoleTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDeleteModal = false;
    public ?int $deletingRoleId = null;
    public string $deletingRoleName = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function confirmDelete(int $id, string $name): void
    {
        $this->deletingRoleId = $id;
        $this->deletingRoleName = $name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingRoleId = null;
        $this->deletingRoleName = '';
    }

    public function deleteRole(): void
    {
        if (! $this->deletingRoleId) {
            return;
        }

        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, Auth::user(), TenancyPermissionConstants::PERMISSION_DELETE_ROLES)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung.');
            $this->cancelDelete();
            return;
        }

        $role = Role::where('id', $this->deletingRoleId)
            ->where('tenant_id', $tenant->id)
            ->where('is_tenant_role', true)
            ->first();

        if (! $role) {
            $this->dispatch('toast', type: 'error', message: 'Rolle nicht gefunden.');
            $this->cancelDelete();
            return;
        }

        // Check if role is in use
        if ($role->users()->count() > 0) {
            $this->dispatch('toast', type: 'error', message: "Die Rolle \"{$role->name}\" wird noch von Benutzern verwendet und kann nicht gelöscht werden.");
            $this->cancelDelete();
            return;
        }

        $role->delete();
        $this->dispatch('toast', type: 'success', message: "Rolle \"{$this->deletingRoleName}\" wurde gelöscht.");
        $this->cancelDelete();
    }

    public function render()
    {
        $tenant = tenant();

        $query = Role::where(function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)
                ->orWhereNull('tenant_id');
        })
            ->where('is_tenant_role', true)
            ->withCount('users');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy('name');

        $roles = $query->paginate(20);

        // Enrich: can_delete only for tenant-specific roles with no users
        $roles->getCollection()->transform(function ($role) use ($tenant) {
            $role->can_delete = $role->tenant_id === $tenant->id && $role->users_count === 0;
            $role->is_global = $role->tenant_id === null;
            return $role;
        });

        return view('livewire.verwaltung.role-table', compact('roles'));
    }
}
