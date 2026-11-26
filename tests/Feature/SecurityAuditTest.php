<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Services\FolderPermissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private FolderPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->permissions = app(FolderPermissionService::class);
    }

    private function createUserWithRole(string $role, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_public_registration_is_disabled_and_no_public_storage_symlink_exists(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Externo',
            'email' => 'externo@sdr.local',
            'password' => 'Secret2026!',
            'password_confirmation' => 'Secret2026!',
        ])->assertNotFound();

        $this->assertFalse(File::exists(public_path('storage')));
    }

    public function test_unauthenticated_users_are_redirected_from_all_protected_modules(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $folder = Folder::create(['name' => 'Privada', 'created_by' => $admin->id]);
        $permission = $this->permissions->grantAccess($folder, $admin, $admin);

        $upload = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Confidencial.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($upload->json('id'));
        $comment = Comment::create([
            'document_id' => $doc->id,
            'user_id' => $admin->id,
            'body' => 'Nota interna',
        ]);

        auth()->logout();

        $this->get(route('home'))->assertRedirect('/login');
        $this->get(route('folders.index'))->assertRedirect('/login');
        $this->post(route('folders.store'), ['name' => 'Hack'])->assertRedirect('/login');
        $this->get(route('folders.show', $folder))->assertRedirect('/login');
        $this->patch(route('folders.update', $folder), ['name' => 'Hack'])->assertRedirect('/login');
        $this->get(route('folders.permissions', $folder))->assertRedirect('/login');
        $this->post(route('folders.permissions.store', $folder), [])->assertRedirect('/login');
        $this->delete(route('folders.permissions.destroy', [$folder, $permission]))->assertRedirect('/login');

        $this->post(route('documents.check-duplicate', $folder), ['name' => 'x.pdf'])->assertRedirect('/login');
        $this->post(route('documents.store', $folder), [])->assertRedirect('/login');
        $this->get(route('documents.show', $doc))->assertRedirect('/login');
        $this->get(route('documents.preview', $doc))->assertRedirect('/login');
        $this->get(route('documents.download', $doc))->assertRedirect('/login');
        $this->delete(route('documents.destroy', $doc))->assertRedirect('/login');

        $this->post(route('comments.store', $doc), ['body' => 'Hack'])->assertRedirect('/login');
        $this->delete(route('comments.destroy', $comment))->assertRedirect('/login');

        $this->get(route('documents.search'))->assertRedirect('/login');
        $this->get(route('notifications.index'))->assertRedirect('/login');
        $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
        $this->postJson(route('notifications.mark-read'))->assertUnauthorized();

        $this->get(route('profile.edit'))->assertRedirect('/login');
        $this->patch(route('profile.update'), ['name' => 'Hack'])->assertRedirect('/login');
        $this->post(route('profile.avatar.update'), [])->assertRedirect('/login');
        $this->delete(route('profile.avatar.destroy'))->assertRedirect('/login');
        $this->put(route('profile.password.update'), [])->assertRedirect('/login');
        $this->get(route('users.avatar', $admin))->assertRedirect('/login');

        $this->get(route('admin.users.index'))->assertRedirect('/login');
        $this->get(route('admin.users.create'))->assertRedirect('/login');
        $this->post(route('admin.users.store'), [])->assertRedirect('/login');
        $this->patch(route('admin.users.update-role', $admin), [])->assertRedirect('/login');
        $this->patch(route('admin.users.toggle-status', $admin))->assertRedirect('/login');
        $this->get(route('admin.trash.index'))->assertRedirect('/login');
        $this->post(route('admin.trash.restore', $doc))->assertRedirect('/login');
        $this->get(route('admin.audit-logs.index'))->assertRedirect('/login');
    }

    public function test_non_administrators_receive_http_403_across_all_admin_routes(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');
        $targetUser = $this->createUserWithRole('Colaborador');

        $folder = Folder::create(['name' => 'Dirección', 'created_by' => $admin->id]);
        $upload = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Borrado.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $trashedDoc = Document::findOrFail($upload->json('id'));
        $trashedDoc->forceFill([
            'status' => Document::STATUS_TRASHED,
            'deleted_at' => now(),
            'deleted_by' => $admin->id,
        ])->save();

        foreach ([$jefatura, $colaborador] as $nonAdmin) {
            $this->actingAs($nonAdmin)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($nonAdmin)->get(route('admin.users.create'))->assertForbidden();
            $this->actingAs($nonAdmin)->post(route('admin.users.store'), [
                'name' => 'No Autorizado',
                'email' => 'hack@sdr.local',
                'password' => 'Sdr2026!',
                'password_confirmation' => 'Sdr2026!',
                'role' => 'Administrador',
            ])->assertForbidden();
            $this->actingAs($nonAdmin)->patch(route('admin.users.update-role', $targetUser), [
                'role' => 'Administrador',
            ])->assertForbidden();
            $this->actingAs($nonAdmin)->patch(route('admin.users.toggle-status', $targetUser))->assertForbidden();

            $this->actingAs($nonAdmin)->get(route('admin.trash.index'))->assertForbidden();
            $this->actingAs($nonAdmin)->post(route('admin.trash.restore', $trashedDoc))->assertForbidden();
            $this->actingAs($nonAdmin)->get(route('admin.audit-logs.index'))->assertForbidden();
        }

        // Administrador sí tiene acceso a los módulos administrativos.
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.trash.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_absolute_deny_precedence_blocks_all_folder_and_document_endpoints_with_403(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');

        $root = Folder::create(['name' => 'Corporativo', 'created_by' => $admin->id]);
        $restrictedParent = Folder::create(['name' => 'Confidencial', 'parent_id' => $root->id, 'created_by' => $admin->id]);
        $child = Folder::create(['name' => 'Subcarpeta', 'parent_id' => $restrictedParent->id, 'created_by' => $admin->id]);

        // Primero colaborador sube un documento en child antes del DENY en el ancestro.
        $this->permissions->grantAccess($root, 'Colaborador', $admin);
        $this->permissions->grantAccess($root, 'Jefatura', $admin);

        $upload = $this->actingAs($colaborador)->postJson(route('documents.store', $child), [
            'file' => UploadedFile::fake()->create('Contrato.pdf', 15, 'application/pdf'),
        ])->assertCreated();
        $doc = Document::findOrFail($upload->json('id'));

        $comment = Comment::create([
            'document_id' => $doc->id,
            'user_id' => $colaborador->id,
            'body' => 'Comentario propio previo al bloqueo',
        ]);

        // Regla DENY en restrictedParent + intento de ALLOW explícito en child:
        // "DENY absoluto sin excepciones hacia abajo" debe bloquear incluso al creador y a Jefatura.
        $this->permissions->denyAccess($restrictedParent, 'Colaborador', $admin);
        $this->permissions->denyAccess($restrictedParent, $jefatura, $admin);
        $childPermission = $this->permissions->grantAccess($child, $colaborador, $admin);
        $this->permissions->grantAccess($child, $jefatura, $admin);

        foreach ([$colaborador, $jefatura] as $blockedUser) {
            $this->actingAs($blockedUser)->get(route('folders.show', $child))->assertForbidden();
            $this->actingAs($blockedUser)->post(route('folders.store'), [
                'name' => 'Subcarpeta Ilegal',
                'parent_id' => $child->id,
            ])->assertForbidden();
            $this->actingAs($blockedUser)->patch(route('folders.update', $child), [
                'name' => 'Renombrada',
            ])->assertForbidden();
            $this->actingAs($blockedUser)->get(route('folders.permissions', $child))->assertForbidden();
            $this->actingAs($blockedUser)->post(route('folders.permissions.store', $child), [
                'subject_type' => FolderPermission::SUBJECT_USER,
                'subject_id' => $blockedUser->id,
                'access' => FolderPermission::ACCESS_ALLOW,
            ])->assertForbidden();
            $this->actingAs($blockedUser)->delete(route('folders.permissions.destroy', [$child, $childPermission]))->assertForbidden();

            $this->actingAs($blockedUser)->postJson(route('documents.check-duplicate', $child), [
                'name' => 'Contrato.pdf',
            ])->assertForbidden();
            $this->actingAs($blockedUser)->postJson(route('documents.store', $child), [
                'file' => UploadedFile::fake()->create('Nuevo.pdf', 10, 'application/pdf'),
            ])->assertForbidden();

            $this->actingAs($blockedUser)->get(route('documents.show', $doc))->assertForbidden();
            $this->actingAs($blockedUser)->get(route('documents.preview', $doc))->assertForbidden();
            $this->actingAs($blockedUser)->get(route('documents.download', $doc))->assertForbidden();
            $this->actingAs($blockedUser)->delete(route('documents.destroy', $doc))->assertForbidden();
            $this->actingAs($blockedUser)->post(route('comments.store', $doc), [
                'body' => 'Intento sin acceso',
            ])->assertForbidden();
        }

        // Incluso el creador del comentario no puede borrarlo mientras esté bloqueado por DENY.
        $this->actingAs($colaborador)->delete(route('comments.destroy', $comment))->assertForbidden();
    }

    public function test_deactivated_user_session_is_blocked_across_protected_routes(): void
    {
        $inactiveAdmin = $this->createUserWithRole('Administrador', isActive: false);

        $this->actingAs($inactiveAdmin)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $inactiveUser = $this->createUserWithRole('Colaborador', isActive: false);

        $this->actingAs($inactiveUser)
            ->getJson(route('notifications.unread-count'))
            ->assertForbidden();
    }
}
