<?php

use App\Services\System\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('browser title application name follows the editable system brand name', function () {
    app(SystemSettingsService::class)->update([
        'brandName' => 'مشروع الحافظ المتميز',
    ]);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('app.name', 'مشروع الحافظ المتميز'))
        ->assertSee('مشروع الحافظ المتميز');
});
