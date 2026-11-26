<?php

namespace App\Console\Commands;

use App\Models\DocumentVersion;
use App\Services\DocumentStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanDocuments extends Command
{
    protected $signature = 'documents:clean-orphans {--hours=24 : Antigüedad mínima en horas para eliminar archivos huérfanos}';

    protected $description = 'Elimina archivos huérfanos en storage/app/private/documents/ sin registro en document_versions con más de 24 horas';

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoffTimestamp = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk(DocumentStorageService::DISK);

        $files = $disk->allFiles(DocumentStorageService::DIRECTORY);
        if ($files === []) {
            $this->info('No se encontraron archivos en documents/.');

            return self::SUCCESS;
        }

        $deletedCount = 0;

        foreach (array_chunk($files, 500) as $chunk) {
            $referencedKeys = DocumentVersion::query()
                ->whereIn('storage_key', $chunk)
                ->pluck('storage_key')
                ->flip()
                ->all();

            foreach ($chunk as $filePath) {
                if (isset($referencedKeys[$filePath])) {
                    continue;
                }

                if ($disk->lastModified($filePath) <= $cutoffTimestamp) {
                    if ($disk->delete($filePath)) {
                        $deletedCount++;
                    }
                }
            }
        }

        $this->info("Archivos huérfanos eliminados: {$deletedCount}.");

        return self::SUCCESS;
    }
}
