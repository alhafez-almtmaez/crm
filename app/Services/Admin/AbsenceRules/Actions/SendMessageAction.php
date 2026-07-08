<?php

namespace App\Services\Admin\AbsenceRules\Actions;

use App\Models\AbsenceRule;
use App\Services\Admin\AbsenceRules\Contracts\RuleActionHandler;
use App\Services\Admin\AbsenceRules\RuleExecutionContext;
use App\Services\Admin\AbsenceRules\RuleExecutionResult;
use App\Services\Admin\AbsenceRules\RuleExecutionSupport;

class SendMessageAction implements RuleActionHandler
{
    public function __construct(private readonly RuleExecutionSupport $support) {}

    public function key(): string
    {
        return AbsenceRule::ACTION_SEND_MESSAGE;
    }

    public function execute(RuleExecutionContext $context): RuleExecutionResult
    {
        $dispatch = $this->support->sendMessage($context);
        $deductedPoints = $this->support->deductPoints($context);

        return new RuleExecutionResult(
            wasMessageSent: $dispatch->sent,
            studentWasFrozen: false,
            deductedPointsCount: $deductedPoints,
            meta: $dispatch->meta,
        );
    }
}
