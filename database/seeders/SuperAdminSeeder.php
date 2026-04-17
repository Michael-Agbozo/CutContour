<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPERADMIN_PASSWORD');

        if (! $password) {
            $this->command->warn('SUPERADMIN_PASSWORD is not set — skipping superadmin seed.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => env('SUPERADMIN_EMAIL', 'agbozomykell8@gmail.com')],
            [
                'name' => env('SUPERADMIN_NAME', 'Michael Agbozo'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        // is_admin is intentionally excluded from $fillable — set explicitly
        $user->forceFill(['is_admin' => true])->save();
    }
}
