<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchiveOldAuditLogs extends Command
{
    protected $signature = 'audit:archive {--days= : Antigüedad mínima en días para archivar registros (por defecto AUDIT_RETENTION_DAYS o 365)}';

    protected $description = 'Archiva registros históricos de auditoría en CSV comprimido (.csv.gz) dentro del almacenamiento privado y registra audit.archived';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = max(1, (int) ($daysOption !== null && $daysOption !== '' ? $daysOption : config('audit.retention_days', 365)));
        $cutoff = now()->subDays($days);

        $baseQuery = AuditLog::query()
            ->where('created_at', '<', $cutoff);

        if (! (clone $baseQuery)->exists()) {
            $this->info("No existen registros de auditoría anteriores a {$days} días.");

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $archivePath = $this->resolveArchivePath($disk);

        $tempCsvPath = tempnam(sys_get_temp_dir(), 'sdr_audit_csv_');
        $tempGzPath = tempnam(sys_get_temp_dir(), 'sdr_audit_gz_');

        if ($tempCsvPath === false || $tempGzPath === false) {
            if (is_string($tempCsvPath) && is_file($tempCsvPath)) {
                @unlink($tempCsvPath);
            }
            if (is_string($tempGzPath) && is_file($tempGzPath)) {
                @unlink($tempGzPath);
            }
            $this->error('No se pudieron crear archivos temporales para el archivado.');

            return self::FAILURE;
        }

        $archivedIds = [];

        try {
            $csvHandle = fopen($tempCsvPath, 'wb');
            if ($csvHandle === false) {
                $this->error('No se pudo abrir el flujo CSV temporal.');

                return self::FAILURE;
            }

            try {
                fwrite($csvHandle, "\xEF\xBB\xBF");
                fputcsv($csvHandle, [
                    'ID',
                    'Fecha y Hora',
                    'Actor ID',
                    'Actor',
                    'Correo Actor',
                    'Acción',
                    'Tipo Recurso',
                    'ID Recurso',
                    'Etiqueta Recurso',
                    'Metadata',
                    'Dirección IP',
                    'User Agent',
                ], ';', '"', '\\');

                (clone $baseQuery)->chunkById(500, function ($logs) use ($csvHandle, &$archivedIds): void {
                    foreach ($logs as $log) {
                        $archivedIds[] = (int) $log->id;
                        $metadataJson = $log->metadata
                            ? (json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                            : '';

                        fputcsv($csvHandle, [
                            $log->id,
                            $log->created_at?->format('Y-m-d H:i:s') ?? '',
                            $log->actor_id ?? '',
                            $log->actor_name ?? '',
                            $log->actor_email ?? '',
                            $log->action,
                            $log->target_type ?? '',
                            $log->target_id ?? '',
                            $log->target_label ?? '',
                            $metadataJson,
                            $log->ip_address ?? '',
                            $log->user_agent ?? '',
                        ], ';', '"', '\\');
                    }
                });
            } finally {
                fclose($csvHandle);
            }

            $gzHandle = gzopen($tempGzPath, 'wb9');
            $csvReadHandle = fopen($tempCsvPath, 'rb');

            if ($gzHandle === false || $csvReadHandle === false) {
                if (is_resource($gzHandle)) {
                    gzclose($gzHandle);
                }
                if (is_resource($csvReadHandle)) {
                    fclose($csvReadHandle);
                }
                $this->error('No se pudo comprimir el archivo de respaldo de auditoría.');

                return self::FAILURE;
            }

            try {
                while (! feof($csvReadHandle)) {
                    $chunk = fread($csvReadHandle, 8192);
                    if ($chunk !== false && $chunk !== '') {
                        gzwrite($gzHandle, $chunk);
                    }
                }
            } finally {
                fclose($csvReadHandle);
                gzclose($gzHandle);
            }

            $gzReadStream = fopen($tempGzPath, 'rb');
            if ($gzReadStream === false) {
                $this->error('No se pudo leer el archivo comprimido generado.');

                return self::FAILURE;
            }

            try {
                $written = $disk->put($archivePath, $gzReadStream);
            } finally {
                if (is_resource($gzReadStream)) {
                    fclose($gzReadStream);
                }
            }

            if (! $written || ! $disk->exists($archivePath) || $disk->size($archivePath) <= 0) {
                $disk->delete($archivePath);
                $this->error('No se pudo verificar la escritura del respaldo de auditoría.');

                return self::FAILURE;
            }
        } finally {
            @unlink($tempCsvPath);
            @unlink($tempGzPath);
        }

        $archivedCount = count($archivedIds);

        DB::transaction(function () use ($archivedIds, $archivedCount, $archivePath, $days, $cutoff): void {
            foreach (array_chunk($archivedIds, 1000) as $idChunk) {
                DB::table('audit_logs')->whereIn('id', $idChunk)->delete();
            }

            $this->audit->log('audit.archived', null, [
                'archived_count' => $archivedCount,
                'archive_path' => $archivePath,
                'retention_days' => $days,
                'cutoff_at' => $cutoff->toIso8601String(),
            ]);
        });

        $this->info("Registros archivados: {$archivedCount} ({$archivePath}).");

        return self::SUCCESS;
    }

    private function resolveArchivePath($disk): string
    {
        $base = 'audit-archives/audit_logs_'.now()->format('Y-m-d_Hi');
        $candidate = $base.'.csv.gz';

        if (! $disk->exists($candidate)) {
            return $candidate;
        }

        return $base.'_'.now()->format('s').'.csv.gz';
    }
}
