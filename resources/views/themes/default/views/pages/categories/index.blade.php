@extends('layouts.app')

@section('title', 'Kategorien — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Alle Kategorien im Überblick. Finden Sie Unternehmen nach Branchen geordnet.')

@section('content')

    {{-- Schema.org: CollectionPage für Kategorieübersicht --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Kategorien',
        'description' => 'Alle Kategorien im Überblick. Finden Sie Unternehmen nach Branchen geordnet.',
        'url' => route('portal.categories.index'),
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => $currentTenant->name ?? config('app.name'),
            'url' => route('home'),
        ],
        'numberOfItems' => $categories->count(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Kategorien'],
    ]])

    <div class="container mx-auto px-4 pb-12">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-base-content">Alle Kategorien</h1>
            <p class="text-sm text-base-content/60 mt-1">
                {{ $categories->count() }} Kategorien mit {{ number_format($totalCompanies) }} Unternehmen
            </p>
        </div>

        @if($categories->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($categories as $category)
                    <a href="{{ route('portal.categories.show', $category->slug) }}"
                       class="group bg-base-100 rounded-xl border border-base-200 p-6 hover:shadow-md hover:border-transparent transition-all">

                        <div class="flex items-start gap-4">
                            {{-- Icon --}}
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-portal-primary/10">
                                @if($category->icon)
                                    <span class="text-2xl">{{ $category->icon }}</span>
                                @else
                                    <span class="text-lg font-bold text-portal-primary-dark">{{ mb_substr($category->name, 0, 1) }}</span>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-base-content group-hover:underline truncate">
                                    {{ $category->name }}
                                </h2>
                                <p class="text-sm text-base-content/50 mt-0.5">
                                    {{ $category->companies_count }} {{ $category->companies_count === 1 ? 'Unternehmen' : 'Unternehmen' }}
                                </p>

                                {{-- Unterkategorien --}}
                                @if($category->children->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach($category->children->take(4) as $child)
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-base-200 text-base-content/60">
                                                {{ $child->name }}
                                            </span>
                                        @endforeach
                                        @if($category->children->count() > 4)
                                            <span class="inline-block px-2 py-0.5 text-xs text-base-content/40">
                                                +{{ $category->children->count() - 4 }} weitere
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            @include('components.empty-state', [
                'icon' => 'folder',
                'title' => 'Keine Kategorien',
                'message' => 'Es wurden noch keine Kategorien angelegt.',
                'action' => ['url' => route('home'), 'label' => 'Zur Startseite', 'icon' => 'back'],
            ])
        @endif
    </div>

@endsection
