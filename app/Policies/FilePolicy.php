<?php

namespace App\Policies;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;

class FilePolicy
{
    /**
     * Grant all abilities to admins.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, File $file): bool
    {
        return $this->grants($user, $file, Permission::ABILITY_READ);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, File $file): bool
    {
        return $this->grants($user, $file, Permission::ABILITY_WRITE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, File $file): bool
    {
        return $this->grants($user, $file, Permission::ABILITY_DELETE);
    }

    /**
     * Determine whether the user can share the model.
     */
    public function share(User $user, File $file): bool
    {
        return $this->grants($user, $file, Permission::ABILITY_SHARE);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, File $file): bool
    {
        return $user->id === $file->owner_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, File $file): bool
    {
        return $user->id === $file->owner_id;
    }

    /**
     * The owner has every ability; otherwise an explicit permission row must
     * grant the ability to the user directly or via their group.
     */
    protected function grants(User $user, File $file, string $ability): bool
    {
        if ($user->id === $file->owner_id) {
            return true;
        }

        return $file->permissions()
            ->where('ability', $ability)
            ->where(function ($query) use ($user) {
                $query
                    ->where(fn ($q) => $q
                        ->where('subject_type', Permission::SUBJECT_USER)
                        ->where('subject_id', $user->id))
                    ->when($user->group_id, fn ($q) => $q
                        ->orWhere(fn ($q) => $q
                            ->where('subject_type', Permission::SUBJECT_GROUP)
                            ->where('subject_id', $user->group_id)));
            })
            ->exists();
    }
}
