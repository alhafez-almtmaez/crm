<?php

use App\Exports\StudentsExport;
use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('student import and export use comma separated group ids', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');

    $center = Center::factory()->create();
    $firstGroup = Group::factory()->create(['center_id' => $center->id, 'name' => 'Group One']);
    $secondGroup = Group::factory()->create(['center_id' => $center->id, 'name' => 'Group Two']);
    $plan = Plan::factory()->create();
    $path = sys_get_temp_dir().'/student-groups-'.Str::uuid().'.xlsx';

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        [
            'first_name',
            'second_name',
            'middle_name',
            'last_name',
            'phone_number',
            'center_id',
            'group_ids',
            'plan_type_id',
            'is_active',
        ],
        [
            'Ahmad',
            'Ali',
            'Saleh',
            'Hassan',
            '962790000001',
            $center->id,
            "{$firstGroup->id},{$secondGroup->id}",
            $plan->id,
            Student::STATUS_ACTIVE,
        ],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    try {
        $result = app(StudentService::class)->importFile(new UploadedFile(
            $path,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        ));
    } finally {
        @unlink($path);
    }

    expect($result['updated'])->toBe(1)
        ->and($result['skipped'])->toBe(0);

    $student = Student::query()->firstOrFail();

    expect($student->groups()->orderBy('groups.id')->pluck('groups.id')->all())
        ->toBe([$firstGroup->id, $secondGroup->id]);

    $export = new StudentsExport(app(StudentService::class)->exportRows((int) $center->id));
    $exportedStudent = array_combine($export->headings(), $export->collection()->first());

    expect($exportedStudent['group_ids'])->toBe("{$firstGroup->id},{$secondGroup->id}")
        ->and($exportedStudent['group_names'])->toBe('Group One,Group Two');
});

test('students can be filtered through any of their groups', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');

    $center = Center::factory()->create();
    $firstGroup = Group::factory()->create(['center_id' => $center->id]);
    $secondGroup = Group::factory()->create(['center_id' => $center->id]);
    $student = Student::factory()->create([
        'center_id' => $center->id,
        'group_id' => $firstGroup->id,
        'admin_id' => $admin->id,
    ]);
    $student->groups()->sync([$firstGroup->id, $secondGroup->id]);

    $students = app(StudentService::class)->list([
        'group_id' => $secondGroup->id,
        'per_page' => 10,
    ]);

    expect($students->total())->toBe(1)
        ->and($students->first()->id)->toBe($student->id)
        ->and($students->first()->group_ids)->toContain($firstGroup->id, $secondGroup->id);
});
