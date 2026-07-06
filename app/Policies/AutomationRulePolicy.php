<?php

namespace App\Policies;

use App\Models\AutomationRule;
use App\Models\User;

class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $rule->user_id === $user->id || $rule->user_id === null || $user->is_admin;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $rule->user_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
