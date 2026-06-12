<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'subject_type' => Permission::SUBJECT_USER,
            'subject_id' => User::factory(),
            'ability' => Permission::ABILITY_READ,
        ];
    }

    /**
     * Grant the permission to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'subject_type' => Permission::SUBJECT_USER,
            'subject_id' => $user->id,
        ]);
    }

    /**
     * Grant the permission to a group.
     */
    public function forGroup(int $groupId): static
    {
        return $this->state(fn () => [
            'subject_type' => Permission::SUBJECT_GROUP,
            'subject_id' => $groupId,
        ]);
    }

    /**
     * Set the granted ability.
     */
    public function ability(string $ability): static
    {
        return $this->state(fn () => ['ability' => $ability]);
    }
}
