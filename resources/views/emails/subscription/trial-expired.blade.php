<x-layouts.email>
    <x-slot name="preview">
        Ihre Testphase ist abgelaufen — sichern Sie sich jetzt Premium
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Ihre Testphase ist abgelaufen
            </h1>
            <p style="margin: 0; line-height: 24px">
                Hallo {{ $subscription->user->name }},
                <br>
                <br>
                Ihre 30-tägige Testphase für den <strong>{{ $subscription->plan->name ?? 'Premium' }}</strong>-Plan ist heute abgelaufen.
            </p>

            <p style="margin: 16px 0; line-height: 24px">
                Ihre bisherigen Inhalte (Bilder, Antworten, Beschreibungen) bleiben gespeichert — sie werden nur nicht mehr öffentlich angezeigt, bis Sie upgraden.
            </p>

            <p style="margin: 16px 0 0 0; line-height: 24px; font-weight: 600;">
                Was Sie mit Premium behalten:
            </p>
            <ul style="margin: 8px 0 24px; padding-left: 20px; line-height: 28px;">
                <li>Auf Bewertungen antworten</li>
                <li>Bildergalerie & Cover-Bild</li>
                <li>Top-Platzierung in Suchergebnissen</li>
                <li>Detaillierte Statistiken</li>
                <li>Verifiziert-Badge</li>
            </ul>

            <div style="text-align: center;">
                <a href="{{ central_route('checkout.convert-local-subscription', ['subscriptionUuid' => $subscription->uuid]) }}" style="margin-top: 8px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    Jetzt Premium aktivieren — ab 9,90 €/Monat
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
