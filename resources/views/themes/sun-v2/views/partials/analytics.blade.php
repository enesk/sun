{{-- GA4 wie im Default-Theme, das Skript (~170 KB) laedt aber erst nach dem Seitenaufbau:
     Aufrufe landen vorher in dataLayer und gehen danach mit, der LCP wartet nicht darauf. --}}
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
        window.addEventListener('load', function () {
            setTimeout(function () {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id={{ config('app.google_tracking_id') }}';
                document.head.appendChild(s);
            }, 1500);
        });
    </script>
@endif

@if (!empty(config('app.tracking_scripts')))
    {!! config('app.tracking_scripts') !!}
@endif
