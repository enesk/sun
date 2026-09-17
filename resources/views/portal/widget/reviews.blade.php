{{--
    Bewertungs-Widget (#12) im iframe auf fremden Websites. Eigenstaendige Seite
    ohne Theme-Layout und ohne Vite-Assets; Farbe aus der Markenfarbe des Tenants.
    Der Link zum Portal ist bewusst ohne nofollow (Backlink).
--}}
@php
    $rating = (float) $company->rating;
    $filled = (int) round($rating);
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>{{ __('portal.widget.iframe_title', ['firma' => $company->name]) }}</title>
  <style>
    :root { --brand: {{ $color }}; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: transparent; }
    body { font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #27272a; }
    .w { border: 1px solid #e4e4e7; border-top: 4px solid var(--brand); border-radius: 12px; background: #fff; padding: 14px 16px; }
    .name { margin: 0; font-size: 15px; font-weight: 600; color: #18181b; }
    .sum { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .score { font-size: 22px; font-weight: 700; color: #18181b; }
    .stars { color: #d4d4d8; letter-spacing: 1px; font-size: 16px; }
    .stars .on { color: #f59e0b; }
    .count { color: #71717a; }
    ul { list-style: none; margin: 12px 0 0; padding: 0; }
    li { border-top: 1px solid #f4f4f5; padding: 8px 0; }
    .meta { display: flex; justify-content: space-between; gap: 8px; color: #71717a; font-size: 12px; }
    .author { font-weight: 600; color: #3f3f46; }
    .text { margin: 2px 0 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .all { display: block; margin-top: 10px; text-align: center; padding: 8px 12px; border-radius: 8px; background: var(--brand); color: #fff; font-weight: 600; text-decoration: none; }
    .all:hover, .all:focus-visible { filter: brightness(.9); }
    .empty { margin: 10px 0 0; color: #71717a; }
  </style>
</head>
<body>
  <div class="w">
    <p class="name">{{ $company->name }}</p>
    <div class="sum">
      @if($company->rating_count > 0)
        <span class="score">{{ number_format($rating, 1, ',', '.') }}</span>
      @endif
      <span class="stars" role="img" aria-label="{{ __('portal.widget.stars', ['anzahl' => $filled]) }}">@for($i = 1; $i <= 5; $i++)<span @class(['on' => $i <= $filled])>★</span>@endfor</span>
      <span class="count">{{ trans_choice('portal.profile.reviews.count', $company->rating_count, ['anzahl' => number_format($company->rating_count, 0, ',', '.')]) }}</span>
    </div>

    @if($reviews->isNotEmpty())
      <ul>
        @foreach($reviews as $review)
          @php($reviewStars = (int) round((float) $review->rating))
          <li>
            <div class="meta">
              <span><span class="author">{{ $review->author_name ?: __('portal.profile.reviews.anonymous') }}</span> · <span class="stars" role="img" aria-label="{{ __('portal.widget.stars', ['anzahl' => $reviewStars]) }}">@for($i = 1; $i <= 5; $i++)<span @class(['on' => $i <= $reviewStars])>★</span>@endfor</span></span>
              <span>{{ $review->created_at->locale('de')->translatedFormat('F Y') }}</span>
            </div>
            @if($review->title || $review->body)
              <p class="text">{{ $review->title ? "{$review->title}: " : '' }}{{ $review->body }}</p>
            @endif
          </li>
        @endforeach
      </ul>
    @else
      <p class="empty">{{ __('portal.widget.empty') }}</p>
    @endif

    <a class="all" href="{{ $profileUrl }}" target="_blank" rel="noopener">{{ __('portal.widget.all', ['portal' => $portalName]) }}</a>
  </div>
  <script>
    (function () {
      function report() {
        parent.postMessage({ type: 'sun-review-widget:height', height: document.documentElement.scrollHeight }, '*');
      }
      window.addEventListener('load', report);
      window.addEventListener('resize', report);
      report();
    })();
  </script>
</body>
</html>
