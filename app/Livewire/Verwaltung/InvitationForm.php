<?php

namespace App\Livewire\Verwaltung;

use App\Constants\InvitationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Models\Invitation;
use App\Models\Team;
use App\Services\TenantPermissionService;
use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class InvitationForm extends Component
{
    public string $emails = '';
    public string $role = '';
    public string $teamId = '';

    protected array $messages = [
        'emails.required' => 'Mindestens eine E-Mail-Adresse ist erforderlich.',
        'role.required' => 'Bitte wähle eine Rolle aus.',
    ];

    public function save(): void
    {
        $this->validate([
            'emails' => 'required|string',
            'role' => 'required|string',
            'teamId' => 'nullable|string',
        ]);

        $tenant = tenant();
        $user = Auth::user();
        $permissionService = app(TenantPermissionService::class);
        $tenantService = app(TenantService::class);

        if (! $permissionService->tenantUserHasPermissionTo($tenant, $user, TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS)) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        // Parse emails (comma or newline separated)
        $emailList = collect(preg_split('/[,\n\r]+/', $this->emails))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emailList->isEmpty()) {
            $this->addError('emails', 'Keine gültige E-Mail-Adresse gefunden.');
            return;
        }

        // Check subscription limits
        if (! $tenantService->canInviteUsers($tenant)) {
            session()->flash('error', 'Das maximale Mitgliederlimit ist erreicht. Upgrade dein Abonnement um weitere Mitglieder einzuladen.');
            return;
        }

        $created = 0;
        $skipped = [];

        foreach ($emailList as $email) {
            // Check for pending invitation
            $existingInvite = Invitation::where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->where('status', InvitationStatus::PENDING->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($existingInvite) {
                $skipped[] = "{$email} (bereits eingeladen)";
                continue;
            }

            // Check if already a member
            $alreadyMember = $tenant->users()->where('email', $email)->exists();
            if ($alreadyMember) {
                $skipped[] = "{$email} (bereits Mitglied)";
                continue;
            }

            $invitation = Invitation::create([
                'uuid' => Str::uuid()->toString(),
                'email' => $email,
                'token' => Str::random(60),
                'expires_at' => now()->addDays(7),
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'status' => InvitationStatus::PENDING->value,
                'role' => $this->role,
                'team_id' => $this->teamId ?: null,
            ]);

            $tenantService->handleAfterInvitationCreated($invitation);
            $created++;
        }

        $message = "{$created} Einladung(en) versendet.";
        if (! empty($skipped)) {
            $message .= ' Übersprungen: ' . implode(', ', $skipped);
        }

        session()->flash('success', $message);
        $this->redirect(route('verwaltung.invitations.index'));
    }

    public function render()
    {
        $tenant = tenant();
        $permissionService = app(TenantPermissionService::class);

        $availableRoles = $permissionService->getAllAvailableTenantRolesForDisplay($tenant);

        $teams = [];
        if (config('app.teams_enabled', false)) {
            $teams = Team::where('tenant_id', $tenant->id)->orderBy('name')->pluck('name', 'id');
        }

        return view('livewire.verwaltung.invitation-form', compact('availableRoles', 'teams'));
    }
}
