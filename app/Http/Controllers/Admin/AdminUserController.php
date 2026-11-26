<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public const AVAILABLE_ROLES = [
        'Administrador',
        'Jefatura',
        'Colaborador',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $roleFilter = trim((string) $request->string('role'));
        $statusFilter = trim((string) $request->string('status'));

        $users = User::query()
            ->with('roles')
            ->search($search)
            ->when($roleFilter !== '' && in_array($roleFilter, self::AVAILABLE_ROLES, true), function ($query) use ($roleFilter): void {
                $query->role($roleFilter);
            })
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => self::AVAILABLE_ROLES,
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => self::AVAILABLE_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::AVAILABLE_ROLES)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        $user = DB::transaction(function () use ($validated, $isActive): User {
            $created = User::create([
                'name' => trim($validated['name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => $isActive,
                'email_verified_at' => now(),
            ]);

            $created->syncRoles([$validated['role']]);

            return $created;
        });

        $this->audit->log('user.created', $user, [
            'role' => $validated['role'],
            'is_active' => $isActive,
        ], $request->user());

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Usuario {$user->name} creado con el rol {$validated['role']}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::AVAILABLE_ROLES)],
        ]);

        if ($user->is($request->user()) && $validated['role'] !== 'Administrador') {
            return back()->with('error', 'No puedes quitarte tu propio rol de Administrador.');
        }

        $previousRole = $user->getRoleNames()->first();
        $user->syncRoles([$validated['role']]);

        $this->audit->log('user.role_updated', $user, [
            'previous_role' => $previousRole,
            'new_role' => $validated['role'],
        ], $request->user());

        return back()->with('status', "Rol de {$user->name} actualizado a {$validated['role']}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        $this->audit->log('user.status_toggled', $user, [
            'is_active' => $user->is_active,
        ], $request->user());

        $stateLabel = $user->is_active ? 'activada' : 'desactivada';

        return back()->with('status', "La cuenta de {$user->name} fue {$stateLabel}.");
    }
}
