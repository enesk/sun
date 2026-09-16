{{-- Bildauswahl als Kacheln; Bildpfade relativ zum Leadsystem werden an dessen Host gehaengt --}}
@php
    $leadHost = rtrim((string) preg_replace('#/api/.*$#', '', (string) config('leads.api_url')), '/');
    $multiple = (bool) ($question->meta['multiple'] ?? false);
@endphp
<fieldset @include('components.sun.funnel._described') @if($question->required) aria-required="true" @endif>
  <legend class="block text-sm font-medium text-zinc-700 mb-2">{{ $label }}</legend>
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
    @foreach($question->options as $option)
      <label class="cursor-pointer">
        <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="{{ $question->key }}{{ $multiple ? '[]' : '' }}" value="{{ $option->value }}" class="peer sr-only">
        <span class="card-interactive h-full p-3 flex flex-col items-center gap-2 text-center text-sm font-medium text-zinc-700 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">
          @if($option->imagePath)
            <img src="{{ str_starts_with($option->imagePath, 'http') ? $option->imagePath : $leadHost.'/storage/'.ltrim($option->imagePath, '/') }}" alt="" width="96" height="96" loading="lazy" class="size-16 object-contain">
          @endif
          {{ $option->label }}
        </span>
      </label>
    @endforeach
  </div>
</fieldset>
