{{--
    Versionshistorie einer Prompt-Vorlage (#20).

    Jede Fassung bleibt mit Datum und Bearbeiter abrufbar. Zurueckholen
    geschieht ueber "Öffnen" und Speichern: das legt die alte Fassung als
    neue Version an, statt eine bestehende zu ueberschreiben.
--}}
<div class="overflow-x-auto">
    <table class="w-full text-content-table">
        <thead>
            <tr class="border-b border-line-strong text-content-label text-text-muted">
                <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Version') }}</th>
                <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Zustand') }}</th>
                <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Geändert') }}</th>
                <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Bearbeiter') }}</th>
                <th class="py-content-2 text-start font-medium"><span class="sr-only">{{ __('Aktion') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
            @foreach ($versions as $version)
                <tr>
                    <td class="py-content-2 pe-content-3 font-semibold text-text-strong">{{ $version->version }}</td>
                    <td class="py-content-2 pe-content-3">
                        <span class="content-status content-status--{{ $version->is_active ? 'published' : 'archived' }}">
                            {{ $version->is_active ? __('aktiv') : __('abgelöst') }}
                        </span>
                    </td>
                    <td class="py-content-2 pe-content-3 text-text-muted">{{ $version->updated_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="py-content-2 pe-content-3 text-text-muted">{{ $version->author?->name ?? '—' }}</td>
                    <td class="py-content-2 text-end">
                        @if ((int) $version->getKey() === $current)
                            <span class="text-content-label text-text-muted">{{ __('geöffnet') }}</span>
                        @else
                            <a
                                href="{{ $resource::getUrl('edit', ['record' => $version]) }}"
                                class="text-content-table font-medium text-content-700 underline underline-offset-4"
                            >{{ __('Öffnen') }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
