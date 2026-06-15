<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Tag;
use App\Services\Audit;
use App\Services\QuotaService;
use App\Support\FileResponse;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FileController extends Controller
{
    // Display files and folders for the current (DB-backed) folder.
    public function index(Request $request)
    {
        $userId = auth()->id();

        $current = null;
        if ($request->filled('folder')) {
            $current = File::folders()->where('owner_id', $userId)->findOrFail($request->integer('folder'));
            $this->authorize('view', $current);
        }

        $sort = in_array($request->get('sort'), ['name', 'size', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $searching = $request->filled('search');
        $advanced = $request->hasAny(['date_from', 'date_to', 'size_min', 'size_max', 'ftype']);
        // Limit search to the current folder (and its subfolders) when requested.
        $scopeFolderId = $request->input('scope') === 'folder' ? ($request->integer('folder') ?: null) : null;
        $starredOnly = $request->boolean('starred');
        $recentOnly = $request->boolean('recent');
        $activeTag = $request->filled('tag')
            ? Tag::where('owner_id', $userId)->find($request->integer('tag'))
            : null;

        // One query for every folder the user owns: powers the tree, the move/copy
        // picker, and search/tag location labels (no per-file parent walking).
        $allFolders = File::folders()->where('owner_id', $userId)
            ->orderBy('name')->get(['id', 'name', 'parent_id']);
        $folderById = $allFolders->keyBy('id');

        // The VibeUI DataTable paginates client-side; a safety cap keeps the
        // payload bounded on very large result sets.
        $cap = 1000;

        if ($advanced || ($searching && $scopeFolderId)) {
            // Structured / folder-scoped search via a DB query.
            $folders = collect();
            $query = $this->advancedQuery($request, $userId, $activeTag);
            if ($scopeFolderId) {
                $query->whereIn('parent_id', $this->subtreeFolderIds($scopeFolderId, $allFolders));
            }
            $files = $query->with(['versions', 'tags'])->latest('created_at')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($searching) {
            // Full-text search (Scout) spans every folder the user owns.
            $folders = collect();
            $files = File::search($request->string('search')->toString())
                ->where('owner_id', $userId)->where('is_dir', false)
                ->take($cap)->get()->load(['versions', 'tags'])
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($activeTag) {
            // Tag filter spans every folder the user owns (folders + files).
            $folders = $activeTag->files()->where('owner_id', $userId)->where('is_dir', true)
                ->withCount('children')->with('tags')->orderBy('name')->get()
                ->map(fn (File $folder) => $this->folderShape($folder));
            $files = $activeTag->files()->where('owner_id', $userId)->where('is_dir', false)
                ->with(['versions', 'tags'])->orderBy('name')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($starredOnly) {
            // Starred items across every folder the user owns.
            $folders = File::query()->where('owner_id', $userId)->where('starred', true)->folders()
                ->withCount('children')->with('tags')->orderBy('name')->get()
                ->map(fn (File $folder) => $this->folderShape($folder));
            $files = File::query()->where('owner_id', $userId)->where('starred', true)->files()
                ->with(['versions', 'tags'])->orderBy('name')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } elseif ($recentOnly) {
            // Most recently uploaded files across every folder.
            $folders = collect();
            $files = File::query()->where('owner_id', $userId)->files()
                ->with(['versions', 'tags'])->latest('created_at')->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file) + ['location' => $this->locationLabel($file, $folderById)]);
        } else {
            $query = File::query()->where('owner_id', $userId)->where('parent_id', $current?->id);

            $folders = (clone $query)->folders()->withCount('children')->with('tags')
                ->orderBy($sort, $direction)->get()
                ->map(fn (File $folder) => $this->folderShape($folder));

            $files = (clone $query)->files()->with(['versions', 'tags'])
                ->orderBy($sort, $direction)->limit($cap)->get()
                ->map(fn (File $file) => $this->transform($file));
        }

        return Inertia::render('Files/Index', [
            'folders' => $folders->values(),
            'files' => $files->values(),
            'current' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'breadcrumbs' => $this->breadcrumbs($current),
            'searching' => $searching || $advanced,
            'advanced' => $advanced,
            'starredOnly' => $starredOnly,
            'recentOnly' => $recentOnly,
            'flat' => $searching || $advanced || (bool) $activeTag || $starredOnly || $recentOnly,
            'activeTag' => $activeTag ? ['id' => $activeTag->id, 'name' => $activeTag->name] : null,
            // Flat list of every folder the user owns — used by the tree + move/copy picker.
            'allFolders' => $allFolders,
            'allTags' => Tag::where('owner_id', $userId)->orderBy('name')->get(['id', 'name', 'color']),
            'storage' => app(QuotaService::class)->summary($userId),
            'maxUploadKb' => Uploads::maxKb(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'date_from' => $request->string('date_from')->toString() ?: null,
                'date_to' => $request->string('date_to')->toString() ?: null,
                'size_min' => $request->input('size_min'),
                'size_max' => $request->input('size_max'),
                'ftype' => $request->string('ftype')->toString() ?: null,
                'scope' => $scopeFolderId ? 'folder' : 'all',
            ],
        ]);
    }

    // File-type categories for advanced search → extension / mime matchers.
    private const TYPE_FILTERS = [
        'image' => ['mime' => 'image/%'],
        'video' => ['mime' => 'video/%'],
        'audio' => ['mime' => 'audio/%'],
        'pdf' => ['ext' => ['pdf']],
        'document' => ['ext' => ['doc', 'docx', 'txt', 'md', 'rtf', 'odt']],
        'spreadsheet' => ['ext' => ['xls', 'xlsx', 'csv', 'ods']],
        'archive' => ['ext' => ['zip', 'rar', '7z', 'tar', 'gz']],
    ];

    /** A folder id plus all of its descendant folder ids (for scoped search). */
    private function subtreeFolderIds(int $folderId, \Illuminate\Support\Collection $allFolders): array
    {
        $childrenByParent = $allFolders->groupBy('parent_id');
        $ids = [$folderId];
        $stack = [$folderId];
        while ($stack) {
            $parent = array_pop($stack);
            foreach ($childrenByParent->get($parent, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    private function advancedQuery(Request $request, int $userId, ?Tag $activeTag)
    {
        $q = File::query()->where('owner_id', $userId)->where('is_dir', false);

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $q->where('name', 'like', "%{$term}%");
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('size_min')) {
            $q->where('size', '>=', (int) ($request->float('size_min') * 1024 * 1024));
        }
        if ($request->filled('size_max')) {
            $q->where('size', '<=', (int) ($request->float('size_max') * 1024 * 1024));
        }
        if ($activeTag) {
            $q->whereHas('tags', fn ($t) => $t->where('tags.id', $activeTag->id));
        }
        if ($request->filled('ftype') && isset(self::TYPE_FILTERS[$request->string('ftype')->toString()])) {
            $rule = self::TYPE_FILTERS[$request->string('ftype')->toString()];
            if (isset($rule['mime'])) {
                $q->where('mime', 'like', $rule['mime']);
            } else {
                $q->where(function ($sub) use ($rule) {
                    foreach ($rule['ext'] as $ext) {
                        $sub->orWhere('name', 'like', "%.{$ext}");
                    }
                });
            }
        }

        return $q;
    }

    // Create a new text/markdown file from editor content.
    public function createText(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'present|string',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');

        $name = trim((string) $request->string('name'));
        if (! pathinfo($name, PATHINFO_EXTENSION)) {
            $name .= '.md';
        }
        $content = (string) $request->input('content');
        if (app(QuotaService::class)->wouldExceed($userId, strlen($content))) {
            throw ValidationException::withMessages(['name' => 'This would exceed your storage quota.']);
        }

        $path = "uploads/{$userId}/".Str::random(40);
        if ($ext = pathinfo($name, PATHINFO_EXTENSION)) {
            $path .= ".{$ext}";
        }
        Storage::disk($disk)->put($path, $content);

        $file = $this->withFolderLock($userId, $parent?->id, function () use ($name, $path, $disk, $content, $parent, $userId) {
            $this->assertNoCollision($parent?->id, $name, $userId);

            return File::create([
                'name' => $name,
                'path' => $path,
                'disk' => $disk,
                'is_dir' => false,
                'mime' => str_ends_with($name, '.md') ? 'text/markdown' : 'text/plain',
                'size' => strlen($content),
                'hash' => hash('sha256', $content),
                'status' => File::STATUS_PENDING,
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);
        Audit::log('file.create', $file);

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Created “{$name}”.");
    }

    // Office document types that can be created blank and edited in the browser.
    private const NEW_DOC_TYPES = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Open the editor on a blank document of the given type (the file is not
    // created until the first Save).
    public function newDocument(Request $request, string $type)
    {
        abort_unless(isset(self::NEW_DOC_TYPES[$type]), 404);

        return Inertia::render('Files/Editor', [
            'file' => null,
            'create' => [
                'type' => $type,
                'name' => 'Untitled.'.$type,
                'parent_id' => $request->integer('folder') ?: null,
            ],
        ]);
    }

    // Persist a freshly created office document (binary blob from the editor).
    public function storeDocument(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', array_keys(self::NEW_DOC_TYPES)),
            'parent_id' => 'nullable|integer|exists:files,id',
            'file' => 'required|file|max:'.Uploads::maxKb(),
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');
        $type = $request->input('type');

        $name = trim((string) $request->string('name'));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $type) {
            $name .= '.'.$type;
        }
        $bytes = $request->file('file')->get();
        if (app(QuotaService::class)->wouldExceed($userId, strlen($bytes))) {
            throw ValidationException::withMessages(['name' => 'This would exceed your storage quota.']);
        }

        $path = "uploads/{$userId}/".Str::random(40).'.'.$type;
        Storage::disk($disk)->put($path, $bytes);

        $file = $this->withFolderLock($userId, $parent?->id, function () use ($name, $path, $disk, $type, $bytes, $parent, $userId) {
            $this->assertNoCollision($parent?->id, $name, $userId);

            return File::create([
                'name' => $name,
                'path' => $path,
                'disk' => $disk,
                'is_dir' => false,
                'mime' => self::NEW_DOC_TYPES[$type],
                'size' => strlen($bytes),
                'hash' => hash('sha256', $bytes),
                'status' => File::STATUS_PENDING,
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);
        Audit::log('file.create', $file);

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Created “{$name}”.");
    }

    // Open the full-screen editor page for an editable file (office docs, text).
    public function edit(File $file)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);

        return Inertia::render('Files/Editor', [
            'file' => [
                'id' => $file->id,
                'name' => $file->name,
                'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
                'mime' => $file->mime,
                'version' => $file->version,
                'parent_id' => $file->parent_id,
                'raw_url' => route('files.raw', $file),
            ],
        ]);
    }

    // Save edited content as a new version of a file. Accepts either a binary
    // upload ("file", for office docs) or raw text ("content"), plus an optional
    // git-style note describing what changed.
    public function updateContent(Request $request, File $file)
    {
        $this->authorize('update', $file);
        abort_if($file->is_dir, 404);

        $request->validate([
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:'.Uploads::maxKb(),
            'note' => 'nullable|string|max:1000',
        ]);

        $upload = $request->file('file');
        if (! $upload && ! $request->has('content')) {
            throw ValidationException::withMessages(['content' => 'Nothing to save.']);
        }

        $content = $upload ? $upload->get() : (string) $request->input('content');
        $note = $request->filled('note') ? trim((string) $request->input('note')) : null;
        $userId = $file->owner_id;
        $disk = config('filemanager.disk');

        if (app(QuotaService::class)->wouldExceed($userId, strlen($content) - (int) $file->size)) {
            throw ValidationException::withMessages(['file' => 'This would exceed your storage quota.']);
        }

        $newPath = "uploads/{$userId}/".Str::random(40);
        if ($ext = pathinfo($file->name, PATHINFO_EXTENSION)) {
            $newPath .= ".{$ext}";
        }
        Storage::disk($disk)->put($newPath, $content);

        DB::transaction(function () use ($file, $newPath, $disk, $content, $note) {
            $this->snapshotVersion($file, null, $note);
            $file->update([
                'path' => $newPath,
                'disk' => $disk,
                'size' => strlen($content),
                'hash' => hash('sha256', $content),
                'version' => $file->version + 1,
                'status' => File::STATUS_PENDING,
                'referenced' => false, // edited content is now app-owned
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);
        Audit::log('file.edit', $file);

        return redirect()->route('files.index', ['folder' => $file->parent_id])
            ->with('success', 'Saved.');
    }

    // ---- Batch operations (multi-select) ----

    /** Load files by id that the current user owns, preserving the given order. */
    private function ownedBatch(array $ids): \Illuminate\Support\Collection
    {
        $userId = auth()->id();
        $files = File::whereIn('id', $ids)->where('owner_id', $userId)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $files->get($id))->filter()->values();
    }

    public function batchMove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'target_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $target = $this->resolveFolder($request->input('target_id'), $userId);

        DB::transaction(function () use ($request, $target) {
            foreach ($this->ownedBatch($request->input('ids')) as $file) {
                if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
                    throw ValidationException::withMessages(['target_id' => "Cannot move \"{$file->name}\" into itself."]);
                }
                $this->assertNoCollision($target?->id, $file->name, $file->owner_id, $file->id);
                $file->update(['parent_id' => $target?->id]);
            }
        });

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Moved selected items.');
    }

    public function batchDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        DB::transaction(function () use ($request) {
            foreach ($this->ownedBatch($request->input('ids')) as $file) {
                if ($file->is_dir) {
                    $this->trashSubtree($file);
                }
                $file->delete();
                Audit::log('file.trash', $file);
            }
        });

        return back()->with('success', 'Moved selected items to trash.');
    }

    // New Folder From Selection: create a folder in the current location and
    // move the selected items into it (Finder-style).
    public function batchFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $name = trim((string) $request->string('name'));
        $this->assertNoCollision($parent?->id, $name, $userId);

        $folder = DB::transaction(function () use ($name, $parent, $userId, $request) {
            $folder = File::create([
                'name' => $name,
                'path' => $name,
                'disk' => config('filemanager.disk'),
                'is_dir' => true,
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);

            foreach ($this->ownedBatch($request->input('ids')) as $file) {
                if ($file->id === $folder->id) {
                    continue;
                }
                $this->assertNoCollision($folder->id, $file->name, $userId, $file->id);
                $file->update(['parent_id' => $folder->id]);
            }

            return $folder;
        });

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Created “{$folder->name}” from selection.");
    }

    // Batch rename: the client computes the final names (find/replace, prefix/
    // suffix, numbering) and previews them; we apply with collision checks.
    public function batchRename(Request $request)
    {
        $request->validate([
            'renames' => 'required|array',
            'renames.*.id' => 'required|integer',
            'renames.*.name' => 'required|string|max:255',
        ]);

        $userId = auth()->id();
        $byId = collect($request->input('renames'))->keyBy('id');

        DB::transaction(function () use ($byId, $userId) {
            $files = File::whereIn('id', $byId->keys())->where('owner_id', $userId)->get();
            foreach ($files as $file) {
                $name = trim((string) $byId[$file->id]['name']);
                if ($name === '' || $name === $file->name) {
                    continue;
                }
                $this->assertNoCollision($file->parent_id, $name, $userId, $file->id);
                $file->update(['name' => $name]);
            }
        });

        return back()->with('success', 'Renamed selected items.');
    }

    // Lazy children for the VSCode-style sidebar tree: direct folders + files of
    // a folder (or the root), folders first.
    public function tree(Request $request, ?File $folder = null)
    {
        $userId = auth()->id();
        if ($folder) {
            abort_unless($folder->owner_id === $userId && $folder->is_dir, 404);
        }

        $children = File::where('owner_id', $userId)
            ->where('parent_id', $folder?->id)
            ->orderByDesc('is_dir')
            ->orderBy('name')
            ->get(['id', 'name', 'is_dir', 'parent_id']);

        // Which of these folders actually contain something — one extra query
        // instead of N, so the sidebar can hide the twisty for empty folders.
        $dirIds = $children->where('is_dir', true)->pluck('id');
        $parentsWithChildren = $dirIds->isEmpty()
            ? collect()
            : File::where('owner_id', $userId)
                ->whereIn('parent_id', $dirIds)
                ->distinct()
                ->pluck('parent_id')
                ->flip();

        return response()->json($children->map(fn (File $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'parent_id' => $f->parent_id,
            'is_dir' => (bool) $f->is_dir,
            'type' => strtolower(pathinfo($f->name, PATHINFO_EXTENSION)),
            'has_children' => (bool) $f->is_dir && $parentsWithChildren->has($f->id),
        ])->values());
    }

    // Toggle a file's or folder's starred (favorite) flag.
    public function star(File $file)
    {
        $this->authorize('update', $file);
        $file->update(['starred' => ! $file->starred]);

        return back()->with('success', $file->starred ? 'Added to starred.' : 'Removed from starred.');
    }

    // Replace a file's tags from a list of names (creating tags as needed).
    public function syncTags(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate([
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ]);

        $ids = collect($request->input('tags', []))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(
                ['owner_id' => $file->owner_id, 'name' => $name],
            )->id);

        $file->tags()->sync($ids);

        return back()->with('success', 'Tags updated.');
    }

    // Human-readable folder path for a search result ("Home / Docs / 2026"),
    // resolved from a prefetched folder map to avoid per-file queries.
    protected function locationLabel(File $file, \Illuminate\Support\Collection $folderById): array
    {
        $names = [];
        for ($id = $file->parent_id; $id && ($node = $folderById->get($id)); $id = $node->parent_id) {
            array_unshift($names, $node->name);
        }

        return [
            'folder_id' => $file->parent_id,
            'path' => 'Home'.collect($names)->reduce(fn ($carry, $n) => $carry.' / '.$n, ''),
        ];
    }

    // Shape a folder model for the frontend (id, counts, tags).
    protected function folderShape(File $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'is_dir' => true,
            'starred' => (bool) $folder->starred,
            'item_count' => $folder->children_count ?? $folder->children()->count(),
            'updated_at' => $folder->updated_at->format('Y-m-d H:i'),
            'tags' => $folder->relationLoaded('tags')
                ? $folder->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
        ];
    }

    // Shape a file model for the frontend.
    protected function transform(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'is_dir' => false,
            'size' => $file->size,
            'mime' => $file->mime,
            'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
            'url' => route('files.raw', $file),
            'status' => $file->status,
            'metadata' => $file->metadata,
            'thumb_url' => $file->thumbnail_path ? route('files.thumbnail', $file) : null,
            'tags' => $file->relationLoaded('tags')
                ? $file->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
            'hash' => $file->hash,
            'version' => $file->version,
            'starred' => (bool) $file->starred,
            'versions' => $file->relationLoaded('versions')
                ? $file->versions->map(fn (FileVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'note' => $v->note,
                    'size' => $v->size,
                    'created_at' => $v->created_at->format('Y-m-d H:i'),
                ])->values()
                : [],
            'created_at' => $file->created_at->format('Y-m-d H:i'),
        ];
    }

    // Build the breadcrumb trail from root to the current folder.
    protected function breadcrumbs(?File $current): array
    {
        $trail = [];
        for ($node = $current; $node; $node = $node->parent) {
            array_unshift($trail, ['id' => $node->id, 'name' => $node->name]);
        }

        return $trail;
    }

    // Upload one or more files into the current folder.
    public function upload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|max:'.Uploads::maxKb(),
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $disk = config('filemanager.disk');

        // Reject the whole batch if it would push the user over their quota.
        $incoming = collect($request->file('files', []))->sum(fn ($f) => $f->getSize());
        if (app(QuotaService::class)->wouldExceed($userId, $incoming)) {
            throw ValidationException::withMessages([
                'files' => 'This upload would exceed your storage quota.',
            ]);
        }

        foreach ($request->file('files', []) as $upload) {
            // Storage is flat per user; the folder hierarchy lives entirely in the DB.
            $path = $upload->store("uploads/{$userId}", $disk);

            $attributes = [
                'name' => $upload->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'hash' => hash_file('sha256', $upload->getRealPath()),
                'status' => File::STATUS_PENDING,
            ];

            // An upload onto an existing file name becomes a new version of that file.
            // (resolveFolder() guarantees $parent is owned by the uploader, so a
            // cross-owner collision in a foreign folder can't occur here.)
            $existing = File::files()
                ->where('owner_id', $userId)
                ->where('parent_id', $parent?->id)
                ->where('name', $upload->getClientOriginalName())
                ->first();

            $file = $existing
                ? $this->overwrite($existing, $attributes, $userId)
                : File::create($attributes + ['is_dir' => false, 'parent_id' => $parent?->id, 'owner_id' => $userId]);

            // Refine mime + extract metadata off the request cycle.
            ProcessUploadedFile::dispatch($file->id);
            Audit::log('file.upload', $file, ['size' => $file->size]);
        }

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', 'Files uploaded successfully!');
    }

    // Download a file resolved by DB id (no client-supplied paths).
    public function download(File $file)
    {
        $this->authorize('view', $file);

        abort_if($file->is_dir, 404);
        // Fail closed: only serve files that finished processing/AV-scanning.
        // Referenced (admin-imported, trusted) blobs are exempt — they were
        // never an untrusted user upload.
        abort_unless($file->status === File::STATUS_READY || $file->referenced, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        Audit::log('file.download', $file);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    // Stream a file's bytes inline (policy-gated) for previews/embeds.
    public function raw(File $file)
    {
        $this->authorize('view', $file);

        abort_if($file->is_dir, 404);
        // Fail closed: only serve files that finished processing/AV-scanning.
        // Referenced (admin-imported, trusted) blobs are exempt — they were
        // never an untrusted user upload.
        abort_unless($file->status === File::STATUS_READY || $file->referenced, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return FileResponse::serve(Storage::disk($file->disk), $file->path, $file->name, $file->mime);
    }

    // Stream a file's cached thumbnail (policy-gated, not a public URL).
    public function thumbnail(File $file)
    {
        $this->authorize('view', $file);

        $disk = \App\Services\ThumbnailGenerator::disk();
        abort_unless(
            $file->thumbnail_path && Storage::disk($disk)->exists($file->thumbnail_path),
            404
        );

        // Thumbnails are app-generated JPEGs — safe to render inline.
        return FileResponse::serve(Storage::disk($disk), $file->thumbnail_path, 'thumbnail.jpg', 'image/jpeg');
    }

    // Soft-delete a file or folder (and its contents) into the trash.
    // Blobs are kept until the item is purged from the trash, so it can be restored.
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        DB::transaction(function () use ($file) {
            if ($file->is_dir) {
                $this->trashSubtree($file);
            }

            $file->delete();
        });

        Audit::log('file.trash', $file);

        return redirect()->route('files.index', ['folder' => $file->parent_id])
            ->with('success', 'Moved to trash.');
    }

    // Create a new folder inside the current folder.
    public function createFolder(Request $request)
    {
        $request->validate([
            'folder_name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $name = trim((string) $request->string('folder_name'));

        $this->withFolderLock($userId, $parent?->id, function () use ($name, $parent, $userId) {
            $this->assertNoCollision($parent?->id, $name, $userId);

            File::create([
                'name' => $name,
                'path' => $name, // informational only; folders have no disk directory
                'disk' => config('filemanager.disk'),
                'is_dir' => true,
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);
        });

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Folder '{$name}' created successfully!");
    }

    // Rename a file or folder (display name only; storage path is stable).
    public function rename(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['name' => 'required|string|max:255']);
        $name = trim((string) $request->string('name'));

        $this->withFolderLock($file->owner_id, $file->parent_id, function () use ($file, $name) {
            $this->assertNoCollision($file->parent_id, $name, $file->owner_id, $file->id);
            $file->update(['name' => $name]);
        });

        return back()->with('success', 'Renamed successfully!');
    }

    // Move a file or folder into another folder (DB reparent only).
    public function move(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        // The destination is resolved + authorized against the ACTOR, never the
        // file's owner — otherwise source access would imply write to the
        // owner's tree.
        $target = $this->resolveFolder($request->input('target_id'), auth()->id());

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot move a folder into itself or one of its subfolders.',
            ]);
        }

        $this->withFolderLock($file->owner_id, $target?->id, function () use ($file, $target) {
            $this->assertNoCollision($target?->id, $file->name, $file->owner_id, $file->id);
            $file->update(['parent_id' => $target?->id]);
        });

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Moved successfully!');
    }

    // Copy a file or folder (deep) into another folder.
    public function copy(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        // Resolve + authorize the destination against the ACTOR, and the copy is
        // owned by the actor — a viewer can save a copy into their own space but
        // never write into the owner's tree.
        $actorId = (int) auth()->id();
        $target = $this->resolveFolder($request->input('target_id'), $actorId);

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot copy a folder into itself or one of its subfolders.',
            ]);
        }

        $this->withFolderLock($actorId, $target?->id, function () use ($file, $target, $actorId) {
            $name = $this->uniqueName($target?->id, $file->name, $actorId);
            DB::transaction(fn () => $this->copyNode($file, $target?->id, $name, $actorId));
        });

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Copied successfully!');
    }

    // Download a specific historical version of a file.
    public function downloadVersion(File $file, FileVersion $version)
    {
        $this->authorize('view', $file);

        abort_unless($version->file_id === $file->id, 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->name);
    }

    // Restore a historical version as the file's current content.
    public function restoreVersion(File $file, FileVersion $version)
    {
        $this->authorize('update', $file);

        abort_unless($version->file_id === $file->id, 404);

        DB::transaction(function () use ($file, $version) {
            // Preserve the current content as a version before overwriting it.
            $this->snapshotVersion($file);

            $file->update([
                'path' => $version->path,
                'disk' => $version->disk,
                'mime' => $version->mime,
                'size' => $version->size,
                'hash' => $version->hash,
                'version' => $file->version + 1,
                'status' => File::STATUS_PENDING,
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);

        return back()->with('success', "Restored version {$version->version}.");
    }

    // Navigate into a folder resolved by DB id.
    public function viewFolder(File $folder)
    {
        $this->authorize('view', $folder);

        return redirect()->route('files.index', ['folder' => $folder->id]);
    }

    // Replace a file's content, archiving its current blob as a version.
    protected function overwrite(File $file, array $attributes, int $userId): File
    {
        DB::transaction(function () use ($file, $attributes, $userId) {
            $this->snapshotVersion($file, $userId);

            $file->update($attributes + ['version' => $file->version + 1]);
        });

        return $file;
    }

    // Archive the file's current blob as a historical version row.
    protected function snapshotVersion(File $file, ?int $createdBy = null, ?string $note = null): void
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

    // Resolve a folder owned by the user, or null for the root.
    protected function resolveFolder($id, int $userId): ?File
    {
        if (! $id) {
            return null;
        }

        $folder = File::folders()->where('owner_id', $userId)->findOrFail($id);
        $this->authorize('update', $folder);

        return $folder;
    }

    // Recursively soft-delete a folder's descendants into the trash (blobs kept).
    protected function trashSubtree(File $folder): void
    {
        foreach ($folder->children as $child) {
            if ($child->is_dir) {
                $this->trashSubtree($child);
            }

            $child->delete();
        }
    }

    // Recursively copy a node under a new parent, duplicating file blobs.
    protected function copyNode(File $source, ?int $parentId, string $name, int $ownerId): File
    {
        $disk = config('filemanager.disk');
        $path = $source->name; // folder placeholder

        if (! $source->is_dir) {
            $path = "uploads/{$ownerId}/".Str::random(40);
            if ($extension = pathinfo($source->path, PATHINFO_EXTENSION)) {
                $path .= ".{$extension}";
            }
            Storage::disk($source->disk)->copy($source->path, $path);
        }

        $copy = File::create([
            'name' => $name,
            'path' => $path,
            'disk' => $source->is_dir ? $disk : $source->disk,
            'is_dir' => $source->is_dir,
            'mime' => $source->mime,
            'size' => $source->size,
            'hash' => $source->hash,
            'status' => $source->status,
            'metadata' => $source->metadata,
            'thumbnail_path' => null,
            'parent_id' => $parentId,
            'owner_id' => $ownerId,
        ]);

        if ($source->is_dir) {
            foreach ($source->children as $child) {
                $this->copyNode($child, $copy->id, $child->name, $ownerId);
            }
        }

        return $copy;
    }

    // True when $target is $folder itself or sits inside its subtree.
    protected function isSelfOrDescendant(File $folder, File $target): bool
    {
        for ($node = $target; $node; $node = $node->parent) {
            if ($node->id === $folder->id) {
                return true;
            }
        }

        return false;
    }

    // Reject a name that already exists among siblings.
    protected function assertNoCollision(?int $parentId, string $name, int $ownerId, ?int $ignoreId = null): void
    {
        $exists = File::where('owner_id', $ownerId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "An item named \"{$name}\" already exists here.",
            ]);
        }
    }

    // Produce a non-colliding name by appending "(copy)" / "(copy N)".
    /**
     * Serialize name-sensitive writes (create/copy/rename/move) within one
     * folder so concurrent requests can't both pass the uniqueness check and
     * insert a duplicate. Uses an atomic cross-process cache lock.
     */
    protected function withFolderLock(int $ownerId, ?int $parentId, Closure $callback): mixed
    {
        $key = "file-write:{$ownerId}:".($parentId ?? 'root');

        return Cache::lock($key, 10)->block(5, $callback);
    }

    protected function uniqueName(?int $parentId, string $name, int $ownerId): string
    {
        $base = $name;
        $extension = '';
        if (($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $base = substr($name, 0, $dot);
            $extension = substr($name, $dot);
        }

        $candidate = $name;
        $n = 0;
        while (File::where('owner_id', $ownerId)->where('parent_id', $parentId)->where('name', $candidate)->exists()) {
            $n++;
            $suffix = $n === 1 ? ' (copy)' : " (copy {$n})";
            $candidate = "{$base}{$suffix}{$extension}";
        }

        return $candidate;
    }
}
