<?php

namespace App\Http\Controllers;

use App\Jobs\RefinePlan;
use App\Models\Goal;
use App\Models\PlanRefinement;
use App\Models\PlanVersion;
use App\Services\AiBudget;
use App\Services\PlanEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanRefinementController extends Controller
{
    /**
     * "Refine your plan": send the person's request to the coach. The proposal comes back for a preview first.
     */
    public function store(Request $request, Goal $goal, PlanEditor $editor, AiBudget $budget): RedirectResponse
    {
        $instruction = trim($request->validateWithBag('refine', [
            'instruction' => ['required', 'string', 'max:'.PlanRefinement::MAX_INSTRUCTION],
        ], [
            'instruction.required' => 'Tell GoalLine what to change, or tap one of the ideas.',
        ])['instruction']);

        $goal->load('weeks.tasks', 'habits.logs');

        if ($editor->editableWeeks($goal)->isEmpty()) {
            throw ValidationException::withMessages(['instruction' => 'There are no upcoming GoalWeeks left to change.'])->errorBag('refine');
        }

        if ($reason = $budget->blockedReason($request->user())) {
            throw ValidationException::withMessages(['instruction' => $reason])->errorBag('refine');
        }

        if ($open = $goal->refinements()->where('status', PlanRefinement::PENDING)->first()) {
            return redirect()->route('goals.refinements.show', [$goal, $open]);
        }

        $refinement = $goal->refinements()->create(['kind' => PlanRefinement::REFINE, 'instruction' => $instruction]);
        RefinePlan::dispatch($refinement);

        return redirect()->route('goals.refinements.show', [$goal, $refinement]);
    }

    /**
     * The preview: what would change, GoalWeek by GoalWeek, with Accept and Discard. While the coach is still
     * working, a short wait that refreshes on its own.
     */
    public function show(Goal $goal, PlanRefinement $refinement, PlanEditor $editor): View|RedirectResponse
    {
        abort_unless($refinement->goal_id === $goal->id, 404);

        if (in_array($refinement->status, [PlanRefinement::ACCEPTED, PlanRefinement::DISCARDED], true)) {
            return redirect()->route('goals.show', $goal);
        }

        $goal->load('weeks.tasks', 'habits.logs');

        return view('goals.refine', [
            'goal' => $goal,
            'refinement' => $refinement,
            'diff' => $refinement->status === PlanRefinement::READY ? $editor->diff($goal, $refinement->proposal) : null,
        ]);
    }

    /**
     * Apply the change to the GoalWeeks that can still change, and save it to plan history.
     */
    public function accept(Goal $goal, PlanRefinement $refinement, PlanEditor $editor): RedirectResponse
    {
        abort_unless($refinement->goal_id === $goal->id && $refinement->status === PlanRefinement::READY, 404);

        $summary = $refinement->kind === PlanRefinement::RESTORE
            ? 'Restored the plan from '.$refinement->version?->created_at->format('M j, g:i a')
            : Str::limit("\u{201C}{$refinement->instruction}\u{201D} ".$refinement->explanation, 480);
        $changed = $editor->apply($goal, $refinement->proposal, $refinement->kind === PlanRefinement::RESTORE ? PlanVersion::RESTORED : PlanVersion::REFINED, $summary);
        $refinement->decide(PlanRefinement::ACCEPTED);

        return redirect()->route('goals.show', $goal)->with('status', $changed > 0
            ? 'Plan updated: '.$changed.' '.Str::plural('GoalWeek', $changed).' changed. Past check-ins stay as they are.'
            : 'Nothing changed: those GoalWeeks have started since the preview.');
    }

    /**
     * Throw the proposal away. The plan stays as it is.
     */
    public function discard(Goal $goal, PlanRefinement $refinement): RedirectResponse
    {
        abort_unless($refinement->goal_id === $goal->id && $refinement->isOpen(), 404);

        $refinement->decide(PlanRefinement::DISCARDED);

        return redirect()->route('goals.show', $goal)->with('status', 'Discarded. Your plan is unchanged.');
    }

    /**
     * Preview bringing back an earlier version for the GoalWeeks that can still change.
     */
    public function restore(Goal $goal, PlanVersion $version, PlanEditor $editor): RedirectResponse
    {
        abort_unless($version->goal_id === $goal->id, 404);
        $goal->load('weeks.tasks', 'habits.logs');
        $proposal = $editor->proposalFromVersion($goal, $version);

        if ($proposal === []) {
            return redirect()->to(route('goals.show', $goal).'#plan-history')->with('status', 'That version has no upcoming GoalWeeks left to bring back.');
        }

        $refinement = $goal->refinements()->create(['kind' => PlanRefinement::RESTORE, 'plan_version_id' => $version->id]);
        $refinement->forceFill([
            'status' => PlanRefinement::READY,
            'explanation' => 'This brings back the upcoming GoalWeeks as they were on '.$version->created_at->format('F j').'.',
            'proposal' => $proposal,
        ])->save();

        return redirect()->route('goals.refinements.show', [$goal, $refinement]);
    }
}
