<?php

namespace App\Services\Admin\AbsenceRules;

use App\Models\StudentFreeze;
use App\Models\Student;
use App\Services\Admin\WhatsAppMessagingService;

class RuleExecutionSupport
{
    public function __construct(private readonly WhatsAppMessagingService $messagingService)
    {
    }

    public function sendMessage(RuleExecutionContext $context): bool
    {
        $content = trim((string) $context->messageContent);
        $group = $context->shouldSendToGroup() ? $context->groupSerialized : null;

        if ($content === '') {
            return false;
        }

        if ($context->recipientPhones === [] && ($group === null || trim($group) === '')) {
            return false;
        }

        $this->messagingService->sendMediaCaption($context->recipientPhones, $content, $group);

        return true;
    }

    public function freezeStudent(RuleExecutionContext $context): ?StudentFreeze
    {
        if ($context->freezeFrom === null || $context->freezeTo === null) {
            return null;
        }

        StudentFreeze::query()
            ->where('student_id', $context->student->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unfrozen_at' => now(),
            ]);

        $freeze = StudentFreeze::query()->create([
            'student_id' => $context->student->id,
            'from' => $context->freezeFrom->toDateString(),
            'to' => $context->freezeTo->toDateString(),
            'reason' => (string) ($context->freezeReason ?? ''),
            'contact_phone' => (string) ($context->centerContactPhone ?? ''),
            'frozen_by' => $context->executedBy,
            'is_active' => true,
        ]);

        $context->student->update([
            'is_active' => Student::STATUS_FROZEN,
        ]);

        return $freeze;
    }

    public function dismissStudent(RuleExecutionContext $context): bool
    {
        StudentFreeze::query()
            ->where('student_id', $context->student->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unfrozen_at' => now(),
            ]);

        return (bool) $context->student->update([
            'is_active' => Student::STATUS_INACTIVE,
        ]);
    }

    public function deductPoints(RuleExecutionContext $context): int
    {
        $points = max(0, (int) $context->rule->deduction_points_count);
        if ($points === 0) {
            return 0;
        }

        $context->student->increment('deducted_points_count', $points);

        return $points;
    }
}
