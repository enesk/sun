{{--
    Hinweisband "review" (design/guide-dashboard.md §4.3, §6): je Regel ein Satz.
    Erwartet: $hints (list<string>)
--}}
<div class="content-refresh-strip content-refresh-strip--marked flex-col items-start" role="note">
    @foreach ($hints as $hint)
        <p>{{ $hint }}</p>
    @endforeach
</div>
