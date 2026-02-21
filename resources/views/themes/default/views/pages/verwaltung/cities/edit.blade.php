@extends('layouts.verwaltung')

@section('title', $city->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $city->name }}</h1>
            <p class="dash-page-subtitle">Stadt bearbeiten</p>
        </div>
    </div>

    @livewire('verwaltung.city-form', ['city' => $city])
@endsection
