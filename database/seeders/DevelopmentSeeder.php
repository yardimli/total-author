<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Development accounts are only allowed locally.');
        }
        User::firstOrCreate(['email' => 'writer@example.test'], ['name' => 'Test Writer', 'password' => Hash::make('LocalWriter8023!')]);
    }
}
