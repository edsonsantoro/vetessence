<?php

namespace App\Providers;

use App\Http\Middleware\AgroVerdeTrustProxies;
use App\Http\Middleware\TrustProxies;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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

        $this->registerTrustedProxies();
    }

    /**
     * Faz o Laravel confiar no nginx compartilhado como proxy.
     *
     * O core declara `$proxies = null` em App\Http\Middleware\TrustProxies —
     * ou seja, nenhum proxy é confiável e todo X-Forwarded-* é ignorado.
     * Atrás do `wp-nginx` isso faz o app responder com http:// e o navegador
     * entra em loop de redirect contra o HTTPS.
     *
     * Trocar o binding resolve sem editar o arquivo do core: o Kernel
     * referencia a classe pelo nome e o container devolve esta instância.
     */
    protected function registerTrustedProxies(): void
    {
        $this->app->bind(
            TrustProxies::class,
            AgroVerdeTrustProxies::class
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
        $this->registerPaginationView();
    }

    /**
     * Usa a paginação em Bootstrap 4.
     *
     * O Laravel 10 passou a renderizar paginação em Tailwind por padrão. Este
     * app é AdminLTE 3 (Bootstrap 4): a view Tailwind entra sem estilo
     * (setas gigantes, mobile e desktop sobrepostos).
     *
     * A view Bootstrap vive em resources/views/vendor/pagination/ — pasta do
     * vendor, território do fork. Publicar a view do framework faria o
     * upstream ver um arquivo nosso como se fosse dele e brigar no merge.
     *
     * Note que não existe `defaultUseBootstrapFour()` nesta versão: o Laravel
     * removeu os atalhos e passou a usar `pagination::tailwind` direto.
     */
    protected function registerPaginationView(): void
    {
        Paginator::defaultView('pagination::bootstrap-4');
        Paginator::defaultSimpleView('pagination::bootstrap-4');

        // A paginação é desenhada no layout, não em cada view: são 59
        // listagens e nenhuma delas deve ganhar um rodapé à mão. O composer
        // varre os dados que a view recebeu e entrega ao layout tudo que é
        // Paginator.
        View::composer('layouts.adminlte', function ($view) {
            $paginas = [];

            foreach ($view->getData() as $chave => $valor) {
                // Chave com "__" é interna do Blade (`__currentLoopData`,
                // `__env`, `__data`...). `__currentLoopData` sobrevive de um
                // `@foreach` da view filha e, no caso de uma lista, chega ao
                // layout ainda apontando para o paginator — o que desenhava o
                // rodapé duas vezes.
                if (str_starts_with($chave, '__')) {
                    continue;
                }

                if ($valor instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                    $paginas[] = $valor;
                }
            }

            $view->with('__paginas', $paginas);
        });
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
