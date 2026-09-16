{{-- Einzeilige Eingabe: text, email, phone, number, date, postal_code --}}
@php
    $attrs = match ($question->type) {
        'email' => ['type' => 'email', 'autocomplete' => 'email', 'inputmode' => 'email'],
        'phone' => ['type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel'],
        'number' => ['type' => 'number', 'inputmode' => 'decimal'],
        'date' => ['type' => 'date'],
        'postal_code' => ['type' => 'text', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'pattern' => '[0-9]{5}'],
        default => ['type' => 'text', 'autocomplete' => $question->key === 'name' ? 'name' : null],
    };
    foreach (['min', 'max', 'step'] as $bound) {
        if ($question->type === 'number' && isset($question->validation[$bound])) {
            $attrs[$bound] = $question->validation[$bound];
        }
    }
    $narrow = in_array($question->type, ['postal_code', 'number', 'date'], true);
@endphp
<label for="{{ $id }}" class="block text-sm font-medium text-zinc-700 mb-1">{{ $label }}@unless($question->required) <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span>@endunless</label>
<input id="{{ $id }}" name="{{ $question->key }}" @class(['input', 'max-w-40' => $narrow])
  @foreach(array_filter($attrs, fn ($value) => $value !== null) as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach
  @if($placeholder) placeholder="{{ $placeholder }}" @endif
  @if($question->required) required aria-required="true" @endif
  @include('components.sun.funnel._described')>
