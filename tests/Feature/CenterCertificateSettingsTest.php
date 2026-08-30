<?php

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $permissions
 */
function centerCertificateSettingsAdmin(array $permissions): User
{
    $role = Role::findOrCreate('admin', 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function centerCertificateSettingsPayload(array $overrides = []): array
{
    return [
        'name' => 'مركز الإتقان',
        'certificate_name' => 'مركز الإتقان القرآني',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'phone' => '+962790000001',
        'group_serialized' => null,
        'working_days' => ['sunday', 'monday'],
        'show_center_manager_signature' => true,
        ...$overrides,
    ];
}

test('an admin can create a center with certificate settings', function () {
    $admin = centerCertificateSettingsAdmin(['centers.create']);

    $this->actingAs($admin, 'web')
        ->post(route('admin.centers.store'), centerCertificateSettingsPayload([
            'show_center_manager_signature' => 0,
        ]))
        ->assertRedirect(route('admin.centers.index'));

    $center = Center::query()->sole();

    expect($center->certificate_name)->toBe('مركز الإتقان القرآني')
        ->and($center->student_gender)->toBe(Center::STUDENT_GENDER_MALE)
        ->and($center->show_center_manager_signature)->toBeFalse()
        ->and($center->group_serialized)->toBeNull()
        ->and($center->working_days)->toBe([]);

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'certificate_name' => 'مركز الإتقان القرآني',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => 0,
    ]);
});

test('an admin can update center certificate settings', function () {
    $admin = centerCertificateSettingsAdmin(['centers.update']);
    $center = Center::factory()->create([
        'certificate_name' => 'الاسم القديم',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => false,
        'group_serialized' => 'legacy-center-group@g.us',
        'working_days' => ['friday'],
    ]);

    $this->actingAs($admin, 'web')
        ->put(route('admin.centers.update', $center), centerCertificateSettingsPayload([
            'name' => $center->name,
            'certificate_name' => null,
            'student_gender' => Center::STUDENT_GENDER_FEMALE,
            'phone' => $center->phone,
            'show_center_manager_signature' => 1,
        ]))
        ->assertRedirect(route('admin.centers.index'));

    $center->refresh();

    expect($center->certificate_name)->toBeNull()
        ->and($center->student_gender)->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($center->show_center_manager_signature)->toBeTrue()
        ->and($center->group_serialized)->toBe('legacy-center-group@g.us')
        ->and($center->working_days)->toBe(['friday']);
});

test('the center edit page exposes certificate settings', function () {
    $admin = centerCertificateSettingsAdmin(['centers.update']);
    $center = Center::factory()->create([
        'certificate_name' => 'الاسم الرسمي على الشهادة',
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'show_center_manager_signature' => false,
    ]);

    $this->actingAs($admin, 'web')
        ->get(route('admin.centers.edit', $center))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Centers/Edit', false)
            ->where('center.id', $center->id)
            ->where('center.certificate_name', 'الاسم الرسمي على الشهادة')
            ->where('center.student_gender', Center::STUDENT_GENDER_FEMALE)
            ->where('center.show_center_manager_signature', false));
});

test('center certificate settings are validated', function () {
    $admin = centerCertificateSettingsAdmin(['centers.create']);

    $this->actingAs($admin, 'web')
        ->post(route('admin.centers.store'), centerCertificateSettingsPayload([
            'certificate_name' => str_repeat('ا', 256),
            'student_gender' => 'mixed',
            'show_center_manager_signature' => 2,
        ]))
        ->assertSessionHasErrors([
            'certificate_name',
            'student_gender',
            'show_center_manager_signature',
        ]);

    expect(Center::query()->count())->toBe(0);
});

test('center student gender has a backward compatible default and factory values are supported', function () {
    $centerWithDatabaseDefault = Center::query()->create([
        'name' => 'مركز القيمة الافتراضية',
        'phone' => '+962790000002',
        'group_serialized' => null,
        'working_days' => ['sunday'],
    ])->refresh();
    $factoryCenter = Center::factory()->create();

    expect($centerWithDatabaseDefault->student_gender)->toBe(Center::STUDENT_GENDER_MALE)
        ->and($factoryCenter->student_gender)->toBeIn([
            Center::STUDENT_GENDER_MALE,
            Center::STUDENT_GENDER_FEMALE,
        ]);
});

test('center records expose the student gender', function () {
    $admin = centerCertificateSettingsAdmin(['centers.view']);
    $center = Center::factory()->create([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $this->actingAs($admin, 'web')
        ->getJson(route('admin.centers.records'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $center->id)
        ->assertJsonPath('data.0.student_gender', Center::STUDENT_GENDER_FEMALE);
});
