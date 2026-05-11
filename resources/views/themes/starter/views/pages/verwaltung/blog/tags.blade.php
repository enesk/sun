@extends('layouts.verwaltung')

@section('title', 'Blog-Tags — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Blog-Tags</h1>
            <p class="dash-page-subtitle">Tags für Ratgeber-Artikel verwalten</p>
        </div>
        <div class="dash-page-actions">
            <a href="{{ route('verwaltung.blog.index') }}" class="dash-btn dash-btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                </svg>
                Zurück zum Ratgeber
            </a>
        </div>
    </div>

    {{-- Tag Manager (Livewire) --}}
    @livewire('verwaltung.blog-tag-manager')
@endsection
