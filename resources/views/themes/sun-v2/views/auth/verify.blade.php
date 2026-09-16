{{--
    E-Mail bestaetigen im Theme sun-v2 (verification.notice, nur angemeldet).
    Daten: keine Variablen, die Adresse kommt aus auth()->user().

    "Link erneut senden" postet an verification.send. Die Route antwortet mit
    back()->with('sent') — der Schluessel steht ohne Wert in der Session,
    darum session()->has('sent').
--}}
@extends('layouts.sun')

@section('title', __('portal.auth.verify.title').' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-16 md:pb-16">
  <div class="card p-5 md:p-8 w-full max-w-md mx-auto">
    <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="mail" class="size-6" /></span>
    <h1 class="mt-4 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">{{ __('portal.auth.verify.title') }}</h1>
    <p class="mt-2 text-zinc-500">{{ __('portal.auth.verify.text', ['email' => auth()->user()?->email]) }}</p>

    @if(session()->has('sent'))
      <p class="mt-4 flex items-start gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700" role="status"><x-sun.icon name="check" class="size-5 shrink-0" />{{ __('portal.auth.verify.resent') }}</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
      @csrf
      <button type="submit" class="btn-primary w-full">{{ __('portal.auth.verify.submit') }}</button>
    </form>

    <div class="mt-6 pt-6 border-t border-zinc-200">
      <p class="text-sm font-medium text-zinc-900">{{ __('portal.auth.verify.help_title') }}</p>
      <ul class="mt-2 space-y-1 text-sm text-zinc-500 list-disc pl-5">
        <li>{{ __('portal.auth.verify.help_spam') }}</li>
        <li>{{ __('portal.auth.verify.help_typo') }}</li>
        <li>{{ __('portal.auth.verify.help_wait') }}</li>
      </ul>
      <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="btn-ghost w-full">{{ __('portal.layout.header.logout') }}</button>
      </form>
    </div>
  </div>
</div>
@endsection
