<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\NoteLinker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reparse a note's body and reconcile its wikilinks, mentions, and tags.
 * Dispatched on explicit saves and on autosave interval boundaries — never on
 * every keystroke — so parsing stays off the save latency path.
 */
class SyncNoteLinks implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $fileId) {}

    public function handle(NoteLinker $linker): void
    {
        $file = File::find($this->fileId);

        if (! $file || $file->is_dir) {
            return;
        }

        $linker->sync($file);
    }
}
