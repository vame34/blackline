<?php

namespace App\Http\Controllers\Auth;

use App\Livewire\Actions\Logout;

final class LogoutController
{
    public function __invoke(Logout $logout)
    {
        $logout();

        return redirect()->route('home');
    }
}
