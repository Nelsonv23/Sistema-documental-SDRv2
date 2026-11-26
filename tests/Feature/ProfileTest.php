<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_access_profile_or_avatar_routes(): void
    {
        $user = User::factory()->create();

        $this->get(route('profile.edit'))->assertRedirect('/login');
        $this->patch(route('profile.update'), ['name' => 'Otro'])->assertRedirect('/login');
        $this->post(route('profile.avatar.update'))->assertRedirect('/login');
        $this->delete(route('profile.avatar.destroy'))->assertRedirect('/login');
        $this->put(route('profile.password.update'))->assertRedirect('/login');
        $this->get(route('users.avatar', $user))->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_and_update_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre Original',
            'email' => 'perfil@sdr.local',
        ]);
        $user->syncRoles(['Colaborador']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('perfil@sdr.local')
            ->assertSee('Colaborador');

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Nombre Actualizado'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $this->assertSame('Nombre Actualizado', $user->fresh()->name);
    }

    public function test_user_can_upload_replace_and_delete_private_avatar(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['name' => 'Ana Torres']);
        $user->syncRoles(['Jefatura']);

        // Sin avatar retorna SVG con iniciales.
        $this->actingAs($user)
            ->get(route('users.avatar', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('AT', false);

        // Subir primer avatar al disco privado (local).
        $firstUpload = UploadedFile::fake()->image('foto1.png', 200, 200);
        $this->actingAs($user)
            ->post(route('profile.avatar.update'), ['avatar' => $firstUpload])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $firstPath = $user->fresh()->avatar_path;
        $this->assertNotNull($firstPath);
        $this->assertStringStartsWith('avatars/', $firstPath);
        Storage::disk('local')->assertExists($firstPath);
        $this->assertFileDoesNotExist(public_path('storage/'.$firstPath));

        $this->actingAs($user)
            ->get(route('users.avatar', $user))
            ->assertOk();

        // Reemplazar avatar elimina el archivo previo.
        $secondUpload = UploadedFile::fake()->image('foto2.jpg', 200, 200);
        $this->actingAs($user)
            ->post(route('profile.avatar.update'), ['avatar' => $secondUpload])
            ->assertRedirect(route('profile.edit'));

        $secondPath = $user->fresh()->avatar_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        // Eliminar avatar limpia archivo y vuelve al SVG de iniciales.
        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($secondPath);

        $this->actingAs($user)
            ->get(route('users.avatar', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
    }

    public function test_avatar_upload_rejects_invalid_file_types_and_oversized_images(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->syncRoles(['Colaborador']);

        $pdf = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $pdf])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $oversized = UploadedFile::fake()->image('grande.png')->size(3072);
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $oversized])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_user_can_update_password_only_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ClaveActual2026!'),
        ]);
        $user->syncRoles(['Colaborador']);

        // Contraseña actual incorrecta.
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'Incorrecta123!',
                'password' => 'NuevaClave2026!',
                'password_confirmation' => 'NuevaClave2026!',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password', null, 'updatePassword');

        $this->assertTrue(Hash::check('ClaveActual2026!', $user->fresh()->password));

        // Contraseña actual correcta.
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'current_password' => 'ClaveActual2026!',
                'password' => 'NuevaClave2026!',
                'password_confirmation' => 'NuevaClave2026!',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NuevaClave2026!', $user->fresh()->password));
    }

    public function test_avatar_upload_handles_empty_missing_and_broken_temp_paths_without_value_error(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->syncRoles(['Colaborador']);

        // 1. Envío sin archivo.
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), [])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        // 2. Archivo vacío (0 bytes).
        $emptyFile = UploadedFile::fake()->create('vacio.png', 0, 'image/png');
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $emptyFile])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        // 3. Archivo con error de carga PHP (UPLOAD_ERR_PARTIAL).
        $tempFile = tempnam(sys_get_temp_dir(), 'sdr_err_');
        file_put_contents($tempFile, 'corrupt');

        try {
            $erroredUpload = new UploadedFile(
                $tempFile,
                'broken.png',
                'image/png',
                UPLOAD_ERR_PARTIAL,
                true
            );

            $this->actingAs($user)
                ->from(route('profile.edit'))
                ->post(route('profile.avatar.update'), ['avatar' => $erroredUpload])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('avatar');
        } finally {
            @unlink($tempFile);
        }

        // 4. Si getRealPath() devuelve false pero getPathname() apunta al archivo temporal válido, debe funcionar sin ValueError.
        $validFake = UploadedFile::fake()->image('fallback.png', 120, 120);
        $fallbackUpload = new class($validFake->getPathname(), 'fallback.png', 'image/png', null, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $fallbackUpload])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $avatarPath = $user->fresh()->avatar_path;
        $this->assertNotNull($avatarPath);
        Storage::disk('local')->assertExists($avatarPath);
    }

    public function test_all_roles_can_upload_profile_avatar(): void
    {
        Storage::fake('local');

        foreach (['Administrador', 'Jefatura', 'Colaborador'] as $role) {
            $user = User::factory()->create();
            $user->syncRoles([$role]);

            $avatar = UploadedFile::fake()->image("avatar_{$role}.png", 200, 200);

            $this->actingAs($user)
                ->from(route('profile.edit'))
                ->post(route('profile.avatar.update'), ['avatar' => $avatar])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHas('status')
                ->assertSessionHasNoErrors();

            $avatarPath = $user->fresh()->avatar_path;
            $this->assertNotNull($avatarPath);
            $this->assertStringStartsWith('avatars/', $avatarPath);
            Storage::disk('local')->assertExists($avatarPath);
        }
    }
}
