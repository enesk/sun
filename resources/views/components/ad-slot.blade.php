@foreach($slots as $slot)
    @if($position === 'auto_ads')
        {!! $slot->code !!}
    @else
        @php
            $deviceClasses = \App\View\Components\AdSlot::deviceClasses($slot->device_visibility ?? []);
            $clsClasses = \App\View\Components\AdSlot::clsContainerClasses($position);
            $hasCode = !empty(trim($slot->code ?? ''));
            $isLazy = \App\View\Components\AdSlot::isLazy($position);
        @endphp
        <div class="{{ collect([$deviceClasses, $clsClasses, 'bg-base-200/30'])->filter()->implode(' ') }}"
            @if($isLazy && $hasCode) data-lazy-ad @endif>
            @if($hasCode)
                <span class="block text-[10px] uppercase tracking-wider text-base-content/40">Anzeige</span>
            @endif
            @if($isLazy && $hasCode)
                <template data-ad-code>
                    {!! $slot->code !!}
                </template>
            @else
                {!! $slot->code !!}
            @endif
        </div>
    @endif
@endforeach

@once
@push('scripts')
<script>
(function(){
    var els = document.querySelectorAll('[data-lazy-ad]');
    if (!els.length) return;
    var io = new IntersectionObserver(function(entries, observer) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;
            var tpl = entry.target.querySelector('template[data-ad-code]');
            if (tpl) {
                var frag = tpl.content.cloneNode(true);
                frag.querySelectorAll('iframe').forEach(function(iframe) {
                    iframe.setAttribute('loading', 'lazy');
                });
                tpl.parentNode.replaceChild(frag, tpl);
            }
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px 200px 0px' });
    els.forEach(function(el) { io.observe(el); });
})();
</script>
@endpush
@endonce
