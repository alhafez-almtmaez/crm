<?php

namespace App\Services\Admin\AbsenceRules;

use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\StudentPointTransaction;
use App\Services\Admin\WhatsAppMessagingService;
use Illuminate\Support\Facades\DB;

class RuleExecutionSupport
{
    public function __construct(private readonly WhatsAppMessagingService $messagingService) {}

    public function sendMessage(RuleExecutionContext $context): MessageDispatchResult
    {
        $content = trim((string) $context->messageContent);
        $group = $context->shouldSendToGroup() ? $context->groupSerialized : null;

        if ($content === '') {
            return MessageDispatchResult::notSent($group);
        }

        if ($this->shouldCreateLocalPreview()) {
            return MessageDispatchResult::localPreview($context->recipientPhones, $group);
        }

        if ($context->recipientPhones === [] && ($group === null || trim($group) === '')) {
            return MessageDispatchResult::notSent($group);
        }

        $this->messagingService->sendMediaCaption($context->recipientPhones, $content, $group);

        return MessageDispatchResult::sent($group);
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

        return DB::transaction(function () use ($context, $points): int {
            $existing = StudentPointTransaction::query()
                ->where('evaluation_student_id', $context->evaluationStudent->id)
                ->where('type', StudentPointTransaction::TYPE_ATTENDANCE_RULE_DEDUCTION)
                ->first();

            if ($existing instanceof StudentPointTransaction) {
                return abs(min(0, (int) $existing->points));
            }

            /** @var Student $student */
            $student = Student::query()
                ->whereKey($context->student->id)
                ->lockForUpdate()
                ->firstOrFail();
            $balanceBefore = (int) $student->points_balance;
            $balanceAfter = $balanceBefore - $points;

            $student->update([
                'points_balance' => $balanceAfter,
                'deducted_points_count' => (int) $student->deducted_points_count + $points,
            ]);

            StudentPointTransaction::query()->create([
                'student_id' => $student->id,
                'homework_id' => null,
                'homework_student_point_id' => null,
                'evaluation_id' => $context->evaluation->id,
                'evaluation_student_id' => $context->evaluationStudent->id,
                'absence_rule_id' => $context->rule->id,
                'plan_point_id' => null,
                'type' => StudentPointTransaction::TYPE_ATTENDANCE_RULE_DEDUCTION,
                'points' => -$points,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'created_by' => $context->executedBy,
            ]);

            $context->student->setRawAttributes($student->getAttributes(), true);

            return $points;
        });
    }

    public function shouldCreateLocalPreview(): bool
    {
        return app()->environment('local');
    }
}
