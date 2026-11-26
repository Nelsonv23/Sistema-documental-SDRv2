<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\User;
use App\Services\FolderPermissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FolderPermissionTest extends TestCase
{
    use RefreshDatabase;

    private FolderPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->service = app(FolderPermissionService::class);
    }

    private function createUserWithRole(string $role, bool $isActive = true): User
    {
        $user = User::factory()->create(['is_active' => $isActive]);
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_observer_calculates_materialized_path_on_create(): void
    {
        $root = Folder::create(['name' => 'Corporativo']);
        $child = Folder::create(['name' => 'Finanzas', 'parent_id' => $root->id]);
        $grandchild = Folder::create(['name' => '2026', 'parent_id' => $child->id]);

        $this->assertSame('/'.$root->id.'/', $root->fresh()->path);
        $this->assertSame('/'.$root->id.'/'.$child->id.'/', $child->fresh()->path);
        $this->assertSame('/'.$root->id.'/'.$child->id.'/'.$grandchild->id.'/', $grandchild->fresh()->path);
    }

    public function test_observer_recalculates_subtree_paths_when_moving_folder(): void
    {
        $areaA = Folder::create(['name' => 'Área A']);
        $areaB = Folder::create(['name' => 'Área B']);
        $proyecto = Folder::create(['name' => 'Proyecto X', 'parent_id' => $areaA->id]);
        $docs = Folder::create(['name' => 'Documentos', 'parent_id' => $proyecto->id]);
        $actas = Folder::create(['name' => 'Actas', 'parent_id' => $docs->id]);
        $sibling = Folder::create(['name' => 'Otro', 'parent_id' => $areaA->id]);

        $proyecto->update(['parent_id' => $areaB->id]);

        $this->assertSame('/'.$areaB->id.'/'.$proyecto->id.'/', $proyecto->fresh()->path);
        $this->assertSame('/'.$areaB->id.'/'.$proyecto->id.'/'.$docs->id.'/', $docs->fresh()->path);
        $this->assertSame('/'.$areaB->id.'/'.$proyecto->id.'/'.$docs->id.'/'.$actas->id.'/', $actas->fresh()->path);
        $this->assertSame('/'.$areaA->id.'/'.$sibling->id.'/', $sibling->fresh()->path);
    }

    public function test_observer_prevents_moving_folder_into_self_or_descendant(): void
    {
        $root = Folder::create(['name' => 'Raíz']);
        $child = Folder::create(['name' => 'Hija', 'parent_id' => $root->id]);
        $grandchild = Folder::create(['name' => 'Nieta', 'parent_id' => $child->id]);

        try {
            $root->update(['parent_id' => $root->id]);
            $this->fail('Se esperaba excepción al mover una carpeta dentro de sí misma.');
        } catch (InvalidArgumentException) {
            $this->assertNull($root->fresh()->parent_id);
        }

        try {
            $root->update(['parent_id' => $grandchild->id]);
            $this->fail('Se esperaba excepción al mover una carpeta dentro de un descendiente.');
        } catch (InvalidArgumentException) {
            $this->assertNull($root->fresh()->parent_id);
            $this->assertSame('/'.$root->id.'/'.$child->id.'/'.$grandchild->id.'/', $grandchild->fresh()->path);
        }
    }

    public function test_access_is_denied_by_default_without_explicit_rules(): void
    {
        $jefatura = $this->createUserWithRole('Jefatura');
        $colaborador = $this->createUserWithRole('Colaborador');
        $folder = Folder::create(['name' => 'Privada']);

        $this->assertFalse($this->service->canAccess($jefatura, $folder));
        $this->assertFalse($this->service->canAccess($colaborador, $folder));
        $this->assertSame([], $this->service->accessibleFolderIds($jefatura));
        $this->assertSame([], $this->service->accessibleFolderIds($colaborador));
    }

    public function test_allow_rule_inherits_downward_to_subfolders(): void
    {
        $colaborador = $this->createUserWithRole('Colaborador');
        $roleColaborador = Role::findByName('Colaborador', 'web');

        $root = Folder::create(['name' => 'General']);
        $child = Folder::create(['name' => 'Manuales', 'parent_id' => $root->id]);
        $grandchild = Folder::create(['name' => 'Operaciones', 'parent_id' => $child->id]);
        $unrelated = Folder::create(['name' => 'Gerencia']);

        $this->service->grantAccess($root, $roleColaborador);

        $this->assertTrue($this->service->canAccess($colaborador, $root));
        $this->assertTrue($this->service->canAccess($colaborador, $child));
        $this->assertTrue($this->service->canAccess($colaborador, $grandchild));
        $this->assertFalse($this->service->canAccess($colaborador, $unrelated));

        $this->assertSame(
            [$root->id, $child->id, $grandchild->id],
            $this->service->accessibleFolderIds($colaborador)
        );
    }

    public function test_parent_deny_cannot_be_overridden_by_child_allow(): void
    {
        $colaborador = $this->createUserWithRole('Colaborador');
        $roleColaborador = Role::findByName('Colaborador', 'web');

        $root = Folder::create(['name' => 'Empresa']);
        $restricted = Folder::create(['name' => 'Restringida', 'parent_id' => $root->id]);
        $child = Folder::create(['name' => 'Subcarpeta', 'parent_id' => $restricted->id]);
        $grandchild = Folder::create(['name' => 'Detalle', 'parent_id' => $child->id]);

        $this->service->grantAccess($root, $roleColaborador);
        $this->service->denyAccess($restricted, $roleColaborador);
        $this->service->grantAccess($child, $roleColaborador);
        $this->service->grantAccess($grandchild, $colaborador);

        $this->assertTrue($this->service->canAccess($colaborador, $root));
        $this->assertFalse($this->service->canAccess($colaborador, $restricted));
        $this->assertFalse($this->service->canAccess($colaborador, $child));
        $this->assertFalse($this->service->canAccess($colaborador, $grandchild));

        $this->assertSame([$root->id], $this->service->accessibleFolderIds($colaborador));
    }

    public function test_user_deny_overrides_role_allow_on_same_folder_and_ancestors(): void
    {
        $jefaturaBlocked = $this->createUserWithRole('Jefatura');
        $jefaturaAllowed = $this->createUserWithRole('Jefatura');
        $roleJefatura = Role::findByName('Jefatura', 'web');

        $root = Folder::create(['name' => 'Dirección']);
        $child = Folder::create(['name' => 'Presupuestos', 'parent_id' => $root->id]);

        $this->service->grantAccess($root, $roleJefatura);
        $this->service->denyAccess($root, $jefaturaBlocked);
        $this->service->grantAccess($child, $jefaturaBlocked);

        $this->assertFalse($this->service->canAccess($jefaturaBlocked, $root));
        $this->assertFalse($this->service->canAccess($jefaturaBlocked, $child));
        $this->assertSame([], $this->service->accessibleFolderIds($jefaturaBlocked));

        $this->assertTrue($this->service->canAccess($jefaturaAllowed, $root));
        $this->assertTrue($this->service->canAccess($jefaturaAllowed, $child));
        $this->assertSame([$root->id, $child->id], $this->service->accessibleFolderIds($jefaturaAllowed));
    }

    public function test_revoke_access_updates_effective_permissions(): void
    {
        $colaborador = $this->createUserWithRole('Colaborador');
        $roleColaborador = Role::findByName('Colaborador', 'web');

        $root = Folder::create(['name' => 'Común']);
        $sub = Folder::create(['name' => 'Temporal', 'parent_id' => $root->id]);

        $this->service->grantAccess($root, $roleColaborador);
        $this->service->denyAccess($sub, $colaborador);
        $this->assertFalse($this->service->canAccess($colaborador, $sub));

        $this->service->revokeAccess($sub, $colaborador);
        $this->assertTrue($this->service->canAccess($colaborador, $sub));

        $this->service->revokeAccess($root, $roleColaborador);
        $this->assertFalse($this->service->canAccess($colaborador, $root));
        $this->assertFalse($this->service->canAccess($colaborador, $sub));
    }

    public function test_administrator_has_full_access_and_inactive_user_has_none(): void
    {
        $admin = $this->createUserWithRole('Administrador');
        $inactiveAdmin = $this->createUserWithRole('Administrador', false);

        $f1 = Folder::create(['name' => 'Uno']);
        $f2 = Folder::create(['name' => 'Dos', 'parent_id' => $f1->id]);

        $this->service->denyAccess($f1, $admin);

        $this->assertTrue($this->service->canAccess($admin, $f1));
        $this->assertTrue($this->service->canAccess($admin, $f2));
        $this->assertSame([$f1->id, $f2->id], $this->service->accessibleFolderIds($admin));

        $this->assertFalse($this->service->canAccess($inactiveAdmin, $f1));
        $this->assertSame([], $this->service->accessibleFolderIds($inactiveAdmin));
    }

    public function test_accessible_folder_ids_executes_without_n_plus_one_queries(): void
    {
        $colaborador = $this->createUserWithRole('Colaborador');
        $roleColaborador = Role::findByName('Colaborador', 'web');

        $root = Folder::create(['name' => 'Raíz']);
        $this->service->grantAccess($root, $roleColaborador);

        $parent = $root;
        for ($i = 1; $i <= 12; $i++) {
            $parent = Folder::create(['name' => "Nivel {$i}", 'parent_id' => $parent->id]);
        }

        $deniedBranch = Folder::create(['name' => 'Bloqueada', 'parent_id' => $root->id]);
        for ($i = 1; $i <= 5; $i++) {
            Folder::create(['name' => "Hija Bloqueada {$i}", 'parent_id' => $deniedBranch->id]);
        }
        $this->service->denyAccess($deniedBranch, $colaborador);

        // Pre-cargar roles para medir únicamente la resolución de carpetas.
        $colaborador->load('roles');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $ids = $this->service->accessibleFolderIds($colaborador);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(13, $ids);
        $this->assertNotContains($deniedBranch->id, $ids);
        $this->assertLessThanOrEqual(3, $queryCount);
    }
}
