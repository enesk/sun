@extends('layouts.app')

@section('title', 'Impressum — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Impressum'],
    ]])

    {{-- Mini-Hero --}}
    <div class="legal-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                </svg>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Impressum</h1>
            <p class="text-white/70 text-sm md:text-base">Angaben gemäß § 5 TMG</p>
        </div>
    </div>

    {{-- Content Card --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="max-w-3xl mx-auto legal-card p-6 md:p-10">
            <div class="legal-content">
                @if(!empty($content))
                    {!! $content !!}
                @else
                    <p class="text-base-content/50 text-center py-12">Impressum wird noch eingerichtet.</p>
                @endif
            </div>
        </div>
    </div>

@endsection
