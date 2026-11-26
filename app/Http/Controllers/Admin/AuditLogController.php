<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $actionFilter = trim((string) ($validated['action'] ?? ''));
        $actorId = $validated['actor_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $search = trim((string) ($validated['q'] ?? ''));

        $logs = AuditLog::query()
            ->with('actor')
            ->when($actionFilter !== '', fn ($query) => $query->where('action', $actionFilter))
            ->when(! empty($actorId), fn ($query) => $query->where('actor_id', (int) $actorId))
            ->when(! empty($dateFrom), fn ($query) => $query->whereDate('created_at', '>=', (string) $dateFrom))
            ->when(! empty($dateTo), fn ($query) => $query->whereDate('created_at', '<=', (string) $dateTo))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('target_label', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            })
            ->recent()
            ->paginate(25)
            ->withQueryString();

        $availableActions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $actors = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'availableActions' => $availableActions,
            'actors' => $actors,
            'filters' => [
                'action' => $actionFilter,
                'actor_id' => $actorId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $search,
            ],
        ]);
    }
}
