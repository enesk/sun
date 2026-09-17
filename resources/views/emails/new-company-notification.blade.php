{{-- Neuer Firmeneintrag: Hinweis an den Portalbetreiber (App\Mail\NewCompanyNotification). Texte aus portal.mail.new_company.*, sun-Mail-Layout (SUN-PREM-015, #16). --}}
@extends('mail.sun.layout')

@section('preview')
    {{ __('portal.mail.new_company.preview', ['firma' => $details['company']]) }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('portal.mail.new_company.heading') }}
    </h1>
    <p style="margin: 0 0 24px;">
        {{ __('portal.mail.new_company.intro') }}
    </p>

    <table style="width: 100%; margin-bottom: 8px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.company') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $details['company'] }}</td>
        </tr>
        @if($details['address'])
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.address') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $details['address'] }}</td>
        </tr>
        @endif
        @if($details['tel'])
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.phone') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $details['tel'] }}</td>
        </tr>
        @endif
        @if($details['website'])
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.website') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $details['website'] }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.owner') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $details['owner'] }} ({{ $details['owner_email'] }})</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.status') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ __($details['is_active'] ? 'portal.mail.new_company.status_online' : 'portal.mail.new_company.status_pending') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">{{ __('portal.mail.new_company.created_at') }}:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ __('portal.mail.new_company.created_at_value', ['zeit' => $details['created_at']]) }}</td>
        </tr>
    </table>

    @include('mail.sun.partials.button', ['url' => $details['edit_url'], 'label' => __('portal.mail.new_company.open')])

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        {{ __('portal.mail.system_note') }}
    </p>
@endsection
