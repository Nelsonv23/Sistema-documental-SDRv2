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
use Spatie\Permission\Models\Role;
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
        $this->delete(route('folders.destroy', $folder))->assertRedirect('/login');
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
        $this->get(route('admin.users.edit', $admin))->assertRedirect('/login');
        $this->patch(route('admin.users.update', $admin), [])->assertRedirect('/login');
        $this->patch(route('admin.users.update-role', $admin), [])->assertRedirect('/login');
        $this->patch(route('admin.users.toggle-status', $admin))->assertRedirect('/login');
        $this->get(route('admin.trash.index'))->assertRedirect('/login');
        $this->post(route('admin.trash.restore', $doc))->assertRedirect('/login');
        $this->get(route('admin.audit-logs.index'))->assertRedirect('/login');
        $this->get(route('audit-logs.export'))->assertRedirect('/login');
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
            $this->actingAs($nonAdmin)->get(route('admin.users.edit', $targetUser))->assertForbidden();
            $this->actingAs($nonAdmin)->patch(route('admin.users.update', $targetUser), [
                'name' => 'Hack',
                'email' => 'hack-update@sdr.local',
            ])->assertForbidden();
            $this->actingAs($nonAdmin)->patch(route('admin.users.update-role', $targetUser), [
                'role' => 'Administrador',
            ])->assertForbidden();
            $this->actingAs($nonAdmin)->patch(route('admin.users.toggle-status', $targetUser))->assertForbidden();

            $this->actingAs($nonAdmin)->get(route('admin.trash.index'))->assertForbidden();
            $this->actingAs($nonAdmin)->post(route('admin.trash.restore', $trashedDoc))->assertForbidden();
            $this->actingAs($nonAdmin)->get(route('admin.audit-logs.index'))->assertForbidden();
            $this->actingAs($nonAdmin)->get(route('audit-logs.export'))->assertForbidden();
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
            $this->actingAs($blockedUser)->delete(route('folders.destroy', $child))->assertForbidden();
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

    public function test_document_deletion_authorization_and_ui_visibility_rules(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaboradorUno = $this->createUserWithRole('Colaborador');
        $colaboradorDos = $this->createUserWithRole('Colaborador');

        $folder = Folder::create(['name' => 'Proyectos', 'created_by' => $admin->id]);
        $this->permissions->grantAccess($folder, 'Jefatura', $admin);
        $this->permissions->grantAccess($folder, 'Colaborador', $admin);

        $ownUpload = $this->actingAs($colaboradorUno)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Propio.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $ownDoc = Document::findOrFail($ownUpload->json('id'));

        $otherUpload = $this->actingAs($colaboradorDos)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Ajeno.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $otherDoc = Document::findOrFail($otherUpload->json('id'));

        $adminTargetUpload = $this->actingAs($colaboradorDos)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('ParaAdmin.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $adminTargetDoc = Document::findOrFail($adminTargetUpload->json('id'));

        // Interfaz de carpeta, detalle y búsqueda muestra eliminar solo si @can('delete', $document).
        $this->actingAs($colaboradorUno)
            ->get(route('folders.show', $folder))
            ->assertOk()
            ->assertSee('action="'.route('documents.destroy', $ownDoc).'"', false)
            ->assertDontSee('action="'.route('documents.destroy', $otherDoc).'"', false)
            ->assertSee('papelera de seguridad por 60 días');

        $this->actingAs($colaboradorUno)
            ->get(route('documents.search', ['folder_id' => $folder->id]))
            ->assertOk()
            ->assertSee('action="'.route('documents.destroy', $ownDoc).'"', false)
            ->assertDontSee('action="'.route('documents.destroy', $otherDoc).'"', false);

        $this->actingAs($colaboradorUno)
            ->get(route('documents.show', $otherDoc))
            ->assertOk()
            ->assertDontSee('action="'.route('documents.destroy', $otherDoc).'"', false);

        // Colaborador NO puede eliminar documentos subidos por otro usuario en la misma carpeta.
        $this->actingAs($colaboradorUno)
            ->delete(route('documents.destroy', $otherDoc))
            ->assertForbidden();
        $this->assertSame(Document::STATUS_ACTIVE, $otherDoc->fresh()->status);

        // Colaborador SÍ puede eliminar un documento propio (soft delete a papelera + auditoría).
        $this->actingAs($colaboradorUno)
            ->delete(route('documents.destroy', $ownDoc))
            ->assertRedirect(route('folders.show', $folder));

        $ownTrashed = $ownDoc->fresh();
        $this->assertSame(Document::STATUS_TRASHED, $ownTrashed->status);
        $this->assertNotNull($ownTrashed->deleted_at);
        $this->assertSame($colaboradorUno->id, $ownTrashed->deleted_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'target_id' => $ownDoc->id,
            'actor_id' => $colaboradorUno->id,
        ]);

        // Jefatura puede eliminar documentos de otros en carpetas autorizadas.
        $this->actingAs($jefatura)
            ->delete(route('documents.destroy', $otherDoc))
            ->assertRedirect(route('folders.show', $folder));

        $this->assertNotNull($otherDoc->fresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'target_id' => $otherDoc->id,
            'actor_id' => $jefatura->id,
        ]);

        // Administrador puede eliminar documentos en carpetas.
        $this->actingAs($admin)
            ->delete(route('documents.destroy', $adminTargetDoc))
            ->assertRedirect(route('folders.show', $folder));

        $this->assertNotNull($adminTargetDoc->fresh()->deleted_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'target_id' => $adminTargetDoc->id,
            'actor_id' => $admin->id,
        ]);

        // Regla DENY absoluto: si la carpeta tiene DENY, ni Colaborador (creador) ni Jefatura pueden eliminar.
        $subDenied = Folder::create(['name' => 'Restringida', 'parent_id' => $folder->id, 'created_by' => $admin->id]);
        $colabDocUpload = $this->actingAs($colaboradorUno)->postJson(route('documents.store', $subDenied), [
            'file' => UploadedFile::fake()->create('Bloqueado.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $blockedDoc = Document::findOrFail($colabDocUpload->json('id'));

        $this->permissions->denyAccess($subDenied, $colaboradorUno, $admin);
        $this->permissions->denyAccess($subDenied, $jefatura, $admin);

        $this->actingAs($colaboradorUno)->delete(route('documents.destroy', $blockedDoc))->assertForbidden();
        $this->actingAs($jefatura)->delete(route('documents.destroy', $blockedDoc))->assertForbidden();
        $this->assertSame(Document::STATUS_ACTIVE, $blockedDoc->fresh()->status);
    }

    public function test_empty_folder_deletion_authorization_audit_and_integrity_guards(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador', true);
        $admin->forceFill(['name' => 'Admin Auditor', 'email' => 'admin-del@sdr.local'])->save();

        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');
        Role::firstOrCreate(['name' => 'Lector', 'guard_name' => 'web']);
        $lector = $this->createUserWithRole('Lector');

        $root = Folder::create(['name' => 'Operaciones', 'created_by' => $admin->id]);
        $parent = Folder::create(['name' => 'Área Norte', 'parent_id' => $root->id, 'created_by' => $admin->id]);
        $emptySub = Folder::create(['name' => 'Temporal', 'parent_id' => $parent->id, 'created_by' => $admin->id]);
        $emptyRoot = Folder::create(['name' => 'Raíz Temporal', 'created_by' => $admin->id]);

        $this->permissions->grantAccess($root, 'Jefatura', $admin);
        $this->permissions->grantAccess($emptySub, 'Colaborador', $admin);
        $this->permissions->grantAccess($emptySub, $lector, $admin);
        $this->permissions->grantAccess($emptyRoot, 'Jefatura', $admin);

        // Colaborador y Lector reciben HTTP 403 y no ven el modal de eliminación.
        foreach ([$colaborador, $lector] as $forbiddenUser) {
            $this->actingAs($forbiddenUser)
                ->get(route('folders.show', $emptySub))
                ->assertOk()
                ->assertDontSee('id="deleteFolderModal"', false);

            $this->actingAs($forbiddenUser)
                ->delete(route('folders.destroy', $emptySub))
                ->assertForbidden();
        }

        // Jefatura no puede eliminar carpeta raíz, pero sí subcarpeta no raíz autorizada sin DENY.
        $this->actingAs($jefatura)->delete(route('folders.destroy', $emptyRoot))->assertForbidden();

        // Si Jefatura recibe DENY en un ancestro, tampoco puede eliminar la subcarpeta.
        $this->permissions->denyAccess($parent, $jefatura, $admin);
        $this->actingAs($jefatura)->delete(route('folders.destroy', $emptySub))->assertForbidden();
        $this->permissions->revokeAccess($parent, $jefatura);

        // Bloqueo si la carpeta tiene subcarpetas.
        $this->actingAs($admin)
            ->get(route('folders.show', $parent))
            ->assertOk()
            ->assertSee('No se puede eliminar la carpeta')
            ->assertSee('Contiene subcarpetas');

        $this->actingAs($admin)
            ->delete(route('folders.destroy', $parent))
            ->assertRedirect(route('folders.show', $parent))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('folders', ['id' => $parent->id]);

        // Bloqueo si la carpeta tiene documentos activos.
        $docUpload = $this->actingAs($admin)->postJson(route('documents.store', $emptySub), [
            'file' => UploadedFile::fake()->create('Acta.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $doc = Document::findOrFail($docUpload->json('id'));

        $this->actingAs($admin)
            ->delete(route('folders.destroy', $emptySub))
            ->assertRedirect(route('folders.show', $emptySub))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('folders', ['id' => $emptySub->id]);

        // Bloqueo si el documento pasó a papelera (deleted_at IS NOT NULL) para proteger trazabilidad y FK.
        $this->actingAs($admin)->delete(route('documents.destroy', $doc))->assertRedirect();
        $this->actingAs($admin)
            ->get(route('folders.show', $emptySub))
            ->assertOk()
            ->assertSee('No se puede eliminar la carpeta')
            ->assertSee('papelera o en el historial de trazabilidad');

        $this->actingAs($admin)
            ->delete(route('folders.destroy', $emptySub))
            ->assertRedirect(route('folders.show', $emptySub))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('folders', ['id' => $emptySub->id]);
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'folder_id' => $emptySub->id]);

        // Jefatura elimina subcarpeta realmente vacía -> redirige al padre y registra folder.deleted.
        $jefaturaSub = Folder::create(['name' => 'Vacia Jefatura', 'parent_id' => $parent->id, 'created_by' => $admin->id]);
        $jefSubId = $jefaturaSub->id;
        $jefSubPath = $jefaturaSub->fresh()->path;

        $this->actingAs($jefatura)
            ->get(route('folders.show', $jefaturaSub))
            ->assertOk()
            ->assertSee('id="deleteFolderModal"', false)
            ->assertSee('quedará registrada en el historial de auditoría');

        $this->actingAs($jefatura)
            ->delete(route('folders.destroy', $jefaturaSub))
            ->assertRedirect(route('folders.show', $parent))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('folders', ['id' => $jefSubId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'folder.deleted',
            'target_id' => $jefSubId,
            'actor_id' => $jefatura->id,
        ]);

        // Administrador elimina carpeta raíz vacía -> borra permisos, redirige a folders.index y audita path/actor snapshot.
        $emptyRootId = $emptyRoot->id;
        $emptyRootPath = $emptyRoot->fresh()->path;

        $this->actingAs($admin)
            ->delete(route('folders.destroy', $emptyRoot))
            ->assertRedirect(route('folders.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('folders', ['id' => $emptyRootId]);
        $this->assertDatabaseMissing('folder_permissions', ['folder_id' => $emptyRootId]);

        $auditLog = \App\Models\AuditLog::where('action', 'folder.deleted')
            ->where('target_id', $emptyRootId)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('Admin Auditor', $auditLog->actor_name);
        $this->assertSame('admin-del@sdr.local', $auditLog->actor_email);
        $this->assertSame($emptyRootId, $auditLog->metadata['folder_id']);
        $this->assertSame('Raíz Temporal', $auditLog->metadata['name']);
        $this->assertSame($emptyRootPath, $auditLog->metadata['path']);
        $this->assertDatabaseHas('audit_logs', ['target_id' => $jefSubId, 'action' => 'folder.deleted']);
        $this->assertSame('/'.$root->id.'/'.$parent->id.'/'.$jefSubId.'/', $jefSubPath);
    }

    public function test_audit_log_csv_export_respects_filters_and_logs_audit_exported(): void
    {
        $admin = $this->createUserWithRole('Administrador', true);
        $admin->forceFill(['name' => 'María Ñandú', 'email' => 'maria-audit@sdr.local'])->save();

        \App\Models\AuditLog::create([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'document.uploaded',
            'target_type' => \App\Models\Document::class,
            'target_id' => 101,
            'target_label' => 'Resolución N° 1.pdf',
            'metadata' => ['folder_id' => 5],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(2),
        ]);

        \App\Models\AuditLog::create([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'login',
            'target_type' => User::class,
            'target_id' => $admin->id,
            'target_label' => $admin->name,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDay(),
        ]);

        // Vista index incluye botón Exportar CSV conservando filtros.
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'document.uploaded']))
            ->assertOk()
            ->assertSee('Exportar CSV')
            ->assertSee(route('audit-logs.export', ['action' => 'document.uploaded']), false);

        // Exportación CSV con filtro devuelve 200 OK, text/csv, BOM UTF-8 y solo eventos coincidentes.
        $response = $this->actingAs($admin)->get(route('audit-logs.export', [
            'action' => 'document.uploaded',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID;"Fecha y Hora";Actor;"Correo Actor";', $csv);
        $this->assertStringContainsString('Resolución N° 1.pdf', $csv);
        $this->assertStringContainsString('María Ñandú', $csv);
        $this->assertStringNotContainsString(';login;', $csv);

        $exportAudit = \App\Models\AuditLog::where('action', 'audit.exported')
            ->where('actor_id', $admin->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($exportAudit);
        $this->assertSame(['action' => 'document.uploaded'], $exportAudit->metadata['filters']);
    }

    public function test_audit_archive_command_backs_up_old_logs_to_private_gzip_and_preserves_recent_logs(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');

        $oldLog = \App\Models\AuditLog::create([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'document.viewed',
            'target_type' => \App\Models\Document::class,
            'target_id' => 77,
            'target_label' => 'Historico_2024.pdf',
            'metadata' => ['folder_id' => 9],
            'ip_address' => '10.0.0.5',
            'created_at' => now()->subDays(400),
        ]);

        $recentLog = \App\Models\AuditLog::create([
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'action' => 'document.uploaded',
            'target_type' => \App\Models\Document::class,
            'target_id' => 88,
            'target_label' => 'Reciente_2026.pdf',
            'ip_address' => '10.0.0.6',
            'created_at' => now()->subDays(15),
        ]);

        $this->artisan('audit:archive', ['--days' => 365])
            ->assertSuccessful();

        // El registro antiguo fue respaldado y eliminado de la tabla activa; el reciente permanece intacto.
        $this->assertDatabaseMissing('audit_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentLog->id]);

        $archiveAudit = \App\Models\AuditLog::where('action', 'audit.archived')
            ->latest('id')
            ->first();

        $this->assertNotNull($archiveAudit);
        $this->assertSame(1, $archiveAudit->metadata['archived_count']);
        $this->assertSame(365, $archiveAudit->metadata['retention_days']);

        $archivePath = $archiveAudit->metadata['archive_path'];
        $this->assertStringStartsWith('audit-archives/audit_logs_', $archivePath);
        $this->assertStringEndsWith('.csv.gz', $archivePath);

        Storage::disk('local')->assertExists($archivePath);

        $compressed = Storage::disk('local')->get($archivePath);
        $decompressed = gzdecode((string) $compressed);
        $this->assertIsString($decompressed);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $decompressed);
        $this->assertStringContainsString('Historico_2024.pdf', $decompressed);
        $this->assertStringNotContainsString('Reciente_2026.pdf', $decompressed);
    }
}
