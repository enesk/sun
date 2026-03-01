{{-- GA4: Vollständiges Tracking — lädt immer mit granted consent --}}
@if (!empty(config('app.google_tracking_id')))
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            'analytics_storage': 'granted',
            'ad_storage': 'granted',
            'ad_user_data': 'granted',
            'ad_personalization': 'granted'
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
