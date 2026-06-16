<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\ShareLink;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareLinkController extends Controller
{
    // List a file's public links (for the Share dialog).
    public function index(File $file)
    {
        $this->authorize('share', $file);

        return response()->json(['links' => $this->shape($file)]);
    }

    // Create a public link for a file.
    public function store(Request $request, File $file)
    {
        $this->authorize('share', $file);
        abort_if($file->is_dir, 422, 'Folders cannot be shared by link yet.');

        $data = $request->validate([
            'allow_download' => 'boolean',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
            'password' => 'nullable|string|min:4|max:255',
        ]);

        ShareLink::create([
            'file_id' => $file->id,
            'token' => Str::random(40),
            'allow_download' => $data['allow_download'] ?? true,
            'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
            'created_by' => auth()->id(),
        ]);

        Audit::log('link.create', $file, [
            'allow_download' => $data['allow_download'] ?? true,
            'protected' => isset($data['password']),
        ]);

        return response()->json(['links' => $this->shape($file)]);
    }

    // Revoke a link.
    public function destroy(File $file, ShareLink $link)
    {
        $this->authorize('share', $file);
        abort_unless($link->file_id === $file->id, 404);

        Audit::log('link.revoke', $file);
        $link->delete();

        return response()->json(['links' => $this->shape($file)]);
    }

    // Shape a file's links for the frontend.
    private function shape(File $file): array
    {
        return $file->shareLinks()->latest()->get()->map(fn (ShareLink $link) => [
            'id' => $link->id,
            'url' => route('shares.public.show', $link->token),
            'allow_download' => $link->allow_download,
            'protected' => $link->isProtected(),
            'expires_at' => $link->expires_at?->format('Y-m-d H:i'),
            'expired' => $link->isExpired(),
        ])->all();
    }
}
