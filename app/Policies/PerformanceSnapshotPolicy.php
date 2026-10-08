<?php

namespace App\Policies;

use App\Models\PerformanceSnapshot;
use App\Models\User;

class PerformanceSnapshotPolicy
{
    public function view(User $user, PerformanceSnapshot $snapshot): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PerformanceSnapshot $snapshot): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PerformanceSnapshot $snapshot): bool
    {
        return $user->hasRole('admin');
    }
}
