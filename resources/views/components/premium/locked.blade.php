{{--
    Hinweis fuer gesperrte Funktionen im Betriebsbereich (#17):
    Schloss-Icon, Kurztext und Link zur Preisseite. Texte: lang/de/premium.php (locked.*).

    <x-premium.locked :feature="\App\Enums\PremiumFeature::Statistics" />
    <x-premium.locked feature="statistics" text="Eigener Kurztext" />

    Ohne text nennt der Hinweis die niedrigste Stufe, die das Feature enthaelt (config/premium.php).
--}}
@props(['feature' => null, 'text' => null])
@php
    $feature = is_string($feature) ? \App\Enums\PremiumFeature::tryFrom($feature) : $feature;
    $requiredTier = $feature instanceof \App\Enums\PremiumFeature
        ? collect(\App\Enums\PlanTier::cases())->first(fn (\App\Enums\PlanTier $tier) => $tier->hasFeature($feature))
        : null;
    $text ??= $requiredTier !== null
        ? __('premium.locked.text', ['plan' => __("premium.tiers.{$requiredTier->value}")])
        : __('premium.locked.label');
@endphp
<p {{ $attributes->class('flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-500') }}>
  <x-sun.icon name="lock" class="size-4 shrink-0 text-zinc-400" />
  <span class="sr-only">{{ __('premium.locked.label') }}:</span>
  <span>{{ $text }}</span>
  <a href="{{ route('portal.premium.pricing') }}" class="font-medium text-brand-700 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand rounded">{{ __('premium.locked.link') }}</a>
</p>
