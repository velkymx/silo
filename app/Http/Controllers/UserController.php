<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\ThumbnailGenerator;
use App\Support\FileResponse;
use Inertia\Inertia;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class UserController extends Controller
{
    // Display the profile edit form
    public function edit()
    {
        $user = Auth::user();

        return Inertia::render('Profile/Edit', [
            'user' => array_merge(
                $user->only('id', 'name', 'email', 'group_id'),
                ['avatar_url' => $user->avatar_path ? route('users.avatar', $user) : null],
            ),
            'groups' => \App\Models\Group::all(['id', 'name']),
        ]);
    }

    // Upload (already client-cropped) avatar image.
    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:5120']);

        $user = Auth::user();
        $disk = Storage::disk(ThumbnailGenerator::disk());

        // Normalize to a 256px square JPEG.
        $image = (new ImageManager(Driver::class))->decodeBinary($request->file('avatar')->get());
        $image->cover(256, 256);
        $path = 'avatars/'.$user->id.'/'.Str::random(24).'.jpg';
        $disk->put($path, (string) $image->encode(new JpegEncoder(quality: 85)));

        if ($user->avatar_path) {
            $disk->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $path]);

        return back()->with('success', 'Photo updated.');
    }

    // Stream a user's avatar (visible to any authenticated user).
    public function avatar(User $user)
    {
        $disk = ThumbnailGenerator::disk();
        abort_unless($user->avatar_path && Storage::disk($disk)->exists($user->avatar_path), 404);

        // Avatars are app-generated JPEGs — safe inline.
        return FileResponse::serve(Storage::disk($disk), $user->avatar_path, 'avatar.jpg', 'image/jpeg');
    }

    // Handle profile update
    public function update(Request $request)
    {
        $user = Auth::user();
    
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed', // Password confirmation required if provided
        ]);

        // Update user details. Group membership is NOT self-assignable — only
        // admins set it (privilege escalation otherwise).
        $user->name = $request->name;
        $user->email = $request->email;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
    
        $user->save();
    
        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}
