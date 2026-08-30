<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_content_templates', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 150);
            $table->json('sections');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('certificate_content_template_assignments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('certificate_content_templates')
                ->restrictOnDelete();
            $table->string('scope_type', 16)->index();
            $table->foreignId('center_id')->nullable()->constrained('centers')->cascadeOnDelete();
            $table->string('student_gender', 10)->nullable();
            $table->string('achievement_type', 32)->nullable();
            $table->string('scope_key', 100)->unique();
            $table->timestamps();

            $table->index(['center_id', 'achievement_type'], 'certificate_content_assignment_center_type_index');
            $table->index(['student_gender', 'achievement_type'], 'certificate_content_assignment_gender_type_index');
        });

        $now = now();
        $generalId = DB::table('certificate_content_templates')->insertGetId([
            'key' => 'general',
            'name' => 'القالب العام لجميع المراكز',
            'sections' => $this->json([
                'title' => 'شَهادَةُ تَمَيُّزٍ وَتَقْدِيرٍ',
                'quote_first' => 'لَوْلَا المَشَقَّةُ سَادَ النَّاسُ كُلُّهُمُ',
                'quote_second' => 'الجُودُ يُفْقِرُ وَالإِقْدَامُ قَتَّالُ',
                'intro' => 'تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبِ العِلْمِ المُتَمَيِّزِ:',
                'student_line' => '﴿ {{ student_name }} ﴾',
                'achievement_line' => 'وَذَلِكَ لِإِنْجَازِهِ {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى',
                'closing' => 'نَسْأَلُ اللهَ لَهُ التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَمُنَّ عَلَيْهِ بِإِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ وَالعَمَلِ بِهِ.',
            ]),
            'is_system' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $femaleId = DB::table('certificate_content_templates')->insertGetId([
            'key' => 'female',
            'name' => 'قالب شهادات الإناث',
            'sections' => $this->json([
                'title' => 'شَهادَةُ تَمَيُّزٍ وَتَقْدِيرٍ',
                'quote_first' => 'لَوْلَا المَشَقَّةُ سَادَ النَّاسُ كُلُّهُمُ',
                'quote_second' => 'الجُودُ يُفْقِرُ وَالإِقْدَامُ قَتَّالُ',
                'intro' => 'تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبَةِ العِلْمِ المُتَمَيِّزَةِ:',
                'student_line' => '﴿ {{ student_name }} ﴾',
                'achievement_line' => 'وَذَلِكَ لِإِنْجَازِهَا {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى',
                'closing' => 'نَسْأَلُ اللهَ لَهَا التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَمُنَّ عَلَيْهَا بِإِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ وَالعَمَلِ بِهِ.',
            ]),
            'is_system' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('certificate_content_template_assignments')->insert([
            [
                'template_id' => $generalId,
                'scope_type' => 'global',
                'center_id' => null,
                'student_gender' => null,
                'achievement_type' => null,
                'scope_key' => 'global:*|type:*',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_id' => $femaleId,
                'scope_type' => 'gender',
                'center_id' => null,
                'student_gender' => 'female',
                'achievement_type' => null,
                'scope_key' => 'gender:female|type:*',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_content_template_assignments');
        Schema::dropIfExists('certificate_content_templates');
    }

    /** @param array<string, string> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
