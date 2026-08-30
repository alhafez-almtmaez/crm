<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('plans centers and groups default to fifty records ordered by ascending id', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $plans = Plan::factory()->count(55)->create();
    $centers = Center::factory()->count(55)->create();
    $groups = Group::factory()->count(55)->create([
        'center_id' => $centers->first()->id,
    ]);

    $this->actingAs($admin, 'web');

    foreach ([
        '/admin/plans/records' => $plans,
        '/admin/centers/records' => $centers,
        '/admin/groups/records' => $groups,
    ] as $endpoint => $records) {
        $response = $this->getJson($endpoint)->assertOk();

        expect($response->json('meta.per_page'))->toBe(50)
            ->and($response->json('meta.total'))->toBe(55)
            ->and(array_column($response->json('data'), 'id'))->toBe(
                $records->pluck('id')->sort()->take(50)->values()->all(),
            );
    }
});
