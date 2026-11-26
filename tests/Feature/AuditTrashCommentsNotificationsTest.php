<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Notification;
use App\Models\User;
use App\Services\FolderPermissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class AuditTrashCommentsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private FolderPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->permissions = app(FolderPermissionService::class);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_audit_log_model_is_immutable_and_records_key_events(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador', [
            'email' => 'admin-audit@sdr.local',
            'password' => Hash::make('Sdr2026!'),
        ]);

        // Login fallido registra actor_email snapshot y login_failed.
        $this->post('/login', [
            'email' => 'admin-audit@sdr.local',
            'password' => 'WrongPass!',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_failed',
            'actor_email' => 'admin-audit@sdr.local',
        ]);

        // Login exitoso registra login.
        $this->post('/login', [
            'email' => 'admin-audit@sdr.local',
            'password' => 'Sdr2026!',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'actor_id' => $admin->id,
            'actor_email' => 'admin-audit@sdr.local',
        ]);

        // Subida, vista previa, descarga y eliminación de documento.
        $folder = Folder::create(['name' => 'Legal']);
        $uploadResponse = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Contrato.pdf', 25, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($uploadResponse->json('id'));

        $this->actingAs($admin)->get(route('documents.preview', $doc))->assertOk();
        $this->actingAs($admin)->get(route('documents.download', $doc))->assertOk();
        $this->actingAs($admin)->delete(route('documents.destroy', $doc))->assertRedirect();

        foreach (['document.uploaded', 'document.viewed', 'document.downloaded', 'document.deleted'] as $expectedAction) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $expectedAction,
                'target_id' => $doc->id,
                'actor_id' => $admin->id,
            ]);
        }

        // Vista de auditoría exclusiva para Administrador.
        $colaborador = $this->createUserWithRole('Colaborador');
        $this->actingAs($colaborador)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'document.uploaded']))
            ->assertOk()
            ->assertSee('document.uploaded')
            ->assertSee('Contrato.pdf');

        // Inmutabilidad: update y delete lanzan LogicException.
        $log = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_delete_throws_logic_exception(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $this->actingAs($admin)->patch(route('profile.update'), ['name' => 'Nuevo Nombre']);

        $log = AuditLog::where('action', 'profile.updated')->firstOrFail();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_trash_restoration_is_exclusive_to_administrator_and_notifies_creator(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');

        $folder = Folder::create(['name' => 'Finanzas']);
        $this->permissions->grantAccess($folder, 'Jefatura');
        $this->permissions->grantAccess($folder, 'Colaborador');

        $upload = $this->actingAs($colaborador)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Factura.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($upload->json('id'));

        $this->actingAs($colaborador)->delete(route('documents.destroy', $doc))->assertRedirect();
        $this->assertSame(Document::STATUS_TRASHED, $doc->fresh()->status);

        // No administradores no pueden ver ni restaurar la papelera.
        $this->actingAs($jefatura)->get(route('admin.trash.index'))->assertForbidden();
        $this->actingAs($jefatura)->post(route('admin.trash.restore', $doc))->assertForbidden();
        $this->actingAs($colaborador)->post(route('admin.trash.restore', $doc))->assertForbidden();

        // Administrador puede listar y restaurar.
        $this->actingAs($admin)
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Factura.pdf');

        $this->actingAs($admin)
            ->post(route('admin.trash.restore', $doc))
            ->assertRedirect(route('admin.trash.index'))
            ->assertSessionHas('status');

        $restored = $doc->fresh();
        $this->assertSame(Document::STATUS_ACTIVE, $restored->status);
        $this->assertNull($restored->deleted_at);
        $this->assertNull($restored->deleted_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.restored',
            'target_id' => $doc->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $colaborador->id,
            'type' => 'document.restored',
        ]);
    }

    public function test_purge_trash_command_deletes_binaries_after_60_days_and_preserves_document_and_audit(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $folder = Folder::create(['name' => 'Archivo']);

        // Subir y reemplazar para tener 2 versiones físicas del documento.
        $upload = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->createWithContent('Antiguo.pdf', 'v1'),
        ])->assertCreated();

        $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->createWithContent('Antiguo.pdf', 'v2'),
            'conflict_strategy' => 'replace',
        ])->assertCreated();

        $expiredDoc = Document::findOrFail($upload->json('id'));
        $expiredKeys = $expiredDoc->versions->pluck('storage_key')->all();

        $recentUpload = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->createWithContent('Reciente.pdf', 'recent'),
        ])->assertCreated();
        $recentDoc = Document::findOrFail($recentUpload->json('id'));
        $recentKey = $recentDoc->currentVersion->storage_key;

        $expiredDoc->forceFill([
            'status' => Document::STATUS_TRASHED,
            'deleted_at' => now()->subDays(61),
            'deleted_by' => $admin->id,
        ])->save();

        $recentDoc->forceFill([
            'status' => Document::STATUS_TRASHED,
            'deleted_at' => now()->subDays(15),
            'deleted_by' => $admin->id,
        ])->save();

        $this->artisan('documents:purge-trash')->assertSuccessful();

        // Binarios del documento > 60 días eliminados; fila en documents preservada como purged.
        foreach ($expiredKeys as $key) {
            Storage::disk('local')->assertMissing($key);
        }

        $this->assertDatabaseHas('documents', [
            'id' => $expiredDoc->id,
            'status' => Document::STATUS_PURGED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.purged',
            'target_id' => $expiredDoc->id,
        ]);

        // Documento reciente (15 días) sigue en trashed con su binario intacto.
        Storage::disk('local')->assertExists($recentKey);
        $this->assertSame(Document::STATUS_TRASHED, $recentDoc->fresh()->status);
    }

    public function test_comments_enforce_document_access_and_create_notifications(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $author = $this->createUserWithRole('Jefatura');
        $colleague = $this->createUserWithRole('Colaborador');
        $outsider = $this->createUserWithRole('Colaborador');

        $folder = Folder::create(['name' => 'Proyectos']);
        $this->permissions->grantAccess($folder, $author);
        $this->permissions->grantAccess($folder, $colleague);

        $upload = $this->actingAs($author)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Plan.pdf', 15, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($upload->json('id'));

        // Usuario sin acceso a carpeta no puede ver ni comentar.
        $this->actingAs($outsider)->get(route('documents.show', $doc))->assertForbidden();
        $this->actingAs($outsider)->post(route('comments.store', $doc), [
            'body' => 'Intento sin acceso',
        ])->assertForbidden();

        // Colega con acceso puede comentar y notifica al autor del documento.
        $this->actingAs($colleague)
            ->post(route('comments.store', $doc), ['body' => 'Revisado y aprobado'])
            ->assertRedirect(route('documents.show', $doc));

        $comment = Comment::where('document_id', $doc->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'comment.created',
            'target_id' => $comment->id,
        ]);

        // Verificar polling de unread-count para el autor del documento.
        $this->actingAs($author)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        $this->actingAs($author)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nuevo comentario en tu documento');

        $this->actingAs($author)
            ->postJson(route('notifications.mark-read'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        // Un tercero (incluso Jefatura) no puede borrar comentario ajeno; autor y Admin sí.
        $this->actingAs($author)->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->actingAs($admin)->delete(route('comments.destroy', $comment))->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'comment.deleted',
            'target_id' => $comment->id,
        ]);
    }

    public function test_comment_notifications_notify_valid_participants_respect_deny_and_update_bell_polling(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador');
        $usuarioA = $this->createUserWithRole('Jefatura', ['name' => 'Usuario A']);
        $usuarioB = $this->createUserWithRole('Colaborador', ['name' => 'Usuario B']);
        $usuarioC = $this->createUserWithRole('Colaborador', ['name' => 'Usuario C']);
        $usuarioE = $this->createUserWithRole('Colaborador', ['name' => 'Usuario E']);

        $folder = Folder::create(['name' => 'Contratos', 'created_by' => $admin->id]);
        $this->permissions->grantAccess($folder, $usuarioA, $admin);
        $this->permissions->grantAccess($folder, $usuarioB, $admin);
        $this->permissions->grantAccess($folder, $usuarioC, $admin);
        $this->permissions->grantAccess($folder, $usuarioE, $admin);

        // Usuario A sube el documento.
        $upload = $this->actingAs($usuarioA)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Contrato_Marco.pdf', 20, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($upload->json('id'));

        // Usuario C y Usuario E comentan primero; luego Usuario C recibe DENY en la carpeta.
        $this->actingAs($usuarioC)->post(route('comments.store', $doc), ['body' => 'Primer aporte C'])->assertRedirect();
        $this->actingAs($usuarioE)->post(route('comments.store', $doc), ['body' => 'Primer aporte E'])->assertRedirect();

        $this->permissions->denyAccess($folder, $usuarioC, $admin);
        Notification::query()->delete();

        // Usuario B comenta el documento.
        $this->actingAs($usuarioB)
            ->post(route('comments.store', $doc), ['body' => 'Revisión completada por B'])
            ->assertRedirect(route('documents.show', $doc));

        // Usuario A (creador) y Usuario E (comentarista previo activo con acceso) reciben notificación;
        // Usuario B (autor del comentario) y Usuario C (con DENY en carpeta) NO reciben notificación.
        $this->assertDatabaseHas('notifications', ['user_id' => $usuarioA->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $usuarioE->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $usuarioB->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $usuarioC->id]);

        $notifA = Notification::where('user_id', $usuarioA->id)->firstOrFail();
        $expectedUrl = route('documents.show', $doc).'#comments';

        $this->assertStringContainsString('Usuario B', (string) $notifA->body);
        $this->assertStringContainsString('Contrato_Marco.pdf', (string) $notifA->body);
        $this->assertSame($expectedUrl, $notifA->data['url'] ?? null);

        // Endpoint de polling y campanita del topbar para Usuario A reflejan unread_count = 1 y URL al documento.
        $this->actingAs($usuarioA)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notifA->id)
            ->assertJsonPath('notifications.0.url', $expectedUrl);

        $this->actingAs($usuarioA)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('sdrNotificationBell(', false)
            ->assertSee('Usuario B comentó en')
            ->assertSee('Contrato_Marco.pdf');

        $this->actingAs($usuarioA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Usuario B comentó en')
            ->assertSee(route('notifications.open', $notifA), false);

        // Otro usuario no puede abrir ni marcar notificación ajena.
        $this->actingAs($usuarioB)
            ->get(route('notifications.open', $notifA))
            ->assertForbidden();

        // Al hacer clic para abrir la notificación individual, se marca leída y redirige al documento#comments.
        $this->actingAs($usuarioA)
            ->get(route('notifications.open', $notifA))
            ->assertRedirect($expectedUrl);

        $this->assertNotNull($notifA->fresh()->read_at);

        $this->actingAs($usuarioA)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_admin_comment_notifies_authorized_folder_collaborators_and_excludes_denied_users(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('Administrador', ['name' => 'Admin Publicador']);
        $colaboradorA = $this->createUserWithRole('Colaborador', ['name' => 'Colaborador A']);
        $colaboradorB = $this->createUserWithRole('Colaborador', ['name' => 'Colaborador B']);

        $folder = Folder::create(['name' => 'Normativas', 'created_by' => $admin->id]);
        $this->permissions->grantAccess($folder, $colaboradorA, $admin);
        $this->permissions->denyAccess($folder, $colaboradorB, $admin);

        // Administrador sube y comenta el documento (incluso sin que Colaborador A haya comentado antes).
        $upload = $this->actingAs($admin)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Circular_Interna.pdf', 12, 'application/pdf'),
        ])->assertCreated();

        $doc = Document::findOrFail($upload->json('id'));

        $this->actingAs($admin)
            ->post(route('comments.store', $doc), ['body' => 'Lectura obligatoria para el equipo.'])
            ->assertRedirect(route('documents.show', $doc));

        // Colaborador A recibe la notificación; Administrador (autor del comentario) y Colaborador B (DENY) no reciben nada.
        $this->assertDatabaseHas('notifications', ['user_id' => $colaboradorA->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $colaboradorB->id]);

        $notifA = Notification::where('user_id', $colaboradorA->id)->firstOrFail();

        // Campanita, barra lateral (enlace + badge) e historial reflejan unread_count = 1 para Colaborador A.
        $this->actingAs($colaboradorA)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.url', route('documents.show', $doc).'#comments');

        $this->actingAs($colaboradorA)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('notifications.index'), false)
            ->assertSee('ta-sidebar-badge', false);

        $this->actingAs($colaboradorA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Admin Publicador comentó en')
            ->assertSee('Circular_Interna.pdf')
            ->assertSee(route('notifications.open', $notifA), false);

        // Colaborador B con DENY no recibe notificación alguna.
        $this->actingAs($colaboradorB)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
