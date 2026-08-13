<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Tutor;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Contas padrão (fallback) caso config('demo.accounts') não esteja disponível.
     * Em produção a lista é lida de config/demo.php para manter seeder, tabela
     * de login e README sincronizados.
     */
    protected array $fallbackAccounts = [
        ['name' => 'Super Administrador', 'email' => 'super@vet.com', 'password' => 'super123', 'role' => 'super-admin'],
        ['name' => 'Administrador', 'email' => 'admin@vet.com', 'password' => 'admin123', 'role' => 'admin'],
        ['name' => 'Dr. João Silva', 'email' => 'vet@vet.com', 'password' => 'vet123', 'role' => 'veterinario'],
        ['name' => 'Dra. Ana Costa', 'email' => 'vet2@vet.com', 'password' => 'vet2123', 'role' => 'veterinario'],
        ['name' => 'Paula Recepcionista', 'email' => 'recep@vet.com', 'password' => 'recep123', 'role' => 'recepcionista'],
        ['name' => 'Carlos Recepcionista', 'email' => 'recep2@vet.com', 'password' => 'recep2123', 'role' => 'recepcionista'],
        ['name' => 'Carlos Financeiro', 'email' => 'financeiro@vet.com', 'password' => 'fin123', 'role' => 'financeiro'],
        ['name' => 'Daniel Super Financeiro', 'email' => 'superfin@vet.com', 'password' => 'superfin123', 'role' => 'super-financial'],
        ['name' => 'Ana Estoque', 'email' => 'estoque@vet.com', 'password' => 'est123', 'role' => 'estoque'],
        ['name' => 'Paula RH', 'email' => 'rh@vet.com', 'password' => 'rh123', 'role' => 'human-resources'],
        ['name' => 'Jorge Auditor', 'email' => 'auditor@vet.com', 'password' => 'auditor123', 'role' => 'auditor'],
        ['name' => 'Maria Tutor', 'email' => 'tutor@vet.com', 'password' => 'tutor123', 'role' => 'tutor'],
    ];

    public function run()
    {
        $configAccounts = config('demo.accounts');
        $accounts = is_array($configAccounts) && count($configAccounts) > 0
            ? $configAccounts
            : $this->fallbackAccounts;

        // Mapeia nome amigável -> email para derivar o nome do usuário quando
        // o config (sem 'name') for a fonte.
        $namesByEmail = collect($this->fallbackAccounts)->pluck('name', 'email')->toArray();

        $defaultBranchId = Branch::where('is_main', true)->value('id');

        foreach ($accounts as $account) {
            $role = Role::where('slug', $account['role'])->first();
            if (!$role) {
                continue;
            }

            $email = $account['email'];
            $name = $account['name']
                ?? ($namesByEmail[$email] ?? ucfirst(explode('@', $email)[0]));

            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($account['password']),
                    'role_id' => $role->id,
                    'branch_id' => $defaultBranchId,
                    'is_active' => true,
                    'is_veterinarian' => $account['role'] === 'super-admin',
                ]
            );

            if ($account['role'] === 'tutor') {
                Tutor::updateOrCreate(
                    ['user_id' => $u->id],
                    [
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($account['password']),
                        'cpf' => '111.222.333-44',
                        'phone' => '(11) 98765-0000',
                        'address' => 'Rua dos Tutores, 100',
                        'city' => 'São Paulo',
                        'state' => 'SP',
                    ]
                );
            }
        }
    }
}
