<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalWeekResult;
use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Services\HighlightFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HighlightController extends Controller
{
    /**
     * How many highlights load at a time.
     */
    private const PAGE_SIZE = 20;

    /**
     * The feed: highlights from people you follow, your GoalLeague mates, and you.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $highlights = (new HighlightFeed($viewer))->latest(self::PAGE_SIZE, $request->integer('before') ?: null);

        return view('highlights.index', [
            'highlights' => $highlights,
            'hasMore' => $highlights->count() === self::PAGE_SIZE,
            'friends' => $viewer->friends(),
        ]);
    }

    /**
     * Preview a highlight before sharing it, with a caption and photo.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $draft = $this->draft($request);

        if ($existing = $this->alreadyShared($request->user(), $draft)) {
            return redirect()->to(route('highlights.index').'#highlight-'.$existing->id)->with('status', 'You already shared this one.');
        }

        return view('highlights.create', ['draft' => $draft]);
    }

    /**
     * Share the highlight with followers and GoalLeague mates.
     */
    public function store(Request $request): RedirectResponse
    {
        $draft = $this->draft($request);

        if ($existing = $this->alreadyShared($request->user(), $draft)) {
            return redirect()->to(route('highlights.index').'#highlight-'.$existing->id);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:'.Highlight::MAX_CAPTION],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'use_goal_photo' => ['nullable', 'boolean'],
        ], [
            'photo.max' => 'Photos can be up to 5 MB.',
        ]);

        $highlight = $request->user()->highlights()->create([
            'kind' => $draft['kind'],
            'goal_id' => $draft['goal']->id,
            'roadmap_week_id' => $draft['week']?->id,
            'headline' => $draft['headline'],
            'detail' => $draft['detail'],
            'caption' => filled($validated['caption'] ?? null) ? trim($validated['caption']) : null,
        ]);

        $photoPath = match (true) {
            $request->hasFile('photo') => $request->file('photo')->store("highlights/{$highlight->id}", Highlight::PHOTO_DISK),
            $request->boolean('use_goal_photo') && $draft['goal']->completion_photo_path => $this->copyGoalPhoto($draft['goal'], $highlight),
            default => null,
        };
        $highlight->forceFill(['photo_path' => $photoPath])->save();

        return redirect()->to(route('highlights.index').'#highlight-'.$highlight->id)->with('status', 'Shared to Highlights.');
    }

    /**
     * Remove one of your highlights.
     */
    public function destroy(Highlight $highlight): RedirectResponse
    {
        $highlight->delete();

        return redirect()->route('highlights.index')->with('status', 'Highlight removed.');
    }

    /**
     * Cheer a highlight, or take the cheer back.
     */
    public function cheer(Request $request, Highlight $highlight): RedirectResponse
    {
        $existing = $highlight->cheers()->where('user_id', $request->user()->id)->first();

        $existing ? $existing->delete() : $highlight->cheers()->create(['user_id' => $request->user()->id]);

        return redirect()->to(url()->previous(route('highlights.index')).'#highlight-'.$highlight->id);
    }

    /**
     * Serve a highlight's photo.
     */
    public function photo(Highlight $highlight): StreamedResponse
    {
        abort_unless($highlight->photo_path, 404);

        return Storage::disk(Highlight::PHOTO_DISK)->response($highlight->photo_path, headers: [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /**
     * A branded share card to download and post anywhere.
     */
    public function card(Highlight $highlight): View
    {
        return view('highlights.card', [
            'highlight' => $highlight,
            'titleLines' => array_slice(explode("\n", wordwrap($highlight->headline, 20, "\n", true)), 0, 3),
            'photo' => $highlight->photo_path
                ? 'data:'.Storage::disk(Highlight::PHOTO_DISK)->mimeType($highlight->photo_path).';base64,'.base64_encode(Storage::disk(Highlight::PHOTO_DISK)->get($highlight->photo_path))
                : null,
        ]);
    }

    /**
     * Work out what's being shared from the request: a completed goal, a great GoalWeek, or a milestone.
     *
     * @return array{kind: HighlightKind, goal: Goal, week: RoadmapWeek|null, headline: string, detail: string, caption: ?string, goalPhoto: ?string}
     */
    private function draft(Request $request): array
    {
        $user = $request->user();

        if ($goalId = $request->integer('goal')) {
            $goal = Goal::findOrFail($goalId);
            abort_unless($goal->user()->is($user) && $goal->isCompleted(), 403, 'Only completed goals can be shared this way.');
            $goal->load('weeks.tasks', 'habits.logs');

            return [
                'kind' => HighlightKind::GoalCompleted,
                'goal' => $goal,
                'week' => null,
                'headline' => $goal->title,
                'detail' => 'Completed '.Clock::local($goal->completed_at)->format('F j, Y').' · '.$goal->points().' '.Str::plural('GoalWeek point', $goal->points()),
                'caption' => $goal->completion_note,
                'goalPhoto' => $goal->completionPhotoUrl(),
            ];
        }

        $week = RoadmapWeek::with('goal.weeks.tasks', 'goal.habits.logs')->findOrFail($request->integer('week') ?: $request->integer('milestone'));
        abort_unless($week->goal->user()->is($user), 403);

        if ($request->filled('milestone')) {
            abort_unless($week->milestone_hit_at !== null, 403, 'Mark the milestone as hit first.');

            return [
                'kind' => HighlightKind::MilestoneHit,
                'goal' => $week->goal,
                'week' => $week,
                'headline' => $week->goal->title,
                'detail' => $week->label().' milestone: '.Str::limit($week->milestone, 180),
                'caption' => null,
                'goalPhoto' => null,
            ];
        }

        $result = $week->result();
        abort_unless(in_array($result, [GoalWeekResult::Past, GoalWeekResult::Smashed], true), 403, 'Share GoalWeeks where you got past or smashed the line.');

        return [
            'kind' => HighlightKind::GreatWeek,
            'goal' => $week->goal,
            'week' => $week,
            'headline' => $week->goal->title,
            'detail' => $result->label().' · '.$week->label().': '.$week->focus,
            'caption' => null,
            'goalPhoto' => null,
        ];
    }

    /**
     * The highlight already shared for the same moment, if any.
     *
     * @param  array{kind: HighlightKind, goal: Goal, week: RoadmapWeek|null}  $draft
     */
    private function alreadyShared(User $user, array $draft): ?Highlight
    {
        return $user->highlights()
            ->where('kind', $draft['kind'])
            ->where('goal_id', $draft['goal']->id)
            ->where('roadmap_week_id', $draft['week']?->id)
            ->first();
    }

    /**
     * Copy the goal's completion photo so the highlight keeps it even if the goal changes.
     */
    private function copyGoalPhoto(Goal $goal, Highlight $highlight): string
    {
        $path = "highlights/{$highlight->id}/".basename($goal->completion_photo_path);
        Storage::disk(Highlight::PHOTO_DISK)->copy($goal->completion_photo_path, $path);

        return $path;
    }
}
