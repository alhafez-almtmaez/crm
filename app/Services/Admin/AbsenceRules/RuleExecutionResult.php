<?php

namespace App\Services\Admin\AbsenceRules;

class RuleExecutionResult
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly bool $wasMessageSent,
        public readonly bool $studentWasFrozen,
        public readonly bool $studentWasDismissed = false,
        public readonly ?int $studentFreezeId = null,
        public readonly int $deductedPointsCount = 0,
        public readonly array $meta = [],
    )
    {
    }
}
