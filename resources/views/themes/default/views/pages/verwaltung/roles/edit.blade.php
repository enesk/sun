@extends('layouts.verwaltung')

@section('title', $role->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $role->name }}</h1>
        <p class="text-sm text-base-content/60 mt-1">Rolle und Berechtigungen bearbeiten</p>
    </div>

    @livewire('verwaltung.role-form', ['role' => $role])
@endsection
