<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->validateFilters($request);

        $logs = $this->buildFilteredQuery($filters)
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
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateFilters($request);
        $query = $this->buildFilteredQuery($filters);
        $maxId = (int) ((clone $query)->max('id') ?? 0);

        $appliedFilters = array_filter(
            $filters,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $this->audit->log('audit.exported', null, [
            'filters' => $appliedFilters,
        ], $request->user());

        $filename = 'auditoria-sdr-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($filters, $maxId): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            // BOM UTF-8 para compatibilidad inmediata con Excel en español.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'ID',
                'Fecha y Hora',
                'Actor',
                'Correo Actor',
                'Acción',
                'Tipo Recurso',
                'ID Recurso',
                'Detalle / Contexto',
                'Dirección IP',
            ], ';', '"', '\\');

            if ($maxId > 0) {
                $this->buildFilteredQuery($filters)
                    ->where('id', '<=', $maxId)
                    ->recent()
                    ->cursor()
                    ->each(function (AuditLog $log) use ($output): void {
                        fputcsv($output, [
                            $log->id,
                            $log->created_at?->format('Y-m-d H:i:s') ?? '',
                            $log->actor_name ?? 'Sistema',
                            $log->actor_email ?? '',
                            $log->action,
                            $log->target_type ? class_basename($log->target_type) : '',
                            $log->target_id ?? '',
                            $this->formatContext($log),
                            $log->ip_address ?? '',
                        ], ';', '"', '\\');
                    });
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{action: string, actor_id: int|string|null, date_from: string|null, date_to: string|null, q: string}
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        return [
            'action' => trim((string) ($validated['action'] ?? '')),
            'actor_id' => $validated['actor_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'q' => trim((string) ($validated['q'] ?? '')),
        ];
    }

    /**
     * @param  array{action: string, actor_id: int|string|null, date_from: string|null, date_to: string|null, q: string}  $filters
     */
    private function buildFilteredQuery(array $filters): Builder
    {
        $actionFilter = $filters['action'];
        $actorId = $filters['actor_id'];
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $search = $filters['q'];

        return AuditLog::query()
            ->with('actor')
            ->when($actionFilter !== '', fn (Builder $query) => $query->where('action', $actionFilter))
            ->when(! empty($actorId), fn (Builder $query) => $query->where('actor_id', (int) $actorId))
            ->when(! empty($dateFrom), fn (Builder $query) => $query->whereDate('created_at', '>=', (string) $dateFrom))
            ->when(! empty($dateTo), fn (Builder $query) => $query->whereDate('created_at', '<=', (string) $dateTo))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('target_label', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            });
    }

    private function formatContext(AuditLog $log): string
    {
        $parts = [];

        if (! empty($log->target_label)) {
            $parts[] = $log->target_label;
        }

        if (! empty($log->metadata)) {
            $encoded = json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $parts[] = $encoded;
            }
        }

        return implode(' | ', $parts);
    }
}
