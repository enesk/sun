@extends('layouts.verwaltung')

@section('title', $company->name . ' bearbeiten — Verwaltung')

@section('content')
    {{-- Trial Banner --}}
    @livewire('verwaltung.trial-banner')

    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $company->name }}</h1>
            <p class="dash-page-subtitle">Firmenprofil bearbeiten</p>
        </div>
    </div>

    @livewire('verwaltung.company-form', ['company' => $company])
@endsection
