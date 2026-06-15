<?php

namespace App\Http\Controllers;

use App\Jobs\ImportScan;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        $root = config('filesystems.disks.import.root');

        // Lazily count up to a cap instead of loading the entire tree into memory
        // (an import mount can hold an unbounded number of files).
        $cap = 10000;
        $fileCount = null;
        $capped = false;
        if ($root && is_dir($root)) {
            $fileCount = 0;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $entry) {
                if ($entry->isFile() && ++$fileCount >= $cap) {
                    $capped = true;
                    break;
                }
            }
        }

        return Inertia::render('Admin/Import', [
            'root' => $root,
            'fileCount' => $fileCount,
            'fileCountCapped' => $capped,
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
