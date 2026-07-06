<?php

namespace App\Policies;

use App\Models\RssItem;
use App\Models\User;

class RssItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RssItem $item): bool
    {
        return $item->user_id === $user->id;
    }

    public function update(User $user, RssItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}
