<?php

namespace App\Services;

use App\Models\File;
use App\Models\NoteLink;
use App\Models\NoteMention;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reconciles a note's parsed syntax into the database: outgoing `[[wikilinks]]`,
 * `@mentions`, and `#tags`. Also re-resolves previously-unresolved inbound links
 * whenever this note's title becomes a match (powering create-on-click and
 * backlinks that light up when a target note is later created or renamed).
 */
class NoteLinker
{
    public function __construct(private NoteParser $parser) {}

    public function sync(File $note): void
    {
        if ($note->is_dir) {
            return;
        }

        $body = Storage::disk($note->disk)->exists($note->path)
            ? Storage::disk($note->disk)->get($note->path)
            : '';

        $links = $this->parser->extractWikiLinks($body);
        $mentions = $this->parser->extractMentions($body);
        $tags = $this->parser->extractTags($body);

        DB::transaction(function () use ($note, $links, $mentions, $tags) {
            $this->syncOutgoingLinks($note, $links);
            $this->syncMentions($note, $mentions);
            $this->syncTags($note, $tags);
            $this->resolveInbound($note);
        });
    }

    /** Replace this note's outgoing links, resolving each title to a target. */
    private function syncOutgoingLinks(File $note, array $links): void
    {
        $note->outgoingLinks()->delete();

        foreach ($links as $link) {
            $target = $this->findNoteByTitle($note->owner_id, $link['title'], $note->id);
            NoteLink::create([
                'source_file_id' => $note->id,
                'target_file_id' => $target?->id,
                'target_title' => mb_strtolower($link['title']),
                'link_text' => $link['alias'] ?? $link['title'],
                'owner_id' => $note->owner_id,
            ]);
        }
    }

    /** Replace this note's mentions with the resolvable @handles. */
    private function syncMentions(File $note, array $handles): void
    {
        $note->mentions()->delete();

        $seen = [];
        foreach ($handles as $handle) {
            $user = $this->resolveUser($handle);
            if (! $user || isset($seen[$user->id])) {
                continue;
            }
            $seen[$user->id] = true;
            NoteMention::create(['file_id' => $note->id, 'mentioned_user_id' => $user->id]);
        }
    }

    /**
     * Attach inline #tags to the note (additive — never detaches manually
     * applied file-manager tags). Reuses the existing per-owner Tag registry.
     */
    private function syncTags(File $note, array $tags): void
    {
        if (! $tags) {
            return;
        }

        $ids = collect($tags)->map(
            fn (string $name) => Tag::firstOrCreate(['owner_id' => $note->owner_id, 'name' => $name])->id,
        );

        $note->tags()->syncWithoutDetaching($ids);
    }

    /** Point any unresolved inbound links at this note now that its title matches. */
    private function resolveInbound(File $note): void
    {
        NoteLink::query()
            ->whereNull('target_file_id')
            ->where('owner_id', $note->owner_id)
            ->where('source_file_id', '!=', $note->id)
            ->where('target_title', mb_strtolower($this->noteTitle($note)))
            ->update(['target_file_id' => $note->id]);
    }

    /** Find an owned markdown note whose filename matches a wikilink title. */
    private function findNoteByTitle(int $ownerId, string $title, int $excludeId): ?File
    {
        $base = File::query()
            ->where('owner_id', $ownerId)
            ->where('is_dir', false)
            ->where('id', '!=', $excludeId);

        return (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($title.'.md')])->first()
            ?? (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($title)])->first();
    }

    /** Resolve an @handle to a user by name (spaces ignored), case-insensitively. */
    private function resolveUser(string $handle): ?User
    {
        return User::query()
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(name) = ?', [$handle])
                ->orWhereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$handle]))
            ->first();
    }

    /** The note's title — its filename without the extension. */
    private function noteTitle(File $note): string
    {
        return pathinfo($note->name, PATHINFO_FILENAME) ?: $note->name;
    }
}
