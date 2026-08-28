<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificates')
            ->where('certificate_number', 'like', 'MDR-%')
            ->update([
                'certificate_number' => DB::raw("REPLACE(certificate_number, 'MDR-', 'HMT-')"),
            ]);
    }

    public function down(): void
    {
        DB::table('certificates')
            ->where('certificate_number', 'like', 'HMT-%')
            ->update([
                'certificate_number' => DB::raw("REPLACE(certificate_number, 'HMT-', 'MDR-')"),
            ]);
    }
};
