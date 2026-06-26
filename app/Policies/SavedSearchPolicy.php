<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy
{
    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->owner_id === $user->id || $user->is_admin;
    }
}
