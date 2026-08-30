<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_NAME = 'دار القرآن مسجد "الصالحين"';

    private const TARGET_NAME = 'دار قرآن مسجد "الصالحين"';

    public function up(): void
    {
        $sourceId = DB::table('centers')->where('name', self::OLD_NAME)->value('id');
        if ($sourceId === null) {
            return;
        }

        $conflictId = DB::table('centers')->where('name', self::TARGET_NAME)->value('id');
        if ($conflictId !== null && (int) $conflictId !== (int) $sourceId) {
            throw new RuntimeException('Cannot normalize the Saleheen center name because the target name already exists.');
        }

        DB::table('centers')->where('id', $sourceId)->update(['name' => self::TARGET_NAME]);
    }

    public function down(): void
    {
        throw new LogicException(
            'The canonical Saleheen center name is intentionally irreversible. Restore the pre-migration backup instead.'
        );
    }
};
