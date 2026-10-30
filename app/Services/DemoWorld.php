<?php

namespace App\Services;

use App\Clock;
use App\GoalCategory;
use App\HighlightKind;
use App\Models\Challenge;
use App\Models\Friendship;
use App\Models\Goal;
use App\Models\GroupTask;
use App\Models\Highlight;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\FriendRequested;
use App\Notifications\GroupTaskProposed;
use App\Notifications\HighlightCheered;
use App\Notifications\Nudged;
use App\PostVisibility;
use App\ProfileVisibility;
use App\RoadmapStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds a one-click demo: a visitor account in its own private world, with pretend friends, two GoalLeagues partway
 * through the season (a weekly matchup live right now), a challenge, posts with cheers, a group task waiting on the
 * visitor's vote, league chat, and an inbox. Everything is dated relative to today, so it always looks current.
 * Nothing in a demo world can reach real members.
 */
class DemoWorld
{
    /**
     * The visitor's name in the demo.
     */
    public const VISITOR_NAME = 'John Cardinal';

    /**
     * Weeks of the season already played before this one.
     */
    private const WEEKS_PLAYED = 3;

    /**
     * A week of marathon training, Monday to Sunday: [task, points, tracked number, unit].
     */
    private const RUNNING_WEEK = [
        ['Easy run', 1, 4, 'miles'],
        ['Strength and core, 30 minutes', 1, null, null],
        ['Tempo run', 1, 5, 'miles'],
        ['Easy run', 1, 3, 'miles'],
        ['Mobility, 15 minutes', 1, null, null],
        ['Long run', 2, 10, 'miles'],
        ['Foam roll and stretch', 1, null, null],
    ];

    /**
     * A week of a 4.0 semester, Monday to Sunday.
     */
    private const STUDY_WEEK = [
        ['Organic Chem problem set', 2, null, null],
        ['Read Econ chapter and take notes', 1, null, null],
        ['Calc office hours', 1, null, null],
        ['Spanish flashcards', 1, 50, 'cards'],
        ['History essay', 2, 500, 'words'],
        ['Review the week\'s lecture notes', 1, null, null],
        ['Plan next week\'s study blocks', 1, null, null],
    ];

    private string $sandbox;

    private CarbonImmutable $today;

    /**
     * This week's Monday.
     */
    private CarbonImmutable $monday;

