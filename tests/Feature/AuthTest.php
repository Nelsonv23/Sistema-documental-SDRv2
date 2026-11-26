<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Iniciar sesión')
            ->assertSee('Ingresar al sistema');
    }

    public function test_active_user_can_authenticate_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@sdr.local',
            'password' => Hash::make('Sdr2026!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@sdr.local',
            'password' => 'Sdr2026!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('action="'.route('logout').'"', false);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'inactivo@sdr.local',
            'password' => Hash::make('Sdr2026!'),
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactivo@sdr.local',
            'password' => 'Sdr2026!',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'desactivada',
            session('errors')->first('email')
        );
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@sdr.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();
    }

    public function test_password_reset_screens_are_enabled_and_two_factor_is_disabled(): void
    {
        $this->get('/forgot-password')
            ->assertStatus(200)
            ->assertSee('Recuperar contraseña');

        $this->get('/reset-password/sample-token?email=usuario@sdr.local')
            ->assertStatus(200)
            ->assertSee('Restablecer contraseña');

        $this->get('/two-factor-challenge')->assertNotFound();
    }

    public function test_auth_validation_and_pagination_messages_are_localized_in_spanish(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('&laquo; Anterior', __('pagination.previous'));
        $this->assertSame('Siguiente &raquo;', __('pagination.next'));

        $this->from('/login')
            ->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertSame('El campo correo electrónico es obligatorio.', session('errors')->first('email'));
        $this->assertSame('El campo contraseña es obligatorio.', session('errors')->first('password'));

        $this->from('/login')
            ->post('/login', [
                'email' => 'noexiste@sdr.local',
                'password' => 'ClaveErronea2026!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertSame('Estas credenciales no coinciden con nuestros registros.', session('errors')->first('email'));

        $this->from('/forgot-password')
            ->post('/forgot-password', [
                'email' => 'desconocido@sdr.local',
            ])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');

        $this->assertSame(
            'No podemos encontrar un usuario con esa dirección de correo electrónico.',
            session('errors')->first('email')
        );
    }
}
