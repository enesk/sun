<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Models\Team;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TeamTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDeleteModal = false;
    public ?string $deletingTeamUuid = null;
    public string $deletingTeamName = '';

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

    public function confirmDelete(string $uuid, string $name): void
    {
        $this->deletingTeamUuid = $uuid;
        $this->deletingTeamName = $name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingTeamUuid = null;
        $this->deletingTeamName = '';
    }

    public function deleteTeam(): void
    {
        if (! $this->deletingTeamUuid) {
            return;
        }

        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, Auth::user(), TenancyPermissionConstants::PERMISSION_MANAGE_TEAM)) {
            $this->dispatch('toast', type: 'error', message: 'Keine Berechtigung.');
            $this->cancelDelete();
            return;
        }

        $team = Team::where('uuid', $this->deletingTeamUuid)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $team) {
            $this->dispatch('toast', type: 'error', message: 'Team nicht gefunden.');
            $this->cancelDelete();
            return;
        }

        // Detach all users first
        $team->tenantUsers()->detach();
        $team->delete();

        $this->dispatch('toast', type: 'success', message: "Team \"{$this->deletingTeamName}\" wurde gelöscht.");
        $this->cancelDelete();
    }

    public function render()
    {
        $tenant = tenant();

        $query = Team::where('tenant_id', $tenant->id)
            ->withCount('tenantUsers');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy('name');

        $teams = $query->paginate(20);

        return view('livewire.verwaltung.team-table', compact('teams'));
    }
}