    /**
     * Build a fresh demo world and return the visitor.
     */
    public function create(): User
    {
        $this->sandbox = (string) Str::uuid();
        $this->today = CarbonImmutable::instance(Clock::today());
        $this->monday = $this->today->startOfWeek(CarbonImmutable::MONDAY);
        $seasonStart = $this->monday->subWeeks(self::WEEKS_PLAYED);

        $visitor = DB::transaction(function () use ($seasonStart) {
            $you = $this->person(self::VISITOR_NAME, 'Training for my first marathon and chasing a 4.0. One more day on the line.');
            $red = $this->person('Red Dufour', 'Marathon #3. Coffee first, then miles.');
            $tim = $this->person('Tim Busch', 'Couch to marathon. Somehow it\'s working.');
            $steph = $this->person('Steph Busch', 'Trail runner. Slow and steady.');
            $mary = $this->person('Mary Mullen', 'Pre-med. Mullen Library regular.');
            $pat = $this->person('Pat Caldwell', 'Busch School of Business. Coffee minor.');
            $kate = $this->person('Kate Gibbons', 'Dean\'s list or bust.');
            $fran = $this->person('Fran Flather', 'Writing a novel, one page at a time.');

            // Two GoalLeagues, three weeks played, this week's matchup live.
            $running = $this->league('Marathon by March', 'Everyone training for a spring marathon. Long runs on Saturdays.', GoalCategory::Fitness, $red, $seasonStart, $seasonStart->addWeeks(20)->subDay());
            $study = $this->league('4.0 This Fall', 'Straight A\'s this semester. Show the work, not just the grade.', GoalCategory::Academic, $you, $seasonStart, $seasonStart->addWeeks(10)->subDay());

            $marathonTarget = $this->today->setMonth(3)->setDay(15)->addYears($this->today->month >= 3 ? 1 : 0);
            $finals = $seasonStart->addWeeks(10)->subDay();

            // How each person's weeks went: [tasks done, milestone hit, bonus tasks], for the weeks already played.
            $runners = [
                [$you, 'Run the DC Marathon by March', [[6, true, 0], [7, true, 1], [7, true, 0]], 1],
                [$red, 'Run a marathon under 3:45 by March', [[7, true, 2], [7, true, 1], [7, true, 3]], 0],
                [$tim, 'Finish my first marathon in March', [[5, false, 0], [6, true, 0], [7, true, 1]], 1],
                [$steph, 'Run the Shamrock Marathon this spring', [[6, true, 0], [4, false, 0], [6, true, 0]], 0],
            ];
            $students = [
                [$you, 'Get a 4.0 GPA this fall', [[7, true, 0], [6, true, 0], [7, true, 1]], 1],
                [$mary, 'Ace Organic Chem and keep a 4.0', [[7, true, 1], [7, true, 0], [6, true, 0]], 0],
                [$pat, 'Get straight A\'s this semester', [[5, true, 0], [6, true, 0], [5, false, 0]], 1],
                [$kate, 'Make the Dean\'s List this fall', [[6, true, 0], [7, true, 2], [7, true, 0]], 0],
            ];

            $goals = [];
            foreach ($runners as [$person, $title, $history, $missedThisWeek]) {
                $goals['run'][$person->id] = $this->goal($person, $title, GoalCategory::Fitness, $marathonTarget, self::RUNNING_WEEK, $history, $missedThisWeek, 'Run', 1);
                $running->members()->create(['user_id' => $person->id, 'goal_id' => $goals['run'][$person->id]->id]);
            }
            foreach ($students as [$person, $title, $history, $missedThisWeek]) {
                $goals['study'][$person->id] = $this->goal($person, $title, GoalCategory::Academic, $finals, self::STUDY_WEEK, $history, $missedThisWeek, 'Study', 0);
                $study->members()->create(['user_id' => $person->id, 'goal_id' => $goals['study'][$person->id]->id]);
            }

            // A daily habit, a personal task the league can see, and last week's results scored.
            $this->habit($goals['run'][$you->id], 'Stretch 10 minutes', 5);
            $this->personalTask($goals['run'][$you->id], 'Ice bath after the long run', 1);
            $this->personalTask($goals['run'][$tim->id], 'Walk 20 minutes on rest days', 1);
            (new LeagueTable($running->fresh()))->settle();
            (new LeagueTable($study->fresh()))->settle();

            $this->friends($you, [$red, $mary, $pat, $fran]);
            $this->friends($red, [$tim, $steph]);
            Friendship::create(['requester_id' => $steph->id, 'addressee_id' => $you->id]);

            $this->posts($you, $red, $tim, $steph, $mary, $pat, $fran, $running, $study, $goals);
            $groupTask = $this->groupTask($running, $red, [$red, $tim]);
            $this->challenge($tim, [$you, $tim, $red, $mary]);
            $this->chat($running, [[$red, 'Week '.(self::WEEKS_PLAYED + 1).', let\'s go. Who\'s in for the river trail on Saturday?', 26], [$tim, 'In. Bringing gels for everyone.', 25], [$steph, 'Count me in. Slow pace please, my calves remember last week.', 20], [$red, 'Proposed a group task for next week. Vote in the Group tasks section.', 3]]);
            $this->chat($study, [[$mary, 'Midterms in two weeks. Office hours are the cheat code.', 30], [$kate, 'Mullen 3rd floor tonight if anyone wants a study buddy.', 9], [$pat, 'Saving me a seat?', 8]]);
            $this->inbox($you, $red, $mary, $steph, $groupTask);

            return $you;
        });

        return $visitor;
    }

