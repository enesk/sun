<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Permission;
use App\Models\Role;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RoleForm extends Component
{
    public ?int $roleId = null;
    public string $name = '';
    public array $selectedPermissions = [];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'selectedPermissions' => 'array',
    ];

    protected array $messages = [
        'name.required' => 'Der Rollenname ist erforderlich.',
    ];

    public function mount(?int $roleId = null): void
    {
        $this->roleId = $roleId;

        if ($roleId) {
            $role = Role::where('id', $roleId)
                ->where(function ($q) {
                    $q->where('tenant_id', tenant()->id)
                        ->orWhereNull('tenant_id');
                })
                ->where('is_tenant_role', true)
                ->firstOrFail();

            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        }
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);
        $requiredPermission = $this->roleId
            ? TenancyPermissionConstants::PERMISSION_UPDATE_ROLES
            : TenancyPermissionConstants::PERMISSION_CREATE_ROLES;

        if (! $permissionService->tenantUserHasPermissionTo($tenant, Auth::user(), $requiredPermission)) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        if ($this->roleId) {
            // Update
            $role = Role::where('id', $this->roleId)
                ->where(function ($q) use ($tenant) {
                    $q->where('tenant_id', $tenant->id)
                        ->orWhereNull('tenant_id');
                })
                ->where('is_tenant_role', true)
                ->firstOrFail();

            // Only allow name change for tenant-specific roles
            if ($role->tenant_id === $tenant->id) {
                $role->update(['name' => $this->name]);
            }
        } else {
            // Create
            $role = Role::create([
                'name' => $this->name,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
                'is_tenant_role' => true,
            ]);
        }

        // Sync permissions
        $permissionIds = array_filter(array_map('intval', $this->selectedPermissions));
        $permissions = Permission::whereIn('id', $permissionIds)
            ->where('name', 'like', TenancyPermissionConstants::TENANCY_PERMISSION_PREFIX . '%')
            ->get();
        $role->syncPermissions($permissions);

        $action = $this->roleId ? 'aktualisiert' : 'erstellt';
        session()->flash('success', "Rolle \"{$this->name}\" wurde {$action}.");

        $this->redirect(route('verwaltung.roles.index'));
    }

    public function render()
    {
        // Only tenancy permissions are assignable
        $availablePermissions = Permission::where('name', 'like', TenancyPermissionConstants::TENANCY_PERMISSION_PREFIX . '%')
            ->orderBy('name')
            ->get()
            ->map(function ($perm) {
                // Friendly display name
                $perm->display_name = str_replace('tenancy: ', '', $perm->name);
                $perm->display_name = ucfirst($perm->display_name);
                return $perm;
            });

        // Group by category
        $permissionGroups = $availablePermissions->groupBy(function ($perm) {
            $name = str_replace('tenancy: ', '', $perm->name);
            $parts = explode(' ', $name);
            return ucfirst(end($parts)); // Group by last word: subscriptions, orders, etc.
        });

        $isGlobalRole = $this->roleId
            ? Role::find($this->roleId)?->tenant_id === null
            : false;

        return view('livewire.verwaltung.role-form', compact('permissionGroups', 'isGlobalRole'));
    }
}
