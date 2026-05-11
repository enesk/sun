@extends('layouts.verwaltung')

@section('title', $category->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $category->name }}</h1>
            <p class="dash-page-subtitle">Kategorie bearbeiten</p>
        </div>
    </div>

    @livewire('verwaltung.category-form', ['category' => $category])
@endsection
