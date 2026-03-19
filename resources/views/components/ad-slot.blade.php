@foreach($slots as $slot)
    @if($position === 'auto_ads')
        {!! $slot->code !!}
    @else
        @php
            $classes = \App\View\Components\AdSlot::deviceClasses($slot->device_visibility ?? []);
        @endphp
        <div @if($classes) class="{{ $classes }}" @endif>
            {!! $slot->code !!}
        </div>
    @endif
@endforeach
