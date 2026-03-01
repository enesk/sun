<x-layouts.email>
    <x-slot name="preview">
        Zahlungsproblem — bitte Zahlungsdaten aktualisieren
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Zahlungsproblem bei Ihrem Abonnement
            </h1>
            <p style="margin: 0; line-height: 24px">
                Hallo {{ $subscription->user->name }},
                <br>
                <br>
                Wir konnten Ihre Zahlung für den <strong>{{ $subscription->plan->name }}</strong>-Plan leider nicht verarbeiten. Bitte aktualisieren Sie Ihre Zahlungsdaten, um Ihren Premium-Zugang nicht zu verlieren.
            </p>

            <div style="text-align: center;">
                <a href="{{ route('verwaltung.subscriptions.index') }}" style="margin-top: 24px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    Zahlungsdaten aktualisieren
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
