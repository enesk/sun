<x-layouts.email>
    <x-slot name="preview">
        Ihr Einmal-Passwort für {{ config('app.name') }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Ihr Einmal-Passwort
            </h1>
            <p style="margin: 0; line-height: 24px">
                Hier ist Ihr Einmal-Passwort für die Anmeldung bei {{ config('app.url') }}:
                <br>
                <br>
                <strong style="font-size: 24px; letter-spacing: 2px;">{{ $oneTimePassword->password }}</strong>
            </p>
            <p style="margin-top: 16px; line-height: 24px">
                Geben Sie diesen Code an niemanden weiter. Falls Sie diese Anmeldung nicht angefordert haben, können Sie diese E-Mail ignorieren.
            </p>
        </td>
    </tr>
</x-layouts.email>
