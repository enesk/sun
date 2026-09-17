<?php

return [
    App\Providers\ConfigProvider::class,
    App\Providers\ContentServiceProvider::class,
    App\Providers\BladeProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\PremiumServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\DashboardPanelProvider::class,
    App\Providers\Filament\ContentPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,
];
