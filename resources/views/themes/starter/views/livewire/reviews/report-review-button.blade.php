<div class="mt-2 ml-12">
    @if($reported)
        <p class="text-xs text-[#64748B]" role="status">Danke für den Hinweis. Wir prüfen die Bewertung.</p>
    @else
        <button type="button"
                wire:click="toggle"
                class="text-xs text-[#94A3B8] hover:text-[#0F172A] underline-offset-2 hover:underline transition-colors"
                aria-expanded="{{ $open ? 'true' : 'false' }}"
                aria-controls="report-review-{{ $reviewId }}">
            Bewertung melden
        </button>

        @if($open)
            <form id="report-review-{{ $reviewId }}" wire:submit="report" class="mt-2 company-review-card !p-3 sm:!p-4">
                <fieldset>
                    <legend class="label-portal mb-2">Warum möchten Sie diese Bewertung melden?</legend>
                    <div class="flex flex-col gap-1.5">
                        @foreach($reasons as $value => $label)
                            <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                                <input type="radio" wire:model="reason" value="{{ $value }}" name="report-reason-{{ $reviewId }}">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label for="report-comment-{{ $reviewId }}" class="label-portal mt-3">Hinweis <span class="text-[#94A3B8] font-normal">(optional)</span></label>
                <textarea id="report-comment-{{ $reviewId }}" wire:model="comment" rows="2" maxlength="500" class="textarea-portal"></textarea>

                @error('reason')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                @error('comment')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror

                <div class="mt-3 flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled" class="btn-portal inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium">
                        Melden
                    </button>
                    <button type="button" wire:click="toggle" class="text-sm text-[#64748B] hover:text-[#0F172A]">Abbrechen</button>
                </div>
            </form>
        @endif
    @endif
</div>
