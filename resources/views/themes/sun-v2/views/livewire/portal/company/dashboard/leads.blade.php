{{--
    Exklusive Anfragen im Betriebsbereich (#9), Logik in
    App\Livewire\Portal\Company\Dashboard\Leads. Aufbau wie die Anfragen-Liste
    darunter (Filter-Pills, Karten-Liste); die Detailansicht klappt im Kasten auf.
    Texte: lang/de/portal.php (owner.leads.*, owner.inquiries.show.*).
    Ohne Freischaltung und ohne alte Anfragen bleibt die Komponente leer.
--}}
@php
    use App\Constants\CompanyLeadStatus;
    use App\Livewire\Portal\Company\Dashboard\Leads;

    $exhausted = $quota !== null && $quota['quota'] !== null && $quota['remaining'] === 0;
    $statusClass = fn (CompanyLeadStatus $status): string => match ($status) {
        CompanyLeadStatus::NEW => 'pill-brand',
        CompanyLeadStatus::CLOSED => 'pill bg-emerald-50 text-emerald-700',
        default => 'pill',
    };
@endphp
<div>
@if($canExclusive || $total > 0)
  <section class="mt-6" aria-labelledby="exklusive-anfragen-titel">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <h2 id="exklusive-anfragen-titel" class="text-xl font-semibold text-zinc-900">{{ __('portal.owner.leads.title') }}</h2>
      @if($quota !== null)
        <p class="text-sm text-zinc-500">
          {{ $quota['quota'] === null
              ? __('portal.owner.overview.lead_quota.unlimited', ['genutzt' => $quota['used']])
              : __('portal.owner.overview.lead_quota.used', ['genutzt' => min($quota['used'], $quota['quota']), 'kontingent' => $quota['quota']]) }}
        </p>
      @endif
    </div>
    <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.leads.intro') }}</p>

    @if($exhausted)
      <div class="mt-4 card border-amber-200 bg-amber-50 p-4 md:p-5" role="status">
        <p class="text-base text-amber-900">{{ __('portal.owner.leads.exhausted') }}</p>
        @if($canUpgrade)
          <a href="{{ route('portal.owner.premium') }}" class="btn-primary mt-3">{{ __('portal.owner.overview.lead_quota.upsell') }}</a>
        @endif
      </div>
    @elseif(! $canExclusive)
      <div class="mt-4 card p-4 md:p-5" role="status">
        <p class="text-base text-zinc-700">{{ __('portal.owner.leads.locked') }}</p>
        <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-3">{{ __('portal.owner.leads.locked_cta') }}</a>
      </div>
    @endif

    @if($total > 0)
      <nav aria-label="{{ __('portal.owner.inquiries.filter_label') }}" class="mt-4 -mx-4 px-4 flex gap-2 overflow-x-auto pb-1 md:mx-0 md:px-0">
        <button type="button" wire:click="filter('')"
                class="{{ $filter === null ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4"
                @if($filter === null) aria-current="true" @endif>{{ __('portal.owner.inquiries.filter_all') }} · {{ $total }}</button>
        @foreach(CompanyLeadStatus::cases() as $status)
          <button type="button" wire:click="filter('{{ $status->value }}')"
                  class="{{ $filter === $status ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4"
                  @if($filter === $status) aria-current="true" @endif>{{ $status->label() }} · {{ $counts[$status->value] ?? 0 }}</button>
        @endforeach
      </nav>
    @endif

    @if($selectedLead)
      @php
          $detailName = $selectedLead->contact_name ?: __('portal.owner.inquiries.unknown_name');
      @endphp
      <article class="mt-4 card p-5 md:p-6" aria-labelledby="exklusive-anfrage-detail" wire:key="lead-detail-{{ $selectedLead->id }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 id="exklusive-anfrage-detail" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.title', ['name' => $detailName]) }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.received', ['datum' => $selectedLead->created_at->format('d.m.Y'), 'uhrzeit' => $selectedLead->created_at->format('H:i')]) }}</p>
          </div>
          <button type="button" class="btn-ghost px-3" wire:click="closeDetail">{{ __('portal.request.close') }}</button>
        </div>

        <div class="mt-5 grid gap-6 md:grid-cols-2">
          <div>
            <h4 class="text-base font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.contact') }}</h4>
            @if($selectedLead->isPurged())
              <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.leads.purged') }}</p>
            @else
              <dl class="mt-2 space-y-1 text-base">
                <div class="flex gap-2"><dt class="w-24 shrink-0 text-zinc-500">{{ __('portal.owner.inquiries.show.name') }}</dt><dd class="text-zinc-900">{{ $selectedLead->contact_name ?: '–' }}</dd></div>
                <div class="flex gap-2"><dt class="w-24 shrink-0 text-zinc-500">{{ __('portal.owner.inquiries.show.phone') }}</dt><dd class="text-zinc-900">{{ $selectedLead->contact_phone ?: '–' }}</dd></div>
                <div class="flex gap-2"><dt class="w-24 shrink-0 text-zinc-500">{{ __('portal.owner.inquiries.show.email') }}</dt><dd class="text-zinc-900 break-all">{{ $selectedLead->contact_email ?: '–' }}</dd></div>
              </dl>
              <div class="mt-3 flex flex-wrap gap-2">
                @if($selectedLead->contact_phone)
                  <a href="tel:{{ preg_replace('/[^0-9+]/', '', $selectedLead->contact_phone) }}" class="btn-primary"><x-sun.icon name="phone" class="icon" />{{ __('portal.owner.inquiries.show.call') }}</a>
                @endif
                @if($selectedLead->contact_email)
                  <a href="mailto:{{ $selectedLead->contact_email }}?subject={{ rawurlencode(__('portal.owner.inquiries.show.reply_subject', ['firma' => $company->name])) }}" class="btn-secondary">{{ __('portal.owner.inquiries.show.write') }}</a>
                @endif
              </div>
            @endif
          </div>

          <div>
            <h4 class="text-base font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.answers') }}</h4>
            @if(empty($selectedLead->answers))
              <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.inquiries.show.no_answers') }}</p>
            @else
              <dl class="mt-2 space-y-2 text-base">
                @foreach($selectedLead->answers as $answer)
                  <div>
                    <dt class="text-sm text-zinc-500">{{ $answer['label'] }}</dt>
                    <dd class="text-zinc-900 whitespace-pre-line">{{ $answer['value_label'] ?? (is_array($answer['value']) ? implode(', ', $answer['value']) : $answer['value']) }}</dd>
                  </div>
                @endforeach
              </dl>
            @endif
          </div>
        </div>

        <div class="mt-6 border-t border-zinc-200 pt-4">
          <h4 class="text-base font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.status') }}</h4>
          <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.status_hint') }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            @foreach(CompanyLeadStatus::cases() as $status)
              @if($selectedLead->status === $status)
                <span class="{{ $statusClass($status) }} min-h-11 px-4 inline-flex items-center">{{ $status->label() }} ({{ __('portal.owner.inquiries.show.status_current') }})</span>
              @else
                <button type="button" class="btn-secondary" wire:click="setStatus({{ $selectedLead->id }}, '{{ $status->value }}')" wire:loading.attr="disabled">{{ __('portal.owner.inquiries.show.status_set', ['status' => $status->label()]) }}</button>
              @endif
            @endforeach
          </div>
        </div>
      </article>
    @endif

    @if($leads->isEmpty())
      @if($canExclusive)
        <div class="mt-4 card p-5 text-base text-zinc-500">{{ $total === 0 ? __('portal.owner.leads.empty') : __('portal.owner.inquiries.empty_filter') }}</div>
      @endif
    @else
      <div class="mt-4 card overflow-hidden">
        <ul class="divide-y divide-zinc-200">
          @foreach($leads as $lead)
            @php
                $isNew = $lead->read_at === null;
                $name = $lead->isPurged() ? __('portal.owner.leads.purged_name') : ($lead->contact_name ?: __('portal.owner.inquiries.unknown_name'));
            @endphp
            <li wire:key="lead-{{ $lead->id }}">
              <button type="button" wire:click="select({{ $lead->id }})"
                      @class(['w-full text-left grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 px-5 py-4 md:grid-cols-[7rem_minmax(0,1fr)_minmax(0,1.5fr)_8rem] md:items-center md:gap-4 md:px-6 hover:bg-zinc-50 focus-visible:outline-hidden focus-visible:bg-brand-50', 'bg-brand-50' => $selectedLead?->id === $lead->id])
                      aria-label="{{ __('portal.owner.inquiries.open', ['name' => $name]) }}">
                <span class="order-3 col-span-2 text-sm text-zinc-500 md:order-none md:col-span-1">
                  <time datetime="{{ $lead->created_at->toIso8601String() }}" title="{{ $lead->created_at->format('d.m.Y H:i') }}">{{ $lead->created_at->diffInMinutes() < 1 ? __('portal.owner.inquiries.just_now') : $lead->created_at->locale('de')->diffForHumans() }}</time>
                </span>
                <span class="order-1 flex min-w-0 items-center gap-2 md:order-none {{ $isNew ? 'font-semibold text-zinc-900' : 'text-zinc-700' }}">
                  @if($isNew)<span class="size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span><span class="sr-only">{{ __('portal.owner.inquiries.new_badge') }}:</span>@endif
                  <span class="truncate">{{ $name }}</span>
                </span>
                <span class="order-4 col-span-2 truncate text-base md:order-none md:col-span-1 {{ $isNew ? 'font-semibold text-zinc-900' : 'text-zinc-700' }}">{{ Leads::subject($lead) ?? __('portal.owner.inquiries.no_subject') }}</span>
                <span class="order-2 md:order-none"><span class="{{ $statusClass($lead->status) }} text-xs whitespace-nowrap">{{ $lead->status->label() }}</span></span>
              </button>
            </li>
          @endforeach
        </ul>
      </div>
      @if($hasMore)
        <button type="button" class="btn-secondary mt-4 w-full" wire:click="loadMore" wire:loading.attr="disabled">{{ __('portal.owner.leads.load_more') }}</button>
      @endif
    @endif
  </section>

  <h2 class="mt-10 text-xl font-semibold text-zinc-900">{{ __('portal.owner.leads.marketplace_title') }}</h2>
@endif
</div>
