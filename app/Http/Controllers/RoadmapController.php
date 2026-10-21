<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\RoadmapStatus;
use Illuminate\Http\RedirectResponse;

class RoadmapController extends Controller
{
    /**
     * Build, or rebuild, the roadmap for a goal.
     */
    public function store(Goal $goal): RedirectResponse
    {
        if ($goal->roadmap_status !== RoadmapStatus::Pending) {
            $goal->requestRoadmap();
        }

        return redirect()->route('goals.show', $goal);
    }
}
