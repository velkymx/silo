<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The staff directory: a searchable people index with profile cards, grouped
 * by department. Reuses the same users that power Notes @mentions.
 */
class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $department = trim((string) $request->string('department'));

        $people = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->when($department !== '', fn ($q) => $q->where('department', $department))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->summary($u));

        return Inertia::render('Directory/Index', [
            'people' => $people->values(),
            'departments' => User::query()->whereNotNull('department')
                ->distinct()->orderBy('department')->pluck('department'),
            'filters' => ['search' => $search, 'department' => $department ?: null],
        ]);
    }

    // Full profile page: the person's real profile data + headshot + their wall.
    public function show(Request $request, User $user)
    {
        $user->load('manager:id,name', 'group:id,name');

        return Inertia::render('Directory/Profile', [
            'person' => $this->person($user),
            'wall' => WallController::latest($user->id, $request->user()),
        ]);
    }

    // JSON detail for the directory's quick-view pane.
    public function card(User $user)
    {
        $user->load('manager:id,name', 'group:id,name');

        return response()->json(['person' => $this->person($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function person(User $user): array
    {
        return $this->summary($user) + [
            'email' => $user->email,
            'bio' => $user->bio,
            'location' => $user->location,
            'start_date' => $user->start_date?->format('Y-m-d'),
            'group' => $user->group?->name,
            'manager' => $user->manager ? ['id' => $user->manager->id, 'name' => $user->manager->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'title' => $user->title,
            'department' => $user->department,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_path ? route('users.avatar', $user) : null,
        ];
    }
}
