{{-- Datenschutz /datenschutz im Theme sun-v2 (Vorlage elektrikerportal-impressum.html), Text aus branding.datenschutz --}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
@endphp

@section('title', 'Datenschutzerklärung | '.$portalName)
@section('meta_description', 'Datenschutzerklärung von '.$portalName.': welche Daten wir verarbeiten, wozu und welche Rechte du hast.')
@empty($content)
    @section('meta_robots', 'noindex, follow')
@endempty

@section('content')
  @include('partials.sun.legal-page', [
      'title' => 'Datenschutzerklärung',
      'subtitle' => 'Informationen nach Art. 13 DSGVO',
      'crumbs' => [['Start', route('home')], ['Datenschutz', null]],
      'footerLinks' => [['Impressum', route('portal.impressum')], ['AGB', route('terms-of-service')]],
  ])
@endsection
