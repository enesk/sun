{{-- Huelle der oeffentlichen Anfragen-Seiten (SUN-RQ #10).

     Bewusst eigenstaendig und ohne Theme-Layout, aus drei Gruenden:

     1. Die Seite wird auf jeder Portal-Domain ausgeliefert, und von den 23
        Portalen laufen nur 5 auf sun-v2 — eine Seite, die es nur im Theme
        sun-v2 gibt, erreicht die Mehrheit nicht.
     2. Sie haengt an keinem Vite-Bundle: `public/build/` ist nicht
        versioniert, und eine Statusseite darf nicht davon abhaengen, dass
        gebaut wurde.
     3. Kein Header, kein Footer, keine Werbung, keine Analytik — die Seite
        zeigt einen Vorgang und sonst nichts (sun-portal-design: keine
        Werbung auf Formularseiten).

     Stile deshalb inline, Gestaltung aber nach sun-portal-design: zinc-Grund,
     weisse Karte mit 16 px Radius, genau eine Farbe (die Markenfarbe des
     Portals), mobile first ab 360 px, Klickflaechen mindestens 44 px.

     Parameter: $title, $portalName, $portalUrl, $brandColor, $slot. --}}
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Eine signierte Adresse ist nicht geheim, gehoert aber nie in den Index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ $portalName }}</title>
    <style>
        :root { --brand: {{ $brandColor }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background-color: #f4f4f5;
            color: #3f3f46;
            font-family: Figtree, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 1rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 36rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .brand {
            display: inline-flex; align-items: center; gap: .5rem;
            color: #18181b; font-weight: 600; text-decoration: none;
        }
        .brand span.dot {
            width: .75rem; height: .75rem; border-radius: .25rem;
            background-color: var(--brand);
        }
        .card {
            background-color: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 1rem;
            padding: 1.25rem;
        }
        @media (min-width: 40rem) { .card { padding: 1.75rem; } }
        h1 { margin: 0 0 .5rem; font-size: 1.5rem; line-height: 1.25; font-weight: 700; color: #18181b; letter-spacing: -.01em; }
        h2 { margin: 0 0 .75rem; font-size: 1.125rem; line-height: 1.3; font-weight: 600; color: #18181b; }
        p { margin: 0 0 1rem; }
        a { color: var(--brand); }
        .muted { color: #71717a; font-size: .875rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 100%; min-height: 2.75rem; padding: .75rem 1.25rem;
            border: 1px solid var(--brand); border-radius: .75rem;
            background-color: var(--brand); color: #fff;
            font: inherit; font-weight: 600; text-align: center; text-decoration: none;
            cursor: pointer;
        }
        .btn-secondary { background-color: #fff; color: #18181b; border-color: #d4d4d8; }
        .btn:focus-visible, a:focus-visible {
            outline: 2px solid var(--brand); outline-offset: 2px;
        }
        .notice {
            margin: 0 0 1rem; padding: .75rem .875rem;
            border-radius: .625rem; background-color: #f0fdf4; color: #166534;
        }
    </style>
</head>
<body>
<div class="wrap">
    <p style="margin: 0 0 1.25rem;">
        <a class="brand" href="{{ $portalUrl }}"><span class="dot"></span>{{ $portalName }}</a>
    </p>

    {{ $slot }}

    <p class="muted" style="margin: 1.25rem 0 0;">
        Fragen zu diesem Vorgang? Antworten Sie einfach auf unsere E-Mail — Ihre Nachricht landet
        direkt beim richtigen Vorgang.
    </p>
</div>
</body>
</html>
