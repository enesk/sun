<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Constants\CompanyVerificationDocumentType;
use App\Constants\CompanyVerificationStatus;
use App\Enums\PremiumFeature;
use App\Mail\Premium\VerificationRejectedMail;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyVerification;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Verifiziert-Badge (#11): Nachweis einreichen, pruefen, Dateien aufraeumen.
 *
 * Freigabe setzt companies.verified_at. Ein Downgrade loescht verified_at
 * nicht; ob das Badge erscheint, entscheidet die View ueber das Feature
 * verified_badge (CompanyEntitlementService).
 */
class CompanyVerificationService
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function openVerification(Company $company): ?CompanyVerification
    {
        return $this->verificationsOf($company)->pending()->latest('id')->first();
    }

    public function latestVerification(Company $company): ?CompanyVerification
    {
        return $this->verificationsOf($company)->latest('id')->first();
    }

    /**
     * @throws RuntimeException ohne Freischaltung oder bei offener Pruefung
     */
    public function submit(Company $company, UploadedFile $file, CompanyVerificationDocumentType $type): CompanyVerification
    {
        if (! $this->entitlements->can($company, PremiumFeature::VerifiedBadge)) {
            throw new RuntimeException('Verifizierung ist fuer diesen Betrieb nicht freigeschaltet.');
        }

        $directory = config('premium.verification.directory')."/{$company->id}";
        $path = $file->store($directory, ['disk' => $this->diskName()]);

        if (! is_string($path)) {
            throw new RuntimeException('Nachweis konnte nicht gespeichert werden.');
        }

        try {
            return DB::connection($company->getConnectionName())->transaction(function () use ($company, $path, $type): CompanyVerification {
                // Sperre auf den Betrieb: verhindert zwei offene Pruefungen bei Doppelklick
                Company::query()->whereKey($company->getKey())->lockForUpdate()->first();

                if ($this->verificationsOf($company)->pending()->exists()) {
                    throw new RuntimeException('Es laeuft bereits eine Pruefung.');
                }

                return CompanyVerification::query()->create([
                    'company_id' => $company->getKey(),
                    'document_path' => $path,
                    'document_type' => $type,
                    'status' => CompanyVerificationStatus::PENDING,
                ]);
            });
        } catch (\Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }
    }

    public function approve(CompanyVerification $verification, User $reviewer): void
    {
        $this->assertPending($verification);

        DB::connection($verification->getConnectionName())->transaction(function () use ($verification, $reviewer): void {
            $verification->update([
                'status' => CompanyVerificationStatus::APPROVED,
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            // verified_at ist bewusst nicht fillable
            $verification->company?->forceFill(['verified_at' => now()])->save();
        });
    }

    public function reject(CompanyVerification $verification, User $reviewer, string $reason): void
    {
        $this->assertPending($verification);

        $verification->update([
            'status' => CompanyVerificationStatus::REJECTED,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $company = $verification->company;
        /** @var User|null $owner */
        $owner = $company?->owner;
        $recipient = $owner?->email ?: $company?->email;

        if ($company === null || ! is_string($recipient) || $recipient === '') {
            return;
        }

        Mail::to($recipient)->queue(new VerificationRejectedMail(
            companyName: (string) $company->name,
            recipientName: $owner?->name,
            reason: $reason,
            retryUrl: url('/firmenprofil/einstellungen#verifizierung'),
        ));
    }

    /**
     * Signierter, kurzlebiger Link auf den Nachweis (nur fuer Admins).
     */
    public function documentUrl(CompanyVerification $verification): ?string
    {
        if (! $verification->hasDocument()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'portal.verifications.document',
            now()->addMinutes((int) config('premium.verification.document_link_ttl_minutes', 30)),
            ['verification' => $verification->getKey()],
        );
    }

    /**
     * Loescht Nachweise, deren Entscheidung mindestens $days Tage zurueckliegt.
     */
    public function purgeDocuments(int $days, bool $dryRun = false): int
    {
        $cutoff = Carbon::now()->subDays($days);
        $count = 0;

        CompanyVerification::query()
            ->whereNull('document_purged_at')
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<=', $cutoff)
            ->where('status', '!=', CompanyVerificationStatus::PENDING)
            ->chunkById(200, function ($verifications) use ($dryRun, &$count): void {
                foreach ($verifications as $verification) {
                    $count++;

                    if ($dryRun) {
                        continue;
                    }

                    $this->disk()->delete($verification->document_path);
                    $verification->update(['document_purged_at' => now()]);
                }
            });

        return $count;
    }

    /**
     * @return Builder<CompanyVerification>
     */
    private function verificationsOf(Company $company): Builder
    {
        return CompanyVerification::query()->where('company_id', $company->getKey());
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('premium.verification.disk', 'private');
    }

    private function assertPending(CompanyVerification $verification): void
    {
        if (! $verification->isPending()) {
            throw new RuntimeException('Die Pruefung ist bereits entschieden.');
        }
    }
}
