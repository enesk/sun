<x-layouts.email>
    <x-slot name="preview">
        Ihre Stellenanzeige „{{ $job->title }}" ist abgelaufen
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Stellenanzeige abgelaufen
            </h1>
            <p style="margin: 0; line-height: 24px">
                Hallo,
                <br>
                <br>
                Ihre Stellenanzeige <strong>„{{ $job->title }}"</strong> für <strong>{{ $company->name }}</strong> ist nach 30 Tagen automatisch abgelaufen und nicht mehr öffentlich sichtbar.
            </p>

            <div style="margin: 24px 0; padding: 16px; background-color: #f8fafc; border-radius: 8px; border-left: 4px solid {{config('app.email_color_tint')}};">
                <p style="margin: 0 0 8px; font-weight: 600; color: #000;">{{ $job->title }}</p>
                <p style="margin: 0; font-size: 14px; color: #64748b;">
                    {{ $job->employment_type_label }}
                    @if($job->location_display) · {{ $job->location_display }} @endif
                    <br>
                    Veröffentlicht: {{ $job->published_at->format('d.m.Y') }} · Abgelaufen: {{ $job->expires_at->format('d.m.Y') }}
                </p>
            </div>

            <p style="margin: 0 0 24px; line-height: 24px">
                Sie können die Stellenanzeige jederzeit in Ihrem Dashboard erneut veröffentlichen. Die Anzeige wird dann für weitere 30 Tage sichtbar.
            </p>

            <div style="text-align: center;">
                @php
                    $tenantDomain = $tenant->domains?->first()?->domain;
                    $dashboardUrl = $tenantDomain
                        ? 'https://' . $tenantDomain . '/firmenprofil/stellenanzeigen'
                        : url('/firmenprofil/stellenanzeigen');
                @endphp
                <a href="{{ $dashboardUrl }}" style="margin-top: 8px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    Stellenanzeigen verwalten
                </a>
            </div>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="margin-top: 16px; padding-top: 12px; padding-bottom: 12px; font-size: 14px; color: #64748b;">
                Sie haben Fragen? Unser Support-Team hilft Ihnen gerne:
                <a href="mailto:{{ config('app.support_email') }}">
                    {{ config('app.support_email') }}
                </a>
            </p>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                Mit freundlichen Grüßen,<br>
                Ihr {{ config('app.name') }}-Team
            </p>
        </td>
    </tr>

</x-layouts.email>
