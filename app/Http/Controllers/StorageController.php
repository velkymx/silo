<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\QuotaService;
use Inertia\Inertia;

class StorageController extends Controller
{
    // WinDirStat-style storage analysis: a sized tree + largest files + type mix.
    public function index()
    {
        $userId = auth()->id();

        $rows = File::where('owner_id', $userId)
            ->get(['id', 'name', 'parent_id', 'is_dir', 'size', 'mime', 'created_at']);

        // Aggregate folder sizes from descendant files (post-order over the tree).
        $childrenByParent = $rows->groupBy(fn ($r) => $r->parent_id ?? 0);
        $sizes = [];
        $aggregate = function ($parentKey) use (&$aggregate, $childrenByParent, &$sizes) {
            $total = 0;
            foreach ($childrenByParent->get($parentKey, collect()) as $node) {
                $self = $node->is_dir ? $aggregate($node->id) : (int) $node->size;
                $sizes[$node->id] = $self;
                $total += $self;
            }
            return $total;
        };
        $aggregate(0);

        $nodes = $rows->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'parent_id' => $r->parent_id,
            'is_dir' => (bool) $r->is_dir,
            'size' => $sizes[$r->id] ?? (int) $r->size,
            'category' => $r->is_dir ? 'folder' : $this->category($r),
        ])->values();

        $largest = $rows->where('is_dir', false)
            ->sortByDesc('size')->take(50)
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'size' => (int) $r->size,
                'category' => $this->category($r),
                'created_at' => $r->created_at->format('Y-m-d'),
            ])->values();

        $byCategory = $rows->where('is_dir', false)
            ->groupBy(fn ($r) => $this->category($r))
            ->map(fn ($g) => $g->sum('size'))
            ->sortDesc();

        return Inertia::render('Storage/Index', [
            'summary' => app(QuotaService::class)->summary($userId),
            'nodes' => $nodes,
            'largest' => $largest,
            'byCategory' => $byCategory,
        ]);
    }

    private function category(File $file): string
    {
        $mime = (string) $file->mime;
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';

        $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if ($ext === 'pdf') return 'pdf';
        if (in_array($ext, ['doc', 'docx', 'txt', 'md', 'rtf', 'odt'])) return 'document';
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'])) return 'spreadsheet';
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) return 'archive';

        return 'other';
    }
}
