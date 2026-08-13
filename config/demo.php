<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo Demonstração
    |--------------------------------------------------------------------------
    |
    | Quando DEMO_MODE=true (env):
    |  - a tela de login exibe a tabela de contas de demonstração;
    |  - o link "Esqueceu a senha?" é ocultado;
    |  - as senhas das contas de demonstração ficam bloqueadas para alteração
    |    (reset de senha e edição por admin), garantindo que visitantes consigam
    |    acessar o sistema com as credenciais publicadas.
    |
    | Em ambiente normal (false) nada disso ocorre.
    |
    */

    'enabled' => env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Contas de demonstração
    |--------------------------------------------------------------------------
    |
    | Fonte única de verdade para o seeder (UserSeeder), a tabela na tela de
    | login e o bloqueio de alteração de senha. Mantenha sincronizado com o
    | README (seção "Demonstração").
    |
    | Ordem: perfil (rótulo amigável), email, senha, role (slug do Spatie).
    |
    */

    'accounts' => [
        ['profile' => 'Super Admin',        'email' => 'super@vet.com',     'password' => 'super123',   'role' => 'super-admin'],
        ['profile' => 'Admin',              'email' => 'admin@vet.com',     'password' => 'admin123',   'role' => 'admin'],
        ['profile' => 'Veterinário',        'email' => 'vet@vet.com',       'password' => 'vet123',     'role' => 'veterinario'],
        ['profile' => 'Veterinário',        'email' => 'vet2@vet.com',      'password' => 'vet2123',    'role' => 'veterinario'],
        ['profile' => 'Recepcionista',      'email' => 'recep@vet.com',     'password' => 'recep123',   'role' => 'recepcionista'],
        ['profile' => 'Recepcionista',      'email' => 'recep2@vet.com',    'password' => 'recep2123',  'role' => 'recepcionista'],
        ['profile' => 'Financeiro',         'email' => 'financeiro@vet.com','password' => 'fin123',     'role' => 'financeiro'],
        ['profile' => 'Super Financeiro',   'email' => 'superfin@vet.com',  'password' => 'superfin123','role' => 'super-financial'],
        ['profile' => 'Estoque',            'email' => 'estoque@vet.com',   'password' => 'est123',     'role' => 'estoque'],
        ['profile' => 'RH',                 'email' => 'rh@vet.com',        'password' => 'rh123',      'role' => 'human-resources'],
        ['profile' => 'Auditor',            'email' => 'auditor@vet.com',   'password' => 'auditor123', 'role' => 'auditor'],
        ['profile' => 'Tutor',              'email' => 'tutor@vet.com',     'password' => 'tutor123',   'role' => 'tutor'],
    ],

];
