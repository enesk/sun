{{-- Einwilligung: nie vorab angehakt, ausser der Funnel setzt meta.default ausdruecklich --}}
<label class="flex items-start gap-3 cursor-pointer">
  <input id="{{ $id }}" type="checkbox" name="{{ $question->key }}" value="1" class="mt-1 size-5 rounded border-zinc-300 text-brand focus:ring-brand"
    @checked((bool) ($question->meta['default'] ?? false))
    @if($question->required) required aria-required="true" @endif
    @include('components.sun.funnel._described')>
  <span class="text-sm text-zinc-700">{{ $label }}</span>
</label>
