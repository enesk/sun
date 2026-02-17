<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('components.layouts.partials.head')

    {{-- Tenant CSS Variables (Portal-Branding) --}}
    {!! $tenantStyles ?? '' !!}
</head>
<body class="min-h-screen bg-base-100 antialiased" style="font-family: var(--portal-font-family, Inter, ui-sans-serif, system-ui, sans-serif);">
    <div id="app">
        <div class="w-full min-h-screen">
            <div class="flex flex-col md:flex-row min-h-screen">

                {{-- LEFT: Form Area --}}
                <div class="w-full md:w-[55%] flex flex-col relative">
                    {{-- Logo --}}
                    <div class="p-6">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 group">
                            @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.logo_path'))
                                <img src="{{ asset($currentTenant->getAttribute('branding.logo_path')) }}"
                                     alt="{{ $currentTenant->name ?? config('app.name') }}"
                                     class="h-8 w-auto transition-transform duration-300 group-hover:scale-105">
                            @else
                                <span class="text-xl font-bold text-portal-primary-dark">
                                    {{ $currentTenant->name ?? config('app.name') }}
                                </span>
                            @endif
                        </a>
                    </div>

                    {{-- Form Content --}}
                    {{ $left }}
                </div>

                {{-- RIGHT: Branding Panel --}}
                <div class="hidden md:flex md:w-[45%] min-h-screen relative overflow-hidden flex-col"
                     style="background: linear-gradient(135deg, var(--portal-primary, #3B82F6), var(--portal-secondary, #1E40AF) 50%, var(--portal-primary-dark, #1E3A5F));">

                    {{-- Mesh Gradient Blobs --}}
                    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
                        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-20"
                             style="background: radial-gradient(circle, var(--portal-accent, #F59E0B), transparent 70%); animation: float-blob-1 20s ease-in-out infinite;"></div>
                        <div class="absolute bottom-1/4 -left-16 w-80 h-80 rounded-full opacity-15"
                             style="background: radial-gradient(circle, rgba(255,255,255,0.3), transparent 70%); animation: float-blob-2 25s ease-in-out infinite;"></div>
                        <div class="absolute top-1/2 right-1/4 w-64 h-64 rounded-full opacity-10"
                             style="background: radial-gradient(circle, var(--portal-accent, #F59E0B), transparent 70%); animation: float-blob-3 18s ease-in-out infinite;"></div>
                    </div>

                    {{-- Back Home Link --}}
                    <div class="relative z-10 flex justify-end p-6">
                        <a href="{{ route('home') }}"
                           class="inline-flex items-center gap-1 text-sm text-white/70 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            {{ __('Zurück zum Portal') }}
                        </a>
                    </div>

                    {{-- Right Panel Content --}}
                    <div class="relative z-10 flex-1 flex flex-col justify-center">
                        {{ $right }}
                    </div>
                </div>

            </div>
        </div>

        @include('components.layouts.partials.tail')
    </div>

    <style>
        @keyframes float-blob-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.1); }
            66% { transform: translate(-15px, 15px) scale(0.95); }
        }
        @keyframes float-blob-2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-20px, 30px) scale(1.05); }
            66% { transform: translate(20px, -10px) scale(0.9); }
        }
        @keyframes float-blob-3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(15px, 25px) scale(1.08); }
        }
    </style>
</body>
</html>
