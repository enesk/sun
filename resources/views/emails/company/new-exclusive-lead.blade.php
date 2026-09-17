{{-- Neue exklusive Anfrage (#9), Premium-Mail-Layout SUN-PREM-015 (#16). --}}
@extends('mail.sun.layout')

@section('preview')
    {{ __('portal.owner.leads.mail.preview', ['firma' => $companyName]) }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('portal.owner.leads.mail.heading') }}
    </h1>
    <p style="margin: 0 0 24px;">
        {{ __('portal.owner.leads.mail.intro', ['firma' => $companyName]) }}
    </p>

    <h2 style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #18181b;">{{ __('portal.owner.inquiries.show.contact') }}</h2>
    <table style="width: 100%; margin-bottom: 24px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; color: #71717a;">{{ __('portal.owner.inquiries.show.name') }}</td>
            <td style="padding: 4px 0;">{{ $lead->contact_name ?: '–' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #71717a;">{{ __('portal.owner.inquiries.show.phone') }}</td>
            <td style="padding: 4px 0;">@if($lead->contact_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->contact_phone) }}">{{ $lead->contact_phone }}</a>@else – @endif</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; color: #71717a;">{{ __('portal.owner.inquiries.show.email') }}</td>
            <td style="padding: 4px 0;">@if($lead->contact_email)<a href="mailto:{{ $lead->contact_email }}">{{ $lead->contact_email }}</a>@else – @endif</td>
        </tr>
    </table>

    @if(! empty($lead->answers))
        <h2 style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #18181b;">{{ __('portal.owner.inquiries.show.answers') }}</h2>
        <table style="width: 100%;" cellpadding="0" cellspacing="0" role="presentation">
            @foreach($lead->answers as $answer)
                <tr>
                    <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">{{ $answer['label'] }}</td>
                    <td style="padding: 4px 0; vertical-align: top;">{{ $answer['value_label'] ?? (is_array($answer['value']) ? implode(', ', $answer['value']) : $answer['value']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @include('mail.sun.partials.button', ['url' => $dashboardUrl, 'label' => __('portal.owner.leads.mail.cta')])

    <p style="margin: 16px 0 0; font-size: 14px; line-height: 20px; color: #71717a;">
        {{ __('portal.owner.leads.mail.exclusive_note') }}
    </p>

    <p style="margin: 32px 0 0;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName()]) }}
    </p>
@endsection
