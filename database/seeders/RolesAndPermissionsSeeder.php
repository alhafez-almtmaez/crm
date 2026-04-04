<?php

namespace Database\Seeders;

use App\Services\Auth\PermissionSyncService;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionSyncService::class)->sync();
    }
}
