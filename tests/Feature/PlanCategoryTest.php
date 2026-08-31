<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function planCategoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));

    return $admin;
}

test('an admin can create plans in the quran and sunnah categories', function () {
    $admin = planCategoryAdmin();

    foreach ([
        'خطة القرآن' => Plan::CATEGORY_QURAN,
        'خطة السنة' => Plan::CATEGORY_SUNNAH,
    ] as $name => $category) {
        $this->actingAs($admin, 'web')
            ->post(route('admin.plans.store'), [
                'name' => $name,
                'category' => $category,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plan_types', [
            'name' => $name,
            'category' => $category,
        ]);
    }
});

test('an admin can update a plan category and the change is logged', function () {
    $admin = planCategoryAdmin();
    $plan = Plan::factory()->quran()->create([
        'name' => 'الخطة الأولى',
    ]);

    $this->actingAs($admin, 'web')
        ->put(route('admin.plans.update', $plan), [
            'name' => 'الخطة الأولى',
            'category' => Plan::CATEGORY_SUNNAH,
        ])
        ->assertRedirect(route('admin.plans.index'));

    expect($plan->refresh()->category)->toBe(Plan::CATEGORY_SUNNAH);

    $activity = Activity::query()
        ->where('log_name', 'plans')
        ->where('subject_type', Plan::class)
        ->where('subject_id', $plan->id)
        ->where('event', 'updated')
        ->where('causer_id', $admin->id)
        ->latest('id')
        ->firstOrFail();

    expect($activity->attribute_changes->get('attributes')['category'] ?? null)->toBe(Plan::CATEGORY_SUNNAH)
        ->and($activity->attribute_changes->get('old')['category'] ?? null)->toBe(Plan::CATEGORY_QURAN);
});

test('the plan edit page and records expose the category', function () {
    $admin = planCategoryAdmin();
    $plan = Plan::factory()->sunnah()->create();

    $this->actingAs($admin, 'web')
        ->get(route('admin.plans.edit', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Plans/Edit', false)
            ->where('plan.id', $plan->id)
            ->where('plan.name', $plan->name)
            ->where('plan.category', Plan::CATEGORY_SUNNAH));

    $this->actingAs($admin, 'web')
        ->getJson(route('admin.plans.records'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $plan->id)
        ->assertJsonPath('data.0.category', Plan::CATEGORY_SUNNAH);
});

test('plan category is required and limited to quran or sunnah', function () {
    $admin = planCategoryAdmin();

    $this->actingAs($admin, 'web')
        ->post(route('admin.plans.store'), [
            'name' => 'خطة بلا صنف',
        ])
        ->assertSessionHasErrors('category');

    $this->actingAs($admin, 'web')
        ->post(route('admin.plans.store'), [
            'name' => 'خطة بصنف غير صالح',
            'category' => 'other',
        ])
        ->assertSessionHasErrors('category');

    $plan = Plan::factory()->quran()->create();

    $this->actingAs($admin, 'web')
        ->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'category' => 'other',
        ])
        ->assertSessionHasErrors('category');

    expect(Plan::query()->count())->toBe(1)
        ->and($plan->refresh()->category)->toBe(Plan::CATEGORY_QURAN);
});

test('existing plan writes keep the quran database default and factory states are supported', function () {
    $defaultPlan = Plan::query()->create([
        'name' => 'خطة بالقيمة الافتراضية',
    ])->refresh();
    $quranPlan = Plan::factory()->quran()->create();
    $sunnahPlan = Plan::factory()->sunnah()->create();

    expect($defaultPlan->category)->toBe(Plan::CATEGORY_QURAN)
        ->and($quranPlan->category)->toBe(Plan::CATEGORY_QURAN)
        ->and($sunnahPlan->category)->toBe(Plan::CATEGORY_SUNNAH);
});

test('plan records can be sorted by category', function () {
    $admin = planCategoryAdmin();
    $sunnahPlan = Plan::factory()->sunnah()->create();
    $firstQuranPlan = Plan::factory()->quran()->create();
    $secondQuranPlan = Plan::factory()->quran()->create();

    $response = $this->actingAs($admin, 'web')
        ->getJson(route('admin.plans.records', [
            'sort_by' => 'category',
            'sort_dir' => 'asc',
        ]))
        ->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([
        $firstQuranPlan->id,
        $secondQuranPlan->id,
        $sunnahPlan->id,
    ]);
});
