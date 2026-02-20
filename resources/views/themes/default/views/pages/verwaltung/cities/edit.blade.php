@extends('layouts.verwaltung')

@section('title', $city->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $city->name }}</h1>
        <p class="text-sm text-base-content/60 mt-1">Stadt bearbeiten</p>
    </div>

    @livewire('verwaltung.city-form', ['city' => $city])
@endsection
