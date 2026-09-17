{{-- Bestellbestaetigung, umgestellt auf das sun-Mail-Layout (#16). Der Tenant kommt aus der Bestellung, weil der Versand im Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $mailTenant = $order->tenant;
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $contactEmail = (string) ($mailTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));

    // Preise sind netto (config/premium.php). Betraege liegen in Cent, deutsch
    // formatiert, weil @money die App-Locale (en) nutzt.
    $currencyCode = $order->currency->code ?? config('premium.currency', 'EUR');
    $euro = fn ($cents) => \Illuminate\Support\Number::currency(((int) $cents) / 100, in: $currencyCode, locale: 'de');
    $vatPercent = (int) config('premium.contract.vat_percent', 19);
    $discount = (int) $order->total_discount_amount;
    $net = (int) ($discount > 0 ? $order->total_amount_after_discount : $order->total_amount);
    $vat = (int) round($net * $vatPercent / 100);
    $gross = $net + $vat;
@endphp

@section('preview')
    Danke für deine Bestellung bei {{ $portalName }}!
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Danke für deine Bestellung!
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Deine Bestellung bei {{ $portalName }} ist bei uns eingegangen.
        <br>
        <br>
        Bestellnummer: {{ $order->uuid }}
    </p>

    @php($index = 1)
    @foreach ($order->items as $item)
        <table cellpadding="0" cellspacing="0" role="none" style="margin-top: 16px; width: 100%;">
            <tr>
                <td style="width: 7%; vertical-align: top; color: #71717a;">
                    #{{ $index++ }}
                </td>
                <td style="vertical-align: top;">
                    <div style="margin-left: 12px;">
                        <div style="font-size: 18px; font-weight: 600; line-height: 26px; color: #18181b;">
                            {{ $item->oneTimeProduct->name }}
                        </div>
                        @if ($item->oneTimeProduct->description)
                            <div style="font-size: 14px; line-height: 22px; color: #3f3f46;">{{ $item->oneTimeProduct->description }}</div>
                        @endif
                        <div style="font-size: 14px; line-height: 22px; margin-top: 8px; color: #71717a;">
                            Anzahl: {{ $item->quantity }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    @endforeach

    <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 24px 0;">&zwj;</div>

    <table cellpadding="0" cellspacing="0" role="none" style="width: 100%;">
        @if ($discount > 0)
            <tr>
                <td align="left" style="padding-bottom: 4px; color: #3f3f46;">Zwischensumme (netto)</td>
                <td align="right" style="padding-bottom: 4px; color: #3f3f46;">{{ $euro($order->total_amount) }}</td>
            </tr>
            <tr>
                <td align="left" style="padding-bottom: 4px; color: #3f3f46;">Rabatt</td>
                <td align="right" style="padding-bottom: 4px; color: #3f3f46;">&minus;{{ $euro($discount) }}</td>
            </tr>
        @endif
        <tr>
            <td align="left" style="padding-bottom: 4px; color: #3f3f46;">Summe netto</td>
            <td align="right" style="padding-bottom: 4px; color: #3f3f46;">{{ $euro($net) }}</td>
        </tr>
        <tr>
            <td align="left" style="padding-bottom: 8px; color: #3f3f46;">zzgl. {{ $vatPercent }} % USt.</td>
            <td align="right" style="padding-bottom: 8px; color: #3f3f46;">{{ $euro($vat) }}</td>
        </tr>
        <tr>
            <td align="left" style="border-top: 1px solid #e4e4e7; padding-top: 8px; font-weight: 600; color: #18181b;">Gesamt (brutto)</td>
            <td align="right" style="border-top: 1px solid #e4e4e7; padding-top: 8px; font-weight: 600; color: #18181b;">{{ $euro($gross) }}</td>
        </tr>
    </table>

    <p style="margin: 8px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
        Unser Angebot richtet sich an Unternehmen. Maßgeblich ist die Rechnung: Bei Sonderfällen der
        Umsatzsteuer (z.&nbsp;B. Reverse-Charge im EU-Ausland) kann der ausgewiesene Steuerbetrag abweichen.
    </p>

    @if ($contactEmail !== '')
        <p style="margin: 16px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
            Du hast Fragen zu deiner Bestellung? Schreib uns einfach:
            <a href="mailto:{{ $contactEmail }}" style="color: #71717a;">{{ $contactEmail }}</a>
        </p>
    @endif

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
