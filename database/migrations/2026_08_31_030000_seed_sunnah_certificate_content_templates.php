<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $maleTemplateId = $this->upsertTemplate(
            'sunnah-general',
            'القالب العام لشهادات السُّنّة',
            [
                'title' => 'شَهادَةُ تَمَيُّزٍ وَتَقْدِيرٍ',
                'quote_first' => 'لَوْلَا المَشَقَّةُ سَادَ النَّاسُ كُلُّهُمُ',
                'quote_second' => 'الجُودُ يُفْقِرُ وَالإِقْدَامُ قَتَّالُ',
                'intro' => 'تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبِ العِلْمِ المُتَمَيِّزِ:',
                'student_line' => '﴿ {{ student_name }} ﴾',
                'achievement_line' => 'وَذَلِكَ لِإِنْجَازِهِ {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى',
                'closing' => 'نَسْأَلُ اللهَ لَهُ التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَنْفَعَ بِهِ، وَأَنْ يَرْزُقَهُ العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ وَالدَّعْوَةَ إِلَيْهَا.',
            ],
            $now,
        );
        $femaleTemplateId = $this->upsertTemplate(
            'sunnah-female',
            'قالب شهادات السُّنّة للإناث',
            [
                'title' => 'شَهادَةُ تَمَيُّزٍ وَتَقْدِيرٍ',
                'quote_first' => 'لَوْلَا المَشَقَّةُ سَادَ النَّاسُ كُلُّهُمُ',
                'quote_second' => 'الجُودُ يُفْقِرُ وَالإِقْدَامُ قَتَّالُ',
                'intro' => 'تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبَةِ العِلْمِ المُتَمَيِّزَةِ:',
                'student_line' => '﴿ {{ student_name }} ﴾',
                'achievement_line' => 'وَذَلِكَ لِإِنْجَازِهَا {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى',
                'closing' => 'نَسْأَلُ اللهَ لَهَا التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَنْفَعَ بِهَا، وَأَنْ يَرْزُقَهَا العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ وَالدَّعْوَةَ إِلَيْهَا.',
            ],
            $now,
        );

        foreach (['sunnah_book', 'sunnah_part'] as $achievementType) {
            $this->upsertAssignment($maleTemplateId, 'male', $achievementType, $now);
            $this->upsertAssignment($femaleTemplateId, 'female', $achievementType, $now);
        }
    }

    public function down(): void
    {
        $templateIds = DB::table('certificate_content_templates')
            ->whereIn('key', ['sunnah-general', 'sunnah-female'])
            ->pluck('id')
            ->all();

        if ($templateIds === []) {
            return;
        }

        DB::table('certificate_content_template_assignments')
            ->whereIn('template_id', $templateIds)
            ->delete();
        DB::table('certificate_content_templates')
            ->whereIn('id', $templateIds)
            ->delete();
    }

    /** @param array<string, string> $sections */
    private function upsertTemplate(string $key, string $name, array $sections, mixed $now): int
    {
        DB::table('certificate_content_templates')->updateOrInsert(
            ['key' => $key],
            [
                'name' => $name,
                'sections' => json_encode(
                    $sections,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'is_system' => true,
                'is_active' => true,
                'deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return (int) DB::table('certificate_content_templates')->where('key', $key)->value('id');
    }

    private function upsertAssignment(int $templateId, string $gender, string $achievementType, mixed $now): void
    {
        DB::table('certificate_content_template_assignments')->updateOrInsert(
            ['scope_key' => "gender:{$gender}|type:{$achievementType}"],
            [
                'template_id' => $templateId,
                'scope_type' => 'gender',
                'center_id' => null,
                'student_gender' => $gender,
                'achievement_type' => $achievementType,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
};
