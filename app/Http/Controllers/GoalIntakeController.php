<?php

namespace App\Http\Controllers;

use App\AiFallback;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\RoadmapStatus;
use App\Services\IntakeQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalIntakeController extends Controller
{
    /**
     * Where the person goes after the questions: back into the walkthrough, back to the league they're joining,
     * on to "Play it with friends?" for a new goal, or back to the goal when editing answers.
     */
    private const FLOWS = ['welcome', 'league', 'new', 'league_home', 'joined'];

    /**
     * The goal-specific questions, one at a time, or a short wait while the coach writes them.
     */
    public function show(Request $request, Goal $goal): View
    {
        $intake = $goal->intake ?? GoalIntake::begin($goal);

        if ($intake->status === GoalIntake::GENERATING && ! $intake->isGenerating()) {
            AiFallback::note('questions', null, ['goal_id' => $goal->id, 'job' => 'still not done after the wait']);
            $intake->useFallback();
        }

        return view('goals.intake', [
            'goal' => $goal,
            'intake' => $intake,
            'from' => in_array($request->query('from'), self::FLOWS, true) ? $request->query('from') : null,
            'answers' => collect($intake->answers ?? [])->keyBy('id'),
        ]);
    }

    /**
     * Save the answers (or the skip) and build the plan from them. Changing answers later re-plans the GoalWeeks
     * that haven't started, so this week and everything checked off stay as they are.
     */
    public function store(Request $request, Goal $goal): RedirectResponse
    {
        $intake = $goal->intake ?? GoalIntake::begin($goal);
        $from = in_array($request->input('from'), self::FLOWS, true) ? $request->input('from') : null;
        $wasAnswered = ! $intake->isOpen();

        if ($request->boolean('skip') || ! $intake->questions) {
            $intake->skip();
        } else {
            $validated = $request->validate(IntakeQuestions::rules($intake->questions), [
                'answers.*.value.max' => 'Keep answers to a number up to :max, or a few words.',
                'answers.*.value.numeric' => 'Enter a number, or tap "I\'m not sure".',
                'answers.*.value.in' => 'Pick one of the choices.',
            ]);
            $intake->answer(IntakeQuestions::answers($intake->questions, $validated['answers'] ?? []));
        }

        $status = $this->buildPlan($goal->load('weeks'), $wasAnswered);

        return match ($from) {
            'welcome' => redirect()->route('welcome.crew')->with('status', 'Goal set. Your roadmap is being built while you finish setting up.'),
            'league' => ($return = $request->session()->get('league_join_return'))
                ? redirect()->to($return.'?goal='.$goal->id)->with('status', 'Goal set. Now join the league with it.')
                : redirect()->route('goals.show', $goal)->with('status', $status),
            'new' => redirect()->route('goals.next', $goal)->with('status', "Goal set. You're in line. Your plan is being built from your answers."),
            // Just joined a GoalLeague with this goal: to the goal, where the plan arrives to look over and accept.
            'joined' => redirect()->route('goals.show', $goal)->with('status', 'Your plan is being built from your answers. Look it over, then tap Accept.'),
            // Back to the GoalLeague this goal was just set for.
            'league_home' => ($league = $goal->leagues()->latest('league_members.id')->first())
                ? redirect()->to(route('leagues.show', $league).'#invite')->with('status', 'Your goal is set for '.$league->name.'. Its plan is being built from your answers.')
                : redirect()->route('goals.show', $goal)->with('status', $status),
            default => redirect()->route('goals.show', $goal)->with('status', $status),
        };
    }

    /**
     * Start the first roadmap, or refresh the upcoming GoalWeeks when answers change.
     */
    private function buildPlan(Goal $goal, bool $wasAnswered): string
    {
        if (in_array($goal->roadmap_status, [RoadmapStatus::None, RoadmapStatus::Failed], true)) {
            $goal->startRoadmap();

            return 'Thanks. Your plan is being built from your answers.';
        }

        if (! $wasAnswered && $goal->roadmap_status === RoadmapStatus::Pending) {
            return 'Thanks. Your plan is being built from your answers.';
        }

        $from = $goal->roadmap_status === RoadmapStatus::Ready && $goal->isActive() ? $goal->upcomingPosition() : null;

        if ($from === null) {
            return 'Answers saved. Your plan has no upcoming GoalWeeks to change.';
        }

        $goal->requestRoadmap($from > 1 ? $from : null);

        return 'Answers saved. Your upcoming GoalWeeks are being re-planned around them. This week stays as it is.';
    }
}
