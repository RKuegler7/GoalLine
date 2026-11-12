<?php

namespace App;

use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where Back goes, for every screen inside the app. The five main tabs have no Back. Every other screen gets a Back
 * arrow in the top bar: it returns to the page the member actually came from when that page is in GoalLine (the
 * browser's history, so scroll and tab state come back too), and otherwise, like after opening a link from an email
 * or a shared invite, to the sensible parent named here. A page can name its own parent instead (x-page-header's
 * back). On desktop the same chain gives a short breadcrumb trail.
 */
class BackNavigation
{
    /**
     * Routes that are the top level of a main tab.
     *
     * @var list<string>
     */
    public const TOP_LEVEL = ['dashboard', 'leagues.index', 'matchups', 'highlights.index', 'profile.show'];

    /**
     * The parent for a screen: url and label, or null for a main tab (or a screen outside the app shell).
     *
     * @return array{url: string, label: string}|null
     */
    public static function parent(Request $request): ?array
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name === null || in_array($name, self::TOP_LEVEL, true)) {
            return null;
        }

        $league = $route->parameter('league');
        $goal = $route->parameter('goal');
        $week = $route->parameter('week');
        $leaguePage = $league instanceof League ? ['url' => route('leagues.show', $league), 'label' => $league->name] : null;
        $goalPage = $goal instanceof Goal ? ['url' => route('goals.show', $goal), 'label' => $goal->title] : null;

        return match (true) {
            $name === 'leagues.standings.player' => ['url' => route('leagues.standings', $league), 'label' => 'Standings'],
            $name === 'leagues.show' => ['url' => route('leagues.index'), 'label' => 'GoalLeagues'],
            Str::startsWith($name, 'leagues.') && $leaguePage !== null => $leaguePage,
            Str::startsWith($name, ['leagues.', 'challenges.']) => ['url' => route('leagues.index'), 'label' => 'GoalLeagues'],
            $name === 'goals.show' => ['url' => route('dashboard'), 'label' => 'Clubhouse'],
            Str::startsWith($name, 'goals.') && $goalPage !== null => $goalPage,
            $name === 'history.weeks.show' => ['url' => route('dashboard', ['tab' => 'history']), 'label' => 'History'],
            $name === 'cards.share' && $week instanceof RoadmapWeek => ['url' => route('goals.show', $week->goal_id), 'label' => 'Your goal'],
            Str::startsWith($name, ['highlights.', 'friends.', 'people.']) => ['url' => route('highlights.index'), 'label' => 'Highlights'],
            $name === 'profiles.show' => ['url' => route('highlights.index'), 'label' => 'Highlights'],
            Str::startsWith($name, ['profile.', 'settings.', 'account.']) => ['url' => route('profile.show'), 'label' => 'My Locker'],
            default => ['url' => route('dashboard'), 'label' => 'Clubhouse'],
        };
    }

    /**
     * The desktop breadcrumb trail: the main tab, the parent (when it isn't the tab itself), and this screen.
     *
     * @param  array{url: string, label: string}  $parent
     * @return list<array{label: string, url: string|null}>
     */
    public static function crumbs(?string $tabLabel, ?string $tabUrl, array $parent, string $title): array
    {
        $trail = [];

        if ($tabLabel !== null && $tabLabel !== $parent['label']) {
            $trail[] = ['label' => $tabLabel, 'url' => $tabUrl];
        }

        $trail[] = ['label' => $parent['label'], 'url' => $parent['url']];
        $trail[] = ['label' => $title, 'url' => null];

        return $trail;
    }

    /**
     * The screen's short title, from the page title ("Standings · Fall Grind · GoalLine" gives "Standings").
     */
    public static function title(string $pageTitle): string
    {
        return trim(Str::before($pageTitle, ' · ')) ?: 'GoalLine';
    }
}
