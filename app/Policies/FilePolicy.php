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
     * The owner has every ability; otherwise an explicit permission must grant
     * the ability to the user (directly or via their group) on the file itself
     * OR on any ancestor folder — folder grants cascade to their contents.
     */
    protected function grants(User $user, File $file, string $ability): bool
    {
        if ($user->id === $file->owner_id) {
            return true;
        }

        return Permission::query()
            ->whereIn('file_id', $this->lineageIds($file))
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

    /**
     * The file's id plus every ancestor folder id (root-ward), so an effective
     * permission can be inherited from any folder above it.
     *
     * @return array<int, int>
     */
    protected function lineageIds(File $file): array
    {
        $ids = [];
        $seen = [];
        $node = $file;

        // Depth cap + visited-set so a corrupt parent cycle can't loop forever;
        // each step fetches only id/parent_id (no full model hydration).
        for ($depth = 0; $node && $depth < 64; $depth++) {
            if (isset($seen[$node->id])) {
                break;
            }
            $seen[$node->id] = true;
            $ids[] = $node->id;

            $node = $node->parent_id
                ? File::withTrashed()->select('id', 'parent_id')->find($node->parent_id)
                : null;
        }

        return $ids;
    }
}
