<?php

namespace Database\Seeders;

use App\Models\SignalCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        SignalCategory::firstOrCreate(['slug' => 'volume-strategy'], ['name' => 'Smart Volume']);
        // These are public development fixtures, never production accounts.
        if (app()->environment(['local', 'testing'])) {
            foreach (['admin', 'user'] as $role) {
                $user = User::firstOrCreate(['email' => $role.'@example.com'], [
                    'name' => ucfirst($role), 'password' => Hash::make('password'), 'email_verified_at' => now(),
                ]);
                $user->assignRole($role);
            }
        }
    }
}
