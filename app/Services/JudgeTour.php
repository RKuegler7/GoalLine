<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The Judge's tour: a short checklist shown only in demo accounts, so someone trying GoalLine for the first time sees
 * the parts that matter. Each item ticks itself off when the visitor does the thing (TrackJudgeTour watches the
 * routes; opening the AI panel pings in). Progress lives in the cache, keyed to the demo world, so Reset demo (a new
 * world) starts it fresh and it disappears with the demo. Nothing here is stored on the user or scored.
 */
class JudgeTour
{
    /**
     * The items in order: title, one-line explanation.
     *
     * @var array<string, array{title: string, explain: string}>
     */
    public const ITEMS = [
        'check_in' => ['title' => 'Check in a task and watch your band move', 'explain' => 'Tap a task’s check. Your band (Short, In Line, Past, Smashed) moves as the week fills in.'],
        'matchup' => ['title' => 'Open your Matchup', 'explain' => 'You against one opponent this week, each measured against your own plan.'],
        'ai_plan' => ['title' => 'See how AI built this plan', 'explain' => 'Open “How this plan was built” on your goal: what the AI drafted, and what code checked and enforced.'],
        'proof' => ['title' => 'Add proof or dispute a check-in', 'explain' => 'Attach a photo or details to a task, or question an opponent’s check-in.'],
        'standings' => ['title' => 'Explore the Standings table', 'explain' => 'Stars rank the table. Swipe for more columns, tap a heading to sort.'],
        'finale' => ['title' => 'Preview the season finale', 'explain' => 'A finished season: medals, the season card and what to go for next.'],
    ];

    /**
     * Route names that tick an item off when they succeed.
     *
     * @var array<string, string>
     */
    public const ROUTES = [
        'roadmap.tasks.check' => 'check_in',
        'matchups' => 'matchup',
        'leagues.matchups' => 'matchup',
        'roadmap.tasks.proof.store' => 'proof',
        'disputes.store' => 'proof',
        'leagues.standings' => 'standings',
        'finale.preview' => 'finale',
    ];

    /**
     * Items the page reports itself (something seen, not a request).
     */
    public const PINGS = ['ai_plan'];

    private const TTL_DAYS = 7;

    public static function applies(?User $user): bool
    {
        return $user !== null && $user->isDemo();
    }

    /**
     * @return list<string>
     */
    public static function done(User $user): array
    {
        return array_values(array_intersect(array_keys(self::ITEMS), self::state($user)['done']));
    }

    public static function hidden(User $user): bool
    {
        return self::state($user)['hidden'];
    }

    public static function mark(User $user, string $item): void
    {
        if (! self::applies($user) || ! isset(self::ITEMS[$item])) {
            return;
        }

        $state = self::state($user);

        if (! in_array($item, $state['done'], true)) {
            $state['done'][] = $item;
            self::save($user, $state);
        }
    }

    public static function setHidden(User $user, bool $hidden): void
    {
        self::save($user, ['hidden' => $hidden] + self::state($user));
    }

    /**
     * Where "Take me there" goes for this visitor.
     */
    public static function destination(User $user, string $item): string
    {
        $goal = fn () => Goal::where('user_id', $user->id)->orderBy('id')->first();
        $membership = fn () => LeagueMember::where('user_id', $user->id)->orderBy('id')->first();

        return match ($item) {
            'check_in' => route('today'),
            'matchup' => route('matchups'),
            'ai_plan' => ($found = $goal()) ? route('goals.show', [$found, 'tour' => 'ai_plan']) : route('about'),
            'proof' => ($found = $goal()) ? route('goals.show', $found) : route('today'),
            'standings' => ($found = $membership()) ? route('leagues.standings', $found->league_id) : route('leagues.index'),
            'finale' => route('finale.preview'),
        };
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    /**
     * @return array{done: list<string>, hidden: bool}
     */
    private static function state(User $user): array
    {
        $state = Cache::get(self::key($user));

        return is_array($state) ? ['done' => array_values($state['done'] ?? []), 'hidden' => (bool) ($state['hidden'] ?? false)] : ['done' => [], 'hidden' => false];
    }

    /**
     * @param  array{done: list<string>, hidden: bool}  $state
     */
    private static function save(User $user, array $state): void
    {
        Cache::put(self::key($user), $state, now()->addDays(self::TTL_DAYS));
    }

    private static function key(User $user): string
    {
        return 'judge-tour:'.$user->demo_sandbox.':'.$user->id;
    }
}
