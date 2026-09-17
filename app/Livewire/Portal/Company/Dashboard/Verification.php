<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Constants\CompanyVerificationDocumentType;
use App\Constants\CompanyVerificationStatus;
use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Nachweis-Upload fuer das Verifiziert-Badge im Betriebsbereich (#11).
 * Eingebettet in /firmenprofil/einstellungen; Upload nur mit Feature
 * verified_badge und nur, solange keine Pruefung offen ist.
 */
class Verification extends Component
{
    use HasEntitlements;
    use WithFileUploads;

    #[Locked]
    public int $companyId = 0;

    /** @var TemporaryUploadedFile|null */
    public $document = null;

    public string $documentType = '';

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
    }

    public function submit(CompanyVerificationService $service): void
    {
        $company = $this->company();

        if (! $this->can(PremiumFeature::VerifiedBadge)) {
            return;
        }

        if ($service->openVerification($company) !== null) {
            $this->addError('document', __('portal.owner.verification.already_pending'));

            return;
        }

        $rateKey = "company-verification-upload:{$company->id}";

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('document', __('portal.owner.verification.failed'));

            return;
        }

        $this->validate([
            'document' => [
                'required',
                'file',
                'max:'.(int) config('premium.verification.max_kb', 10240),
                'mimes:'.implode(',', (array) config('premium.verification.mimes')),
            ],
            'documentType' => ['required', Rule::enum(CompanyVerificationDocumentType::class)],
        ], [
            'document.required' => __('portal.owner.verification.errors.file_required'),
            'document.file' => __('portal.owner.verification.errors.file_required'),
            'document.mimes' => __('portal.owner.verification.errors.file_mimes'),
            'document.max' => __('portal.owner.verification.errors.file_max'),
            'documentType.required' => __('portal.owner.verification.errors.type_required'),
            'documentType.enum' => __('portal.owner.verification.errors.type_required'),
        ]);

        RateLimiter::hit($rateKey, 3600);

        try {
            $service->submit($company, $this->document, CompanyVerificationDocumentType::from($this->documentType));
        } catch (\Throwable $e) {
            Log::warning('Verifizierungsnachweis nicht gespeichert', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
            $this->addError('document', __('portal.owner.verification.failed'));

            return;
        }

        $this->reset('document', 'documentType');
        $this->dispatch('toast', type: 'success', message: __('portal.owner.verification.success'));
    }

    public function render(CompanyVerificationService $service): View
    {
        $company = $this->company();
        $latest = $service->latestVerification($company);

        return view('livewire.portal.company.dashboard.verification', [
            'company' => $company,
            'allowed' => $this->can(PremiumFeature::VerifiedBadge),
            'pending' => $latest?->isPending() ? $latest : null,
            'rejected' => $latest !== null && $latest->status === CompanyVerificationStatus::REJECTED ? $latest : null,
            'documentTypes' => CompanyVerificationDocumentType::cases(),
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    private function company(): Company
    {
        return $this->company ??= Company::ownedBy((int) Auth::id())
            ->when($this->companyId > 0, fn ($query) => $query->whereKey($this->companyId))
            ->firstOrFail();
    }
}
