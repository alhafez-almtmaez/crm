<?php

namespace App\Services\Admin;

use App\Models\Student;
use App\Models\StudentCongratulatory;
use App\Models\StudentFreeze;
use App\Support\PhoneNumberHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentCommunicationService
{
    public function __construct(private readonly WhatsAppMessagingService $whatsAppMessagingService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function freeze(Student $student, array $data): void
    {
        $phones = $this->extractPhones($data);

        $from = Carbon::parse((string) $data['from'])->locale('ar')->translatedFormat('l ، j F ، Y');
        $to = Carbon::parse((string) $data['to'])->locale('ar')->translatedFormat('l ، j F ، Y');
        $reason = (string) $data['reason'];
        $contactPhone = (string) $data['phone'];

        $content = "⚠️❄️ *إعلان تجـمـيـد*❄️⚠️ \n\n"
            ."السلام عليكم ورحمة الله وبركاته\n\n"
            ."*يُمنَع* الطالب *{$student->full_name}* من الدوام {$this->attendanceLocationText($student)} وذلك بدءًا من *[{$from}]*، حتى نهاية *[ {$to} ].*\n\n"
            ."*#سبب التجميد:*\n{$reason} ‼️\n"
            ."ـــــــــــــــــــــــــــــــــــــــ\n"
            ."⚠️ *الرجاء من ولي أمر الطالب التواصل معنا عبر هذا الرقم {$contactPhone}*⚠️";

        $this->whatsAppMessagingService->sendMediaCaption(
            $phones,
            $content,
            $student->center?->group_serialized,
        );

        $student->update([
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($data['parent_phone_number'] ?? null),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($data['phone_number'] ?? null),
            'is_active' => 2,
        ]);

        StudentFreeze::query()->create([
            'student_id' => $student->id,
            'from' => $data['from'],
            'to' => $data['to'],
            'reason' => $reason,
            'contact_phone' => $contactPhone,
            'frozen_by' => Auth::id(),
            'is_active' => true,
        ]);
    }

    public function unfreeze(Student $student): void
    {
        if ((int) $student->is_active !== 2) {
            return;
        }

        $phones = $this->extractPhones([
            'parent_phone_number' => $student->parent_phone_number,
            'phone_number' => $student->phone_number,
        ]);
        $date = Carbon::now()->locale('ar')->translatedFormat('l ، j F ، Y');

        $content = "⚠️ 🌬️ *إعلان انتهاء تجميد*🌬️⚠️ \n\n"
            ."السلام عليكم ورحمة الله وبركاته\n\n"
            ."نعلمكم بأنه تم *انتهاء تجميد*🔓 الطالب *\"{$student->full_name}\"،* وعلى هذا فإنه *يسمح* له بالدوام {$this->attendanceLocationText($student)} ، وذلك بدءًا من *[{$date}].* \n\n"
            ."وفقه الله لمرضاته.🤲\n\n#إدارة_مشروع_الحافظ_المتميز";

        $this->whatsAppMessagingService->sendMediaCaption(
            $phones,
            $content,
            $student->center?->group_serialized,
        );

        $student->update(['is_active' => 1]);

        StudentFreeze::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unfrozen_at' => now(),
            ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function congratulatory(Student $student, array $data): void
    {
        $phones = $this->extractPhones($data);

        $now = Carbon::now()->locale('ar');
        $day = $now->translatedFormat('l');
        $date = $now->translatedFormat('j F ، Y');
        $reason = (string) $data['reason'];

        $content = trim(" ✨ *رسالة تهنئة وتبريك* ✨\n\n"
            ."تتقدم إدارة مشروع الحافظ المتميِّز *بالتهنئة والتبريك* للطالب المتميّز:\n\n"
            ."*♕{$student->full_name} ♕*\n\n"
            ."{$reason}\n"
            ."☘️✨\n\n"
            ."*نسأل الله له التوفيق والسداد، وأن يجعله من أهل القرآن.*🤲🪻\n\n"
            ."[{$day}]، الموافق: [{$date}] مـ\n\n"
            ."*- إدارة مشروع الحافظ المتميِّز*✨");

        $this->whatsAppMessagingService->sendMediaCaption(
            $phones,
            $content,
            $student->center?->group_serialized,
        );

        $student->update([
            'parent_phone_number' => PhoneNumberHelper::normalizeForStorage($data['parent_phone_number'] ?? null),
            'phone_number' => PhoneNumberHelper::normalizeForStorage($data['phone_number'] ?? null),
        ]);

        StudentCongratulatory::query()->create([
            'student_id' => $student->id,
            'reason' => $reason,
            'sent_by' => Auth::id(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function extractPhones(array $data): array
    {
        $phones = [];

        foreach (['parent_phone_number', 'phone_number'] as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $phones[] = $trimmed;
        }

        return array_values(array_unique($phones));
    }

    private function attendanceLocationText(Student $student): string
    {
        return match ((int) $student->center_id) {
            1000 => '*على برنامج الزوم*',
            3 => 'في *دار القرآن*',
            default => 'في *المركز*',
        };
    }
}
