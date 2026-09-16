<label for="{{ $id }}" class="block text-sm font-medium text-zinc-700 mb-1">{{ $label }}</label>
<textarea id="{{ $id }}" name="{{ $question->key }}" rows="{{ (int) ($question->meta['rows'] ?? 4) }}" class="input py-3 h-auto"
  @if($placeholder) placeholder="{{ $placeholder }}" @endif
  @if($question->required) required aria-required="true" @endif
  @include('components.sun.funnel._described')></textarea>
