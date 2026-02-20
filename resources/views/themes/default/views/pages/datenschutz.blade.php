@extends('layouts.app')

@section('title', 'Datenschutz — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Datenschutz'],
    ]])

    {{-- Mini-Hero --}}
    <div class="legal-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Datenschutzerklärung</h1>
            <p class="text-white/70 text-sm md:text-base">Transparenz und Sicherheit für Ihre Daten</p>
        </div>
    </div>

    {{-- Content Card --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="max-w-3xl mx-auto legal-card p-6 md:p-10">
            <div class="legal-content">
                @if(!empty($content))
                    {!! $content !!}
                @else
                    <p class="text-base-content/50 text-center py-12">Datenschutzerklärung wird noch eingerichtet.</p>
                @endif
            </div>
        </div>
    </div>

@endsection
