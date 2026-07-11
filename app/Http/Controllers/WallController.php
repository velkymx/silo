<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WallPost;
use App\Models\WallReaction;
use App\Services\Rss\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Wall: MySpace-2004 public message boards. One shared dashboard wall
 * (wall_user_id null) plus one wall per user profile. Everything is readable
 * and postable by every authenticated user; bodies are sanitized HTML from
 * the WYSIWYG composer; reactions are a curated VibeIcon set with Slack-style
 * toggle semantics.
 */
class WallController extends Controller
{
    /** Curated reaction icons (Bootstrap Icons names). Mirrored in WallReactions.vue. */
    public const REACTION_ICONS = [
        'hand-thumbs-up', 'heart-fill', 'emoji-laughing', 'emoji-surprised',
        'emoji-frown', 'fire', 'rocket-takeoff', 'star-fill',
    ];

    public const PAGE_SIZE = 25;

    /** JSON page for "load more": posts on one wall older than ?before. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wall_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'before' => ['nullable', 'integer'],
        ]);

        $posts = WallPost::query()
            ->forWall($data['wall_user_id'] ?? null)
            ->when($data['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->with(['author:id,name,avatar_path', 'reactions'])
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE)
            ->get();

        return response()->json([
            'posts' => $posts->map(fn (WallPost $p) => self::shape($p, $request->user()))->values(),
            'hasMore' => $posts->count() === self::PAGE_SIZE,
        ]);
    }

    public function store(Request $request, HtmlSanitizer $sanitizer)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'wall_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $body = (string) $sanitizer->clean($data['body']);
        if (trim(strip_tags($body)) === '') {
            throw ValidationException::withMessages(['body' => 'The post is empty.']);
        }

        WallPost::create([
            'wall_user_id' => $data['wall_user_id'] ?? null,
            'author_id' => $request->user()->id,
            'body' => $body,
        ]);

        return back();
    }

    public function destroy(WallPost $post)
    {
        $this->authorize('delete', $post);
        $post->delete();

        return back();
    }

    /** Slack-style toggle: same (post, user, icon) again removes the reaction. */
    public function react(Request $request, WallPost $post)
    {
        $data = $request->validate([
            'icon' => ['required', 'string', Rule::in(self::REACTION_ICONS)],
        ]);

        $existing = WallReaction::query()
            ->where('wall_post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->where('icon', $data['icon'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            WallReaction::create([
                'wall_post_id' => $post->id,
                'user_id' => $request->user()->id,
                'icon' => $data['icon'],
                'created_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Presentation shape shared by the dashboard, profile pages, and /wall.
     *
     * @return array<string, mixed>
     */
    public static function shape(WallPost $post, User $viewer): array
    {
        $reactions = $post->reactions
            ->groupBy('icon')
            ->map(fn ($group, $icon) => [
                'icon' => $icon,
                'count' => $group->count(),
                'mine' => $group->contains('user_id', $viewer->id),
            ])
            ->values()
            ->all();

        return [
            'id' => $post->id,
            'body' => $post->body,
            'created_at' => $post->created_at->toIso8601String(),
            'author' => [
                'id' => $post->author->id,
                'name' => $post->author->name,
                'avatar_url' => $post->author->avatar_path ? route('users.avatar', $post->author) : null,
            ],
            'can_delete' => $viewer->can('delete', $post),
            'reactions' => $reactions,
        ];
    }

    /**
     * Latest page of one wall, shaped for Inertia props.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function latest(?int $wallUserId, User $viewer): array
    {
        return WallPost::query()
            ->forWall($wallUserId)
            ->with(['author:id,name,avatar_path', 'reactions'])
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->map(fn (WallPost $p) => self::shape($p, $viewer))
            ->values()
            ->all();
    }
}
