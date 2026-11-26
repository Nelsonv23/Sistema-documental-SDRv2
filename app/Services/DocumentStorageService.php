<?php

namespace App\Services;

use App\Exceptions\DuplicateDocumentException;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentStorageService
{
    public const DISK = 'local';

    public const DIRECTORY = 'documents';

    public const CONFLICT_REJECT = 'reject';

    public const CONFLICT_REPLACE = 'replace';

    public const CONFLICT_KEEP_BOTH = 'keep_both';

    public const PREVIEW_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'txt',
    ];

    public function isPreviewable(?string $extension): bool
    {
        return $extension !== null && in_array(strtolower($extension), self::PREVIEW_EXTENSIONS, true);
    }

    public function findDuplicate(Folder $folder, string $filename): ?Document
    {
        $sanitized = $this->sanitizeFilename($filename);

        return Document::query()
            ->active()
            ->where('folder_id', $folder->id)
            ->where('name', $sanitized)
            ->first();
    }

    /**
     * @return array{duplicate: bool, document_id: int|null, name: string}
     */
    public function checkDuplicate(Folder $folder, string $filename): array
    {
        $sanitized = $this->sanitizeFilename($filename);
        $existing = $this->findDuplicate($folder, $sanitized);

        return [
            'duplicate' => $existing !== null,
            'document_id' => $existing?->id,
            'name' => $sanitized,
        ];
    }

    public function storeUpload(
        Folder $folder,
        UploadedFile $file,
        User $user,
        string $conflictStrategy = self::CONFLICT_REJECT,
    ): Document {
        $originalName = $this->sanitizeFilename($file->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $existing = $this->findDuplicate($folder, $originalName);

        if ($existing !== null && $conflictStrategy === self::CONFLICT_REJECT) {
            throw new DuplicateDocumentException("Ya existe un documento llamado \"{$originalName}\" en esta carpeta.");
        }

        $targetName = $originalName;
        if ($existing !== null && $conflictStrategy === self::CONFLICT_KEEP_BOTH) {
            $targetName = $this->generateUniqueName($folder, $originalName);
            $existing = null;
        }

        if (! $file->isValid()) {
            throw new RuntimeException('El archivo subido no es válido o se recibió incompleto.');
        }

        $realPath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException('No se pudo acceder al archivo temporal subido.');
        }

        $sha256 = hash_file('sha256', $realPath) ?: null;
        $size = (int) ($file->getSize() ?: filesize($realPath));
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $storageKey = self::DIRECTORY.'/'.Str::uuid()->toString();

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('No se pudo abrir el flujo de lectura del archivo.');
        }

        try {
            $written = Storage::disk(self::DISK)->writeStream($storageKey, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written || ! Storage::disk(self::DISK)->exists($storageKey)) {
            Storage::disk(self::DISK)->delete($storageKey);
            throw new RuntimeException('No se pudo persistir el archivo en el almacenamiento privado.');
        }

        try {
            return DB::transaction(function () use (
                $folder,
                $user,
                $existing,
                $conflictStrategy,
                $targetName,
                $originalName,
                $extension,
                $storageKey,
                $mimeType,
                $size,
                $sha256
            ): Document {
                if ($existing !== null && $conflictStrategy === self::CONFLICT_REPLACE) {
                    $document = Document::query()->lockForUpdate()->findOrFail($existing->id);

                    DocumentVersion::query()
                        ->where('document_id', $document->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    $nextVersion = ((int) DocumentVersion::query()
                        ->where('document_id', $document->id)
                        ->max('version_number')) + 1;
                } else {
                    $document = Document::create([
                        'folder_id' => $folder->id,
                        'name' => $targetName,
                        'extension' => $extension !== '' ? mb_substr($extension, 0, 20) : null,
                        'status' => Document::STATUS_ACTIVE,
                        'created_by' => $user->id,
                    ]);
                    $nextVersion = 1;
                }

                $version = DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'storage_key' => $storageKey,
                    'storage_disk' => self::DISK,
                    'original_name' => $originalName,
                    'mime_type' => mb_substr($mimeType, 0, 150),
                    'size' => $size,
                    'sha256' => $sha256,
                    'uploaded_by' => $user->id,
                    'is_active' => true,
                ]);

                $document->forceFill([
                    'current_version_id' => $version->id,
                    'extension' => $extension !== '' ? mb_substr($extension, 0, 20) : null,
                ])->save();

                return $document->load(['currentVersion', 'creator']);
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($storageKey);

            throw $e;
        }
    }

    public function streamResponse(Document $document, bool $inline): StreamedResponse
    {
        $version = $document->currentVersion;

        if (! $version || ! Storage::disk($version->storage_disk)->exists($version->storage_key)) {
            abort(404, 'El archivo físico no se encuentra disponible.');
        }

        if ($inline && $this->isPreviewable($document->extension)) {
            return Storage::disk($version->storage_disk)->response(
                $version->storage_key,
                $document->name,
                [
                    'Content-Type' => $version->mime_type ?: 'application/octet-stream',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        return Storage::disk($version->storage_disk)->download(
            $version->storage_key,
            $document->name
        );
    }

    public function generateUniqueName(Folder $folder, string $filename): string
    {
        $sanitized = $this->sanitizeFilename($filename);
        $base = pathinfo($sanitized, PATHINFO_FILENAME);
        $ext = pathinfo($sanitized, PATHINFO_EXTENSION);
        $suffix = $ext !== '' ? '.'.$ext : '';

        $existingNames = Document::query()
            ->active()
            ->where('folder_id', $folder->id)
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower($name))
            ->all();

        $counter = 1;
        do {
            $candidate = "{$base} ({$counter}){$suffix}";
            $counter++;
        } while (in_array(mb_strtolower($candidate), $existingNames, true));

        return $candidate;
    }

    public function sanitizeFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', trim($filename)));
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?: 'archivo';

        return mb_substr($base, 0, 255);
    }
}
