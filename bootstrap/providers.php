<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    // Telescope wird NUR lokal registriert (AppServiceProvider::register)
    // — in Produktion ist das Paket nicht installiert (require-dev).
    TenancyServiceProvider::class,
];
