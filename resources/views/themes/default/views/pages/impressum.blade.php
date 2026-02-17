@extends('layouts.app')

@section('title', 'Impressum — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Impressum'],
    ]])

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto prose">
            <h1>Impressum</h1>

            @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.impressum'))
                {!! $currentTenant->getAttribute('branding.impressum') !!}
            @else
                <p class="text-base-content/50">Impressum wird noch eingerichtet.</p>
            @endif
        </div>
    </div>

@endsection
