<fieldset @include('components.sun.funnel._described') @if($question->required) aria-required="true" @endif>
  <legend class="block text-sm font-medium text-zinc-700 mb-2">{{ $label }}</legend>
  <div class="flex flex-wrap gap-2">
    @foreach($question->options as $option)
      @include('components.sun.funnel._pill', ['inputType' => 'radio', 'name' => $question->key, 'checked' => ($question->meta['default'] ?? null) === $option->value])
    @endforeach
  </div>
</fieldset>
