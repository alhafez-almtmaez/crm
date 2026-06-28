<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function studentDailyLimitPayload(array $overrides = []): array
{
    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->create();

    return [
        'first_name' => 'Ahmad',
        'second_name' => 'Ali',
        'middle_name' => 'Saleh',
        'last_name' => 'Hassan',
        'id_number' => null,
        'parent_phone_number' => '962790000001',
        'phone_number' => null,
        'email' => null,
        'date_of_birth' => null,
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'max_daily_weight' => 2,
        'admin_id' => null,
        'is_active' => Student::STATUS_ACTIVE,
        ...$overrides,
    ];
}

function studentDailyLimitUserWithCreatePermission(): User
{
    Permission::findOrCreate('students.create', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('students.create');

    return $user;
}

test('student daily limit must be an integer', function () {
    $this->actingAs(studentDailyLimitUserWithCreatePermission(), 'web')
        ->from('/admin/students/create')
        ->post('/admin/students', studentDailyLimitPayload(['max_daily_weight' => 2.5]))
        ->assertRedirect('/admin/students/create')
        ->assertSessionHasErrors('max_daily_weight');
});

test('student daily limit accepts integer values', function () {
    $this->actingAs(studentDailyLimitUserWithCreatePermission(), 'web')
        ->post('/admin/students', studentDailyLimitPayload(['max_daily_weight' => 2]))
        ->assertRedirect('/admin/students')
        ->assertSessionDoesntHaveErrors();

    expect(Student::query()->firstOrFail()->max_daily_weight)->toBe(2);
});
