<?php

namespace App\Services\Admin\AbsenceRules\Contracts;

use App\Services\Admin\AbsenceRules\RuleExecutionContext;
use App\Services\Admin\AbsenceRules\RuleExecutionResult;

interface RuleActionHandler
{
    public function key(): string;

    public function execute(RuleExecutionContext $context): RuleExecutionResult;
}
