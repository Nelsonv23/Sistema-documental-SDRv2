<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentStorageService;
use App\Services\FolderPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderPermissionService $permissions,
        private readonly DocumentStorageService $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleIds = $this->permissions->accessibleFolderIds($user);

        $subfolders = $accessibleIds === []
            ? collect()
            : Folder::query()
                ->whereIn('id', $accessibleIds)
                ->where(function ($query) use ($accessibleIds): void {
                    $query->whereNull('parent_id')
                        ->orWhereNotIn('parent_id', $accessibleIds);
                })
                ->orderBy('name')
                ->get();

        return view('folders.show', [
            'currentFolder' => null,
            'breadcrumbs' => collect(),
            'subfolders' => $subfolders,
            'documents' => null,
            'movableFolders' => collect(),
            'storage' => $this->storage,
        ]);
    }

    public function show(Request $request, Folder $folder): View
    {
        $this->authorize('view', $folder);

        $user = $request->user();
        $accessibleIds = $this->permissions->accessibleFolderIds($user);

        $subfolders = $accessibleIds === []
            ? collect()
            : $folder->children()
                ->whereIn('id', $accessibleIds)
                ->orderBy('name')
                ->get();

        $documents = $folder->documents()
            ->active()
            ->with(['currentVersion', 'creator'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $breadcrumbs = $this->buildBreadcrumbs($folder, $accessibleIds);

        $movableFolders = $user->can('update', $folder) && $accessibleIds !== []
            ? Folder::query()
                ->whereIn('id', $accessibleIds)
                ->where('path', 'not like', $folder->path.'%')
                ->orderBy('path')
                ->get(['id', 'name', 'path'])
            : collect();

        return view('folders.show', [
            'currentFolder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'subfolders' => $subfolders,
            'documents' => $documents,
            'movableFolders' => $movableFolders,
            'storage' => $this->storage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $parent = ! empty($validated['parent_id'])
            ? Folder::findOrFail((int) $validated['parent_id'])
            : null;

        $this->authorize('create', [Folder::class, $parent]);

        $folder = Folder::create([
            'name' => trim($validated['name']),
            'parent_id' => $parent?->id,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log('folder.created', $folder, [
            'parent_id' => $folder->parent_id,
        ], $request->user());

        if ($parent) {
            return redirect()
                ->route('folders.show', $parent)
                ->with('status', "Carpeta \"{$folder->name}\" creada correctamente.");
        }

        return redirect()
            ->route('folders.show', $folder)
            ->with('status', "Carpeta raíz \"{$folder->name}\" creada correctamente.");
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $attributes = [];
        if (array_key_exists('name', $validated)) {
            $attributes['name'] = trim($validated['name']);
        }
        if (array_key_exists('parent_id', $validated)) {
            $targetParentId = $validated['parent_id'] ? (int) $validated['parent_id'] : null;
            if ($targetParentId !== null) {
                $targetParent = Folder::findOrFail($targetParentId);
                $this->authorize('view', $targetParent);
            } else {
                $this->authorize('createRoot', Folder::class);
            }
            $attributes['parent_id'] = $targetParentId;
        }

        $oldParentId = $folder->parent_id;
        $oldName = $folder->name;

        try {
            $folder->update($attributes);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('folder.moved', $folder, [
            'previous_parent_id' => $oldParentId,
            'new_parent_id' => $folder->parent_id,
            'previous_name' => $oldName,
            'new_name' => $folder->name,
        ], $request->user());

        return redirect()
            ->route('folders.show', $folder)
            ->with('status', "Carpeta \"{$folder->name}\" actualizada.");
    }

    public function permissions(Request $request, Folder $folder): View
    {
        $this->authorize('managePermissions', $folder);

        $ancestorIds = $this->extractAncestorIds($folder);
        $parentAncestorIds = array_values(array_filter(
            $ancestorIds,
            fn (int $id): bool => $id !== (int) $folder->id
        ));

        $directPermissions = $folder->permissions()
            ->with('creator')
            ->orderBy('subject_type')
            ->orderBy('access')
            ->get();

        $inheritedPermissions = $parentAncestorIds === []
            ? collect()
            : FolderPermission::query()
                ->whereIn('folder_id', $parentAncestorIds)
                ->with(['folder', 'creator'])
                ->orderBy('folder_id')
                ->get();

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $users = User::query()->active()->orderBy('name')->get(['id', 'name', 'email']);

        return view('folders.permissions', [
            'folder' => $folder,
            'breadcrumbs' => $this->buildBreadcrumbs($folder, $this->permissions->accessibleFolderIds($request->user())),
            'directPermissions' => $directPermissions,
            'inheritedPermissions' => $inheritedPermissions,
            'rolesById' => $roles->keyBy('id'),
            'usersById' => $users->keyBy('id'),
            'roles' => $roles,
            'users' => $users,
        ]);
    }

    public function storePermission(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('managePermissions', $folder);

        $validated = $request->validate([
            'subject_type' => ['required', 'string', 'in:role,user'],
            'subject_id' => ['required', 'integer'],
            'access' => ['required', 'string', 'in:allow,deny'],
        ]);

        $subject = $validated['subject_type'] === FolderPermission::SUBJECT_ROLE
            ? Role::findOrFail((int) $validated['subject_id'])
            : User::findOrFail((int) $validated['subject_id']);

        if ($validated['access'] === FolderPermission::ACCESS_ALLOW) {
            $this->permissions->grantAccess($folder, $subject, $request->user());
        } else {
            $this->permissions->denyAccess($folder, $subject, $request->user());
        }

        $this->audit->log('folder.permissions_updated', $folder, [
            'operation' => 'set',
            'subject_type' => $validated['subject_type'],
            'subject_id' => (int) $validated['subject_id'],
            'access' => $validated['access'],
        ], $request->user());

        return redirect()
            ->route('folders.permissions', $folder)
            ->with('status', 'Regla de acceso guardada correctamente.');
    }

    public function destroyPermission(Request $request, Folder $folder, FolderPermission $permission): RedirectResponse
    {
        $this->authorize('managePermissions', $folder);

        if ((int) $permission->folder_id !== (int) $folder->id) {
            abort(404);
        }

        $this->audit->log('folder.permissions_updated', $folder, [
            'operation' => 'revoked',
            'subject_type' => $permission->subject_type,
            'subject_id' => $permission->subject_id,
            'access' => $permission->access,
        ], $request->user());

        $permission->delete();

        return redirect()
            ->route('folders.permissions', $folder)
            ->with('status', 'Regla de acceso eliminada.');
    }

    /**
     * @param  array<int, int>  $accessibleIds
     */
    private function buildBreadcrumbs(Folder $folder, array $accessibleIds): Collection
    {
        $ancestorIds = $this->extractAncestorIds($folder);
        $foldersById = Folder::query()
            ->whereIn('id', $ancestorIds)
            ->get(['id', 'name', 'path'])
            ->keyBy('id');

        return collect($ancestorIds)
            ->map(function (int $id) use ($foldersById, $accessibleIds): ?array {
                $item = $foldersById->get($id);
                if (! $item) {
                    return null;
                }

                return [
                    'folder' => $item,
                    'accessible' => in_array((int) $item->id, $accessibleIds, true),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function extractAncestorIds(Folder $folder): array
    {
        $path = trim((string) $folder->path, '/');

        return $path !== ''
            ? array_values(array_map('intval', explode('/', $path)))
            : [(int) $folder->id];
    }
}
