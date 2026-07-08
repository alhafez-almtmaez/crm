<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('students records can be filtered by center group plan and status', function () {
    Permission::findOrCreate('students.view', 'web');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('students.view');

    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $otherGroup = Group::factory()->create(['center_id' => $otherCenter->id]);
    $plan = Plan::factory()->create();
    $otherPlan = Plan::factory()->create();

    $matchingStudent = Student::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'admin_id' => $viewer->id,
        'is_active' => Student::STATUS_FROZEN,
    ]);

    Student::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'admin_id' => $viewer->id,
        'is_active' => Student::STATUS_ACTIVE,
    ]);

    Student::factory()->create([
        'center_id' => $otherCenter->id,
        'group_id' => $otherGroup->id,
        'plan_type_id' => $otherPlan->id,
        'admin_id' => $viewer->id,
        'is_active' => Student::STATUS_FROZEN,
    ]);

    $this->actingAs($viewer, 'web')
        ->getJson('/admin/students/records?'.http_build_query([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'is_active' => Student::STATUS_FROZEN,
        ]))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $matchingStudent->id);
});
