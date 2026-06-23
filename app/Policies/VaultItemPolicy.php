<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultItem;

class VaultItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Read: owner, a member of the item's shared group, or an admin. */
    public function view(User $user, VaultItem $item): bool
    {
        if ($item->owner_id === $user->id || $user->is_admin) {
            return true;
        }

        return $item->group_id !== null && $item->group_id === $user->group_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Write/delete: owner or admin only (group members get read, not write). */
    public function update(User $user, VaultItem $item): bool
    {
        return $item->owner_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, VaultItem $item): bool
    {
        return $this->update($user, $item);
    }
}
