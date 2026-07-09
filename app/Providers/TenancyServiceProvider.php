<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * @return array<class-string, array<int, mixed>>
     */
    public function events(): array
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Seedet Rollen & Berechtigungen (Database\Seeders\TenantDatabaseSeeder,
                    // konfiguriert in config/tenancy.php -> seeder_parameters).
                    Jobs\SeedDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // Lokal synchron; in Produktion auf true stellen (Queue).
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            // SICHERHEITSENTSCHEIDUNG: Die automatische DB-Löschung ist hier bewusst
            // ENTFERNT. Tenants nutzen SoftDeletes — auch ein Soft Delete feuert das
            // Eloquent-"deleted"-Event und würde sonst die Tenant-Datenbank physisch
            // vernichten. Die DB wird ausschließlich über die explizite
            // App\Actions\Tenancy\DeleteTenantAction (forceDelete + DeleteDatabase-Job) entfernt.
            Events\TenantDeleted::class => [],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
        $this->configureUniversalLivewireUpdateRoute();
    }

    /**
     * Livewire-Update-Route tenancy-fähig machen.
     *
     * WARUM: Livewire registriert seine POST-Update-Route selbst — nur mit
     * 'web'-Middleware, OHNE Tenancy. Auf Tenant-Domains lag die Session
     * dadurch beim GET in der Tenant-DB, beim Livewire-POST wurde sie aber
     * in der ZENTRALEN DB gesucht → CSRF-Fehler 419 („This page has
     * expired") in Endlosschleife; Login im Tenant-Panel unmöglich.
     *
     * Lösung (stancl-Doku "Integrations > Livewire"):
     * - Gleicher Pfad wie die Default-Route (ersetzt sie in der Collection),
     *   zusätzlich InitializeTenancyByDomain.
     * - 'universal'-Flag + UniversalRoutes-Feature (config/tenancy.php):
     *   Auf ZENTRALEN Domains schlägt die Tenant-Identifikation fehl und
     *   die Route läuft dann bewusst OHNE Tenancy weiter — das zentrale
     *   Admin-Panel nutzt dieselbe Route.
     * - Livewire ergänzt RequireLivewireHeaders + Routennamen selbst.
     */
    protected function configureUniversalLivewireUpdateRoute(): void
    {
        // Leere Middleware-Gruppe als "universal"-Flag für stancl.
        Route::middlewareGroup('universal', []);

        Livewire::setUpdateRoute(function ($handle, string $path) {
            return Route::post($path, $handle)->middleware([
                'web',
                'universal',
                Middleware\InitializeTenancyByDomain::class,
            ]);
        });

        // Livewire-JavaScript OHNE ".js"-Endung ausliefern: nginx-Setups
        // (CloudPanel/Produktion) fangen *.js-Adressen als statische
        // Dateien ab und antworten 404 — dieses Skript kommt aber
        // dynamisch aus Laravel. Ohne das Skript funktioniert kein
        // Login (Filament ist Livewire). Keine Middleware nötig:
        // statischer JS-Inhalt, domain-unabhängig.
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire-script', $handle);
        });

        // Datei-VORSCHAU (GET) der Livewire-Uploads: liest aus dem
        // tenant-gesuffixten Temp-Storage → braucht ebenfalls Tenancy.
        // Die Upload-Route (POST) wird über config/livewire.php →
        // temporary_file_upload.middleware tenancy-fähig gemacht.
        FilePreviewController::$middleware = [
            'web',
            'universal',
            Middleware\InitializeTenancyByDomain::class,
        ];
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
