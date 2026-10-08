<?php

namespace App\Policies;

use App\Models\SignalUpdate;
use App\Models\User;

class SignalUpdatePolicy
{
    public function view(User $user, SignalUpdate $update): bool
    {
        return $user->hasRole('admin') || $update->created_by === $user->id;
    }

    public function update(User $user, SignalUpdate $update): bool
    {
        return $this->view($user, $update);
    }

    public function delete(User $user, SignalUpdate $update): bool
    {
        return $this->view($user, $update);
    }
}
