<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use App\Services\FolderPermissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentExplorerTest extends TestCase
{
    use RefreshDatabase;

    private FolderPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->permissions = app(FolderPermissionService::class);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_folder_creation_and_permission_management_authorization(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');

        $root = Folder::create(['name' => 'Operaciones', 'created_by' => $admin->id]);
        $this->permissions->grantAccess($root, 'Jefatura');
        $this->permissions->grantAccess($root, 'Colaborador');

        // Colaborador no puede crear raíz ni administrar permisos, pero sí subcarpetas donde tiene acceso.
        $this->actingAs($colaborador)
            ->post(route('folders.store'), ['name' => 'Raíz Prohibida'])
            ->assertForbidden();

        $this->actingAs($colaborador)
            ->get(route('folders.permissions', $root))
            ->assertForbidden();

        $this->actingAs($colaborador)
            ->post(route('folders.store'), ['name' => 'Subcarpeta Colaborador', 'parent_id' => $root->id])
            ->assertRedirect(route('folders.show', $root));

        // Jefatura con acceso puede gestionar permisos.
        $this->actingAs($jefatura)
            ->get(route('folders.permissions', $root))
            ->assertOk();

        $this->actingAs($jefatura)
            ->post(route('folders.permissions.store', $root), [
                'subject_type' => 'user',
                'subject_id' => $colaborador->id,
                'access' => 'deny',
            ])
            ->assertRedirect(route('folders.permissions', $root));

        $this->actingAs($colaborador)
            ->get(route('folders.show', $root))
            ->assertForbidden();
    }

    public function test_upload_stores_uuid_in_private_disk_and_releases_session(): void
    {
        Storage::fake('local');

        $colaborador = $this->createUserWithRole('Colaborador');
        $folder = Folder::create(['name' => 'Contratos']);
        $this->permissions->grantAccess($folder, $colaborador);

        $file = UploadedFile::fake()->create('Contrato_2026.pdf', 120, 'application/pdf');

        $response = $this->actingAs($colaborador)
            ->postJson(route('documents.store', $folder), [
                'file' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Contrato_2026.pdf')
            ->assertJsonPath('previewable', true);

        $document = Document::findOrFail($response->json('id'));
        $version = $document->currentVersion;

        $this->assertNotNull($version);
        $this->assertStringStartsWith('documents/', $version->storage_key);
        $this->assertStringNotContainsString('Contrato_2026', $version->storage_key);
        $this->assertSame(64, strlen((string) $version->sha256));
        Storage::disk('local')->assertExists($version->storage_key);
        $this->assertFileDoesNotExist(public_path('storage/'.$version->storage_key));
    }

    public function test_duplicate_detection_replace_and_keep_both_strategies(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('Jefatura');
        $folder = Folder::create(['name' => 'Políticas']);
        $this->permissions->grantAccess($folder, $user);

        $firstFile = UploadedFile::fake()->createWithContent('Manual.pdf', 'contenido-v1');
        $this->actingAs($user)
            ->postJson(route('documents.store', $folder), ['file' => $firstFile])
            ->assertCreated();

        $document = Document::where('folder_id', $folder->id)->where('name', 'Manual.pdf')->firstOrFail();
        $v1 = $document->currentVersion;

        // check-duplicate detecta el archivo existente.
        $this->actingAs($user)
            ->postJson(route('documents.check-duplicate', $folder), ['name' => 'Manual.pdf'])
            ->assertOk()
            ->assertJson([
                'duplicate' => true,
                'document_id' => $document->id,
                'name' => 'Manual.pdf',
            ]);

        // Estrategia reject devuelve 409 Conflict.
        $duplicateFile = UploadedFile::fake()->createWithContent('Manual.pdf', 'contenido-v2');
        $this->actingAs($user)
            ->postJson(route('documents.store', $folder), [
                'file' => $duplicateFile,
                'conflict_strategy' => 'reject',
            ])
            ->assertStatus(409)
            ->assertJsonPath('duplicate', true);

        // Estrategia replace crea nueva versión activa y conserva el binario previo.
        $this->actingAs($user)
            ->postJson(route('documents.store', $folder), [
                'file' => UploadedFile::fake()->createWithContent('Manual.pdf', 'contenido-v2'),
                'conflict_strategy' => 'replace',
            ])
            ->assertCreated()
            ->assertJsonPath('version_number', 2);

        $document->refresh();
        $v2 = $document->currentVersion;

        $this->assertSame(2, $document->versions()->count());
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertTrue($v2->is_active);
        $this->assertSame($v2->id, $document->current_version_id);
        Storage::disk('local')->assertExists($v1->storage_key);
        Storage::disk('local')->assertExists($v2->storage_key);

        // Estrategia keep_both crea documento paralelo "Manual (1).pdf".
        $this->actingAs($user)
            ->postJson(route('documents.store', $folder), [
                'file' => UploadedFile::fake()->createWithContent('Manual.pdf', 'contenido-copia'),
                'conflict_strategy' => 'keep_both',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Manual (1).pdf');
    }

    public function test_preview_versus_download_and_authorization(): void
    {
        Storage::fake('local');

        $allowed = $this->createUserWithRole('Colaborador');
        $blocked = $this->createUserWithRole('Colaborador');

        $folder = Folder::create(['name' => 'Archivos']);
        $this->permissions->grantAccess($folder, $allowed);

        $pdfResponse = $this->actingAs($allowed)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Ficha.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $docxResponse = $this->actingAs($allowed)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create(
                'Planilla.docx',
                10,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])->assertCreated();

        $pdf = Document::findOrFail($pdfResponse->json('id'));
        $docx = Document::findOrFail($docxResponse->json('id'));

        $previewPdf = $this->actingAs($allowed)->get(route('documents.preview', $pdf));
        $previewPdf->assertOk();
        $this->assertStringContainsString('inline', (string) $previewPdf->headers->get('Content-Disposition'));

        $downloadDocx = $this->actingAs($allowed)->get(route('documents.download', $docx));
        $downloadDocx->assertOk();
        $this->assertStringContainsString('attachment', (string) $downloadDocx->headers->get('Content-Disposition'));

        // Incluso entrando a preview con docx, fuerza descarga directa (attachment).
        $previewDocx = $this->actingAs($allowed)->get(route('documents.preview', $docx));
        $previewDocx->assertOk();
        $this->assertStringContainsString('attachment', (string) $previewDocx->headers->get('Content-Disposition'));

        // Usuario sin acceso recibe 403 en subida, preview, descarga y eliminación.
        $this->actingAs($blocked)
            ->postJson(route('documents.store', $folder), [
                'file' => UploadedFile::fake()->create('Intento.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
        $this->actingAs($blocked)->get(route('documents.preview', $pdf))->assertForbidden();
        $this->actingAs($blocked)->get(route('documents.download', $pdf))->assertForbidden();
        $this->actingAs($blocked)->delete(route('documents.destroy', $pdf))->assertForbidden();
    }

    public function test_hybrid_search_respects_folder_permissions_and_filters(): void
    {
        Storage::fake('local');

        $author = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');

        $allowedRoot = Folder::create(['name' => 'Comercial']);
        $allowedChild = Folder::create(['name' => 'Licitaciones', 'parent_id' => $allowedRoot->id]);
        $deniedChild = Folder::create(['name' => 'Confidencial', 'parent_id' => $allowedRoot->id]);

        $this->permissions->grantAccess($allowedRoot, 'Jefatura');
        $this->permissions->grantAccess($allowedRoot, 'Colaborador');
        $this->permissions->denyAccess($deniedChild, $colaborador);

        $this->actingAs($author)->postJson(route('documents.store', $allowedChild), [
            'file' => UploadedFile::fake()->create('R-01 Propuesta Cliente.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $this->actingAs($author)->postJson(route('documents.store', $deniedChild), [
            'file' => UploadedFile::fake()->create('R-01 Secreto Comercial.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $response = $this->actingAs($colaborador)->get(route('documents.search', [
            'q' => 'R-01',
            'folder_id' => $allowedRoot->id,
            'include_subfolders' => '1',
            'extension' => 'pdf',
        ]));

        $response->assertOk()
            ->assertSee('R-01 Propuesta Cliente.pdf')
            ->assertDontSee('R-01 Secreto Comercial.pdf');
    }

    public function test_clean_orphans_command_deletes_only_old_unreferenced_files(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('Administrador');
        $folder = Folder::create(['name' => 'Respaldo']);

        $uploaded = $this->actingAs($user)->postJson(route('documents.store', $folder), [
            'file' => UploadedFile::fake()->create('Valido.pdf', 10, 'application/pdf'),
        ])->assertCreated();

        $validDoc = Document::findOrFail($uploaded->json('id'));
        $validKey = $validDoc->currentVersion->storage_key;

        $oldOrphan = 'documents/orphan-old-uuid';
        $freshOrphan = 'documents/orphan-fresh-uuid';

        Storage::disk('local')->put($oldOrphan, 'old-data');
        Storage::disk('local')->put($freshOrphan, 'fresh-data');

        // Ajustar fecha de modificación del huérfano antiguo a hace 30 horas.
        $oldFullPath = Storage::disk('local')->path($oldOrphan);
        touch($oldFullPath, now()->subHours(30)->getTimestamp());

        $this->artisan('documents:clean-orphans')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($oldOrphan);
        Storage::disk('local')->assertExists($freshOrphan);
        Storage::disk('local')->assertExists($validKey);
    }
}
