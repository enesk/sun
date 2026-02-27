<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\ClaimRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Claim-Verifizierung Status-Banner im Dashboard.
 *
 * Zeigt den Status des aktuellen Claim-Requests:
 * - pending: "Verifizierung wird geprüft" + 48h Hinweis
 * - rejected: Ablehnungsgrund + CTA "Neue Dokumente einreichen"
 * - approved/cancelled/none: Banner nicht sichtbar
 */
class ClaimStatusBanner extends Component
{
    public ?ClaimRequest $claimRequest = null;
    public string $companyName = '';
    public string $companySlug = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Neuesten Claim-Request des Users laden (pending oder rejected)
        $this->claimRequest = ClaimRequest::where('user_id', $user->id)
            ->whereIn('status', [ClaimRequest::STATUS_PENDING, ClaimRequest::STATUS_REJECTED])
            ->with('company')
            ->latest()
            ->first();

        if ($this->claimRequest && $this->claimRequest->company) {
            $this->companyName = $this->claimRequest->company->name;
            $this->companySlug = $this->claimRequest->company->slug ?? '';
        }
    }

    public function render()
    {
        return view('livewire.verwaltung.claim-status-banner');
    }
}
