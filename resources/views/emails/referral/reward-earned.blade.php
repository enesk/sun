{{-- Empfehlungsbonus erhalten, umgestellt auf das sun-Mail-Layout (#16). Ohne Tenant-Bezug, daher Portalname aus dem Kontext bzw. Standardfarbe. --}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Deine Empfehlung hat geklappt — dein Bonus ist da!
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Glückwunsch — dein Empfehlungsbonus ist da!
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Deine Empfehlung hat geklappt: {{ $referral->referredUser->name }} hat sich angemeldet. Dafür bekommst du von uns einen Bonus.
    </p>

    <table cellpadding="0" cellspacing="0" role="none" style="width: 100%; margin: 24px 0 0;">
        <tr>
            <td style="padding: 20px; border-radius: 12px; background-color: #fafafa; border: 1px solid #e4e4e7;">
                <p style="margin: 0 0 8px; font-size: 14px; line-height: 22px; font-weight: 600; color: #71717a;">Dein Gutschein-Code:</p>
                <p style="margin: 0; font-size: 24px; line-height: 32px; font-weight: 700; letter-spacing: 2px; color: #18181b;">
                    {{ $discountCode->code }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Empfiehl uns weiter und sicher dir weitere Boni!
    </p>

    @include('mail.sun.partials.button', ['url' => route('dashboard'), 'label' => 'Zum Dashboard'])

    <p style="margin: 32px 0 0; color: #3f3f46;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
