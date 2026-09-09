{{--
    Sofortmeldung eines kritischen Alarms der Content-Pipeline (#22).
    Aufbau wie der Tagesbericht: Tabellenlayout, Inline-Styles, keine Assets.
--}}
<x-layouts.email>
    <x-slot name="preview">
        {{ __('Ratgeber-Alarm') }}: {{ $alert->message }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('Ratgeber-Alarm') }}
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px; color: #b91c1c;">
                {{ $alert->message }}
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                @if ($portal !== null)
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 220px;">{{ __('Portal') }}</td>
                        <td style="padding: 8px 0;">{{ $portal }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 220px;">{{ __('Ursache') }}</td>
                    <td style="padding: 8px 0;">{{ $alert->key }}</td>
                </tr>
                @if ($alert->for_date)
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('Tag') }}</td>
                        <td style="padding: 8px 0;">{{ $alert->for_date }}</td>
                    </tr>
                @endif
                @if ($alert->slot !== null)
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('Slot') }}</td>
                        <td style="padding: 8px 0;">{{ $alert->slot }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('Vorkommen') }}</td>
                    <td style="padding: 8px 0;">{{ $alert->occurrences }}</td>
                </tr>
            </table>

            <p style="margin: 0 0 16px; line-height: 24px; color: #64748b;">
                {{ __('Der Alarm steht im Störungsband der Übersicht und im Tagesbericht um 20:00 Uhr, bis er sich auflöst.') }}
            </p>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                {{ __('Mit freundlichen Grüßen,') }}<br>
                {{ __('Ihr :app-System', ['app' => config('app.name')]) }}
            </p>
        </td>
    </tr>
</x-layouts.email>
