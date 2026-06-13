<?php

namespace App\Http\Controllers;

use App\Jobs\ImportScan;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        $root = config('filesystems.disks.import.root');

        $fileCount = null;
        try {
            $fileCount = count(Storage::disk('import')->allFiles());
        } catch (\Throwable $e) {
            // Disk root not present (no folder mounted) — leave count unknown.
        }

        return Inertia::render('Admin/Import', [
            'root' => $root,
            'fileCount' => $fileCount,
        ]);
    }

    public function rescan(Request $request)
    {
        $name = trim((string) $request->input('name')) ?: 'Imported';

        ImportScan::dispatch(auth()->id(), $name);
        Audit::log('import.rescan', null, ['name' => $name]);

        return back()->with('success', 'Re-scan started. New files will appear as they are indexed.');
    }
}
