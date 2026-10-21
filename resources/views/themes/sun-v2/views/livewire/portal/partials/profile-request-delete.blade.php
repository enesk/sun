{{--
    Loeschweg des Self-Service-Dialogs (SUN-RQ #4), Theme sun-v2.
    Eingebunden aus livewire/portal/profile-request-dialog.

    Hier wird nichts geloescht: der Antrag geht in die Inbox (#7), wird per
    Magic-Link bestaetigt (#6) und laeuft erst nach der Karenzfrist von 48
    Stunden durch die Loeschpipeline (#8/#9). Der Hinweis auf die Frist steht
    deshalb vor dem Knopf und nicht erst in der Bestaetigung.
--}}
<fieldset class="flex flex-col gap-3">
  <legend class="text-sm font-semibold text-zinc-900">{{ __('portal.profile_request.delete.reason_label') }}</legend>

  <div>
    <label for="pr-delete-reason" class="sr-only">{{ __('portal.profile_request.delete.reason_label') }}</label>
    <select id="pr-delete-reason" wire:model.live="deleteReason" class="input @error('deleteReason') border-red-500 @enderror">
      <option value="">{{ __('portal.profile_request.delete.reason_placeholder') }}</option>
      @foreach(\App\Livewire\Portal\ProfileRequestDialog::DELETE_REASONS as $grund)
        <option value="{{ $grund }}">{{ __('portal.profile_request.delete.reasons.'.$grund) }}</option>
      @endforeach
    </select>
    @error('deleteReason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  </div>

  <div>
    <label for="pr-delete-note" class="block text-sm font-medium text-zinc-700 mb-1">
      {{ __('portal.profile_request.delete.note_label') }}
      @unless($deleteReason === 'other')<span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span>@endunless
    </label>
    <textarea id="pr-delete-note" wire:model.blur="deleteNote" rows="4" maxlength="1000" class="input py-3 h-auto @error('deleteNote') border-red-500 @enderror"></textarea>
    @error('deleteNote')
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @else
      <p class="mt-1 text-sm text-zinc-500">{{ __('portal.profile_request.delete.note_hint') }}</p>
    @enderror
  </div>
</fieldset>

<p class="flex items-start gap-2 rounded-xl bg-zinc-50 p-3 text-sm text-zinc-700">
  <x-sun.icon name="clock" class="icon shrink-0 text-zinc-500" />
  <span>{{ __('portal.profile_request.delete.grace') }}</span>
</p>

<div>
  <label class="flex items-start gap-2 text-sm text-zinc-900">
    <input type="checkbox" wire:model="deleteConfirmed" class="mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-brand focus:ring-brand">
    <span>{{ __('portal.profile_request.delete.confirm') }}</span>
  </label>
  @error('deleteConfirmed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
