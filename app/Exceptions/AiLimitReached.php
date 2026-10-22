<?php

namespace App\Exceptions;

/**
 * A Claude call was skipped because a spend limit was reached. Not a bug: nothing to report.
 */
class AiLimitReached extends RoadmapPlanningException
{
    /**
     * Spend limits are expected, so they don't go to the error log or alerts.
     */
    public function report(): bool
    {
        return true;
    }
}
