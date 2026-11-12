<?php

namespace App\Services;

use App\Clock;
use App\GoalOutcome;
use App\GoalStatus;
use App\HighlightKind;
use App\Jobs\RecommendNextGoals;
use App\LeagueStatus;
use App\Models\Goal;
use App\Models\GoalResult;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\User;
use App\PostVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Goal Day: the check-in at the end of a goal ("Did you reach your goal?"). Detected lazily when pages load, so no
 * scheduled job is needed. The answer is self-reported and never changes stars, standings or the table.
 */
class GoalDay
{
    /**
     * The "Goal Day is in N days" banner shows in the final two weeks.
     */
    public const BANNER_DAYS = 14;

    public function __construct(public ProofImage $images, public MedalAwarder $medals) {}

    /**
     * Whether the check-in is waiting on an answer: the goal's date has come (or its league's season ended), or the
     * goal was already marked complete.
     */
    public function isDue(Goal $goal): bool
    {
        if ($goal->status === GoalStatus::Retired || $goal->result !== null) {
            return false;
        }

        return $goal->isCompleted()
            || $goal->target_date->lte(Clock::today())
            || ($this->league($goal)?->status() === LeagueStatus::Finished);
    }

    /**
     * "I finished early": an active goal before its date can be checked in as done.
     */
    public function canFinishEarly(Goal $goal): bool
    {
        return $goal->isActive() && $goal->result === null && ! $this->isDue($goal);
    }

    /**
     * The person's goals waiting on a Goal Day answer, earliest first.
     *
     * @return Collection<int, Goal>
     */
    public function pending(User $user): Collection
    {
        return $user->goals()
            ->where('status', '!=', GoalStatus::Retired)
            ->whereDoesntHave('result')
            ->orderBy('target_date')
            ->with(['result', 'leagues'])
            ->get()
            ->filter(fn (Goal $goal) => $this->isDue($goal))
            ->values();
    }

    /**
     * The active goal whose Goal Day is closest, if it's within the final two weeks.
     */
    public function upcoming(User $user): ?Goal
    {
        return $user->goals()->active()
            ->whereDoesntHave('result')
            ->whereDate('target_date', '>', Clock::today()->toDateString())
            ->whereDate('target_date', '<=', Clock::today()->addDays(self::BANNER_DAYS)->toDateString())
            ->orderBy('target_date')
            ->first();
    }

    /**
     * The league the goal was played in: the most recent season it's part of.
     */
    public function league(Goal $goal): ?League
    {
        $leagues = $goal->relationLoaded('leagues') ? $goal->leagues : $goal->leagues()->get();

        return $leagues->sortByDesc(fn (League $league) => $league->starts_on->timestamp)->first();
    }

    /**
     * Save (or change) the check-in. Proof makes it Verified; a shared answer in a league goes to the league's news.
     *
     * @param  array{outcome: string, finished_early?: bool, actual_result?: ?string, hardest_part?: ?string, helped_most?: ?string, shared?: bool}  $answer
     */
    public function record(Goal $goal, array $answer, ?UploadedFile $proof = null): GoalResult
    {
        $league = $this->league($goal);
        $result = $goal->result ?? new GoalResult(['user_id' => $goal->user_id]);
        $outcome = GoalOutcome::from($answer['outcome']);

        $result->goal()->associate($goal);
        $result->fill([
            'league_id' => $league?->id,
            'outcome' => $outcome,
            'finished_early' => (bool) ($answer['finished_early'] ?? $result->finished_early ?? false),
            'actual_result' => $this->clean($answer['actual_result'] ?? null),
            'hardest_part' => $this->clean($answer['hardest_part'] ?? null),
            'helped_most' => $this->clean($answer['helped_most'] ?? null),
            'shared' => (bool) ($answer['shared'] ?? true),
            'answered_at' => $result->answered_at ?? now(),
        ]);

        if ($proof) {
            $clean = $this->images->clean($proof);
            $path = "goal-day/{$goal->id}/".Str::random(16).'.'.$clean['extension'];
            Storage::disk(RoadmapTask::proofDisk())->put($path, $clean['bytes']);

            if ($result->proof_path) {
                Storage::disk(RoadmapTask::proofDisk())->delete($result->proof_path);
            }

            $result->forceFill(['proof_path' => $path, 'proof_mime_type' => StoredPhoto::typeFor($path), 'verified_at' => now(), 'verified_by' => null]);
        }

        // Only an achieved goal can be verified.
        if (! $result->isAchieved()) {
            $result->forceFill(['verified_at' => null, 'verified_by' => null]);
        }

        $outcomeChanged = $result->isDirty('outcome');
        $result->save();

        // New next-goal ideas when the outcome is new or changed (the templates show meanwhile).
        if ($outcomeChanged && ! DemoCoach::applies($goal->user) && filled(config('services.anthropic.key'))) {
            $result->forceFill(['recommendations' => null])->save();
            RecommendNextGoals::dispatch($result);
        }

        if ($result->isAchieved() && ! $goal->isCompleted()) {
            $goal->complete();
        }

        $this->syncLeaguePost($result);
        $this->medals->forGoal($goal->fresh());

        return $result;
    }

    /**
     * A league mate confirms the result with one tap.
     */
    public function confirm(GoalResult $result, User $by): void
    {
        abort_unless($result->canBeConfirmedBy($by), 403);

        $result->forceFill(['verified_at' => now(), 'verified_by' => $by->id])->save();
        $this->syncLeaguePost($result);
        $this->medals->forGoal($result->goal);
    }

    /**
     * Shared results in a league show as a Goal Day post that league mates can cheer; keeping it private removes it.
     */
    public function syncLeaguePost(GoalResult $result): void
    {
        $user = $result->user;
        $existing = $user->highlights()->where('kind', HighlightKind::GoalDay)->where('goal_id', $result->goal_id)->first();

        if (! $result->shared || $result->league_id === null) {
            $existing?->delete();

            return;
        }

        $detail = $result->outcome->label()
            .($result->isVerified() ? ' · Verified' : '')
            .($result->actual_result ? ' · '.$result->actual_result : '');

        $post = $existing ?? $user->highlights()->make(['kind' => HighlightKind::GoalDay]);
        $post->fill([
            'goal_id' => $result->goal_id,
            'league_id' => $result->league_id,
            'visibility' => PostVisibility::League,
            'headline' => $result->goal->title,
            'detail' => $detail,
            'caption' => $result->helped_most ? 'What helped most: '.$result->helped_most : null,
        ])->save();
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
