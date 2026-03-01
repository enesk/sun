<x-layouts.email>
    <x-slot name="preview">
        Ihre Empfehlung war erfolgreich — Sie haben einen Bonus erhalten!
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Herzlichen Glückwunsch — Empfehlungsbonus erhalten!
            </h1>
            <p style="margin: 0; line-height: 24px">
                Ihre Empfehlung war erfolgreich! {{ $referral->referredUser->name }} hat sich bei uns angemeldet, und Sie haben dafür einen Bonus erhalten.
            </p>

            <div style="padding: 20px; border-radius: 8px; margin: 20px 0; background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #334155;">Ihr Gutschein-Code:</p>
                <p style="font-size: 24px; font-weight: bold; color: #10b981; margin: 0; letter-spacing: 2px;">
                    {{ $discountCode->code }}
                </p>
            </div>

            <p style="margin: 0; line-height: 24px">
                Empfehlen Sie uns weiter und sichern Sie sich weitere Boni!
            </p>

            <div style="text-align: center;">
                <a href="{{ route('dashboard') }}" style="margin-top: 24px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    Zum Dashboard
                </a>
            </div>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                Mit freundlichen Grüßen,<br>
                Ihr {{ config('app.name') }}-Team
            </p>
        </td>
    </tr>
</x-layouts.email>
