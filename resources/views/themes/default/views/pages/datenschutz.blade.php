@extends('layouts.app')

@section('title', 'Datenschutz — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Datenschutz'],
    ]])

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto prose">
            <h1>Datenschutzerklärung</h1>

            @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.datenschutz'))
                {!! $currentTenant->getAttribute('branding.datenschutz') !!}
            @else
                <p class="text-base-content/50">Datenschutzerklärung wird noch eingerichtet.</p>
            @endif
        </div>
    </div>

@endsection
