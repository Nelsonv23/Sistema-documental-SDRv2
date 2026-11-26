<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_home(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_the_home_layout(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123!'),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertStatus(200)
            ->assertSee('tailadmin-sdr.css', false)
            ->assertSee('sdrNetworkMonitor', false)
            ->assertSee('Explorador documental')
            ->assertSee('Cerrar sesión');
    }
}
