@extends('layouts.verwaltung')

@section('title', $category->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $category->name }}</h1>
        <p class="text-sm text-base-content/60 mt-1">Kategorie bearbeiten</p>
    </div>

    @livewire('verwaltung.category-form', ['category' => $category])
@endsection
