@foreach($slots as $slot)
    @php
        $classes = \App\View\Components\AdSlot::deviceClasses($slot->device_visibility ?? []);
    @endphp
    <div @if($classes) class="{{ $classes }}" @endif>
        {!! $slot->code !!}
    </div>
@endforeach
