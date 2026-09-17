{{--
    Profil bearbeiten im Betriebsbereich, Theme sun-v2 (Vorlage profil-bearbeiten-elektrikerportal.html).
    Formular und Speichern: Livewire ProfileEditForm, Markup in livewire/portal/dashboard/profile-edit-form.
    Texte: lang/de/portal.php (owner.edit.*).
--}}
@extends('layouts.panel')

@section('title', __('portal.owner.edit.title'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.edit.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.edit.intro', ['portal' => ($currentTenant?->terms ?? \App\Support\Tenancy\TenantTerms::defaults())['portal']]) }}</p>
    </div>
    <a href="{{ $company->portal_url }}" class="btn-secondary" target="_blank" rel="noopener">
      <x-sun.icon name="external" class="icon" /> {{ __('portal.owner.overview.view_company') }}
    </a>
  </div>

  {{-- Upsell (#8): Free-Profile zeigen Werbung und Wettbewerber --}}
  @unlesspremiumFeature($company, \App\Enums\PremiumFeature::AdFree)
    <div class="mt-4 rounded-2xl bg-brand-50 p-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-base text-zinc-700 flex items-center gap-2"><x-sun.icon name="sparkles" class="icon text-brand" />{{ __('portal.owner.edit.upsell_competitors') }}</p>
      <a href="{{ route('portal.owner.premium') }}" class="btn-secondary">{{ __('portal.owner.edit.upsell_competitors_cta') }}</a>
    </div>
  @endpremiumFeature

  @livewire('portal.dashboard.profile-edit-form')

  {{-- Profil-Ausbau (#13): Leistungskatalog (ab Pro) und Projektreferenzen (Premium) --}}
  <livewire:portal.company.dashboard.services />
  <livewire:portal.company.dashboard.references />
@endsection
