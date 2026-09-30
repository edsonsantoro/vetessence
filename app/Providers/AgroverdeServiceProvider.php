<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AgroverdeServiceProvider
 *
 * Ponto de extensão único para as customizações do fork AgroVerde.
 * Mantém o código customizado isolado do core do VetEssence, permitindo
 * sincronizar com o upstream (hlmitecnologia/vetessence) sem conflitos.
 *
 * Ver AGROVERDE.md — estratégia de fork em 3 camadas.
 */
class AgroverdeServiceProvider extends ServiceProvider
{
    /**
     * Registra bindings e configurações.
     */
    public function register(): void
    {
        // Config próprio (merge com o default)
        $this->mergeConfigFrom(
            base_path('config/agroverde.php'),
            'agroverde'
        );
    }

    /**
     * Bootstrap dos serviços customizados.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
        $this->registerObservers();
    }

    /**
     * Rotas customizadas (arquivo próprio, não toca em routes/web.php).
     */
    protected function registerRoutes(): void
    {
        $routesFile = base_path('routes/agroverde.php');

        if (file_exists($routesFile)) {
            $this->loadRoutesFrom($routesFile);
        }
    }

    /**
     * Views customizadas (namespace 'agroverde').
     *
     * Além do namespace, o overlay em config/view.php faz com que
     * resources/views/agroverde/ sobrescreva views do core.
     */
    protected function registerViews(): void
    {
        $viewsPath = resource_path('views/agroverde');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'agroverde');
        }
    }

    /**
     * Migrations customizadas (pasta separada, nunca modifica as do core).
     */
    protected function registerMigrations(): void
    {
        $migrationsPath = config('agroverde.migrations_path');

        if ($migrationsPath && is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Observers dos models customizados.
     *
     * Descomente conforme os módulos forem implementados.
     */
    protected function registerObservers(): void
    {
        // Exemplo (Fase A2):
        // \App\Models\PetPathology::observe(\App\Observers\Agroverde\PetPathologyObserver::class);
        // \App\Models\PetDocument::observe(\App\Observers\Agroverde\PetDocumentObserver::class);
    }
}
