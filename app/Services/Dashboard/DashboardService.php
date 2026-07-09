<?php

namespace App\Services\Dashboard;

use App\Models\File;
use App\Models\User;

/**
 * Assembles the home-screen payload. Each public method answers one of the
 * four home-screen questions and returns a small, presentation-ready DTO (or
 * null) so the controller stays thin and the Vue components stay dumb.
 *
 * This first slice answers "where do I continue?" via jumpBackIn(). Sibling
 * methods (continueWorking, whatsNew, needsAttention, workspaceSummary) land
 * in later tasks.
 */
class DashboardService
{
    /**
     * The user's most recently *content-edited* file or note — the top
     * "Jump Back In" CTA. Returns null when the user has never edited
     * anything (the card is hidden rather than faked).
     *
     * `content_edited_at` is the honest edit signal: it is set on note
     * autosave and file-version writes, not on plain uploads, so a freshly
     * uploaded-but-untouched file never masquerades as "where you left off".
     */
    public function jumpBackIn(User $user): ?JumpBackInItem
    {
        $file = File::query()
            ->where('owner_id', $user->id)
            ->files()
            ->whereNotNull('content_edited_at')
            ->orderByDesc('content_edited_at')
            ->first();

        if ($file === null) {
            return null;
        }

        $isNote = $file->mime === 'text/markdown';

        return new JumpBackInItem(
            id: $file->id,
            title: $file->name,
            type: $isNote ? 'note' : 'file',
            url: $isNote
                ? route('notes.index', ['open' => $file->id])
                : route('files.index', ['folder' => $file->parent_id]),
            editedAt: $file->content_edited_at->toIso8601String(),
        );
    }
}
