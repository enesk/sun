{{-- Redaktionsprinzipien /ratgeber/redaktion im Theme sun-v2 (Vorlage elektrikerportal-impressum.html) --}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
@endphp

@section('title', 'So arbeitet unsere Redaktion | '.$portalName)
@section('meta_description', 'Wie die Ratgeber auf '.$portalName.' entstehen: Themenauswahl, Entstehungsweg, Quellen, Aktualisierung.')

@push('scripts')
<script type="application/ld+json">
{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'name' => 'So arbeitet unsere Redaktion',
    'url' => route('portal.blog.editorial'),
    'publisher' => [
        '@type' => 'Organization',
        'name' => $portalName,
        'url' => url('/'),
        'publishingPrinciples' => route('portal.blog.editorial'),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endpush

@section('content')
  @include('partials.sun.legal-page', [
      'title' => 'So arbeitet unsere Redaktion',
      'subtitle' => 'Redaktionsgrundsätze unserer Ratgeber',
      'crumbs' => [['Start', route('home')], ['Ratgeber', route('portal.blog.index')], ['Redaktion', null]],
      'footerLinks' => [['Impressum', route('portal.impressum')], ['Datenschutzerklärung', route('portal.datenschutz')]],
  ])
@endsection
