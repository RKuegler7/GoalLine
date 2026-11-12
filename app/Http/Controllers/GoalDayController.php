<?php

namespace App\Http\Controllers;

use App\GoalOutcome;
use App\Models\Goal;
use App\Models\GoalResult;
use App\Models\RoadmapTask;
use App\Services\GoalDay;
use App\Services\StoredPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoalDayController extends Controller
{
    /**
     * "Did you reach your goal?" On Goal Day, or early ("I finished early") from the goal page.
     */
    public function show(Request $request, Goal $goal, GoalDay $goalDay): View|RedirectResponse
    {
        $goal->load('result');
        $result = $goal->result;
        $early = $goalDay->canFinishEarly($goal);

        if ($result && ! $result->isEditable()) {
            return redirect()->route('finale.show', $goal);
        }

        if (! $result && ! $early && ! $goalDay->isDue($goal)) {
            return redirect()->route('goals.show', $goal);
        }

        return view('goal-day.show', [
            'goal' => $goal,
            'result' => $result,
            'early' => $early,
            'league' => $goalDay->league($goal),
        ]);
    }

    /**
     * Save the answer. It's self-reported and never changes stars or standings.
     */
    public function store(Request $request, Goal $goal, GoalDay $goalDay): RedirectResponse
    {
        $goal->load('result');
        abort_if($goal->result && ! $goal->result->isEditable(), 403, 'Goal Day answers can be changed for '.GoalResult::EDITABLE_DAYS.' days.');
        $early = ! $goal->result && $goalDay->canFinishEarly($goal);
        abort_unless($goal->result || $early || $goalDay->isDue($goal), 403);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(GoalOutcome::class)],
            'actual_result' => ['nullable', 'string', 'max:80'],
            'hardest_part' => ['nullable', 'string', 'max:280'],
            'helped_most' => ['nullable', 'string', 'max:280'],
            'shared' => ['nullable', 'boolean'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ], [
            'outcome.required' => 'Pick one: I did it, Partly, or I came up short.',
            'actual_result.max' => 'Keep the result to 80 characters.',
            'hardest_part.max' => 'Keep it to 280 characters.',
            'helped_most.max' => 'Keep it to 280 characters.',
            'proof.mimes' => 'Use a JPG, PNG, or WebP image.',
            'proof.mimetypes' => 'Use a JPG, PNG, or WebP image.',
            'proof.max' => 'Photos can be up to 5 MB.',
        ]);

        if ($early && $validated['outcome'] !== GoalOutcome::Achieved->value) {
            return back()->withErrors(['outcome' => 'Finishing early means you reached it. Otherwise, keep going: your Goal Day check-in opens on '.$goal->target_date->format('M j').'.'])->withInput();
        }

        try {
            $goalDay->record($goal, [
                ...$validated,
                'finished_early' => $early ?: null,
                'shared' => $request->boolean('shared'),
            ], $request->file('proof'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['proof' => "That file doesn't look like a JPG, PNG, or WebP image."])->withInput();
        }

        return redirect()->route('finale.show', $goal)->with('status', 'Goal Day check-in saved.');
    }

    /**
     * A league mate confirms an achieved result with one tap. You can't confirm your own.
     */
    public function confirm(Request $request, GoalResult $goalResult, GoalDay $goalDay): RedirectResponse
    {
        $goalDay->confirm($goalResult, $request->user());

        return back()->with('status', 'Confirmed. '.$goalResult->user->name.'\'s result is Verified.');
    }

    /**
     * The Goal Day proof photo: for its owner, and for league mates when the result is shared.
     */
    public function proof(Request $request, Goal $goal, StoredPhoto $photos): Response
    {
        $result = $goal->result;
        abort_unless($result?->proof_path, 404);
        abort_unless(Gate::allows('view', $goal) || ($result->shared && $result->league?->memberFor($request->user()) !== null), 403);

        return $photos->serve(RoadmapTask::proofDisk(), $result->proof_path, $result->proof_mime_type, ['Cache-Control' => 'private, max-age=604800'],
            forget: fn () => $result->forceFill(['proof_path' => null, 'proof_mime_type' => null])->save());
    }
}
