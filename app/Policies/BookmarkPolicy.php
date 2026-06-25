<?php

namespace App\Policies;

use App\Models\Bookmark;
use App\Models\User;

class BookmarkPolicy
{
    /** Any authenticated user can browse the launchpad. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** View an individual bookmark: owner, admin, or a shared one. */
    public function view(User $user, Bookmark $bookmark): bool
    {
        return $bookmark->owner_id === $user->id || $bookmark->shared || $user->is_admin;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only the owner (or an admin) may edit or delete. */
    public function update(User $user, Bookmark $bookmark): bool
    {
        return $bookmark->owner_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, Bookmark $bookmark): bool
    {
        return $this->update($user, $bookmark);
    }
}
