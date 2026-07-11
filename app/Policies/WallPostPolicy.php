<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WallPost;

/**
 * Walls are public: any authenticated user can read and post anywhere, so the
 * only gated action is delete. Author retracts, the profile-wall owner curates
 * their own wall ("my wall, my rules"), and admins moderate everything. The
 * dashboard wall has no owner, so there it is author + admin only.
 */
class WallPostPolicy
{
    public function delete(User $user, WallPost $post): bool
    {
        return $user->is_admin
            || $post->author_id === $user->id
            || ($post->wall_user_id !== null && $post->wall_user_id === $user->id);
    }
}
