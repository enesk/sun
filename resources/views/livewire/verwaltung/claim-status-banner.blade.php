{{-- Claim-Verifizierung Status-Banner --}}
{{-- Zeigt den Status der Claim-Verifizierung im Dashboard --}}
{{-- Livewire-Component: Dimitri liefert $claimRequest mit status, rejection_reason --}}
<div>
    @if($claimRequest)
        {{-- ================================================================ --}}
        {{-- STATE: PENDING — Verifizierung läuft                            --}}
        {{-- ================================================================ --}}
        @if($claimRequest->status === 'pending')
            <div class="dash-claim-banner dash-claim-banner--pending" role="status" aria-label="Verifizierung läuft">
                <div class="dash-claim-banner__icon dash-claim-banner__icon--pending">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="dash-claim-banner__content">
                    <h3 class="dash-claim-banner__title">Verifizierung wird geprüft</h3>
                    <p class="dash-claim-banner__text">
                        Ihre Dokumente für <strong>{{ $companyName }}</strong> werden von unserem Team geprüft.
                        Wir melden uns innerhalb von <strong>48 Stunden</strong> per E-Mail.
                    </p>
                    @if($claimRequest->created_at)
                        <p class="dash-claim-banner__meta">
                            Eingereicht am {{ $claimRequest->created_at->format('d.m.Y \u\m H:i') }} Uhr
                        </p>
                    @endif
                </div>
            </div>

        {{-- ================================================================ --}}
        {{-- STATE: REJECTED — Abgelehnt, neue Dokumente möglich             --}}
        {{-- ================================================================ --}}
        @elseif($claimRequest->status === 'rejected')
            <div class="dash-claim-banner dash-claim-banner--rejected" role="alert" aria-label="Verifizierung abgelehnt">
                <div class="dash-claim-banner__icon dash-claim-banner__icon--rejected">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <div class="dash-claim-banner__content">
                    <h3 class="dash-claim-banner__title">Verifizierung nicht bestanden</h3>
                    <p class="dash-claim-banner__text">
                        Ihre Verifizierung für <strong>{{ $companyName }}</strong> wurde leider abgelehnt.
                    </p>
                    @if($claimRequest->rejection_reason)
                        <div class="dash-claim-banner__reason">
                            <strong>Grund:</strong> {{ $claimRequest->rejection_reason }}
                        </div>
                    @endif
                    <a href="{{ route('companies.suggest-edit', $companySlug) }}"
                       class="dash-claim-banner__cta">
                        Neue Dokumente einreichen
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        @endif
    @endif
</div>
