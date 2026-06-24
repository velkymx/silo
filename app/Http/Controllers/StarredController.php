<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\File;
use Inertia\Inertia;

/**
 * A cross-app view of everything the user has starred: notes, bookmarks, and
 * files — each linking back to its own surface.
 */
class StarredController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $notes = File::where('owner_id', $userId)->files()->where('starred', true)
            ->where('mime', 'text/markdown')->orderBy('name')->get()
            ->map(fn (File $f) => [
                'id' => $f->id,
                'title' => pathinfo($f->name, PATHINFO_FILENAME),
                'updated_at' => ($f->content_edited_at ?? $f->updated_at)->format('Y-m-d H:i'),
            ]);

        $files = File::where('owner_id', $userId)->files()->where('starred', true)
            ->where(fn ($q) => $q->whereNull('mime')->orWhere('mime', '!=', 'text/markdown'))
            ->orderBy('name')->get()
            ->map(fn (File $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'type' => strtolower(pathinfo($f->name, PATHINFO_EXTENSION)),
            ]);

        $bookmarks = Bookmark::visibleTo($userId)->where('starred', true)
            ->orderBy('title')->get()
            ->map(fn (Bookmark $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'url' => $b->url,
            ]);

        return Inertia::render('Starred/Index', [
            'notes' => $notes->values(),
            'bookmarks' => $bookmarks->values(),
            'files' => $files->values(),
        ]);
    }
}
