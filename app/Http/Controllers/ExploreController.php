<?php

namespace App\Http\Controllers;

use App\GoalCategory;
use App\LeagueStatus;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\Models\UserMute;
use App\PostVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Explore: public GoalLeagues you can join, and posts their authors chose to make public. Nothing else ever shows here:
 * a league is listed only when its owner turns it on, and a post only when its author picked "Public (Explore)". Each
 * GoalLine world stays separate: a demo sees only its own example leagues and posts, never real members' content.
 */
class ExploreController extends Controller
{
    public const PAGE_SIZE = 12;

    /**
     * Example goals that open "Start a GoalLeague" filled in.
     *
     * @var list<array{title: string, category: string, name: string}>
     */
    public const INSPIRATION = [
        ['title' => 'Pass a CPA section', 'category' => 'career', 'name' => 'CPA Prep Crew'],
        ['title' => 'Run a marathon', 'category' => 'fitness', 'name' => 'Marathon Crew'],
        ['title' => 'Finish 75 Hard', 'category' => 'personal', 'name' => '75 Hard Squad'],
        ['title' => 'Finish my thesis', 'category' => 'academic', 'name' => 'Thesis Writers'],
    ];

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $before = $request->integer('before') ?: null;

        $leagues = $before ? collect() : League::query()
            ->where('is_public', true)
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->whereHas('commissioner', fn (Builder $owner) => $owner->where('demo_sandbox', $viewer->demo_sandbox))
            ->whereNotIn('commissioner_id', UserMute::where('user_id', $viewer->id)->select('muted_user_id'))
            ->withCount('members')
            ->orderBy('starts_on')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->filter(fn (League $league) => $league->isJoinable())
            ->values();

        $posts = Highlight::query()
            ->visibleTo($viewer)
            ->where('visibility', PostVisibility::Public)
            ->when($before, fn (Builder $query) => $query->where('highlights.id', '<', $before))
            ->with(['user', 'cheers.user', 'comments.user', 'task', 'league'])
            ->orderByDesc('highlights.id')
            ->limit(self::PAGE_SIZE)
            ->get();

        return view('highlights.explore', [
            'leagues' => $leagues,
            'memberOf' => $viewer->leagues()->pluck('leagues.id')->flip(),
            'posts' => $posts,
            'hasMore' => $posts->count() === self::PAGE_SIZE,
            'inspiration' => $before ? [] : self::INSPIRATION,
        ]);
    }

    /**
     * Join a public GoalLeague: the usual join flow, through the league's shareable invite link.
     */
    public function join(Request $request, League $league): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($league->is_public && $league->commissioner?->sharesWorldWith($viewer), 404);

        if ($league->memberFor($viewer)) {
            return redirect()->route('leagues.show', $league);
        }

        if (! $league->isJoinable()) {
            return redirect()->route('highlights.explore')->with('status', $league->isFull() ? 'That GoalLeague is full.' : 'That GoalLeague\'s season is over.');
        }

        return redirect()->to($league->shareableInvite()->url());
    }

    /**
     * The owner lists a GoalLeague in Explore, or takes it out.
     */
    public function listLeague(Request $request, League $league): RedirectResponse
    {
        Gate::authorize('manage', $league);
        $public = $request->boolean('is_public');
        $league->forceFill(['is_public' => $public])->save();

        return redirect()->to(route('leagues.show', $league).'#invite')
            ->with('status', $public ? 'Listed in Explore. Anyone on GoalLine can find it and join while there\'s room.' : 'Taken out of Explore.');
    }

    /**
     * Change who sees one of your posts (for example from Public back to your league); it leaves Explore at once.
     */
    public function visibility(Request $request, Highlight $highlight): RedirectResponse
    {
        abort_unless($highlight->user_id === $request->user()->id, 403);
        $validated = $request->validate(['visibility' => ['required', Rule::in($highlight->visibilityChoices())]]);

        $highlight->forceFill(['visibility' => $validated['visibility'] === 'circle' ? null : $validated['visibility']])->save();

        return back()->with('status', $validated['visibility'] === PostVisibility::Public->value ? 'Public: anyone on GoalLine can see this post in Explore.' : 'Updated. It\'s no longer in Explore.');
    }

    /**
     * Mute someone: their posts never show for you again. Unmute any time from their profile.
     */
    public function mute(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422);

        if ($request->isMethod('delete')) {
            UserMute::where('user_id', $request->user()->id)->where('muted_user_id', $user->id)->delete();

            return back()->with('status', "Unmuted {$user->name}.");
        }

        UserMute::firstOrCreate(['user_id' => $request->user()->id, 'muted_user_id' => $user->id]);

        return back()->with('status', "Muted {$user->name}. Their posts won't show for you.");
    }

    public static function categoryLabel(?GoalCategory $category): string
    {
        return $category?->label() ?? 'Any goal';
    }

    public static function weekLabel(League $league): string
    {
        return match ($league->status()) {
            LeagueStatus::Forming => 'Kicks off '.$league->starts_on->format('M j'),
            default => 'Week '.$league->currentWeek().' of '.$league->weeksCount(),
        };
    }
}
