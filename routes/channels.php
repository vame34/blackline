<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('signals.subscribers', fn ($user) => $user && ! $user->isBanned() && $user->hasVerifiedEmail());
