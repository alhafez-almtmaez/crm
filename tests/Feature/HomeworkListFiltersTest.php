<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Homework;
use App\Models\Student;
use App\Models\User;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionSyncService::class)->sync();
    $this->withoutVite();
});

test('admin can combine inclusive homework dates with location, supervisor and search', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $supervisor = User::factory()->create(['name' => 'First Supervisor']);
    $other = User::factory()->create(['name' => 'Second Supervisor']);
    $center = Center::factory()->create(['name' => 'Main Center']);
    $otherCenter = Center::factory()->create(['name' => 'Other Center']);
    $group = Group::factory()->create(['center_id' => $center->id, 'name' => 'Alpha Group']);
    $otherGroup = Group::factory()->create(['center_id' => $otherCenter->id]);

    $first = Homework::factory()->for($group)->create(['date' => '2026-11-01', 'admin_id' => $supervisor->id]);
    $last = Homework::factory()->for($group)->create(['date' => '2026-11-05', 'admin_id' => $supervisor->id]);
    Homework::factory()->for($group)->create(['date' => '2026-11-03', 'admin_id' => $other->id]);
    Homework::factory()->for($group)->create(['date' => '2026-10-31', 'admin_id' => $supervisor->id]);
    $otherHomework = Homework::factory()->for($otherGroup, 'group')->create(['date' => '2026-11-04', 'admin_id' => $other->id]);

    $this->actingAs($admin)->get(route('admin.homeworks.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Homeworks')
        ->where('filterOptions.can_filter_by_admin', true)
        ->has('filterOptions.centers', 2)
        ->has('filterOptions.groups', 2)
        ->has('filterOptions.admins', 2));

    $this->getJson(route('admin.homeworks.records', [
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-05',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $supervisor->id,
        'search' => 'Alpha',
    ]))->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $last->id)
        ->assertJsonPath('data.1.id', $first->id);

    $this->getJson(route('admin.homeworks.records', ['date_from' => '2026-11-01', 'date_to' => '2026-11-01']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);

    $this->getJson(route('admin.homeworks.records', ['center_id' => $otherCenter->id, 'date_to' => '2026-11-04']))
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $otherHomework->id)
        ->assertJsonPath('data.0.center_name', 'Other Center');

    $this->getJson(route('admin.homeworks.records', ['date_from' => '2026-11-05', 'date_to' => '2026-11-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('date_to');
});

test('limited roles see filter choices and results only for their own homework students', function () {
    $role = Role::create(['name' => 'limited homework viewer', 'guard_name' => 'web']);
    $role->givePermissionTo('homeworks.view');
    $supervisor = User::factory()->create();
    $supervisor->assignRole($role);
    $other = User::factory()->create();
    $visibleGroup = Group::factory()->create();
    $hiddenGroup = Group::factory()->create();
    $visible = Homework::factory()->for($visibleGroup, 'group')->create(['date' => '2026-11-05', 'admin_id' => $other->id]);
    $hidden = Homework::factory()->for($hiddenGroup, 'group')->create(['date' => '2026-11-05', 'admin_id' => $supervisor->id]);
    $ownStudent = Student::factory()->create(['admin_id' => $supervisor->id]);
    $otherStudent = Student::factory()->create(['admin_id' => $other->id]);
    $visible->students()->create(['student_id' => $ownStudent->id]);
    $visible->students()->create(['student_id' => $otherStudent->id]);
    $hidden->students()->create(['student_id' => $otherStudent->id]);

    $this->actingAs($supervisor)->get(route('admin.homeworks.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Homeworks')
        ->where('filterOptions.can_filter_by_admin', false)
        ->has('filterOptions.admins', 0)
        ->has('filterOptions.centers', 1)
        ->has('filterOptions.groups', 1)
        ->where('filterOptions.centers.0.id', $visibleGroup->center_id)
        ->where('filterOptions.groups.0.id', $visibleGroup->id));

    $this->getJson(route('admin.homeworks.records', ['date_from' => '2026-11-05', 'group_id' => $visibleGroup->id]))
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.students_count', 1);

    $this->getJson(route('admin.homeworks.records', ['group_id' => $hiddenGroup->id]))
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('admin.homeworks.records', ['admin_id' => $other->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('admin_id');
});
