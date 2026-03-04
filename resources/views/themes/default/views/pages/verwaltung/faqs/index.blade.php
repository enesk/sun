@extends('layouts.verwaltung')

@section('title', 'FAQ verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">FAQ</h1>
            <p class="dash-page-subtitle">Häufig gestellte Fragen für das Portal verwalten</p>
        </div>
    </div>

    {{-- FAQ Manager (Livewire) --}}
    @livewire('verwaltung.faq-manager')
@endsection
