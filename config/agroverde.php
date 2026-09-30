<?php

/*
|--------------------------------------------------------------------------
| AgroVerde — Configuração de Customizações
|--------------------------------------------------------------------------
|
| Este arquivo concentra as customizações do fork AgroVerde sobre o
| VetEssence upstream. Segue o mesmo padrão de config/demo.php.
|
| Ver AGROVERDE.md para a estratégia de fork em 3 camadas.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Identidade visual da clínica. Aplicada via overlay de views e/ou
    | pelo sistema de branding nativo (/configuracoes/branding).
    |
    */

    'branding' => [
        'name' => env('AGROVERDE_NAME', 'AgroVerde'),
        'primary_color' => env('AGROVERDE_PRIMARY_COLOR', '#2E7D32'),
        'secondary_color' => env('AGROVERDE_SECONDARY_COLOR', '#9AAA7E'),
        'accent_color' => env('AGROVERDE_ACCENT_COLOR', '#D6C38D'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Módulos AgroVerde (feature flags)
    |--------------------------------------------------------------------------
    |
    | Liga/desliga os módulos customizados. Útil para rollout gradual e
    | para desativar um módulo sem remover código.
    |
    */

    'modules' => [
        'pathologies' => env('AGROVERDE_MODULE_PATHOLOGIES', true),
        'documents' => env('AGROVERDE_MODULE_DOCUMENTS', true),
        'photos' => env('AGROVERDE_MODULE_PHOTOS', true),
        'observations' => env('AGROVERDE_MODULE_OBSERVATIONS', true),
        'videos' => env('AGROVERDE_MODULE_VIDEOS', true),
        'prescription_templates' => env('AGROVERDE_MODULE_PRESCRIPTION_TEMPLATES', true),
        'consolidated_pet_view' => env('AGROVERDE_MODULE_CONSOLIDATED_PET_VIEW', true),
        'attendance_wizard' => env('AGROVERDE_MODULE_ATTENDANCE_WIZARD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Overlay de Views
    |--------------------------------------------------------------------------
    |
    | Quando true, o path resources/views/agroverde é consultado ANTES do
    | path do core (configurado em config/view.php). Permite sobrescrever
    | views sem tocar nos arquivos originais.
    |
    */

    'view_overlay' => [
        'enabled' => env('AGROVERDE_VIEW_OVERLAY', true),
        'path' => resource_path('views/agroverde'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI / UX
    |--------------------------------------------------------------------------
    |
    | Ajustes de interface específicos da AgroVerde.
    |
    */

    'ui' => [
        // Número de registros exibidos na timeline inline da tela do pet
        'timeline_inline_limit' => env('AGROVERDE_TIMELINE_INLINE_LIMIT', 5),

        // Atalhos de adição exibidos na tela consolidada do pet
        'pet_quick_actions' => [
            'attendance' => true,
            'weight' => true,
            'pathology' => true,
            'document' => true,
            'exam' => true,
            'photo' => true,
            'vaccine' => true,
            'prescription' => true,
            'observation' => true,
            'video' => true,
            'hospitalization' => true,
        ],

        // Wizard de atendimento: passos
        'attendance_wizard_steps' => ['subjective', 'objective', 'assessment_plan'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | Pasta de migrations customizadas (carregada pelo AgroverdeServiceProvider).
    |
    */

    'migrations_path' => database_path('migrations/agroverde'),

    /*
    |--------------------------------------------------------------------------
    | Importação do SimplesVet
    |--------------------------------------------------------------------------
    |
    | Arquivo JSON extraído do ERP. Fica dentro do app (e fora do git) em vez
    | de apontar para o repositório vizinho: dentro do container o outro repo
    | não existe, e acoplar os dois por caminho quebraria o importador.
    |
    | usage: php artisan agroverde:import-clientes
    |        php artisan agroverde:import-clientes --file=/outro/caminho.json
    |
    */

    'import' => [
        'source_path' => database_path('data/clientes_completos.json'),
    ],

];
