<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'student_monthly_plans';

    private const LEGACY_UNIQUE = 'student_monthly_plans_student_month_unique';

    private const GROUP_UNIQUE = 'student_monthly_plans_student_group_month_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->assertIndexIsExpectedOrMissing(
            self::LEGACY_UNIQUE,
            ['student_id', 'year', 'month'],
        );
        $this->assertIndexIsExpectedOrMissing(
            self::GROUP_UNIQUE,
            ['student_id', 'group_id', 'year', 'month'],
        );
        $this->assertNoDuplicateGroupPlans();

        // Add the replacement first so a failed migration never leaves this table
        // without a uniqueness guard. Schema's grammar handles both SQLite indexes
        // and PostgreSQL unique constraints correctly.
        if (! Schema::hasIndex(self::TABLE, self::GROUP_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->unique(
                    ['student_id', 'group_id', 'year', 'month'],
                    self::GROUP_UNIQUE,
                );
            });
        }

        if (Schema::hasIndex(self::TABLE, self::LEGACY_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->assertIndexIsExpectedOrMissing(
            self::LEGACY_UNIQUE,
            ['student_id', 'year', 'month'],
        );
        $this->assertIndexIsExpectedOrMissing(
            self::GROUP_UNIQUE,
            ['student_id', 'group_id', 'year', 'month'],
        );
        $this->assertNoDuplicateStudentMonths();

        if (! Schema::hasIndex(self::TABLE, self::LEGACY_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->unique(
                    ['student_id', 'year', 'month'],
                    self::LEGACY_UNIQUE,
                );
            });
        }

        if (Schema::hasIndex(self::TABLE, self::GROUP_UNIQUE, 'unique')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropUnique(self::GROUP_UNIQUE);
            });
        }
    }

    /**
     * Refuse to replace a same-named but differently defined production index.
     * This also makes an interrupted migration safe to retry.
     *
     * @param  array<int, string>  $expectedColumns
     */
    private function assertIndexIsExpectedOrMissing(string $indexName, array $expectedColumns): void
    {
        $index = collect(Schema::getIndexes(self::TABLE))
            ->first(static fn (array $index): bool => $index['name'] === $indexName);

        if ($index === null) {
            return;
        }

        if (! $index['unique'] || $index['columns'] !== $expectedColumns) {
            throw new RuntimeException(sprintf(
                'Cannot migrate %s: index %s has an unexpected definition.',
                self::TABLE,
                $indexName,
            ));
        }
    }

    private function assertNoDuplicateGroupPlans(): void
    {
        $duplicate = DB::table(self::TABLE)
            ->select(['student_id', 'group_id', 'year', 'month'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('student_id', 'group_id', 'year', 'month')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('student_id')
            ->orderBy('group_id')
            ->orderBy('year')
            ->orderBy('month')
            ->first();

        if ($duplicate === null) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot scope student monthly plans to groups. Duplicate found for student %s, group %s, %04d-%02d.',
            (string) $duplicate->student_id,
            $duplicate->group_id === null ? 'NULL' : (string) $duplicate->group_id,
            (int) $duplicate->year,
            (int) $duplicate->month,
        ));
    }

    private function assertNoDuplicateStudentMonths(): void
    {
        $duplicate = DB::table(self::TABLE)
            ->select(['student_id', 'year', 'month'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('student_id', 'year', 'month')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('student_id')
            ->orderBy('year')
            ->orderBy('month')
            ->first();

        if ($duplicate === null) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Cannot restore one monthly plan per student. Student %s has multiple plans for %04d-%02d.',
            (string) $duplicate->student_id,
            (int) $duplicate->year,
            (int) $duplicate->month,
        ));
    }
};
