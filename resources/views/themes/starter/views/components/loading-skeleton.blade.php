{{-- Loading Skeleton Component --}}
{{-- Usage: @include('components.loading-skeleton', ['type' => 'company-card', 'count' => 6, 'layout' => 'grid']) --}}
@php
    $type = $type ?? 'company-card';
    $count = $count ?? 6;
    $layout = $layout ?? ($themeOptions['listing_layout'] ?? 'grid');
@endphp

@if($type === 'company-card')
    @if($layout === 'grid')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @for($i = 0; $i < $count; $i++)
                <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden animate-pulse" aria-hidden="true">
                    {{-- Image skeleton --}}
                    <div class="aspect-[16/10] bg-base-200"></div>
                    <div class="p-4 space-y-3">
                        {{-- Badge --}}
                        <div class="flex gap-1">
                            <div class="h-5 w-16 bg-base-200 rounded"></div>
                        </div>
                        {{-- Title --}}
                        <div class="h-5 bg-base-200 rounded w-3/4"></div>
                        {{-- Rating --}}
                        <div class="flex gap-1">
                            @for($s = 0; $s < 5; $s++)
                                <div class="w-4 h-4 bg-base-200 rounded"></div>
                            @endfor
                        </div>
                        {{-- Description --}}
                        <div class="space-y-1.5">
                            <div class="h-3 bg-base-200 rounded w-full"></div>
                            <div class="h-3 bg-base-200 rounded w-2/3"></div>
                        </div>
                        {{-- Categories --}}
                        <div class="flex gap-1">
                            <div class="h-5 w-20 bg-base-200 rounded-full"></div>
                            <div class="h-5 w-16 bg-base-200 rounded-full"></div>
                        </div>
                        {{-- Address --}}
                        <div class="h-3 bg-base-200 rounded w-1/2"></div>
                    </div>
                </div>
            @endfor
        </div>
    @else
        {{-- List layout skeletons --}}
        <div class="space-y-4">
            @for($i = 0; $i < $count; $i++)
                <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden animate-pulse" aria-hidden="true">
                    <div class="flex flex-col sm:flex-row">
                        <div class="sm:w-40 md:w-48 shrink-0">
                            <div class="aspect-[4/3] sm:aspect-square bg-base-200"></div>
                        </div>
                        <div class="flex-1 p-4 space-y-3">
                            <div class="flex justify-between">
                                <div class="h-5 bg-base-200 rounded w-1/2"></div>
                                <div class="h-5 w-16 bg-base-200 rounded"></div>
                            </div>
                            <div class="flex gap-1">
                                @for($s = 0; $s < 5; $s++)
                                    <div class="w-4 h-4 bg-base-200 rounded"></div>
                                @endfor
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-3 bg-base-200 rounded w-full"></div>
                                <div class="h-3 bg-base-200 rounded w-3/4"></div>
                            </div>
                            <div class="flex gap-1">
                                <div class="h-5 w-20 bg-base-200 rounded-full"></div>
                                <div class="h-5 w-16 bg-base-200 rounded-full"></div>
                            </div>
                            <div class="h-3 bg-base-200 rounded w-1/3"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>
    @endif

@elseif($type === 'detail')
    {{-- Company Detail Page Skeleton --}}
    <div class="animate-pulse space-y-6" aria-hidden="true">
        {{-- Hero image --}}
        <div class="h-64 bg-base-200 rounded-xl"></div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-4">
                <div class="h-8 bg-base-200 rounded w-2/3"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-base-200 rounded w-full"></div>
                    <div class="h-4 bg-base-200 rounded w-full"></div>
                    <div class="h-4 bg-base-200 rounded w-3/4"></div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="h-48 bg-base-200 rounded-xl"></div>
                <div class="h-32 bg-base-200 rounded-xl"></div>
            </div>
        </div>
    </div>

@elseif($type === 'sidebar')
    {{-- Sidebar Skeleton --}}
    <div class="animate-pulse space-y-4" aria-hidden="true">
        <div class="h-5 bg-base-200 rounded w-1/2 mb-3"></div>
        @for($i = 0; $i < 6; $i++)
            <div class="flex justify-between items-center">
                <div class="h-4 bg-base-200 rounded w-2/3"></div>
                <div class="h-4 bg-base-200 rounded w-8"></div>
            </div>
        @endfor
    </div>
@endif
