<div>
    <div class="dash-card" style="border-left: 3px solid var(--portal-primary);">
        <div style="padding: 1.5rem;">
            {{-- Section 1: Grunddaten --}}
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin-bottom: 1rem;">
                Artikeldaten
            </h3>

            <div class="dash-form-grid" style="gap: 1rem;">
                {{-- Title --}}
                <div>
                    <label class="dash-label" for="post-title">Titel *</label>
                    <input type="text"
                           wire:model.live.debounce.500ms="title"
                           id="post-title"
                           class="dash-input @error('title') dash-input-error @enderror"
                           placeholder="z.B. 10 Tipps für die Handwerkersuche">
                    @error('title')
                        <p class="dash-error-text">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Slug --}}
                <div>
                    <label class="dash-label" for="post-slug">URL-Slug *</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; color: var(--dash-text-muted); white-space: nowrap;">/ratgeber/</span>
                        <input type="text"
                               wire:model.live.debounce.500ms="slug"
                               id="post-slug"
                               class="dash-input @error('slug') dash-input-error @enderror"
                               placeholder="10-tipps-handwerkersuche"
                               style="flex: 1;">
                    </div>
                    @error('slug')
                        <p class="dash-error-text">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category + Status --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="dash-label" for="post-category">Kategorie</label>
                        <select wire:model="category_id"
                                id="post-category"
                                class="dash-select">
                            <option value="">Keine Kategorie</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="dash-label" for="post-status">Status</label>
                        <select wire:model="status"
                                id="post-status"
                                class="dash-select">
                            <option value="draft">Entwurf</option>
                            <option value="published">Veröffentlicht</option>
                            <option value="archived">Archiviert</option>
                        </select>
                    </div>
                </div>

                {{-- Published At --}}
                <div>
                    <label class="dash-label" for="post-published-at">Veröffentlichungsdatum (optional)</label>
                    <input type="datetime-local"
                           wire:model="published_at"
                           id="post-published-at"
                           class="dash-input"
                           style="max-width: 300px;">
                    <p style="font-size: 0.75rem; color: var(--dash-text-muted); margin-top: 0.25rem;">
                        Leer lassen = sofort bei Veröffentlichung
                    </p>
                </div>

                {{-- Excerpt --}}
                <div>
                    <label class="dash-label" for="post-excerpt">Auszug (optional)</label>
                    <textarea wire:model="excerpt"
                              id="post-excerpt"
                              class="dash-textarea @error('excerpt') dash-input-error @enderror"
                              rows="2"
                              placeholder="Kurze Zusammenfassung für die Übersichtsseite (max. 1000 Zeichen)"></textarea>
                    @error('excerpt')
                        <p class="dash-error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Section 2: Artikeltext --}}
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin: 1.5rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                Artikeltext
            </h3>

            <div>
                <label class="dash-label" for="post-body">Inhalt * (HTML erlaubt)</label>
                <textarea wire:model="body"
                          id="post-body"
                          class="dash-textarea @error('body') dash-input-error @enderror"
                          rows="16"
                          placeholder="Ihr Artikeltext... HTML-Tags sind erlaubt (h2, h3, p, ul, ol, li, strong, em, a, img)."
                          style="font-family: monospace; font-size: 0.8125rem; line-height: 1.6;"></textarea>
                @error('body')
                    <p class="dash-error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Section 3: Tags --}}
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin: 1.5rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                Tags
            </h3>

            <div>
                <label class="dash-label" for="post-tags">Tags (kommasepariert)</label>
                <input type="text"
                       wire:model="tagInput"
                       id="post-tags"
                       class="dash-input"
                       placeholder="z.B. Handwerker, Tipps, Renovierung">
                <p style="font-size: 0.75rem; color: var(--dash-text-muted); margin-top: 0.25rem;">
                    Neue Tags werden automatisch erstellt. Bestehende Tags:
                    @foreach($allTags as $tag)
                        <span style="display: inline-block; padding: 0.125rem 0.375rem; margin: 0.125rem; font-size: 0.6875rem; background: var(--dash-bg-tertiary, #f1f5f9); border-radius: 4px; cursor: pointer;"
                              wire:click="$set('tagInput', '{{ $tagInput ? $tagInput . ', ' . $tag->name : $tag->name }}')"
                              title="Klicken zum Hinzufügen">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </p>
            </div>

            {{-- Section 4: Beitragsbild --}}
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin: 1.5rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                Beitragsbild
            </h3>

            <div>
                @if($existingImage && !$removeImage)
                    <div style="margin-bottom: 1rem;">
                        <img src="{{ $existingImage }}" alt="Aktuelles Beitragsbild"
                             style="max-width: 300px; border-radius: 8px; border: 1px solid var(--dash-border-color, #e2e8f0);">
                        <button wire:click="$set('removeImage', true)"
                                class="dash-btn dash-btn-sm dash-btn-danger"
                                style="display: block; margin-top: 0.5rem;">
                            Bild entfernen
                        </button>
                    </div>
                @endif

                <input type="file"
                       wire:model="featured_image"
                       id="post-image"
                       accept="image/*"
                       class="dash-input"
                       style="padding: 0.5rem;">
                @error('featured_image')
                    <p class="dash-error-text">{{ $message }}</p>
                @enderror
                <p style="font-size: 0.75rem; color: var(--dash-text-muted); margin-top: 0.25rem;">
                    Max. 5 MB. Empfohlen: 1200x630px (16:9)
                </p>
            </div>

            {{-- Section 5: SEO --}}
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--dash-text-primary); margin: 1.5rem 0 1rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                SEO-Einstellungen
            </h3>

            <div class="dash-form-grid" style="gap: 1rem;">
                <div>
                    <label class="dash-label" for="post-meta-title">Meta-Titel (max. 160 Zeichen)</label>
                    <input type="text"
                           wire:model="meta_title"
                           id="post-meta-title"
                           class="dash-input @error('meta_title') dash-input-error @enderror"
                           placeholder="Wird in der Google-Suche angezeigt (leer = Artikeltitel)">
                    @error('meta_title')
                        <p class="dash-error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="dash-label" for="post-meta-desc">Meta-Beschreibung (max. 500 Zeichen)</label>
                    <textarea wire:model="meta_description"
                              id="post-meta-desc"
                              class="dash-textarea @error('meta_description') dash-input-error @enderror"
                              rows="2"
                              placeholder="Kurze Beschreibung für Google-Suchergebnisse"></textarea>
                    @error('meta_description')
                        <p class="dash-error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Actions --}}
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--dash-border-color, #e2e8f0);">
                <a href="{{ route('verwaltung.blog.index') }}" class="dash-btn dash-btn-sm">
                    Abbrechen
                </a>
                <button wire:click="saveAsDraft"
                        class="dash-btn dash-btn-sm">
                    Als Entwurf speichern
                </button>
                <button wire:click="publish"
                        class="dash-btn dash-btn-primary dash-btn-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5"/>
                    </svg>
                    {{ $isEdit ? 'Speichern & Veröffentlichen' : 'Veröffentlichen' }}
                </button>
            </div>
        </div>
    </div>
</div>
