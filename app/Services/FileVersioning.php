<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Support\Carbon;

/**
 * Archives and inspects a file's historical version snapshots. Extracted from
 * FileController so the Notes autosave path can reuse it (and decide *when* to
 * snapshot) without going through the version-on-every-save edit endpoint.
 */
class FileVersioning
{
    /** Archive the file's current blob as a historical version row. */
    public function snapshot(File $file, ?int $createdBy = null, ?string $note = null): void
    {
        FileVersion::create([
            'file_id' => $file->id,
            'version' => $file->version,
            'note' => $note,
            'name' => $file->name,
            'path' => $file->path,
            'disk' => $file->disk,
            'mime' => $file->mime,
            'size' => $file->size,
            'hash' => $file->hash,
            'created_by' => $createdBy ?? $file->owner_id,
        ]);
    }

    /** When the most recent version was archived, or null if there are none. */
    public function lastSnapshotAt(File $file): ?Carbon
    {
        $at = FileVersion::where('file_id', $file->id)->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }
}
