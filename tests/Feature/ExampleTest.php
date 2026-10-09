<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_pagina_de_login_carga(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_no_existe_ruta_de_registro(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_observador_no_puede_ver_bitacora(): void
    {
        $observador = User::factory()->create(['rol' => User::ROL_OBSERVADOR]);

        $this->actingAs($observador)->get('/bitacora')->assertForbidden();
    }

    public function test_administrador_ve_dashboard_y_bitacora(): void
    {
        $admin = User::factory()->create(['rol' => User::ROL_ADMINISTRADOR]);

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Dashboard');
        $this->actingAs($admin)->get('/bitacora')->assertOk();
    }
}
