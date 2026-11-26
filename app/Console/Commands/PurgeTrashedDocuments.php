<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeTrashedDocuments extends Command
{
    protected $signature = 'documents:purge-trash {--days=60 : Días de permanencia en papelera antes de purgar}';

    protected $description = 'Elimina binarios físicos de documentos en papelera con más de 60 días y los marca como purged preservando integridad de auditoría';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $documents = Document::query()
            ->trashed()
            ->where('deleted_at', '<=', $cutoff)
            ->with('versions')
            ->get();

        $purgedCount = 0;

        foreach ($documents as $document) {
            $deletedBinaries = 0;

            foreach ($document->versions as $version) {
                $disk = Storage::disk($version->storage_disk);
                if ($disk->exists($version->storage_key)) {
                    if ($disk->delete($version->storage_key)) {
                        $deletedBinaries++;
                    }
                }
            }

            DB::transaction(function () use ($document, $deletedBinaries): void {
                DocumentVersion::query()
                    ->where('document_id', $document->id)
                    ->update(['is_active' => false]);

                $document->forceFill([
                    'status' => Document::STATUS_PURGED,
                ])->save();

                $this->audit->log('document.purged', $document, [
                    'folder_id' => $document->folder_id,
                    'purged_versions' => $document->versions->count(),
                    'deleted_binaries' => $deletedBinaries,
                ]);
            });

            $purgedCount++;
        }

        $this->info("Documentos purgados: {$purgedCount}.");

        return self::SUCCESS;
    }
}
