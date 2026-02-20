@extends('layouts.verwaltung')

@section('title', $company->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $company->name }}</h1>
        <p class="text-sm text-base-content/60 mt-1">Firmenprofil bearbeiten</p>
    </div>

    @livewire('verwaltung.company-form', ['company' => $company])
@endsection
