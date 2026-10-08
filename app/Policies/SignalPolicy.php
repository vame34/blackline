<?php

namespace App\Policies;

use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Support\Carbon;

class SignalPolicy
{
    public function view(User $user, Signal $signal): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! in_array($signal->status, [SignalStatus::Published, SignalStatus::Closed], true)) {
            return false;
        }

        $now = Carbon::now();
        if ($signal->published_at && $signal->published_at->gt($now)) {
            return false;
        }

        if ($signal->expires_at && $signal->expires_at->lte($now)) {
            return false;
        }

        return true;
    }
}
