{{-- Impressum /impressum im Theme sun-v2 (Vorlage elektrikerportal-impressum.html), Text aus branding.impressum --}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
@endphp

@section('title', 'Impressum | '.$portalName)
@section('meta_description', 'Impressum und Anbieterkennzeichnung von '.$portalName.'.')
{{-- Ohne hinterlegten Text ist die Seite rechtlich unvollstaendig — dann nicht indexieren lassen. --}}
@empty($content)
    @section('meta_robots', 'noindex, follow')
@endempty

@section('content')
  @include('partials.sun.legal-page', [
      'title' => 'Impressum',
      'subtitle' => 'Angaben gemäß § 5 DDG',
      'crumbs' => [['Start', route('home')], ['Impressum', null]],
      'footerLinks' => [['Datenschutzerklärung', route('portal.datenschutz')], ['AGB', route('terms-of-service')]],
  ])
@endsection
