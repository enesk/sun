<fieldset @include('components.sun.funnel._described') @if($question->required) aria-required="true" @endif>
  <legend class="block text-sm font-medium text-zinc-700 mb-2">{{ $label }}</legend>
  <div class="flex flex-wrap gap-2">
    @foreach($question->options as $option)
      @include('components.sun.funnel._pill', ['inputType' => 'checkbox', 'name' => $question->key.'[]', 'checked' => in_array($option->value, (array) ($question->meta['default'] ?? []), true)])
    @endforeach
  </div>
</fieldset>
