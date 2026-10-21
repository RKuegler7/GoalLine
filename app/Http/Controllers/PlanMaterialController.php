<?php

namespace App\Http\Controllers;

use App\Jobs\ReadPlanMaterial;
use App\Models\Goal;
use App\Models\PlanMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanMaterialController extends Controller
{
    /**
     * Upload a plan, course, or program for Claude to read.
     */
    public function store(Request $request, Goal $goal): RedirectResponse
    {
        if ($goal->materials()->count() >= PlanMaterial::MAX_PER_GOAL) {
            throw ValidationException::withMessages([
                'material' => 'You can attach up to '.PlanMaterial::MAX_PER_GOAL.' files to a goal. Remove one to add another.',
            ]);
        }

        $validated = $request->validate(PlanMaterial::rules());
        $material = PlanMaterial::storeUpload($goal, $validated['material'], $validated['material_note'] ?? null);

        ReadPlanMaterial::dispatch($material);

        return redirect()->to(route('goals.show', $goal).'#materials')
            ->with('status', 'Got it. GoalLine is reading your file now.');
    }

    /**
     * Remove an uploaded material and its file.
     */
    public function destroy(PlanMaterial $material): RedirectResponse
    {
        Gate::authorize('update', $material->goal);

        $material->delete();

        return redirect()->to(route('goals.show', $material->goal).'#materials')
            ->with('status', 'File removed. Re-plan your roadmap if it was built around it.');
    }
}
