@if($cookieConsentConfig['enabled'] && ! $alreadyConsentedWithCookies)

    @include('cookie-consent::dialogContents')

    {{-- Verhalten in resources/js/modules/cookie-bar.js: die Paketvorlage trug
         ihr JavaScript inline, hier stehen nur noch die Werte (#21). --}}
    <div data-cookie-consent
         data-cookie-name="{{ $cookieConsentConfig['cookie_name'] }}"
         data-cookie-lifetime="{{ $cookieConsentConfig['cookie_lifetime'] }}"
         data-cookie-domain="{{ config('session.domain') ?? request()->getHost() }}"
         @if(config('session.secure')) data-cookie-secure @endif
         @if(config('session.same_site')) data-cookie-same-site="{{ config('session.same_site') }}" @endif
         hidden></div>

@endif
