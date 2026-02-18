{{-- Google Analytics 4 & Google Tag Manager --}}
@if(!empty($currentTenant))
    @php
        $gaId = $currentTenant->getAttribute('settings.google_analytics_id');
        $gtmId = $currentTenant->getAttribute('settings.google_tag_manager_id');
    @endphp

    @if(!empty($gaId))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ e($gaId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ e($gaId) }}');
        </script>
    @endif

    @if(!empty($gtmId))
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ e($gtmId) }}');
        </script>
    @endif
@endif
