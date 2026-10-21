{{--
    Loeschweg des Self-Service-Dialogs (SUN-RQ #4), Theme default (#14).
    Eingebunden aus livewire/portal/profile-request-dialog.

    Hier wird nichts geloescht: der Antrag geht in die Inbox (#7), wird per
    Magic-Link bestaetigt (#6) und laeuft erst nach der Karenzfrist von 48
    Stunden durch die Loeschpipeline (#8/#9). Der Hinweis auf die Frist steht
    deshalb vor dem Knopf und nicht erst in der Bestaetigung.
--}}
<fieldset class="flex flex-col gap-3">
    <legend class="text-sm font-semibold text-base-content mb-1.5">{{ __('portal.profile_request.delete.reason_label') }}</legend>

    <div>
        <label for="pr-delete-reason" class="sr-only">{{ __('portal.profile_request.delete.reason_label') }}</label>
        <select id="pr-delete-reason" wire:model.live="deleteReason" class="input-portal w-full">
            <option value="">{{ __('portal.profile_request.delete.reason_placeholder') }}</option>
            @foreach(\App\Livewire\Portal\ProfileRequestDialog::DELETE_REASONS as $grund)
                <option value="{{ $grund }}">{{ __('portal.profile_request.delete.reasons.'.$grund) }}</option>
            @endforeach
        </select>
        @error('deleteReason')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="pr-delete-note" class="label-portal mb-1.5">
            {{ __('portal.profile_request.delete.note_label') }}
            @unless($deleteReason === 'other')<span class="text-base-content/40">{{ __('portal.layout.optional') }}</span>@endunless
        </label>
        <textarea id="pr-delete-note" wire:model.blur="deleteNote" rows="4" maxlength="1000" class="input-portal w-full min-h-[100px] resize-y"></textarea>
        @error('deleteNote')
            <p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>
        @else
            <p class="text-xs text-base-content/50 mt-1">{{ __('portal.profile_request.delete.note_hint') }}</p>
        @enderror
    </div>
</fieldset>

<p class="flex items-start gap-2 rounded-xl bg-base-200/60 p-3 text-sm text-base-content/70">
    <svg class="w-4 h-4 shrink-0 mt-0.5 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span>{{ __('portal.profile_request.delete.grace') }}</span>
</p>

<div>
    <label class="flex items-start gap-2 text-sm text-base-content">
        <input type="checkbox" wire:model="deleteConfirmed" class="mt-0.5 w-4 h-4 shrink-0 rounded border-base-content/20">
        <span>{{ __('portal.profile_request.delete.confirm') }}</span>
    </label>
    @error('deleteConfirmed')<p class="text-xs text-red-500 mt-1" role="alert">{{ $message }}</p>@enderror
</div>
