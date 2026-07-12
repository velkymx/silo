<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFile;
use App\Jobs\SyncNoteLinks;
use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use App\Services\Audit;
use App\Services\FileVersioning;
use App\Services\QuotaService;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The note-centric surface: a 3-pane app over markdown File records living
 * under a per-user "Notes" folder. Reuses File storage/versioning/search/tags;
 * adds snapshot-free autosave plus wikilink/mention autocomplete + backlinks.
 */
class NoteController extends Controller
{
    // Render the 3-pane Notes app: folder tree, tags, and the notes list.
    public function index(Request $request)
    {
        $userId = auth()->id();
        $root = $this->notesRoot($userId);

        $folders = File::folders()->where('owner_id', $userId)
            ->orderBy('name')->get(['id', 'name', 'parent_id']);
        $subtreeIds = $this->subtreeIds($root->id, $folders);

        $notes = File::query()->where('owner_id', $userId)->files()
            ->where('mime', 'text/markdown')
            ->whereIn('parent_id', $subtreeIds)
            ->with('tags')
            ->orderByDesc('content_edited_at')->orderByDesc('updated_at')
            ->get()
            ->map(fn (File $note) => $this->noteShape($note));

        return Inertia::render('Notes/Index', [
            'rootId' => $root->id,
            // Folders inside the Notes subtree (excluding the root itself).
            'folders' => $folders->whereIn('id', $subtreeIds)->where('id', '!=', $root->id)
                ->map(fn (File $f) => ['id' => $f->id, 'name' => $f->name, 'parent_id' => $f->parent_id])
                ->values(),
            'notes' => $notes->values(),
            'tags' => Tag::where('owner_id', $userId)->orderBy('name')->get(['id', 'name', 'color']),
            // Deep-link hooks: ?open=<id> selects a note, ?create=<title> seeds a new one.
            'open' => $request->integer('open') ?: null,
            'createTitle' => $request->string('create')->toString() ?: null,
        ]);
    }

    // Create a note under the Notes root (New note button + create-on-click).
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $root = $this->notesRoot($userId);
        $parent = $this->resolveNotesFolder($request->input('parent_id'), $userId, $root);
        $disk = config('filemanager.disk');

        $title = trim((string) $request->input('name')) ?: 'Untitled';
        $name = str_ends_with(strtolower($title), '.md') ? $title : $title.'.md';
        $content = (string) $request->input('content');

        // Create-on-click is idempotent: an existing note with this exact name
        // is opened rather than duplicated.
        $existing = File::where('owner_id', $userId)->where('parent_id', $parent->id)
            ->where('name', $name)->first();
        if ($existing) {
            return redirect()->route('notes.index', ['open' => $existing->id]);
        }

        $file = app(\App\Services\NoteCreator::class)->create($userId, $parent, $name, $content);

