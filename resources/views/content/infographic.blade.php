{{--
    SVG-Infografik der Key-Facts-Tabelle (#16).

    Reines SVG, ohne externe Referenzen und ohne eingebettete Schrift: die
    Datei wird als eigenstaendiges Bild ausgeliefert und muss deshalb ohne
    Netzwerkzugriff auskommen. Die Schriftfamilie ist eine Systemkette; ein
    Font-Import waere ein zweiter Request und in vielen Viewern wirkungslos.

    Alle Farben kommen als geprueftes Hex aus dem InfographicRenderer, alle
    Texte durch {{ }} und damit escaped. Direkt hier darf nichts unescaped
    ausgegeben werden — die Datei liegt oeffentlich.
--}}
@php
    $rowHeight = 62;
    $tableTop = 210;
    $padding = 64;
    $valueX = $width - $padding;
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" role="img" aria-label="{{ $title }}">
    <title>{{ $title }}</title>
    <defs>
        <linearGradient id="kopf" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="{{ $colors['primary'] }}"/>
            <stop offset="100%" stop-color="{{ $colors['secondary'] }}"/>
        </linearGradient>
    </defs>

    <rect x="0" y="0" width="{{ $width }}" height="{{ $height }}" fill="{{ $colors['surface'] }}"/>
    <rect x="0" y="0" width="{{ $width }}" height="150" fill="url(#kopf)"/>
    <rect x="0" y="150" width="{{ $width }}" height="6" fill="{{ $colors['accent'] }}"/>

    <text x="{{ $padding }}" y="72" font-family="Helvetica, Arial, sans-serif" font-size="38" font-weight="700" fill="#ffffff">{{ \Illuminate\Support\Str::limit($title, 58) }}</text>
    <text x="{{ $padding }}" y="114" font-family="Helvetica, Arial, sans-serif" font-size="22" fill="#ffffff" opacity="0.85">{{ $subtitle }}</text>

    @foreach ($rows as $index => $row)
        @php
            $y = $tableTop + $index * $rowHeight;
        @endphp
        @if ($index % 2 === 0)
            <rect x="{{ $padding - 20 }}" y="{{ $y - 38 }}" width="{{ $width - 2 * $padding + 40 }}" height="{{ $rowHeight }}" fill="{{ $colors['accent'] }}" opacity="0.08"/>
        @endif
        <text x="{{ $padding }}" y="{{ $y }}" font-family="Helvetica, Arial, sans-serif" font-size="24" fill="{{ $colors['text'] }}">{{ \Illuminate\Support\Str::limit($row['label'], 52) }}</text>
        <text x="{{ $valueX }}" y="{{ $y }}" text-anchor="end" font-family="Helvetica, Arial, sans-serif" font-size="26" font-weight="700" fill="{{ $colors['primary'] }}">{{ \Illuminate\Support\Str::limit($row['value'], 24) }}</text>
    @endforeach

    @if ($caption)
        <text x="{{ $padding }}" y="{{ $height - 42 }}" font-family="Helvetica, Arial, sans-serif" font-size="18" fill="{{ $colors['muted'] }}">Quelle: {{ $caption }}</text>
    @endif
    <text x="{{ $valueX }}" y="{{ $height - 42 }}" text-anchor="end" font-family="Helvetica, Arial, sans-serif" font-size="18" fill="{{ $colors['muted'] }}">{{ $subtitle }}</text>
</svg>
