<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicShareController extends Controller
{
    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Public landing page for a share link.
    public function show(string $token)
    {
        $link = $this->resolve($token);
        $file = $link->file;

        if ($link->isProtected() && ! $this->unlocked($token)) {
            return Inertia::render('Public/Share', ['locked' => true, 'token' => $token, 'name' => $file->name]);
        }

        $type = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

        return Inertia::render('Public/Share', [
            'locked' => false,
            'token' => $token,
            'name' => $file->name,
            'size' => $file->size,
            'type' => $type,
            'mime' => $file->mime,
            'allow_download' => $link->allow_download,
            'previewable' => in_array($type, self::IMAGE_TYPES, true) || $type === 'pdf',
            'preview_url' => route('shares.public.raw', $token),
            'download_url' => $link->allow_download ? route('shares.public.download', $token) : null,
        ]);
    }

    // Verify a password and unlock the link for this session.
    public function unlock(Request $request, string $token)
    {
        $link = $this->resolve($token);
        $request->validate(['password' => 'required|string']);

        if (! $link->isProtected() || ! Hash::check($request->input('password'), $link->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put($this->sessionKey($token), true);

        return redirect()->route('shares.public.show', $token);
    }

    // Stream the file inline for preview.
    public function raw(string $token)
    {
        $link = $this->resolve($token);
        $this->assertUnlocked($link, $token);

        abort_unless(Storage::disk($link->file->disk)->exists($link->file->path), 404);

        return Storage::disk($link->file->disk)->response($link->file->path, $link->file->name, [
            'Content-Type' => $link->file->mime ?: 'application/octet-stream',
        ]);
    }

    // Download the file (only when the link allows it).
    public function download(string $token)
    {
        $link = $this->resolve($token);
        $this->assertUnlocked($link, $token);
        abort_unless($link->allow_download, 403);
        abort_unless(Storage::disk($link->file->disk)->exists($link->file->path), 404);

        Audit::log('link.download', $link->file, ['token' => $token], userId: $link->created_by);

        return Storage::disk($link->file->disk)->download($link->file->path, $link->file->name);
    }

    // Resolve a live, non-expired link to an existing (non-trashed) file.
    protected function resolve(string $token): ShareLink
    {
        $link = ShareLink::where('token', $token)->first();

        abort_if($link === null || $link->isExpired() || $link->file === null, 404);

        return $link;
    }

    protected function assertUnlocked(ShareLink $link, string $token): void
    {
        abort_if($link->isProtected() && ! $this->unlocked($token), 403);
    }

    protected function unlocked(string $token): bool
    {
        return (bool) session($this->sessionKey($token));
    }

    protected function sessionKey(string $token): string
    {
        return "share_unlocked.{$token}";
    }
}
