{{--
    Gemeinsames Layout der Premium-Mails (#16, SUN-PREM-015): Monatsreport, exklusive Anfrage (#9),
    Zahlung fehlgeschlagen (#5), Nachweis abgelehnt (#11).

    Aufruf: @extends('mail.premium.layout') mit den Sections
    - preview  (Vorschautext im Postfach, optional)
    - content  (Inhalt der weissen Karte inkl. Grussformel; Anrede Du/Sie bleibt bei der Mail)
    - footer   (Zusatzzeile im Fuss, z.B. Abmeldelink, optional)
    Sections werden vor dem Layout gerendert, Variablen von hier kommen dort also nicht an:
    Buttons ueber @include('mail.premium.partials.button', ['url' => ..., 'label' => ...]),
    Portalname/Farbe im Inhalt ueber TenantMailBranding::current().
    Ausserhalb des Tenant-Kontexts den Tenant als View-Variable $mailTenant mitgeben.
    Farben/URLs: App\Support\Tenancy\TenantMailBranding. Nur Inline-Styles (Mail-Clients).
--}}
@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $brandColor = $branding->brandColor();
    $portalName = $branding->portalName();
    $portalUrl = $branding->url('/');
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $portalName }}</title>
    <!--[if mso]>
    <style>
        td,th,div,p,a,h1,h2,h3 {font-family: "Segoe UI", sans-serif; mso-line-height-rule: exactly;}
    </style>
    <![endif]-->
    <style>
        @media (max-width: 600px) {
            .sm-px-4 { padding-left: 16px !important; padding-right: 16px !important; }
            .sm-px-6 { padding-left: 24px !important; padding-right: 24px !important; }
            .sm-my-6 { margin-top: 24px !important; margin-bottom: 24px !important; }
            .sm-block { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; width: 100%; padding: 0; -webkit-font-smoothing: antialiased; word-break: break-word; background-color: #fafafa;">
<div style="display: none; max-height: 0; overflow: hidden;">
    @yield('preview')
    &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847;
</div>
<div role="article" aria-roledescription="email" aria-label="{{ $portalName }}" lang="de">
    <div class="sm-px-4" style="background-color: #fafafa; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;">
        <table align="center" cellpadding="0" cellspacing="0" role="none" style="width: 100%;">
            <tr>
                <td align="center">
                    <table cellpadding="0" cellspacing="0" role="none" style="width: 560px; max-width: 100%;">
                        <tr>
                            <td class="sm-my-6" style="padding: 40px 0 24px; text-align: left;">
                                <a href="{{ $portalUrl }}" style="text-decoration: none;">
                                    <span style="display: inline-block; width: 12px; height: 12px; border-radius: 4px; background-color: {{ $brandColor }}; vertical-align: middle;"></span>
                                    <span style="font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.3px; vertical-align: middle; padding-left: 6px;">{{ $portalName }}</span>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="height: 4px; background-color: {{ $brandColor }}; border-radius: 16px 16px 0 0; line-height: 4px; font-size: 0;">&zwj;</td>
                        </tr>
                        <tr>
                            <td class="sm-px-6" style="padding: 40px; font-size: 16px; line-height: 24px; color: #3f3f46; background-color: #ffffff; border: 1px solid #e4e4e7; border-top: 0; border-radius: 0 0 16px 16px;">
                                @yield('content')
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 24px 24px 40px; text-align: center; font-size: 12px; line-height: 18px; color: #71717a;">
                                @hasSection('footer')
                                    <p style="margin: 0 0 12px;">@yield('footer')</p>
                                @endif
                                <p style="margin: 0;">
                                    &copy; {{ date('Y') }} <a href="{{ $portalUrl }}" style="color: #71717a;">{{ $portalName }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