    /**
     * Delete demo worlds older than the time limit, people, goals, leagues, posts, and photos.
     *
     * @return int How many demo accounts were removed.
     */
    public static function pruneExpired(): int
    {
        $expired = User::whereNotNull('demo_sandbox')
            ->where('created_at', '<', now()->subHours(config('goalline.demo.ttl_hours')))
            ->get();

        $expired->each->delete();

        return $expired->count();
    }

    private function person(string $name, string $bio): User
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name, '.').'.'.Str::lower(Str::random(10)).'@demo.goalline.invalid',
            'password' => Str::password(32),
            'bio' => $bio,
            'profile_visibility' => ProfileVisibility::Leagues,
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'onboarded_at' => now()->subWeeks(5),
            'kickoff_dismissed_at' => now(),
            'discoverable' => true,
            'demo_sandbox' => $this->sandbox,
        ])->save();

        return $user;
    }

    private function league(string $name, string $description, GoalCategory $category, User $owner, CarbonImmutable $startsOn, CarbonImmutable $endsOn): League
    {
        $league = new League(['name' => $name, 'description' => $description, 'category' => $category, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
        $league->commissioner()->associate($owner)->save();

        return $league;
    }

    /**
     * A goal with a roadmap: the weeks already played (as the history says), this week partway through, and two
     * weeks ahead.
     *
     * @param  list<array{0: string, 1: int, 2: int|null, 3: string|null}>  $template
     * @param  list<array{0: int, 1: bool, 2: int}>  $history
     */
    private function goal(User $owner, string $title, GoalCategory $category, CarbonImmutable $target, array $template, array $history, int $missedThisWeek, string $focusWord, int $stepUp): Goal
    {
        $goal = $owner->goals()->create(['title' => $title, 'category' => $category, 'target_date' => $target, 'why' => 'Because doing it with friends makes it stick.']);
        $goal->forceFill([
            'roadmap_status' => RoadmapStatus::Ready,
            'roadmap_summary' => 'Build steadily week by week, with the hardest work in the middle and a lighter final week.',
            'created_at' => $this->monday->subWeeks(self::WEEKS_PLAYED)->subDays(3),
        ])->saveQuietly();

        foreach (range(-self::WEEKS_PLAYED, 2) as $offset) {
            $starts = $this->monday->addWeeks($offset);
            $week = $goal->weeks()->create([
                'position' => $offset + self::WEEKS_PLAYED + 1,
                'starts_on' => $starts,
                'ends_on' => $starts->addDays(6),
                'focus' => $focusWord === 'Run' ? 'Build the base: easy miles and a longer long run' : 'Stay ahead of every class',
                'milestone' => $focusWord === 'Run' ? 'Finish a '.(10 + ($offset + self::WEEKS_PLAYED) * $stepUp).'-mile long run' : 'Every assignment in on time',
            ]);

            $tasks = $this->tasksFor($week, $template, $offset + self::WEEKS_PLAYED, $stepUp);

            if ($offset < 0) {
                [$done, $milestone, $bonus] = $history[$offset + self::WEEKS_PLAYED];
                $this->playPastWeek($week, $tasks, $done, $milestone, $bonus);
            } elseif ($offset === 0) {
                $this->playThisWeek($tasks, $missedThisWeek);
            }
        }

        return $goal;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int|null, 3: string|null}>  $template
     * @return list<RoadmapTask>
     */
    private function tasksFor(RoadmapWeek $week, array $template, int $weekIndex, int $stepUp): array
    {
        $tasks = [];

        foreach ($template as $day => [$description, $points, $number, $unit]) {
            $task = $week->tasks()->create(['position' => $day + 1, 'description' => $description, 'planned_on' => $week->starts_on->copy()->addDays($day)]);
            $task->forceFill(['points' => $points]);

            if ($number) {
                $task->trackNumber($number + ($unit === 'miles' && $description === 'Long run' ? $weekIndex * $stepUp : 0), $unit);
            }

            $task->save();
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * @param  list<RoadmapTask>  $tasks
     */
    private function playPastWeek(RoadmapWeek $week, array $tasks, int $done, bool $milestone, int $bonus): void
    {
        foreach (array_slice($tasks, 0, $done) as $task) {
            $this->checkOff($task, CarbonImmutable::instance($task->planned_on)->setTime(rand(6, 20), rand(0, 59)));
        }

        if ($milestone) {
            $week->forceFill(['milestone_hit_at' => CarbonImmutable::instance($week->ends_on)->setTime(18, 0)])->save();
        }

        for ($extra = 1; $extra <= $bonus; $extra++) {
            $task = $week->tasks()->create(['position' => 20 + $extra, 'description' => 'Extra session', 'is_bonus' => true]);
            $this->checkOff($task, CarbonImmutable::instance($week->ends_on)->subDays($extra)->setTime(12, 0));
        }
    }

    /**
     * This week so far: everything before today checked off except the ones missed, today's still to do.
     *
     * @param  list<RoadmapTask>  $tasks
     */
    private function playThisWeek(array $tasks, int $missed): void
    {
        $earlier = array_values(array_filter($tasks, fn (RoadmapTask $task) => $task->planned_on->lt($this->today)));

        foreach ($earlier as $index => $task) {
            if ($index < $missed) {
                continue;
            }

            $this->checkOff($task, CarbonImmutable::instance($task->planned_on)->setTime(rand(6, 20), rand(0, 59)));
        }
    }

    private function checkOff(RoadmapTask $task, CarbonImmutable $when): void
    {
        $task->forceFill([
            'completed_at' => $when,
            'result_value' => $task->result_required ? $task->result_target : null,
        ])->saveQuietly();
    }

    private function habit(Goal $goal, string $title, int $timesPerWeek): void
    {
        $habit = $goal->habits()->create(['title' => $title, 'times_per_week' => $timesPerWeek]);
        $habit->forceFill(['created_at' => $goal->created_at])->saveQuietly();

        for ($day = $this->monday->subWeeks(self::WEEKS_PLAYED); $day->lt($this->today); $day = $day->addDay()) {
            if ($day->dayOfWeekIso <= 5) {
                $habit->logs()->create(['logged_on' => $day]);
            }
        }
    }

    private function personalTask(Goal $goal, string $description, int $points): void
    {
        $week = $goal->weeks()->whereDate('starts_on', $this->monday)->sole();
        $week->tasks()->create(['position' => 30, 'description' => $description, 'planned_on' => $this->monday->addDays(5)])
            ->forceFill(['is_personal' => true, 'points' => $points])->save();
    }

    /**
     * @param  list<User>  $others
     */
    private function friends(User $person, array $others): void
    {
        foreach ($others as $other) {
            Friendship::create(['requester_id' => $person->id, 'addressee_id' => $other->id])
                ->forceFill(['status' => Friendship::ACCEPTED, 'accepted_at' => now()->subWeeks(4)])->save();
        }
    }

    /**
     * Check-in posts with photos, a finished goal, cheers, and a comment or two.
     *
     * @param  array<string, array<int, Goal>>  $goals
     */
    private function posts(User $you, User $red, User $tim, User $steph, User $mary, User $pat, User $fran, League $running, League $study, array $goals): void
    {
        $redLong = $this->post($red, $goals['run'][$red->id], $running, 'Long run', PostVisibility::LeagueFriends,
            'Long run done along the river. Legs are toast, heart is full.', 'Rock Creek Park trail', 'Long run', '12.0 mi · 9:02 /mi', '#176B45', '#43B96A', 2);
        $yourTempo = $this->post($you, $goals['run'][$you->id], $running, 'Tempo run', PostVisibility::LeagueFriends,
            'First tempo run that felt smooth. The process is working.', 'Riverside path', 'Tempo run', '5.0 mi · 8:41 /mi', '#1E2922', '#176B45', 1);
        $maryStudy = $this->post($mary, $goals['study'][$mary->id], $study, 'Organic Chem problem set', PostVisibility::League,
            'Library till close. Orgo set done, and I finally get stereochemistry.', 'Mullen Library, 3rd floor', 'Problem set 6', '24 of 24 done', '#D4AC58', '#176B45', 3);
        $patCards = $this->post($pat, $goals['study'][$pat->id], $study, 'Spanish flashcards', PostVisibility::League,
            'Fifty cards before breakfast. Small reps add up.', null, 'Spanish', '50 cards · 46 right', '#43B96A', '#1E2922', 1);

        $franWin = $fran->highlights()->create([
            'kind' => HighlightKind::GoalCompleted,
            'headline' => 'Finish the first draft of my novel',
            'detail' => 'Completed '.$this->today->subDays(2)->format('F j, Y').' · 38 GoalWeek points',
            'caption' => '82,000 words. A page a day for eleven months. Thank you all for the cheers along the way.',
        ]);
        $this->photo($franWin, 'First draft', '82,000 words', '#D4AC58', '#1E2922');
        $franWin->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        foreach ([[$redLong, [$you, $tim, $steph]], [$yourTempo, [$red, $steph, $tim]], [$maryStudy, [$you, $pat]], [$patCards, [$mary]], [$franWin, [$you, $red]]] as [$post, $fans]) {
            foreach ($fans as $fan) {
                $post->cheers()->create(['user_id' => $fan->id]);
            }
        }

        $yourTempo->comments()->create(['user_id' => $tim->id, 'body' => 'That pace though. Teach me your ways.']);
        $redLong->comments()->create(['user_id' => $steph->id, 'body' => 'Saturday we go together!']);
    }

    private function post(User $author, Goal $goal, League $league, string $taskName, PostVisibility $visibility, string $caption, ?string $location, string $photoTitle, string $photoSubtitle, string $from, string $to, int $daysAgo): Highlight
    {
        $task = RoadmapTask::whereIn('roadmap_week_id', $goal->weeks()->select('id'))
            ->where('description', $taskName)->whereNotNull('completed_at')->latest('completed_at')->first();

        $post = $author->highlights()->create([
            'kind' => HighlightKind::CheckIn,
            'goal_id' => $goal->id,
            'roadmap_week_id' => $task?->roadmap_week_id,
            'roadmap_task_id' => $task?->id,
            'league_id' => $league->id,
            'visibility' => $visibility,
            'headline' => $goal->title,
            'detail' => $task?->description ?? $taskName,
            'caption' => $caption,
            'location' => $location,
        ]);
        $path = $this->photo($post, $photoTitle, $photoSubtitle, $from, $to);
        $post->forceFill(['created_at' => now()->subDays($daysAgo)->subHours(rand(1, 6))])->saveQuietly();

        // The photo doubles as the task's proof, so the post shows Verified.
        $task?->forceFill(['proof_path' => $path, 'proof_uploaded_at' => $post->created_at])->saveQuietly();

        return $post;
    }

    /**
     * A simple drawn picture, so a demo never needs real photos.
     */
    private function photo(Highlight $highlight, string $title, string $subtitle, string $from, string $to): string
    {
        $path = "highlights/{$highlight->id}/demo.svg";
        $title = e($title);
        $subtitle = e($subtitle);
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
              <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$from}"/><stop offset="1" stop-color="{$to}"/></linearGradient></defs>
              <rect width="800" height="800" fill="url(#g)"/>
              <g stroke="#FAFAF5" stroke-opacity="0.12" stroke-width="4" fill="none">
                <path d="M0 560 C 200 480, 400 640, 800 520"/><path d="M0 620 C 220 540, 420 700, 800 580"/><path d="M0 680 C 240 600, 440 760, 800 640"/>
              </g>
              <circle cx="640" cy="170" r="70" fill="#FAFAF5" fill-opacity="0.15"/>
              <text x="64" y="380" font-family="Georgia, serif" font-size="76" fill="#FAFAF5">{$title}</text>
              <text x="64" y="450" font-family="Arial, sans-serif" font-size="40" fill="#FAFAF5" fill-opacity="0.85">{$subtitle}</text>
              <text x="64" y="740" font-family="Arial, sans-serif" font-size="26" letter-spacing="6" fill="#FAFAF5" fill-opacity="0.7">GOALLINE DEMO</text>
            </svg>
            SVG;

        Storage::disk(Highlight::PHOTO_DISK)->put($path, $svg);
        $highlight->forceFill(['photo_path' => $path])->saveQuietly();

        return $path;
    }

    /**
     * A group task for next week with two yes votes of the three needed. The visitor's vote passes it.
     *
     * @param  list<User>  $yesVotes
     */
    private function groupTask(League $league, User $proposer, array $yesVotes): GroupTask
    {
        $groupTask = $league->groupTasks()->create([
            'proposer_id' => $proposer->id,
            'title' => '10-minute foam roll after the long run',
            'description' => 'Recovery is part of the process. Same night as the long run.',
            'points' => 2,
            'week' => self::WEEKS_PLAYED + 2,
            'closes_at' => now()->addHours(20),
        ]);
        $groupTask->forceFill(['created_at' => now()->subHours(4)])->saveQuietly();

        foreach ($yesVotes as $voter) {
            $groupTask->votes()->create(['user_id' => $voter->id, 'approve' => true]);
        }

        return $groupTask;
    }

    /**
     * @param  list<User>  $members  The first is the visitor; the creator runs it.
     */
    private function challenge(User $creator, array $members): void
    {
        $startsOn = $this->today->subDays(12);
        $challenge = new Challenge(['title' => '10K steps a day', 'description' => 'Every day for three weeks. Walks count, runs count, pacing on calls counts.', 'starts_on' => $startsOn, 'ends_on' => $startsOn->addDays(20), 'target_days' => 21]);
        $challenge->creator()->associate($creator)->save();

        foreach ($members as $index => $member) {
            $challenge->members()->create(['user_id' => $member->id]);

            for ($day = $startsOn; $day->lt($this->today); $day = $day->addDay()) {
                // Everyone misses a day here and there; the visitor hasn't checked in today yet.
                if (($day->day + $index) % 6 !== 0) {
                    $challenge->checkIns()->create(['user_id' => $member->id, 'checked_on' => $day]);
                }
            }
        }
    }

    /**
     * @param  list<array{0: User, 1: string, 2: int}>  $messages  [who, what, hours ago]
     */
    private function chat(League $league, array $messages): void
    {
        foreach ($messages as [$author, $body, $hoursAgo]) {
            $league->messages()->create(['user_id' => $author->id, 'body' => $body])
                ->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
        }
    }

    private function inbox(User $you, User $red, User $mary, User $steph, GroupTask $groupTask): void
    {
        $yourPost = $you->highlights()->first();

        foreach ([
            [new FriendRequested($steph), 2],
            [new GroupTaskProposed($groupTask), 4],
            [new Nudged($mary), 7],
            [new HighlightCheered($yourPost, $red), 20],
        ] as [$notification, $hoursAgo]) {
            // Sent as of a few hours ago, so the inbox reads like a real day.
            $now = Carbon::getTestNow();
            Carbon::setTestNow(now()->subHours($hoursAgo));
            $you->notifyNow($notification, ['database']);
            Carbon::setTestNow($now);
        }
    }
}
