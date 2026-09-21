{{--
    Alarm-Mail des Ratgebersystems (#38 G1, design/guide-dashboard.md §11.3).
    sun-Mail-Layout wie der Tagesbericht, nur Inline-Styles. Nur die
    Überschrift in #b91c1c, genau eine Schaltfläche.
--}}
@extends('mail.sun.layout')

@section('preview')
    {{ $title }}: {{ $portal ?? 'alle Portale' }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #b91c1c;">
        {{ $title }}
    </h1>

    <p style="margin: 0 0 16px;">
        @if ($portal !== null)
            <strong>{{ $portal }}:</strong>
        @endif
        {{ $alert->message }}
        <span style="color: #71717a;">(seit {{ $since }})</span>
    </p>

    <p style="margin: 0 0 16px;">
        <strong>Was jetzt passiert:</strong> {{ $consequence }}
    </p>

    @include('mail.sun.partials.button', ['url' => $url, 'label' => 'Im Content-Panel ansehen'])

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Diese Nachricht kommt höchstens einmal am Tag je Alarm. Weitere Vorkommen stehen im Tagesbericht.
    </p>
@endsection
