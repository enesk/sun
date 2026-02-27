<div>
    {{-- ================================================================ --}}
    {{-- STATE: UPLOAD FORM (pending submission)                         --}}
    {{-- ================================================================ --}}
    @if(!$submitted)
        <div class="claim-verify-card">
            {{-- Dropzone zuerst — Action First, Info Second --}}
            <div class="claim-verify-dropzone"
                 x-data="claimUploadDropzone()"
                 x-on:dragover.prevent="dragover = true"
                 x-on:dragleave.prevent="dragover = false"
                 x-on:drop.prevent="handleDrop($event)"
                 :class="{ 'claim-verify-dropzone--active': dragover }"
                 role="region"
                 aria-label="Dokumente hochladen">

                <input type="file"
                       wire:model="documents"
                       multiple
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="claim-verify-dropzone__input"
                       id="claim-documents"
                       x-ref="fileInput"
                       aria-describedby="upload-hint">

                <label for="claim-documents" class="claim-verify-dropzone__label">
                    <div class="claim-verify-dropzone__icon">
                        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                    </div>
                    <p class="claim-verify-dropzone__text">
                        <strong>Dateien hierher ziehen</strong> oder klicken zum Auswählen
                    </p>
                    <p class="claim-verify-dropzone__hint" id="upload-hint">
                        PDF, JPG oder PNG — max. 10 MB pro Datei — max. 5 Dateien
                    </p>
                </label>
            </div>

            {{-- Info-Box: Akzeptierte Dokumente (unterhalb der Dropzone) --}}
            <details class="claim-verify-info" x-data="{ open: false }">
                <summary class="claim-verify-info__toggle" style="cursor: pointer; list-style: none; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink: 0; color: var(--portal-primary, #3B82F6);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="claim-verify-info__title" style="margin: 0;">Welche Dokumente werden akzeptiert?</span>
                </summary>
                <ul class="claim-verify-info__list" style="margin-top: 0.5rem;">
                    <li>Gewerbeanmeldung</li>
                    <li>Handelsregisterauszug</li>
                    <li>Handwerkskarte</li>
                    <li>IHK-/HWK-Bescheinigung</li>
                    <li>Geschäftsbrief/Rechnung mit Firmenname + Adresse</li>
                </ul>
            </details>

            {{-- Upload Progress --}}
            <div wire:loading wire:target="documents" class="claim-verify-progress">
                <div class="claim-verify-progress__bar">
                    <div class="claim-verify-progress__fill"></div>
                </div>
                <p class="claim-verify-progress__text">Dateien werden hochgeladen...</p>
            </div>

            {{-- Datei-Liste --}}
            @if(count($documents) > 0)
                <div class="claim-verify-files" role="list" aria-label="Hochgeladene Dateien">
                    @foreach($documents as $index => $doc)
                        <div class="claim-verify-file" role="listitem" wire:key="doc-{{ $index }}">
                            <div class="claim-verify-file__icon">
                                @if(str_contains($doc->getMimeType(), 'pdf'))
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                @else
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="claim-verify-file__info">
                                <span class="claim-verify-file__name">{{ $doc->getClientOriginalName() }}</span>
                                <span class="claim-verify-file__size">{{ number_format($doc->getSize() / 1024, 0) }} KB</span>
                            </div>
                            <button type="button"
                                    wire:click="removeDocument({{ $index }})"
                                    class="claim-verify-file__remove"
                                    aria-label="Datei entfernen: {{ $doc->getClientOriginalName() }}">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Validation Errors --}}
            @error('documents.*')
                <div class="claim-verify-error" role="alert">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    {{ $message }}
                </div>
            @enderror

            {{-- Optionaler Kommentar --}}
            <div class="claim-verify-comment">
                <label for="claim-comment" class="claim-verify-comment__label">
                    Optionaler Kommentar
                </label>
                <textarea wire:model="comment"
                          id="claim-comment"
                          rows="3"
                          placeholder="Z.B. Ich bin der Inhaber seit 2018, die Gewerbeanmeldung ist auf meinen Namen ausgestellt..."
                          class="claim-verify-comment__input"
                          maxlength="1000"></textarea>
                <p class="claim-verify-comment__hint">
                    Hilft uns bei der Prüfung — ist aber nicht verpflichtend.
                </p>
            </div>

            {{-- DSGVO-Hinweis --}}
            <div class="claim-verify-dsgvo">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span>
                    Ihre Dokumente werden verschlüsselt übertragen und nach 90 Tagen automatisch gelöscht.
                    <a href="{{ route('portal.datenschutz') }}" class="claim-verify-dsgvo__link">Datenschutzerklärung</a>
                </span>
            </div>

            {{-- Submit CTA --}}
            <button type="button"
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                    wire:target="submit"
                    class="claim-verify-submit"
                    {{ count($documents) === 0 ? 'disabled' : '' }}>
                <span wire:loading.remove wire:target="submit">
                    Dokumente einreichen
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </span>
                <span wire:loading wire:target="submit">
                    Wird eingereicht...
                </span>
            </button>
        </div>

    {{-- ================================================================ --}}
    {{-- STATE: SUCCESS — Dokumente eingereicht                          --}}
    {{-- ================================================================ --}}
    @else
        <div class="claim-verify-card claim-verify-card--success">
            {{-- Animated Checkmark --}}
            <div class="claim-verify-success__icon">
                <svg class="claim-verify-success__check" width="64" height="64" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" opacity="0.2"/>
                    <path d="M7.5 12.5L10.5 15.5L16.5 9.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="claim-verify-success__path"/>
                </svg>
            </div>

            <h2 class="claim-verify-success__title">Ihre Unterlagen sind eingereicht!</h2>
            <p class="claim-verify-success__text">
                Wir prüfen Ihre Dokumente innerhalb von <strong>48 Stunden</strong>. Sie erhalten eine E-Mail, sobald die Prüfung abgeschlossen ist.
            </p>

            {{-- Was passiert als Nächstes? --}}
            <div class="claim-verify-success__next">
                <h3 class="claim-verify-success__next-title">Was passiert jetzt?</h3>
                <div class="claim-verify-success__next-steps">
                    <div class="claim-verify-success__step">
                        <div class="claim-verify-success__step-icon claim-verify-success__step-icon--done">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span>Dokumente hochgeladen</span>
                    </div>
                    <div class="claim-verify-success__step">
                        <div class="claim-verify-success__step-icon claim-verify-success__step-icon--active">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span>Prüfung durch unser Team (bis 48h)</span>
                    </div>
                    <div class="claim-verify-success__step">
                        <div class="claim-verify-success__step-icon">3</div>
                        <span>Firma wird Ihnen zugewiesen + Trial startet</span>
                    </div>
                </div>
            </div>

            {{-- CTA: Zurück zum Portal --}}
            <a href="{{ route('home') }}" class="claim-verify-success__cta">
                Zurück zum Portal
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function claimUploadDropzone() {
        return {
            dragover: false,
            handleDrop(event) {
                this.dragover = false;
                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    this.$refs.fileInput.files = files;
                    this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }
    }
</script>
@endpush
