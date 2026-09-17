{{-- Primaer-Button der Premium-Mails (#16) in der Markenfarbe des Tenants. Parameter: $url, $label --}}
<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 32px 0 8px;">
    <tr>
        <td style="border-radius: 12px; background-color: {{ \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->brandColor() }};">
            <a href="{{ $url }}" style="display: inline-block; padding: 14px 28px; font-size: 16px; font-weight: 600; line-height: 20px; color: #ffffff; text-decoration: none; border-radius: 12px;">{{ $label }}</a>
        </td>
    </tr>
</table>
