@extends('layouts.dashboard')

@section('title', 'Profil bearbeiten')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Profil bearbeiten</h1>
        <p class="text-sm text-base-content/60 mt-1">Aktualisieren Sie Ihre Firmendaten, Bilder und Kontaktinformationen.</p>
    </div>

    <div class="max-w-3xl">
        @livewire('portal.dashboard.profile-edit-form')
    </div>
@endsection
