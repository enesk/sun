<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Role;
use App\Models\Team;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class TeamForm extends Component
{
    public ?string $teamUuid = null;
    public string $name = '';
    public array $selectedRoles = [];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'selectedRoles' => 'array',
    ];

    protected array $messages = [
        'name.required' => 'Der Teamname ist erforderlich.',
    ];

    public function mount(?string $teamUuid = null): void
    {
        $this->teamUuid = $teamUuid;

        if ($teamUuid) {
            $team = Team::where('uuid', $teamUuid)
                ->where('tenant_id', tenant()->id)
                ->firstOrFail();

            $this->name = $team->name;
            $this->selectedRoles = $team->roles->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        }
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, Auth::user(), TenancyPermissionConstants::PERMISSION_MANAGE_TEAM)) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        if ($this->teamUuid) {
            // Update
            $team = Team::where('uuid', $this->teamUuid)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $team->update(['name' => $this->name]);
        } else {
            // Create
            $team = Team::create([
                'name' => $this->name,
                'tenant_id' => $tenant->id,
                'uuid' => Str::uuid()->toString(),
            ]);
        }

        // Sync roles
        $roleIds = array_filter(array_map('intval', $this->selectedRoles));
        $team->syncRoles(Role::whereIn('id', $roleIds)->get());

        $action = $this->teamUuid ? 'aktualisiert' : 'erstellt';
        session()->flash('success', "Team \"{$this->name}\" wurde {$action}.");

        $this->redirect(route('verwaltung.teams.index'));
    }

    public function render()
    {
        $tenant = tenant();

        // Available roles (tenant-specific + global tenant roles)
        $availableRoles = Role::where(function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)
                ->orWhereNull('tenant_id');
        })
            ->where('is_tenant_role', true)
            ->orderBy('name')
            ->get();

        return view('livewire.verwaltung.team-form', compact('availableRoles'));
    }
}
