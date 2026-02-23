{{-- GA4 Consent Mode v2: GA lädt IMMER, cookieless bis User zustimmt --}}
@if (!empty(config('app.google_tracking_id')))
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            'analytics_storage': 'denied',
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'wait_for_update': 500
        });
        gtag('js', new Date());
        gtag('config', '{{ config('app.google_tracking_id') }}', {
            'anonymize_ip': true
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.google_tracking_id') }}"></script>
@endif

@if (!empty(config('app.tracking_scripts')))
    {!! config('app.tracking_scripts') !!}
@endif
