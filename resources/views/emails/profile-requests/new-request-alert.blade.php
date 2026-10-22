{{-- Sofortmeldung über einen Löschwunsch oder Übernahmeantrag
     (App\ProfileRequests\Mail\NewRequestAlertMail, SUN-RQ #23).

     Interne Mail, deshalb Du-Form und kein Portal-Branding. Sie beantwortet
     eine Frage: Muss ich jetzt hinsehen? Also der Vorgang in zwei Zeilen und
     ein Knopf ins Panel — alles Weitere steht dort.

     Parameter: $request, $portal, $betrieb, $panelUrl, $istLoeschung. --}}
@extends('mail.sun.layout')

@section('preview')
    {{ $istLoeschung ? 'Löschwunsch' : 'Übernahmeantrag' }} für {{ $betrieb }} ({{ $portal }})
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ $istLoeschung ? 'Löschwunsch' : 'Übernahmeantrag' }} eingegangen
    </h1>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 20px;">
        <tr>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; font-size: 14px; color: #71717a; width: 100px;">Betrieb</td>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; font-weight: 600; color: #18181b;">{{ $betrieb }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Portal</td>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; color: #18181b;">{{ $portal }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Absender</td>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; color: #18181b;">
                {{ $request->requester_email ?: 'keine Adresse angegeben' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Vorgang</td>
            <td style="padding: 8px 0; border-top: 1px solid #e4e4e7; color: #18181b;">REQ-{{ $request->id }}</td>
        </tr>
    </table>

    @if (filled($request->message))
        <p style="margin: 0 0 4px; font-size: 14px; color: #71717a;">Wortlaut</p>
        <p style="margin: 0 0 20px; padding: 12px 16px; background-color: #f4f4f5; border-radius: 8px; color: #27272a; white-space: pre-line;">{{ \Illuminate\Support\Str::limit($request->message, 600) }}</p>
    @endif

    @include('mail.sun.partials.button', ['url' => $panelUrl, 'label' => 'Im Panel öffnen'])

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Diese Mail kommt nur bei Löschwünschen und Übernahmen. Alles andere steht im
        Tagesdigest um 08:00.
        @if ($istLoeschung)
            Eine bestätigte Löschung führt die Automatik erst nach der Karenzfrist aus — bis
            dahin lässt sie sich im Panel stoppen.
        @endif
    </p>
@endsection
