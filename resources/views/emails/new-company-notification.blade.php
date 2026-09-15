<x-layouts.email>
    <x-slot name="preview">
        {{ __('portal.mail.new_company.preview', ['firma' => $details['company']]) }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('portal.mail.new_company.heading') }}
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                {{ __('portal.mail.new_company.intro') }}
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 140px;">{{ __('portal.mail.new_company.company') }}:</td>
                    <td style="padding: 8px 0;">{{ $details['company'] }}</td>
                </tr>
                @if($details['address'])
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.address') }}:</td>
                    <td style="padding: 8px 0;">{{ $details['address'] }}</td>
                </tr>
                @endif
                @if($details['tel'])
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.phone') }}:</td>
                    <td style="padding: 8px 0;">{{ $details['tel'] }}</td>
                </tr>
                @endif
                @if($details['website'])
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.website') }}:</td>
                    <td style="padding: 8px 0;">{{ $details['website'] }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.owner') }}:</td>
                    <td style="padding: 8px 0;">{{ $details['owner'] }} ({{ $details['owner_email'] }})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.status') }}:</td>
                    <td style="padding: 8px 0;">{{ __($details['is_active'] ? 'portal.mail.new_company.status_online' : 'portal.mail.new_company.status_pending') }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('portal.mail.new_company.created_at') }}:</td>
                    <td style="padding: 8px 0;">{{ __('portal.mail.new_company.created_at_value', ['zeit' => $details['created_at']]) }}</td>
                </tr>
            </table>

            <p style="margin: 0 0 16px; line-height: 24px">
                <a href="{{ $details['edit_url'] }}" style="color: #1d4ed8; font-weight: 600;">{{ __('portal.mail.new_company.open') }}</a>
            </p>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                {{ __('portal.mail.sign_off') }}<br>
                {{ __('portal.mail.sign_off_name') }}
            </p>
        </td>
    </tr>
</x-layouts.email>
