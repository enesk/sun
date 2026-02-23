{{-- GA4 Consent Mode v2: GA lädt IMMER, aber cookieless bis User zustimmt --}}
{{-- Meta-Tags für den Alpine cookieConsent-Component (Consent-Upgrade) --}}
@if(!empty($currentTenant))
    @php
        $gaId = $currentTenant->getAttribute('settings.google_analytics_id');
        $gtmId = $currentTenant->getAttribute('settings.google_tag_manager_id');
    @endphp

    @if(!empty($gaId))
        <meta name="ga-id" content="{{ e($gaId) }}">
        {{-- GA4 Consent Mode v2: Default = denied (cookieless), Script lädt sofort --}}
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}

            // Consent-Default MUSS vor dem GA-Script stehen
            gtag('consent', 'default', {
                'analytics_storage': 'denied',
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'wait_for_update': 500
            });

            // Region-spezifisch: EU/EEA = denied default (redundant, aber explizit)
            gtag('consent', 'default', {
                'analytics_storage': 'denied',
                'ad_storage': 'denied',
                'region': ['DE', 'AT', 'CH']
            });

            gtag('js', new Date());
            gtag('config', '{{ e($gaId) }}', {
                'anonymize_ip': true,
                'send_page_view': true
            });
        </script>
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ e($gaId) }}" data-ga="true"></script>
    @endif

    @if(!empty($gtmId))
        <meta name="gtm-id" content="{{ e($gtmId) }}">
        {{-- GTM lädt ebenfalls sofort — Consent Mode wird von GTM respektiert --}}
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.dataset.gtm='true';
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ e($gtmId) }}');
        </script>
    @endif
@endif
