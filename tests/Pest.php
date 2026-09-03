<?php

use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function recordStudentPlanCompletion(
    Student $student,
    PlanPoint $point,
    string $completedAt = '2026-08-20 09:00:00',
): StudentPointTransaction {
    $existing = StudentPointTransaction::query()
        ->where('student_id', $student->id)
        ->where('plan_point_id', $point->id)
        ->where('type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
        ->first();
    if ($existing !== null) {
        return $existing;
    }

    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => 0,
        'balance_before' => 0,
        'balance_after' => 0,
        'created_by' => $student->admin_id,
    ]);
    $transaction->created_at = $completedAt;
    $transaction->updated_at = $completedAt;
    $transaction->save();

    return $transaction;
}
