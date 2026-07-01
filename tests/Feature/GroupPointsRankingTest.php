<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('public group points ranking orders active students by point balance', function () {
    $center = Center::factory()->create(['name' => 'Main Center']);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'Group A',
        'ulid' => (string) Str::ulid(),
    ]);
    $plan = Plan::factory()->create(['name' => 'Plan A']);

    Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Second Student',
            'points_balance' => 16,
        ]);

    Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Top Student',
            'points_balance' => 25,
        ]);

    Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Tied Student',
            'points_balance' => 16,
        ]);

    Student::factory()
        ->inactive()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Inactive Student',
            'points_balance' => 90,
        ]);

    $this
        ->get(route('groups.points-ranking', ['publicId' => $group->ulid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/PointsRanking')
            ->where('ranking.group_name', 'Group A')
            ->where('ranking.summary.students_count', 3)
            ->where('ranking.summary.leader_points', 25)
            ->where('ranking.summary.top_student', 'Top Student')
            ->where('ranking.rows.0.full_name', 'Top Student')
            ->where('ranking.rows.0.rank', 1)
            ->where('ranking.rows.1.full_name', 'Second Student')
            ->where('ranking.rows.1.rank', 2)
            ->where('ranking.rows.2.full_name', 'Tied Student')
            ->where('ranking.rows.2.rank', 2)
            ->where('ranking.rows.2.points_gap_from_leader', 9)
            ->where('ranking.homework_report_url', route('groups.homeworks.report', ['publicId' => $group->ulid], false))
        );
});
