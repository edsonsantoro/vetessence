<?php

namespace Tests\Feature\Modules;

use App\Models\Tutor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\ModuleTestCase;

class PortalAuthTest extends ModuleTestCase
{
    /**
     * O login é unificado em /login. A rota legada do portal redireciona para lá.
     */
    public function test_portal_login_redirects_to_main_login()
    {
        $response = $this->get('/portal/login');
        $response->assertRedirect('/login');
    }

    /**
     * Um tutor se autentica pela tela única /login e é levado ao dashboard do portal.
     */
    public function test_tutor_logs_in_via_unified_login()
    {
        $tutorUser = User::factory()->create([
            'email' => 'tutor.login@test.com',
            'password' => Hash::make('password'),
            'role_id' => null,
        ]);
        $tutorUser->assignRole('tutor');

        Tutor::factory()->create([
            'user_id' => $tutorUser->id,
            'email' => 'tutor.login@test.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'tutor.login@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($tutorUser, 'web');
        // Tutor autenticado é direcionado à área do portal (dashboard ou home).
        $this->assertTrue(
            $response->isRedirect(route('portal.dashboard')) ||
            $response->isRedirect(route('dashboard'))
        );
    }
}
