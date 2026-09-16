{{-- Schieberegler; die Anzeige daneben aktualisiert lead-dialog.js (#27), kein Inline-Skript --}}
@php
    $min = $question->validation['min'] ?? $question->meta['min'] ?? 0;
    $max = $question->validation['max'] ?? $question->meta['max'] ?? 100;
    $step = $question->meta['step'] ?? $question->validation['step'] ?? 1;
    $value = $question->meta['default'] ?? $min;
    $unit = isset($question->meta['unit']) ? ' '.$question->meta['unit'] : '';
@endphp
<label for="{{ $id }}" class="block text-sm font-medium text-zinc-700 mb-1">{{ $label }}</label>
<div class="flex items-center gap-3">
  <input id="{{ $id }}" name="{{ $question->key }}" type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" class="flex-1 accent-brand"
    @include('components.sun.funnel._described')>
  <output for="{{ $id }}" data-slider-output data-unit="{{ $unit }}" class="min-w-16 text-right tabular-nums text-sm font-medium text-zinc-900">{{ $value }}{{ $unit }}</output>
</div>
