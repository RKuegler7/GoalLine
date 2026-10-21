<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\RoadmapStatus;
use Illuminate\Http\RedirectResponse;

class RoadmapController extends Controller
{
    /**
     * Build the roadmap, or re-plan it from the current stretch onward.
     */
    public function store(Goal $goal): RedirectResponse
    {
        if ($goal->roadmap_status !== RoadmapStatus::Pending) {
            $from = $goal->roadmap_status === RoadmapStatus::Ready ? $goal->replanFromPosition() : null;

            $goal->requestRoadmap($from > 1 ? $from : null);
        }

        return redirect()->route('goals.show', $goal);
    }
}
