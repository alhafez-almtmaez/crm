<?php

namespace App\Services\Admin\AbsenceRules;

use App\Models\Student;
use App\Models\StudentFreeze;
use App\Services\Admin\WhatsAppMessagingService;

class RuleExecutionSupport
{
    public function __construct(private readonly WhatsAppMessagingService $messagingService) {}

    public function sendMessage(RuleExecutionContext $context): MessageDispatchResult
    {
        $content = trim((string) $context->messageContent);
        $group = $context->shouldSendToGroup() ? $context->groupSerialized : null;

        if ($content === '') {
            return MessageDispatchResult::notSent();
        }

        if ($this->shouldCreateLocalPreview()) {
            return MessageDispatchResult::localPreview($context->recipientPhones, $group);
        }

        if ($context->recipientPhones === [] && ($group === null || trim($group) === '')) {
            return MessageDispatchResult::notSent();
        }

        $this->messagingService->sendMediaCaption($context->recipientPhones, $content, $group);

        return MessageDispatchResult::sent();
    }

    public function freezeStudent(RuleExecutionContext $context): ?StudentFreeze
    {
        if ($this->shouldCreateLocalPreview()) {
            return null;
        }

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
        if ($this->shouldCreateLocalPreview()) {
            return false;
        }

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
        if ($this->shouldCreateLocalPreview()) {
            return 0;
        }

        $points = max(0, (int) $context->rule->deduction_points_count);
        if ($points === 0) {
            return 0;
        }

        $context->student->increment('deducted_points_count', $points);

        return $points;
    }

    public function shouldCreateLocalPreview(): bool
    {
        return app()->environment('local');
    }
}
