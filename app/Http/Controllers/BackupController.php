<?php

namespace App\Http\Controllers;

use App\Jobs\RestoreBackup;
use App\Jobs\RunBackup;
use App\Models\Backup;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    public const FREQUENCIES = ['off', 'daily', 'weekly', 'monthly'];

    public function index()
    {
        $backups = Backup::with('creator:id,name')
            ->latest('id')
            ->get()
            ->map(fn (Backup $b) => [
                'id' => $b->id,
                'filename' => $b->filename,
                'size' => $b->size,
                'status' => $b->status,
                'compression' => $b->compression,
                'note' => $b->note,
                'created_by' => $b->creator?->name,
                'created_at' => $b->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Backups', [
            'backups' => $backups,
            'schedule' => [
                'frequency' => Setting::get('backup.frequency', 'off'),
                'retention' => (int) Setting::get('backup.retention', 7),
            ],
            'frequencies' => self::FREQUENCIES,
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'frequency' => 'required|in:'.implode(',', self::FREQUENCIES),
            'retention' => 'required|integer|min:1|max:365',
        ]);

        Setting::put('backup.frequency', $data['frequency']);
        Setting::put('backup.retention', $data['retention']);
        // The scheduler caches the frequency — drop it so the change applies now.
        \Illuminate\Support\Facades\Cache::forget('schedule.backup.frequency');

        return back()->with('success', 'Backup schedule saved.');
    }

    // Kick off a backup now (queued so a large archive doesn't block the request).
    public function run()
    {
        RunBackup::dispatch(auth()->id());
        Audit::log('backup.run');

        return back()->with('success', 'Backup started. It will appear below when ready.');
    }

    public function download(Backup $backup)
    {
        abort_unless($backup->status === Backup::STATUS_READY && $backup->path, 404);
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);

        Audit::log('backup.download', null, ['backup_id' => $backup->id]);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    // Restore from a backup. Destructive — overwrites the live DB + files.
    public function restore(Backup $backup)
    {
        abort_unless($backup->status === Backup::STATUS_READY, 404);

        RestoreBackup::dispatch($backup->id);
        Audit::log('backup.restore', null, ['backup_id' => $backup->id]);

        return back()->with('success', 'Restore started. The app may be briefly unavailable while data is replaced.');
    }

    public function destroy(Backup $backup, BackupService $service)
    {
        $service->delete($backup);
        Audit::log('backup.delete', null, ['backup_id' => $backup->id]);

        return back()->with('success', 'Backup deleted.');
    }
}
