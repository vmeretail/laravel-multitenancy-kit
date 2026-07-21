<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit\Database\Seeders;

use Illuminate\Database\Seeder;

final class LandlordSeeder extends Seeder
{
    public function run(): void
    {
        $model = config('multitenancy-kit.central_user_model');

        $model::query()->firstOrCreate(
            ['email' => 'admin@vme.coop'],
            [
                'name' => 'Admin',
                'password' => 'admin',
                'is_admin' => true,
            ],
        );
    }
}
