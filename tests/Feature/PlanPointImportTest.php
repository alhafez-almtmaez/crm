<?php

use App\Imports\PlanPointsImport;
use App\Models\Plan;
use App\Models\PlanPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('plan point imports support files containing 6700 points', function () {
    $plan = Plan::factory()->quran()->create();
    $import = new PlanPointsImport($plan);
    $rows = collect([
        collect(['خطة التسميع', 'النقاط']),
    ]);

    foreach (range(1, 6700) as $index) {
        $rows->push(collect(["عنصر {$index}", $index]));
    }

    expect($import->limit())->toBeGreaterThanOrEqual(6701);

    $import->collection($rows);

    expect($import->result())
        ->toMatchArray([
            'imported' => 6700,
            'skipped' => 0,
            'errors' => [],
        ])
        ->and(PlanPoint::query()->where('plan_id', $plan->id)->count())->toBe(6700);
});
