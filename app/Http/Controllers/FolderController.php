<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentStorageService;
use App\Services\FolderPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                ->withCount([
                    'documents as active_documents_count' => static fn ($query) => $query->whereNull('deleted_at'),
                    'children as accessible_children_count' => static fn ($query) => $query->whereIn('id', $accessibleIds),
                ])
                ->orderBy('name')
                ->get();

        $folderTree = $this->buildAccessibleTree($accessibleIds);

        $accessibleFoldersCount = count($accessibleIds);
        $rootFoldersCount = $accessibleIds === []
            ? 0
            : Folder::query()
                ->whereIn('id', $accessibleIds)
                ->whereNull('parent_id')
                ->count();

        $accessibleDocumentsCount = $accessibleIds === []
            ? 0
            : Document::query()
                ->active()
                ->whereIn('folder_id', $accessibleIds)
                ->count();

        $sevenDaysAgo = now()->subDays(7);
        $recentDocumentsCount = $accessibleIds === []
            ? 0
            : Document::query()
                ->active()
                ->whereIn('folder_id', $accessibleIds)
                ->where(function ($query) use ($sevenDaysAgo): void {
                    $query->where('created_at', '>=', $sevenDaysAgo)
                        ->orWhere('updated_at', '>=', $sevenDaysAgo);
                })
                ->count();

        $isAdmin = $user->hasRole('Administrador');
        $trashedDocumentsCount = 0;
        $totalStorageFormatted = '0 KB';
        $myDocumentsCount = 0;

        if ($isAdmin) {
            $trashedDocumentsCount = Document::query()->trashed()->count();

            $totalStorageBytes = (int) DocumentVersion::query()
                ->join('documents', 'documents.current_version_id', '=', 'document_versions.id')
                ->where('documents.status', Document::STATUS_ACTIVE)
                ->whereNull('documents.deleted_at')
                ->sum('document_versions.size');

            $totalStorageFormatted = $this->formatStorageSize($totalStorageBytes);
        } else {
            $myDocumentsCount = $accessibleIds === []
                ? 0
                : Document::query()
                    ->active()
                    ->whereIn('folder_id', $accessibleIds)
                    ->where('created_by', $user->id)
                    ->count();
        }

        return view('folders.index', [
            'currentFolder' => null,
            'folderTree' => $folderTree,
            'expandedFolderIds' => [],
            'subfolders' => $subfolders,
            'accessibleFoldersCount' => $accessibleFoldersCount,
            'rootFoldersCount' => $rootFoldersCount,
            'accessibleDocumentsCount' => $accessibleDocumentsCount,
            'recentDocumentsCount' => $recentDocumentsCount,
            'trashedDocumentsCount' => $trashedDocumentsCount,
            'totalStorageFormatted' => $totalStorageFormatted,
            'myDocumentsCount' => $myDocumentsCount,
            'isAdmin' => $isAdmin,
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
                ->withCount([
                    'documents as active_documents_count' => static fn ($query) => $query->whereNull('deleted_at'),
                    'children as accessible_children_count' => static fn ($query) => $query->whereIn('id', $accessibleIds),
                ])
                ->orderBy('name')
                ->get();

        $folderTree = $this->buildAccessibleTree($accessibleIds);
        $expandedFolderIds = $this->extractAncestorIds($folder);

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

        $deleteBlockReasons = [];
        if ($user->can('delete', $folder)) {
            if ($folder->children()->exists()) {
                $deleteBlockReasons[] = 'Contiene subcarpetas que deben eliminarse o moverse primero.';
            }

            if ($folder->documents()->whereNull('deleted_at')->exists()) {
                $deleteBlockReasons[] = 'Contiene documentos activos que deben enviarse primero a la papelera.';
            }

            if ($folder->documents()->whereNotNull('deleted_at')->exists()) {
                $deleteBlockReasons[] = 'Existen documentos asociados en la papelera o en el historial de trazabilidad.';
            }
        }

        return view('folders.show', [
            'currentFolder' => $folder,
            'folderTree' => $folderTree,
            'expandedFolderIds' => $expandedFolderIds,
            'breadcrumbs' => $breadcrumbs,
            'subfolders' => $subfolders,
            'documents' => $documents,
            'movableFolders' => $movableFolders,
            'deleteBlockReasons' => $deleteBlockReasons,
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

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $user = $request->user();
        $parentId = $folder->parent_id;
        $folderName = $folder->name;

        $blockReason = DB::transaction(function () use ($folder, $user): ?string {
            /** @var Folder|null $lockedFolder */
            $lockedFolder = Folder::query()->lockForUpdate()->find($folder->id);

            if (! $lockedFolder) {
                return null;
            }

            if ($lockedFolder->children()->exists()) {
                return "No se puede eliminar la carpeta \"{$lockedFolder->name}\" porque aún contiene subcarpetas. Elimínalas o muévelas primero.";
            }

            if ($lockedFolder->documents()->whereNull('deleted_at')->exists()) {
                return "No se puede eliminar la carpeta \"{$lockedFolder->name}\" porque contiene documentos activos. Envíalos primero a la papelera.";
            }

            if ($lockedFolder->documents()->exists()) {
                return "No se puede eliminar la carpeta \"{$lockedFolder->name}\" porque existen documentos asociados en la papelera o en el historial de trazabilidad.";
            }

            $this->audit->log('folder.deleted', $lockedFolder, [
                'folder_id' => $lockedFolder->id,
                'name' => $lockedFolder->name,
                'path' => $lockedFolder->path,
                'parent_id' => $lockedFolder->parent_id,
            ], $user);

            $lockedFolder->permissions()->delete();
            $lockedFolder->delete();

            return null;
        });

        if ($blockReason !== null) {
            return redirect()
                ->route('folders.show', $folder)
                ->with('error', $blockReason);
        }

        if ($parentId !== null) {
            return redirect()
                ->route('folders.show', $parentId)
                ->with('status', "Carpeta \"{$folderName}\" eliminada correctamente.");
        }

        return redirect()
            ->route('folders.index')
            ->with('status', "Carpeta raíz \"{$folderName}\" eliminada correctamente.");
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

    /**
     * Construye en memoria el árbol jerárquico de carpetas autorizadas sin consultas N+1.
     *
     * @param  array<int, int>  $accessibleIds
     */
    private function buildAccessibleTree(array $accessibleIds): Collection
    {
        if ($accessibleIds === []) {
            return collect();
        }

        $folders = Folder::query()
            ->whereIn('id', $accessibleIds)
            ->withCount([
                'documents as active_documents_count' => static fn ($query) => $query->whereNull('deleted_at'),
                'children as accessible_children_count' => static fn ($query) => $query->whereIn('id', $accessibleIds),
            ])
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'path']);

        $byId = $folders->keyBy('id');
        $childrenByParent = [];
        $roots = [];

        foreach ($folders as $folder) {
            $parentId = $folder->parent_id !== null ? (int) $folder->parent_id : null;
            if ($parentId !== null && $byId->has($parentId)) {
                $childrenByParent[$parentId][] = $folder;
            } else {
                $roots[] = $folder;
            }
        }

        foreach ($folders as $folder) {
            $children = collect($childrenByParent[(int) $folder->id] ?? []);
            $folder->setRelation('treeChildren', $children);
        }

        return collect($roots);
    }

    private function formatStorageSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 2).' KB';
    }
}
