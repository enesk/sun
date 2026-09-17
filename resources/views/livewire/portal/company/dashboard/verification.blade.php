{{--
    Verifiziert-Badge im Betriebsbereich, Default-/Starter-Theme (#11).
    Logik: App\Livewire\Portal\Company\Dashboard\Verification. Texte: portal.owner.verification.*
--}}
<div id="verifizierung" class="dash-card dash-card-padded">
    <h2 class="dash-card-header-title mb-4 flex items-center gap-2">
        {{ __('portal.owner.verification.title') }}
        @unless($allowed)
            <span class="dash-badge dash-badge-premium">{{ __('portal.owner.edit.locked.badge') }}</span>
        @endunless
    </h2>

    @if($company->verified_at)
        <p class="text-sm"><span class="dash-badge dash-badge-success">✓</span>
            {{ $allowed ? __('portal.owner.verification.verified', ['datum' => $company->verified_at->format('d.m.Y')]) : __('portal.owner.verification.verified_hidden') }}
        </p>
    @elseif(! $allowed)
        <p class="text-sm mb-4" style="color: var(--dash-text-secondary)">{{ __('portal.owner.verification.locked') }}</p>
        <a href="{{ route('portal.owner.premium') }}" class="dash-btn dash-btn-primary dash-btn-sm">{{ __('portal.owner.verification.locked_cta') }}</a>
    @elseif($pending)
        <p class="text-sm"><span class="dash-badge dash-badge-info">{{ \App\Constants\CompanyVerificationStatus::PENDING->label() }}</span>
            {{ __('portal.owner.verification.pending', ['datum' => $pending->created_at->format('d.m.Y')]) }}
        </p>
    @else
        <p class="text-sm mb-4" style="color: var(--dash-text-secondary)">{{ __('portal.owner.verification.intro') }}</p>
        @if($rejected)
            <p class="text-sm mb-4"><span class="dash-badge dash-badge-warning">{{ \App\Constants\CompanyVerificationStatus::REJECTED->label() }}</span>
                {{ __('portal.owner.verification.rejected', ['grund' => $rejected->rejection_reason]) }}
            </p>
        @endif

        <form wire:submit="submit" class="flex flex-col gap-4">
            <div>
                <label for="verification-type" class="block text-sm font-medium mb-1">{{ __('portal.owner.verification.type_label') }}</label>
                <select id="verification-type" wire:model="documentType" class="dash-select w-full">
                    <option value="">–</option>
                    @foreach($documentTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('documentType')<p class="dash-input-error-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="verification-document" class="block text-sm font-medium mb-1">{{ __('portal.owner.verification.file_label') }}</label>
                <input id="verification-document" type="file" wire:model="document" accept=".pdf,.jpg,.jpeg,.png" class="dash-input w-full">
                <p class="dash-input-hint">{{ __('portal.owner.verification.file_hint') }}</p>
                <p wire:loading wire:target="document" class="dash-input-hint">{{ __('portal.owner.verification.uploading') }}</p>
                @error('document')<p class="dash-input-error-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <button type="submit" wire:loading.attr="disabled" wire:target="submit,document" class="dash-btn dash-btn-primary">
                    <span wire:loading.remove wire:target="submit">{{ __('portal.owner.verification.submit') }}</span>
                    <span wire:loading wire:target="submit">{{ __('portal.owner.verification.sending') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
