<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UserTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    public bool $showRemoveModal = false;
    public ?int $removingUserId = null;
    public string $removingUserName = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterRole' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterRole = '';
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function changeRole(int $userId, string $roleName): void
    {
        $tenant = tenant();
        $user = Auth::user();
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_TEAM)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung.');
            return;
        }

        if ($userId === $user->id) {
            $this->dispatch('toast', type: 'error', message: 'Du kannst deine eigene Rolle nicht ändern.');
            return;
        }

        $targetUser = $tenant->users()->where('users.id', $userId)->first();
        if (! $targetUser) {
            $this->dispatch('toast', type: 'error', message: 'Benutzer nicht gefunden.');
            return;
        }

        $permissionService->assignTenantUserRole($tenant, $targetUser, $roleName);
        $this->dispatch('toast', type: 'success', message: "Rolle von {$targetUser->name} wurde auf \"{$roleName}\" geändert.");
    }

    public function confirmRemove(int $userId, string $userName): void
    {
        $this->removingUserId = $userId;
        $this->removingUserName = $userName;
        $this->showRemoveModal = true;
    }

    public function cancelRemove(): void
    {
        $this->showRemoveModal = false;
        $this->removingUserId = null;
        $this->removingUserName = '';
    }

    public function removeUser(): void
    {
        if (! $this->removingUserId) {
            return;
        }

        $tenant = tenant();
        $user = Auth::user();
        $tenantService = app(TenantService::class);
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_MANAGE_TEAM)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung.');
            $this->cancelRemove();
            return;
        }

        $targetUser = $tenant->users()->where('users.id', $this->removingUserId)->first();
        if (! $targetUser) {
            $this->dispatch('toast', type: 'error', message: 'Benutzer nicht gefunden.');
            $this->cancelRemove();
            return;
        }

        if (! $tenantService->canRemoveUser($tenant, $targetUser)) {
            $this->dispatch('toast', type: 'error', message: 'Dieser Benutzer kann nicht entfernt werden (mindestens ein Mitglied muss bleiben).');
            $this->cancelRemove();
            return;
        }

        $tenantService->removeUser($tenant, $targetUser);
        $this->dispatch('toast', type: 'success', message: "{$targetUser->name} wurde aus dem Workspace entfernt.");
        $this->cancelRemove();
    }

    public function render()
    {
        $tenant = tenant();
        $currentUser = Auth::user();
        $permissionService = app(TenantPermissionService::class);

        $query = $tenant->users()->with([]);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Sort
        $allowedSorts = ['name', 'email', 'last_seen_at'];
        $sortBy = in_array($this->sortBy, $allowedSorts) ? $this->sortBy : 'name';
        $query->orderBy($sortBy, $this->sortDir);

        $users = $query->paginate(20);

        // Enrich with role info
        $users->getCollection()->transform(function ($user) use ($tenant, $permissionService, $currentUser) {
            $roles = $permissionService->getTenantUserRoles($tenant, $user);
            $user->tenant_roles = $roles;
            $user->primary_role = $roles[0] ?? 'user';
            $user->is_current_user = $user->id === $currentUser->id;
            return $user;
        });

        // Filter by role (after enrichment since roles come from pivot)
        if ($this->filterRole) {
            $filtered = $users->getCollection()->filter(fn ($user) => in_array($this->filterRole, $user->tenant_roles));
            $users->setCollection($filtered);
        }

        // Available roles for assignment
        $availableRoles = $permissionService->getAllAvailableTenantRolesForDisplay($tenant);

        return view('livewire.verwaltung.user-table', compact('users', 'availableRoles'));
    }
}
