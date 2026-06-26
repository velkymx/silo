<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;

class AlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Album $album): bool
    {
        return $album->owner_id === $user->id || $user->is_admin;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Album $album): bool
    {
        return $album->owner_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, Album $album): bool
    {
        return $this->update($user, $album);
    }
}