        return redirect()->route('notes.index', ['open' => $file->id]);
    }

    // Create a subfolder under the Notes root (or a folder inside it).
    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $root = $this->notesRoot($userId);
        $parent = $this->resolveNotesFolder($request->input('parent_id'), $userId, $root);

        $name = $this->uniqueName($parent->id, trim((string) $request->string('name')), $userId);
        File::create([
            'name' => $name,
            'path' => $name,
            'disk' => config('filemanager.disk'),
            'is_dir' => true,
            'parent_id' => $parent->id,
            'owner_id' => $userId,
        ]);

        return redirect()->route('notes.index');
    }

    /**
     * Rename a note (its title = filename without extension). Re-syncs links so
     * any unresolved [[wikilinks]] pointing at the new title light up, and
     * backlinks follow the note.
     */
    public function rename(Request $request, File $file)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);
        abort_unless($file->mime === 'text/markdown', 404);

        $request->validate(['title' => 'required|string|max:255']);
        $title = trim((string) $request->string('title'));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title cannot be empty.']);
        }
        $name = str_ends_with(strtolower($title), '.md') ? $title : $title.'.md';

        $clash = File::where('owner_id', $file->owner_id)
            ->where('parent_id', $file->parent_id)
            ->where('name', $name)
            ->where('id', '!=', $file->id)
            ->exists();
        if ($clash) {
            throw ValidationException::withMessages(['title' => "A note named \"{$title}\" already exists here."]);
        }

        $file->update(['name' => $name]);
        SyncNoteLinks::dispatch($file->id);
        Audit::log('note.rename', $file);

        return redirect()->route('notes.index', ['open' => $file->id]);
    }

    /**
     * Snapshot-free autosave. Overwrites the working blob in place so rapid
     * saves don't orphan bytes or spam versions; archives a checkpoint version
     * only once the previous snapshot (or the note's creation) is older than
     * the configured interval. Explicit "Save version" uses files.content.
     */
    public function autosave(Request $request, File $file, FileVersioning $versioning)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);
        abort_unless($file->mime === 'text/markdown', 404);

        $request->validate([
            'content' => 'nullable|string',
            // An explicit "Save version" forces a checkpoint snapshot regardless
            // of the interval, with an optional commit note.
            'checkpoint' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ]);
        $content = (string) $request->input('content');
        $userId = $file->owner_id;
        $disk = config('filemanager.disk');

        if (app(QuotaService::class)->wouldExceed($userId, strlen($content) - (int) $file->size)) {
            throw ValidationException::withMessages(['content' => 'This would exceed your storage quota.']);
        }

        $hashChanged = hash('sha256', $content) !== $file->hash;
        $interval = (int) config('filemanager.notes.snapshot_interval', 10);
        $baseline = $versioning->lastSnapshotAt($file) ?? $file->created_at;
        $checkpoint = $request->boolean('checkpoint');
        $shouldSnapshot = $hashChanged && ($checkpoint || $baseline->lt(now()->subMinutes($interval)));

        $attributes = [
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'status' => File::STATUS_PENDING,
            'referenced' => false,
            'content_edited_at' => now(),
        ];

        if ($shouldSnapshot || $file->referenced) {
            // Checkpoint (or fork a referenced original): archive the current
            // blob and write the new content to a fresh path so the version's
            // bytes stay intact.
            if ($shouldSnapshot) {
                $versioning->snapshot($file, null, $request->filled('note') ? trim((string) $request->input('note')) : null);
                $attributes['version'] = $file->version + 1;
            }
            $newPath = "uploads/{$userId}/".Str::random(40).'.md';
            Storage::disk($disk)->put($newPath, $content);
            $attributes['path'] = $newPath;
            $attributes['disk'] = $disk;
        } else {
            // Overwrite the stable working blob in place — no orphaned bytes.
            Storage::disk($file->disk)->put($file->path, $content);
        }

        $file->update($attributes);

        ProcessUploadedFile::dispatch($file->id);
        SyncNoteLinks::dispatch($file->id);

        return response()->json([
            'saved_at' => $file->content_edited_at->toIso8601String(),
            'version' => $file->version,
        ]);
    }

    /**
     * Serve a note's raw markdown. Unlike files.raw this is not gated on the
     * READY status — notes are app-authored markdown (no untrusted-upload/AV
     * concern), so the editor can load content immediately without waiting on a
     * queue worker to finish post-processing.
     */
    public function content(File $file)
    {
        $this->authorize('view', $file);
        abort_if($file->is_dir, 404);
        abort_unless($file->mime === 'text/markdown', 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return FileResponse::serve(Storage::disk($file->disk), $file->path, $file->name, $file->mime);
    }

    // Backlinks ("Linked Mentions"): notes that link to this one.
    public function backlinks(File $file)
    {
        $this->authorize('view', $file);

        $links = $file->incomingLinks()->with('source:id,name')->get()
            ->filter(fn ($link) => $link->source !== null)
            ->map(fn ($link) => [
                'id' => $link->source_file_id,
                'title' => pathinfo($link->source->name, PATHINFO_FILENAME),
                'link_text' => $link->link_text,
            ])->values();

        return response()->json(['backlinks' => $links]);
    }

    // Autocomplete: owned markdown notes whose title matches the query.
    public function searchNotes(Request $request)
    {
        $userId = auth()->id();
        $q = trim((string) $request->string('q'));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $results = File::query()->where('owner_id', $userId)->files()
            ->where('mime', 'text/markdown')
            ->where('name', 'like', '%'.$q.'%')
            ->orderBy('name')->limit(10)->get(['id', 'name'])
            ->map(fn (File $f) => ['id' => $f->id, 'title' => pathinfo($f->name, PATHINFO_FILENAME)]);

        return response()->json(['results' => $results]);
    }

    // Autocomplete: users whose name matches the query (for @mentions).
    public function searchUsers(Request $request)
    {
        $q = trim((string) $request->string('q'));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $results = User::query()->where('name', 'like', '%'.$q.'%')
            ->orderBy('name')->limit(10)->get(['id', 'name'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'handle' => mb_strtolower(str_replace(' ', '', $u->name)),
            ]);

        return response()->json(['results' => $results]);
    }

    // ---- helpers ----

    // Shape a note for the list pane.
    private function noteShape(File $note): array
    {
        return [
            'id' => $note->id,
            'title' => pathinfo($note->name, PATHINFO_FILENAME),
            'name' => $note->name,
            'parent_id' => $note->parent_id,
            'raw_url' => route('notes.content', $note),
            'status' => $note->status,
            'starred' => (bool) $note->starred,
            'updated_at' => ($note->content_edited_at ?? $note->updated_at)->format('Y-m-d H:i'),
            'tags' => $note->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values(),
        ];
    }

    /**
     * Resolve a target folder for a new note/folder: the given folder if it is
     * owned by the user and lives inside the Notes subtree, otherwise the root.
     */
    private function resolveNotesFolder($id, int $userId, File $root): File
    {
        if (! $id || (int) $id === $root->id) {
            return $root;
        }

        $folder = File::folders()->where('owner_id', $userId)->find($id);
        if (! $folder) {
            return $root;
        }

        // Single recursive CTE: walk the ancestor chain from $id up to the root.
        // Returns a row if $root->id appears in the path — no N+1 query loop.
        $found = \Illuminate\Support\Facades\DB::selectOne('
            WITH RECURSIVE anc(id, parent_id) AS (
                SELECT id, parent_id FROM files WHERE id = :start
                UNION ALL
                SELECT f.id, f.parent_id FROM files f
                    INNER JOIN anc ON f.id = anc.parent_id
            )
            SELECT 1 AS found FROM anc WHERE id = :root LIMIT 1
        ', ['start' => $folder->id, 'root' => $root->id]);

        return $found ? $folder : $root;
    }

    // Lazily create (and return) the user's root Notes folder.
    private function notesRoot(int $userId): File
    {
        return app(\App\Services\NoteCreator::class)->rootFor($userId);
    }

    /**
     * All folder ids in the subtree rooted at $rootId (inclusive), walked from
     * the prefetched folder collection — no recursive queries.
     *
     * @param  \Illuminate\Support\Collection<int, File>  $folders
     * @return array<int, int>
     */
    private function subtreeIds(int $rootId, $folders): array
    {
        $childrenByParent = $folders->groupBy('parent_id');
        $ids = [$rootId];
        $stack = [$rootId];
        while ($stack) {
            $parent = array_pop($stack);
            foreach ($childrenByParent->get($parent, []) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    // Append "(copy)" / "(copy N)" until the name is free under the folder.
    private function uniqueName(int $parentId, string $name, int $ownerId): string
    {
        return app(\App\Services\NoteCreator::class)->uniqueName($parentId, $name, $ownerId);
    }
}
