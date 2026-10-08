@if (config('app.recaptcha_enabled'))

    <div wire:ignore>
        <input type="text" wire:model="recaptcha" x-on:captcha-success.window="$wire.recaptcha = $event.detail.token" hidden>
        <div class="my-4">
            {!! htmlFormSnippet([
                "callback" => "onRecaptchaSuccess"
            ]) !!}
        </div>
    </div>

    @error('g-recaptcha-response')
        <span class="text-xs text-red-500" role="alert">
            {{ $message }}
        </span>
    @enderror

    {{-- onRecaptchaSuccess und das Zuruecksetzen stehen in
         resources/js/modules/recaptcha-bridge.js — kein Inline-Skript (#21). --}}
    @push('tail')
        {!! htmlScriptTagJsApi() !!}
    @endpush

@endif
