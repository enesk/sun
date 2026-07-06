<x-layouts.email>
    <x-slot name="preview">
        Neuer Änderungsvorschlag für {{ $suggestion->company->name ?? 'Unbekannt' }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Neuer Änderungsvorschlag
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                Es wurde ein neuer Änderungsvorschlag eingereicht:
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 140px;">Firma:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->company->name ?? 'Unbekannt' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Bereich:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->field_label }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Vorschlag:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->suggested_value }}</td>
                </tr>
                @if($suggestion->reason)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Begründung:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->reason }}</td>
                </tr>
                @endif
                @if($suggestion->reporter_name)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Name:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->reporter_name }}</td>
                </tr>
                @endif
                @if($suggestion->reporter_email)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">E-Mail:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->reporter_email }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Tenant:</td>
                    <td style="padding: 8px 0;">{{ tenant()?->name ?? tenant()?->id ?? '-' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Zeitpunkt:</td>
                    <td style="padding: 8px 0;">{{ $suggestion->created_at->format('d.m.Y H:i') }} Uhr</td>
                </tr>
            </table>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                Mit freundlichen Grüßen,<br>
                Ihr {{ config('app.name') }}-System
            </p>
        </td>
    </tr>
</x-layouts.email>
