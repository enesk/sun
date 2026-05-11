@extends('layouts.dashboard')

@section('title', 'Profil bearbeiten')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Profil bearbeiten</h1>
            <p class="dash-page-subtitle">Aktualisieren Sie Ihre Firmendaten, Bilder und Kontaktinformationen.</p>
        </div>
    </div>

    <div class="max-w-3xl">
        @livewire('portal.dashboard.profile-edit-form')
    </div>
@endsection
