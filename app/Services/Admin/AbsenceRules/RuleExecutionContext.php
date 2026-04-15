<?php

namespace App\Services\Admin\AbsenceRules;

use App\Models\AbsenceRule;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use Carbon\CarbonImmutable;

class RuleExecutionContext
{
    /**
     * @param array<int, string> $recipientPhones
     */
    public function __construct(
        public readonly AbsenceRule $rule,
        public readonly Evaluation $evaluation,
        public readonly EvaluationStudent $evaluationStudent,
        public readonly Student $student,
        public readonly string $attendanceType,
        public readonly int $attendanceValue,
        public readonly int $occurrenceNumber,
        public readonly ?string $messageTemplateSnapshot,
        public readonly ?string $messageContent,
        public readonly array $recipientPhones,
        public readonly ?string $groupSerialized,
        public readonly ?CarbonImmutable $freezeFrom,
        public readonly ?CarbonImmutable $freezeTo,
        public readonly ?string $freezeReason,
        public readonly ?string $centerContactPhone,
        public readonly ?int $executedBy,
    )
    {
    }

    public function shouldSendToGroup(): bool
    {
        return (bool) $this->rule->send_to_center_group;
    }
}
