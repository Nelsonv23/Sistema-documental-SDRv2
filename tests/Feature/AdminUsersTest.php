<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_non_administrators_cannot_access_admin_users_module(): void
    {
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');
        $target = $this->createUserWithRole('Colaborador');

        foreach ([$jefatura, $colaborador] as $user) {
            $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.users.store'), [
                'name' => 'Intento',
                'email' => 'intento@sdr.local',
                'password' => 'Sdr2026!',
                'password_confirmation' => 'Sdr2026!',
                'role' => 'Colaborador',
            ])->assertForbidden();
            $this->actingAs($user)->get(route('admin.users.edit', $target))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.users.update', $target), [
                'name' => 'Intento Edit',
                'email' => 'intento-edit@sdr.local',
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.users.update-role', $target), [
                'role' => 'Jefatura',
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.users.toggle-status', $target))->assertForbidden();
        }
    }

    public function test_sidebar_shows_users_link_only_to_administrators(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $colaborador = $this->createUserWithRole('Colaborador');

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Usuarios y roles')
            ->assertSee(route('admin.users.index'));

        $this->actingAs($colaborador)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Usuarios y roles')
            ->assertDontSee(route('admin.users.index'));
    }

    public function test_administrator_can_list_and_filter_users(): void
    {
        $admin = $this->createUserWithRole('Administrador', ['name' => 'Admin Principal']);
        $this->createUserWithRole('Jefatura', ['name' => 'Carla Jefa', 'email' => 'carla@sdr.local']);
        $this->createUserWithRole('Colaborador', ['name' => 'Pedro Inactivo', 'email' => 'pedro@sdr.local', 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Admin Principal')
            ->assertSee('Carla Jefa')
            ->assertSee('Pedro Inactivo');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'Jefatura']))
            ->assertOk()
            ->assertSee('Carla Jefa')
            ->assertDontSee('Pedro Inactivo');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Pedro Inactivo')
            ->assertDontSee('Carla Jefa');
    }

    public function test_administrator_can_create_user_with_role(): void
    {
        $admin = $this->createUserWithRole('Administrador');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Lucía Pérez',
            'email' => 'LUCIA@SDR.LOCAL',
            'password' => 'ClaveSegura2026!',
            'password_confirmation' => 'ClaveSegura2026!',
            'role' => 'Jefatura',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $created = User::where('email', 'lucia@sdr.local')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->hasRole('Jefatura'));
    }

    public function test_administrator_can_change_role_and_toggle_status_of_other_users(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $target = $this->createUserWithRole('Colaborador', ['is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update-role', $target), [
                'role' => 'Jefatura',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertTrue($target->hasRole('Jefatura'));
        $this->assertFalse($target->hasRole('Colaborador'));

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle-status', $target))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle-status', $target))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_administrator_cannot_deactivate_self_or_remove_own_admin_role(): void
    {
        $admin = $this->createUserWithRole('Administrador', ['is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle-status', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update-role', $admin), [
                'role' => 'Colaborador',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasRole('Administrador'));
    }

    public function test_administrator_can_edit_user_name_email_and_optional_password_with_audit_and_remember_token_rotation(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $other = $this->createUserWithRole('Colaborador', ['email' => 'existente@sdr.local']);
        $target = $this->createUserWithRole('Colaborador', [
            'name' => 'Nombre Original',
            'email' => 'original@sdr.local',
            'password' => Hash::make('ClaveOriginal2026!'),
            'remember_token' => 'token-inicial-12345',
        ]);

        // Vista de edición accesible solo para Administrador.
        $this->actingAs($admin)
            ->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('Nombre Original')
            ->assertSee('original@sdr.local');

        // Rechaza correo electrónico duplicado perteneciente a otro usuario.
        $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->patch(route('admin.users.update', $target), [
                'name' => 'Nombre Intento',
                'email' => 'EXISTENTE@SDR.LOCAL',
            ])
            ->assertRedirect(route('admin.users.edit', $target))
            ->assertSessionHasErrors('email');

        $this->assertSame('original@sdr.local', $target->fresh()->email);

        // Actualiza nombre y correo manteniendo contraseña actual cuando password viene vacío.
        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), [
                'name' => 'Nombre Actualizado',
                'email' => 'ACTUALIZADO@SDR.LOCAL',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertSame('Nombre Actualizado', $target->name);
        $this->assertSame('actualizado@sdr.local', $target->email);
        $this->assertTrue(Hash::check('ClaveOriginal2026!', $target->password));
        $this->assertNotSame('token-inicial-12345', $target->remember_token);
        $tokenAfterEmailChange = $target->remember_token;

        $logEmail = AuditLog::where('action', 'user.updated')
            ->where('target_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($logEmail);
        $this->assertSame(['name', 'email'], $logEmail->metadata['changed_fields']);
        $this->assertSame('Nombre Original', $logEmail->metadata['previous_name']);
        $this->assertSame('Nombre Actualizado', $logEmail->metadata['new_name']);
        $this->assertSame('original@sdr.local', $logEmail->metadata['previous_email']);
        $this->assertSame('actualizado@sdr.local', $logEmail->metadata['new_email']);

        // También permite mantener el mismo correo (Rule::unique ignore) y cambiar solo la contraseña.
        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), [
                'name' => 'Nombre Actualizado',
                'email' => 'actualizado@sdr.local',
                'password' => 'NuevaClave2026!',
                'password_confirmation' => 'NuevaClave2026!',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertTrue(Hash::check('NuevaClave2026!', $target->password));
        $this->assertNotSame($tokenAfterEmailChange, $target->remember_token);

        $logPassword = AuditLog::where('action', 'user.updated')
            ->where('target_id', $target->id)
            ->latest('id')
            ->first();

        $this->assertSame(['password'], $logPassword->metadata['changed_fields']);
        $this->assertTrue($logPassword->metadata['password_updated']);
    }
}
