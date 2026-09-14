<div class="mt-2">
  @if($reported)
    <p class="text-sm text-zinc-500" role="status">Danke für den Hinweis. Wir prüfen die Bewertung.</p>
  @else
    <button type="button" wire:click="toggle" class="text-sm text-zinc-500 hover:text-zinc-900 hover:underline underline-offset-2" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="report-review-{{ $reviewId }}">Bewertung melden</button>

    @if($open)
      <form id="report-review-{{ $reviewId }}" wire:submit="report" class="mt-2 rounded-2xl bg-zinc-50 p-4 flex flex-col gap-3">
        <fieldset>
          <legend class="text-sm font-medium text-zinc-700 mb-2">Warum möchtest du diese Bewertung melden?</legend>
          <div class="flex flex-col gap-2">
            @foreach($reasons as $value => $label)
              <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                <input type="radio" wire:model="reason" value="{{ $value }}" name="report-reason-{{ $reviewId }}">
                {{ $label }}
              </label>
            @endforeach
          </div>
        </fieldset>
        <div>
          <label for="report-comment-{{ $reviewId }}" class="block text-sm font-medium text-zinc-700 mb-1">Hinweis <span class="text-zinc-500 font-normal">(optional)</span></label>
          <textarea id="report-comment-{{ $reviewId }}" wire:model="comment" rows="2" maxlength="500" class="input py-3 h-auto"></textarea>
        </div>
        @error('reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @error('comment')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="flex flex-col-reverse sm:flex-row gap-2">
          <button type="button" wire:click="toggle" class="btn-ghost">Abbrechen</button>
          <button type="submit" wire:loading.attr="disabled" class="btn-secondary">Melden</button>
        </div>
      </form>
    @endif
  @endif
</div>
