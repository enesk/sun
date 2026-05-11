@extends('layouts.verwaltung')

@section('title', $role->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $role->name }}</h1>
            <p class="dash-page-subtitle">Rolle und Berechtigungen bearbeiten</p>
        </div>
    </div>

    @livewire('verwaltung.role-form', ['role' => $role])
@endsection
