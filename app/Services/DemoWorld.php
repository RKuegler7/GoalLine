<?php

namespace App\Services;

use App\Clock;
use App\GoalCategory;
use App\HighlightKind;
use App\Lent;
use App\Models\Challenge;
use App\Models\Friendship;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\GroupTask;
use App\Models\Habit;
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
use LogicException;
use Throwable;

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
    public const VISITOR_NAME = 'Ryan Kuegler';

    /**
     * The sandbox of the demo world made by the database seeder, so re-running the seeder replaces it.
     */
    public const SEEDED_SANDBOX = 'seeded';

    /**
     * Weeks of the season already played before this one.
     */
    private const WEEKS_PLAYED = 5;

    /**
     * A week of marathon training, Monday to Sunday: [task, points, tracked number, unit, tracked in played weeks only].
     * Played-only numbers are how the big weeks beat their plan; in this week and later ones those tasks stay a
     * one-tap check, so the visitor always has a task to tap.
     */
    private const RUNNING_WEEK = [
        ['Easy run', 1, 4, 'miles'],
        ['Strength and core, 30 minutes', 1, 30, 'minutes', true],
        ['Tempo run', 1, 5, 'miles'],
        ['Easy run', 1, 3, 'miles'],
        ['Mobility, 20 minutes', 1, 20, 'minutes', true],
        ['Long run', 2, 10, 'miles'],
        ['Foam roll and stretch', 1, null, null],
    ];

    /**
     * A week of SIE exam prep, Monday to Sunday (the live league and last season's).
     */
    private const SIE_WEEK = [
        ['Practice questions, 40', 1, 40, 'questions'],
        ['Read and outline one chapter', 1, null, null],
        ['Timed practice set', 2, 40, 'questions', true],
        ['Flashcards', 1, 50, 'cards', true],
        ['Review missed questions', 1, 20, 'questions', true],
        ['Mock exam block', 2, 90, 'minutes', true],
        ['Plan next week', 1, null, null],
    ];

    /**
     * How long last season of SIE Exam Study Prep ran.
     */
    private const FINISHED_SEASON_WEEKS = 6;

    private string $sandbox;

    private CarbonImmutable $today;

    /**
     * This week's Monday.
     */
    private CarbonImmutable $monday;

    /**
     * Build a fresh demo world and return the visitor.
     */
    public function create(?string $sandbox = null): User
    {
        $this->sandbox = $sandbox ?? (string) Str::uuid();
        $this->today = CarbonImmutable::instance(Clock::today());
        $this->monday = $this->today->startOfWeek(CarbonImmutable::MONDAY);
        $seasonStart = $this->monday->subWeeks(self::WEEKS_PLAYED);

        // Nothing is awarded while the world is built (no medal locks or INSERT IGNOREs inside this transaction): the
        // sample season's finale and medals are written below as seeded data.
        $visitor = MedalAwarder::whileSeeding(fn () => DB::transaction(function () use ($seasonStart) {
            // The Busch School crew, only ever shown doing well: the visitor is Ryan, with his league mates and friends.
            $you = $this->person(self::VISITOR_NAME, "West Chester | DC | Catholic Men's Soccer | Finance and Sales at the Busch School of Business. Building GoalLine.");
            $peter = $this->person('Peter Kilpatrick', 'Catholic University. Miles before meetings.');
            $tim = $this->person('Tim Busch', "Couch to marathon. Somehow it's working.");
            $steph = $this->person('Steph Busch', 'Trail runner. Slow and steady.');
            $andrew = $this->person('Andrew Abela', 'Busch School of Business. Back in exam prep with the students.');
            $jack = $this->person('Jack Yoest', 'Busch School professor. Studying alongside my class this winter.');

            // Rich profiles for the people judges will open.
            $you->forceFill(['location' => 'Washington, DC'])->save();
            $this->identity($you, 'Building GoalLine, one GoalWeek at a time', ['Soccer', 'Running', 'Finance', 'Faith'], '#176B45', '#43B96A');
            $this->identity($peter, 'Early miles, then the day', ['Running', 'Faith', 'Reading'], '#1E2922', '#176B45');
            $this->identity($tim, 'Proving couch to marathon works', ['Running', 'Cooking'], '#43B96A', '#176B45');
            $this->identity($steph, 'Trails, hills and long Saturdays', ['Running', 'Outdoors', 'Yoga'], '#176B45', '#1E2922');
            $this->identity($andrew, 'Business as a force for good', ['Business', 'Faith', 'Reading'], '#D4AC58', '#176B45');
            $this->identity($jack, 'Teaching, and learning with my students', ['Teaching', 'Business', 'Reading'], '#1E2922', '#D4AC58');
            $this->photoFor($you, resource_path('demo/ryan-kuegler.jpg'));

            // Two GoalLeagues, five weeks played, this week's Matchup live.
            $running = $this->league('Marathon by March', 'Everyone training for a spring marathon. Long runs on Saturdays.', GoalCategory::Fitness, $peter, $seasonStart, $seasonStart->addWeeks(20)->subDay());
            $study = $this->league('SIE Exam Study Prep', 'Getting ready for the SIE together. Show the work, not just the score.', GoalCategory::Academic, $you, $seasonStart, $seasonStart->addWeeks(14)->subDay());

            $marathonTarget = $this->today->setMonth(3)->setDay(15)->addYears($this->today->month >= 3 ? 1 : 0);
            $examDay = $seasonStart->addWeeks(14)->subDay();

            // How each person's weeks went: [tasks done, milestone hit, extra points from beating the numbers], for the weeks
            // already played.
            // Last week Peter and Steph both reached Smashed (150%+): Peter with proof, Steph without, so hers counts as Past.
            $runners = [
                [$you, 'Run the DC Marathon by March', [[5, true, 0], [6, true, 0], [6, true, 0], [7, true, 1], [7, true, 0]], 1],
                [$peter, 'Finish a spring marathon', [[6, true, 1], [7, true, 0], [7, true, 2], [7, true, 1], [7, true, 5]], 0],
                [$tim, 'Finish my first marathon in March', [[4, false, 0], [5, true, 0], [5, false, 0], [6, true, 0], [7, true, 1]], 1],
                [$steph, 'Run the Shamrock Marathon this spring', [[6, true, 0], [6, true, 1], [6, true, 0], [4, false, 0], [6, true, 0]], 0],
            ];
            $students = [
                [$you, 'Pass the SIE exam', [[6, true, 0], [7, true, 0], [7, true, 0], [6, true, 0], [7, true, 1]], 1],
                [$andrew, 'Pass the SIE this winter', [[7, true, 0], [6, true, 0], [7, true, 1], [7, true, 0], [6, true, 0]], 0],
                [$jack, 'Pass the SIE with my students', [[6, true, 0], [5, true, 0], [5, true, 0], [6, true, 0], [5, false, 0]], 1],
                [$steph, 'Pass the SIE before spring', [[5, true, 0], [6, true, 1], [6, true, 0], [7, true, 2], [7, true, 6]], 0],
            ];

            $goals = [];
            foreach ($runners as [$person, $title, $history, $missedThisWeek]) {
                $goals['run'][$person->id] = $this->goal($person, $title, GoalCategory::Fitness, $marathonTarget, self::RUNNING_WEEK, $history, $missedThisWeek, 'Run', 1);
                $running->members()->create(['user_id' => $person->id, 'goal_id' => $goals['run'][$person->id]->id]);
            }
            foreach ($students as [$person, $title, $history, $missedThisWeek]) {
                $goals['study'][$person->id] = $this->goal($person, $title, GoalCategory::Academic, $examDay, self::SIE_WEEK, $history, $missedThisWeek, 'Study', 0);
                $study->members()->create(['user_id' => $person->id, 'goal_id' => $goals['study'][$person->id]->id]);
            }

            // Each league's shared goal, the template new members start from.
            $running->forceFill(['goal_title' => 'Run a spring marathon', 'goal_target_date' => $marathonTarget, 'goal_description' => 'Any 26.2 this spring. Long runs on Saturdays.', 'goal_target_result' => 'Finish strong'])->save();
            $study->forceFill(['goal_title' => 'Pass the SIE exam', 'goal_target_date' => $examDay, 'goal_description' => 'Study blocks every week. Show the work, not just the score.', 'goal_target_result' => 'A 70 or higher'])->save();

            // A daily habit, a personal task the league can see, and last week's results scored.
            $this->habit($goals['run'][$you->id], 'Stretch', 5, 10);
            $this->personalTask($goals['run'][$you->id], 'Ice bath after the long run', 1);
            $this->personalTask($goals['run'][$tim->id], 'Walk 20 minutes on rest days', 1);
            $this->proofLastWeek($goals['run'][$peter->id], 'Long run', 'Further than the plan');
            (new LeagueTable($running->fresh()))->settle();
            (new LeagueTable($study->fresh()))->settle();

            // Last season of SIE Exam Study Prep: Goal Day answered, medals awarded, ready for the finale preview.
            $this->finishedSeason($you, $andrew, $jack, $steph, $seasonStart);

            $this->friends($you, [$peter, $andrew, $jack, $tim]);
            $this->friends($peter, [$tim, $steph]);
            $this->friends($tim, [$steph]);
            Friendship::create(['requester_id' => $steph->id, 'addressee_id' => $you->id]);

            $this->posts($you, $peter, $tim, $steph, $andrew, $jack, $running, $study, $goals);
            $this->exploreExamples($seasonStart);
            $this->planHonesty($goals, $you, $jack, $running);
            $groupTask = $this->groupTask($running, $peter, [$peter, $tim]);
            $this->challenges($you, $peter, $tim, $steph, $andrew, $jack);
            $this->chat($running, [[$peter, 'Week '.(self::WEEKS_PLAYED + 1).", let's go. Who's in for the river trail on Saturday?", 26], [$tim, 'In. Bringing gels for everyone.', 25], [$steph, 'Count me in. Slow pace please, my calves remember last week.', 20], [$peter, 'Proposed a group task for next week. Vote in the Group tasks section.', 3]]);
            $this->chat($study, [[$andrew, 'Practice exam Friday. Timed, like the real thing.', 30], [$jack, 'My class is taking it too. Friendly competition.', 9], [$steph, 'Saving me a seat in Maloney?', 8]]);
            $this->inbox($you, $peter, $andrew, $steph, $groupTask);

            return $you;
        }));

        return $visitor;
    }

    /**
     * A finished season for History and the finale preview: "SIE Exam Study Prep · Season 1", six weeks that ended
     * before this season began. Goal Day is answered by everyone (the visitor's verified by Andrew's confirm), medals
     * are awarded by code from the settled table, and the results are in the league's news.
     */
    private function finishedSeason(User $you, User $andrew, User $jack, User $steph, CarbonImmutable $before): League
    {
        $endsOn = $before->subWeek()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $startsOn = $endsOn->subWeeks(self::FINISHED_SEASON_WEEKS)->addDay();
        $league = $this->league('SIE Exam Study Prep · Season 1', 'Four of us, one exam, six weeks of study blocks.', GoalCategory::Career, $you, $startsOn, $endsOn);

        // [person, goal, weekly history [tasks done, milestone, extra points from beating the numbers], Goal Day answer]
        $season = [
            [$you, 'Score 80+ on a full SIE practice exam', [[6, true, 0], [7, true, 0], [7, true, 1], [5, true, 0], [7, true, 6], [7, true, 1]], ['achieved', 'Scored an 84 on the full practice exam', 'Timing on the long sections', 'Sunday study blocks with the league']],
            [$andrew, 'Finish the full SIE content review', [[7, true, 0], [6, true, 0], [7, true, 0], [7, true, 0], [6, true, 0], [7, true, 0]], ['achieved', 'Every chapter reviewed and outlined', 'Fitting study around the day', 'Early mornings before meetings']],
            [$jack, 'Score 75+ on every SIE practice set', [[5, true, 0], [6, true, 0], [4, false, 0], [6, true, 0], [6, true, 0], [5, true, 0]], ['partly', 'Five of six sets above 75', 'Options questions', 'Studying alongside my students']],
            [$steph, 'Learn the SIE basics in six weeks', [[6, true, 0], [5, false, 0], [6, true, 0], [7, true, 1], [5, true, 0], [6, true, 0]], ['achieved', 'Through every module', 'Municipal securities', 'Flashcards on the go']],
        ];

        $goals = [];

        foreach ($season as [$person, $title, $history]) {
            $goal = $person->goals()->create(['title' => $title, 'category' => GoalCategory::Career, 'target_date' => $endsOn, 'target_result' => 'Ready for exam day', 'why' => 'Ready for the SIE, together.']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'plan_source' => 'coach', 'plan_accepted_at' => $startsOn->subDays(3), 'created_at' => $startsOn->subDays(3)])->saveQuietly();

            foreach ($history as $index => [$done, $milestone, $extra]) {
                $starts = $startsOn->addWeeks($index);
                $week = $goal->weeks()->create([
                    'position' => $index + 1,
                    'starts_on' => $starts,
                    'ends_on' => $starts->addDays(6),
                    'focus' => 'Study blocks and practice questions',
                    'milestone' => 'Score 75%+ on a timed practice set',
                ]);
                $this->playPastWeek($week, $this->tasksFor($week, self::SIE_WEEK, $index, 0, played: true), $done, $milestone, $extra);
            }

            app(PlanFloor::class)->setFloors($goal->fresh());
            $league->members()->create(['user_id' => $person->id, 'goal_id' => $goal->id]);
            $goals[$person->id] = $goal;
        }

        // Finished as seeded data: marked finished before the table settles, so the lazy season finale (awards, medals,
        // notifications) never runs for it, then its awards and medals written directly.
        $league->forceFill(['finalized_at' => now()])->save();
        (new LeagueTable($league->fresh()))->settle();
        $league = $league->fresh();
        (new SeasonRecap($league, new LeagueTable($league)))->seedFinale();
        $medals = app(MedalAwarder::class);
        $medals->seed($medals->desiredForLeague($league->fresh()) ?? []);

        // Goal Day answers, all shared with the league.
        $goalDay = app(GoalDay::class);

        foreach ($season as [$person, , , [$outcome, $actual, $hardest, $helped]]) {
            $goalDay->record($goals[$person->id], [
                'outcome' => $outcome,
                'actual_result' => $actual,
                'hardest_part' => $hardest,
                'helped_most' => $helped,
                'shared' => true,
            ]);
        }

        $goalDay->confirm($goals[$you->id]->fresh()->result, $andrew);

        foreach ($goals as $goal) {
            $medals->seed($medals->desiredForGoal($goal->fresh()));
        }

        // Highlights from that season, for the finale's reel (their photos double as proof).
        $daysAgo = (int) $endsOn->diffInDays($this->today) + 3;
        $mock = $this->post($you, $goals[$you->id], $league, 'Mock exam block', PostVisibility::League,
            'Full practice exam under a timer. Brain is fried, score is up.', 'Busch School, Maloney Hall', 'Mock exam', '78% · up from 64%', '#176B45', '#D4AC58', $daysAgo);
        $set = $this->post($you, $goals[$you->id], $league, 'Timed practice set', PostVisibility::League,
            'Practice sets finally clicking. Sunday study block with the crew.', null, 'Practice set', '38 of 45 · 42 minutes', '#1E2922', '#176B45', $daysAgo + 7);

        foreach ([[$mock, [$andrew, $jack, $steph]], [$set, [$andrew]]] as [$post, $fans]) {
            foreach ($fans as $fan) {
                $post->cheers()->create(['user_id' => $fan->id]);
            }
        }

        // League mates cheered the shared results.
        foreach ([[$you, [$andrew, $jack, $steph]], [$andrew, [$you, $jack]], [$jack, [$you, $andrew]], [$steph, [$you, $andrew]]] as [$author, $fans]) {
            $post = $author->highlights()->where('kind', HighlightKind::GoalDay)->first();

            foreach ($fans as $fan) {
                $post?->cheers()->create(['user_id' => $fan->id]);
            }
        }

        return $league;
    }

    /**
     * A demo person's own photo, from a file in resources/demo (location and camera data removed, like any upload).
     * Without the file they keep their initials.
     */
    private function photoFor(User $person, string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        $images = app(ProofImage::class);
        $bytes = (string) file_get_contents($file);
        $type = $images->detectType($bytes);

        if ($type === null) {
            return;
        }

        $clean = match ($type) {
            'jpg' => $images->stripJpeg($bytes),
            'png' => $images->stripPng($bytes),
            'webp' => $images->stripWebp($bytes),
        };
        $path = "avatars/demo-{$person->id}-".Str::lower(Str::random(8)).'.'.$type;

        if ($this->store(User::AVATAR_DISK, $path, $clean)) {
            $person->forceFill(['avatar_path' => $path, 'avatar_mime_type' => StoredPhoto::typeFor($path)])->save();
        }
    }

    /**
     * A demo person's profile: "Why I'm here", interests and a generated cover, so judges see a full profile.
     *
     * @param  list<string>  $interests
     */
    private function identity(User $person, string $why, array $interests, string $from, string $to): void
    {
        $person->forceFill(['why_here' => $why, 'interests' => $interests])->save();

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 500" width="1600" height="500">
              <defs><linearGradient id="c" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$from}"/><stop offset="1" stop-color="{$to}"/></linearGradient></defs>
              <rect width="1600" height="500" fill="url(#c)"/>
              <g stroke="#FAFAF5" stroke-opacity="0.14" stroke-width="5" fill="none">
                <path d="M0 360 C 400 280, 800 440, 1600 320"/><path d="M0 420 C 440 340, 840 500, 1600 380"/>
              </g>
              <line x1="1180" y1="80" x2="1180" y2="420" stroke="#FAFAF5" stroke-opacity="0.25" stroke-width="6"/>
              <circle cx="980" cy="250" r="16" fill="#FAFAF5" fill-opacity="0.35"/>
            </svg>
            SVG;

        $path = "covers/demo-{$person->id}-".Str::lower(Str::random(8)).'.svg';

        if ($this->store(User::AVATAR_DISK, $path, $svg)) {
            $person->forceFill(['cover_path' => $path, 'cover_mime_type' => 'image/svg+xml'])->save();
        }
    }

    /**
     * Explore for the demo: made-up people, three public GoalLeagues to join and four public posts, all inside this
     * demo world (a demo never sees real members, and real members never see this).
     */
    private function exploreExamples(CarbonImmutable $seasonStart): void
    {
        $ana = $this->person('Ana Ruiz', 'Ironman 70.3 in June. Swim is the hard part.');
        $ben = $this->person('Ben Okoro', 'Bar exam, July. Flashcards everywhere.');
        $chloe = $this->person('Chloe Park', 'Day 41 of 75 Hard. Still smiling.');
        $dev = $this->person('Dev Shah', 'Writing a thesis on urban heat islands.');
        $this->identity($ana, 'Swim, bike, run, repeat', ['Swimming', 'Cycling', 'Running'], '#176B45', '#0369A1');
        $this->identity($ben, 'Passing the bar on the first try', ['Exams', 'Career'], '#1E2922', '#176B45');
        $this->identity($chloe, '75 days, no excuses', ['Mindfulness', 'Lifting'], '#D4AC58', '#176B45');
        $this->identity($dev, 'Chapter a month', ['Writing', 'Studying'], '#176B45', '#D4AC58');

        $nextMonday = $this->monday->addWeek();
        $leagues = [
            [$ana, 'Spring Triathletes', 'Training for a spring tri. Every distance welcome.', GoalCategory::Fitness, $nextMonday, $nextMonday->addWeeks(12)->subDay(), [$ana, $chloe]],
            [$ben, 'Bar Prep Study Hall', 'Daily study blocks for the summer bar exam.', GoalCategory::Career, $seasonStart->addWeeks(2), $seasonStart->addWeeks(14)->subDay(), [$ben, $dev]],
            [$dev, 'Thesis Writing Club', 'A page a day. Accountability for grad students.', GoalCategory::Academic, $nextMonday, $nextMonday->addWeeks(10)->subDay(), [$dev]],
        ];

        foreach ($leagues as [$owner, $name, $description, $category, $starts, $ends, $members]) {
            $league = $this->league($name, $description, $category, $owner, $starts, $ends);
            $league->forceFill(['is_public' => true])->save();

            foreach ($members as $member) {
                $league->members()->create(['user_id' => $member->id]);
            }
        }

        // Public Challenges to find in the GoalLeagues tab and Explore (examples, in this demo world only).
        $publicChallenges = [
            [$ana, '10,000 steps a day', Challenge::TARGET, ['label' => 'Steps', 'threshold' => 10000, 'unit' => 'steps', 'per' => 'day', 'max' => 100000], $nextMonday, $nextMonday->addDays(29), [$ana, $chloe], 3],
            [$ben, 'Read 20 pages a day', Challenge::TARGET, ['label' => 'Pages', 'threshold' => 20, 'unit' => 'pages', 'per' => 'day', 'max' => 500], $this->today->subDays(6), $this->today->addDays(23), [$ben, $dev], 3],
            [$chloe, 'Workout 4 times a week', Challenge::WEEKLY, ['label' => 'Workout', 'times' => 4], $this->today->subDays(9), $this->today->addDays(20), [$chloe, $ana], 0],
        ];

        foreach ($publicChallenges as [$owner, $title, $type, $rules, $starts, $ends, $members, $misses]) {
            $challenge = new Challenge(['title' => $title, 'type' => $type, 'rules' => $rules, 'starts_on' => $starts, 'ends_on' => $ends, 'allowed_misses' => $misses, 'is_public' => true, 'target_days' => 1]);
            $challenge->creator()->associate($owner)->save();

            foreach ($members as $index => $member) {
                $challenge->members()->create(['user_id' => $member->id]);

                for ($day = $starts; $day->lt($this->today); $day = $day->addDay()) {
                    if (($day->day + $index) % 4 !== 0) {
                        $challenge->checkIns()->create(['user_id' => $member->id, 'checked_on' => $day, 'value' => $type === Challenge::TARGET ? $rules['threshold'] + 5 * $index : null]);
                    }
                }
            }
        }

        $posts = [
            [$ana, HighlightKind::GreatWeek, 'Ironman 70.3 in June', 'Past the line this GoalWeek', 'First open-water swim of the year. 1,500m and still smiling.', 'Swim', '1,500 m · open water', '#0369A1', '#176B45', 2],
            [$ben, HighlightKind::MilestoneHit, 'Pass the bar exam', 'Milestone: first full practice test', '200 questions, one sitting. 71% on the first try.', 'Practice test', '142 of 200', '#1E2922', '#D4AC58', 3],
            [$chloe, HighlightKind::GreatWeek, 'Finish 75 Hard', 'Smashed the line this GoalWeek', 'Two workouts, a gallon of water, ten pages. Day 41 done.', 'Day 41', '75 Hard', '#D4AC58', '#1E2922', 1],
            [$dev, HighlightKind::GoalCompleted, 'Finish the lit review', 'Completed this week', 'Chapter 2 is done: 34 pages, 112 sources. On to methods.', 'Chapter 2', '34 pages · done', '#176B45', '#43B96A', 4],
        ];

        foreach ($posts as [$author, $kind, $headline, $detail, $caption, $photoTitle, $photoSubtitle, $from, $to, $daysAgo]) {
            $post = $author->highlights()->create([
                'kind' => $kind,
                'visibility' => PostVisibility::Public,
                'headline' => $headline,
                'detail' => $detail,
                'caption' => $caption,
            ]);
            $this->photo($post, $photoTitle, $photoSubtitle, $from, $to);
            $post->forceFill(['created_at' => now()->subDays($daysAgo)->subHours(2)])->saveQuietly();
        }
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

        $expired->each(fn (User $user) => self::remove($user));

        return $expired->count();
    }

    /**
     * Delete one demo account and its world. Cleanup never blocks a demo: if its photo files can't be removed (photo
     * storage down, say), that's logged and the database rows go anyway.
     */
    public static function remove(User $user): void
    {
        JudgeTour::forget($user);

        try {
            $user->delete();
        } catch (Throwable $exception) {
            report($exception);
            User::withoutEvents(fn () => $user->delete());
        }
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
     * @param  list<array{0: string, 1: int, 2: int|null, 3: string|null, 4?: bool}>  $template
     * @param  list<array{0: int, 1: bool, 2: int}>  $history
     */
    private function goal(User $owner, string $title, GoalCategory $category, CarbonImmutable $target, array $template, array $history, int $missedThisWeek, string $focusWord, int $stepUp): Goal
    {
        $goal = $owner->goals()->create(['title' => $title, 'category' => $category, 'target_date' => $target, 'why' => 'Because doing it with friends makes it stick.']);
        $goal->forceFill([
            'roadmap_status' => RoadmapStatus::Ready,
            'plan_source' => 'coach',
            'plan_accepted_at' => now(),
            'roadmap_summary' => 'Build steadily week by week, with the hardest work in the middle and a lighter final week.',
            'created_at' => $this->monday->subWeeks(self::WEEKS_PLAYED)->subDays(3),
        ])->saveQuietly();

        foreach (range(-self::WEEKS_PLAYED, 2) as $offset) {
            $starts = $this->monday->addWeeks($offset);
            $week = $goal->weeks()->create([
                'position' => $offset + self::WEEKS_PLAYED + 1,
                'starts_on' => $starts,
                'ends_on' => $starts->addDays(6),
                'focus' => $focusWord === 'Run' ? 'Build the base: easy miles and a longer long run' : 'Steady SIE study blocks',
                'milestone' => $focusWord === 'Run' ? 'Finish a '.(10 + ($offset + self::WEEKS_PLAYED) * $stepUp).'-mile long run' : 'Score 75%+ on a timed practice set',
            ]);

            $tasks = $this->tasksFor($week, $template, $offset + self::WEEKS_PLAYED, $stepUp, played: $offset < 0);

            if ($offset < 0) {
                [$done, $milestone, $extra] = $history[$offset + self::WEEKS_PLAYED];
                $this->playPastWeek($week, $tasks, $done, $milestone, $extra);
            } elseif ($offset === 0) {
                $this->playThisWeek($tasks, $missedThisWeek, earlyBird: $missedThisWeek === 0);
            }
        }

        return $goal;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int|null, 3: string|null, 4?: bool}>  $template
     * @return list<RoadmapTask>
     */
    private function tasksFor(RoadmapWeek $week, array $template, int $weekIndex, int $stepUp, bool $played): array
    {
        $tasks = [];

        foreach ($template as $day => $row) {
            [$description, $points, $number, $unit] = $row;
            $number = ($row[4] ?? false) && ! $played ? null : $number;
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
    private function playPastWeek(RoadmapWeek $week, array $tasks, int $done, bool $milestone, int $extra): void
    {
        $checkedOff = array_slice($tasks, 0, $done);

        foreach ($checkedOff as $task) {
            $this->checkOff($task, CarbonImmutable::instance($task->planned_on)->setTime(rand(6, 20), rand(0, 59)));
        }

        if ($milestone) {
            $week->forceFill(['milestone_hit_at' => CarbonImmutable::instance($week->ends_on)->setTime(18, 0)])->save();
        }

        $this->beatTheNumbers($checkedOff, $extra);
    }

    /**
     * A big week the way players have one: more than planned on the tasks that track a number, which earns extra
     * points (8 of a planned 5 miles earns 1.6 times). The extra is spread across those tasks, up to 1.5 times the
     * plan each first, then up to twice.
     *
     * @param  list<RoadmapTask>  $tasks  Tasks already checked off.
     */
    private function beatTheNumbers(array $tasks, int $extra): void
    {
        $left = (float) $extra;

        foreach ([1.5, 2.0] as $multiple) {
            foreach ($tasks as $task) {
                if ($left <= 0 || ! $task->result_required) {
                    continue;
                }

                $earned = $task->points * $task->result_value / $task->result_target;
                $add = min($left, $task->points * $multiple - $earned);

                if ($add > 0) {
                    $task->forceFill(['result_value' => round($task->result_target * ($earned + $add) / $task->points, 2)])->saveQuietly();
                    $left -= $add;
                }
            }
        }

        if ($left > 0.001) {
            throw new LogicException("A demo week asks for {$extra} extra points, more than its numbers can give.");
        }
    }

    /**
     * This week so far: everything before today checked off except the ones missed. Early birds (league mates with a
     * perfect week so far) have also done today's task first thing, so even on a Monday the matchup has check-ins to
     * see and dispute. The visitor's today is always still to do.
     *
     * @param  list<RoadmapTask>  $tasks
     */
    private function playThisWeek(array $tasks, int $missed, bool $earlyBird = false): void
    {
        $earlier = array_values(array_filter($tasks, fn (RoadmapTask $task) => $task->planned_on->lt($this->today)));

        foreach ($earlier as $index => $task) {
            if ($index < $missed) {
                continue;
            }

            $this->checkOff($task, CarbonImmutable::instance($task->planned_on)->setTime(rand(6, 20), rand(0, 59)));
        }

        $morning = $this->today->setTime(6, 15)->min(CarbonImmutable::now()->subMinutes(20));

        if (! $earlyBird || $morning->lt($this->today)) {
            return;
        }

        foreach ($tasks as $task) {
            if ($task->planned_on->isSameDay($this->today)) {
                $this->checkOff($task, $morning);
            }
        }
    }

    private function checkOff(RoadmapTask $task, CarbonImmutable $when): void
    {
        $task->forceFill([
            'completed_at' => $when,
            'result_value' => $task->result_required ? $task->result_target : null,
        ])->saveQuietly();
    }

    /**
     * A minutes habit ("Stretch · 10+ minutes"), logged on weekdays so far.
     */
    private function habit(Goal $goal, string $title, int $timesPerWeek, int $minutes): void
    {
        $habit = $goal->habits()->create(['title' => $title, 'times_per_week' => $timesPerWeek, 'rule' => Habit::MINUTES, 'threshold' => $minutes]);
        $habit->forceFill(['created_at' => $goal->created_at])->saveQuietly();

        for ($day = $this->monday->subWeeks(self::WEEKS_PLAYED); $day->lt($this->today); $day = $day->addDay()) {
            if ($day->dayOfWeekIso <= 5) {
                $habit->logs()->create(['logged_on' => $day, 'value' => $minutes + ($day->day % 3) * 5, 'met' => true]);
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
     * Plan honesty, so it can be seen: intake answers behind the visitor's plans, every plan's minimum pace, the
     * visitor's next study week raised to its floor (with the note), Jack's lighter week for grading, and a friendly
     * dispute from the visitor's opponent on one of their check-ins.
     *
     * @param  array<string, array<int, Goal>>  $goals
     */
    private function planHonesty(array $goals, User $you, User $jack, League $running): void
    {
        foreach (['run' => GoalCategory::Fitness, 'study' => GoalCategory::Academic] as $key => $category) {
            $intake = $goals[$key][$you->id]->intake()->create();
            $intake->useQuestions(IntakeQuestions::fallback($category, $category === GoalCategory::Fitness), GoalIntake::SOURCE_FALLBACK);
            $intake->answer(IntakeQuestions::answers($intake->questions, $category === GoalCategory::Fitness
                ? ['experience' => ['value' => 'Some experience'], 'starting_point' => ['value' => '18 miles a week, longest run 10'], 'days' => ['value' => '5'], 'health' => ['value' => 'None']]
                : ['hours' => ['value' => 10], 'weakest' => ['value' => 'Options and municipal securities'], 'best_time' => ['value' => 'Early mornings']]));
        }

        $planFloor = app(PlanFloor::class);
        collect($goals)->flatten()->each(fn (Goal $goal) => $planFloor->setFloors($goal->fresh()));

        // Next week's study plan came back thin, so code raised it to the minimum pace.
        $nextStudy = $goals['study'][$you->id]->weeks()->whereDate('starts_on', $this->monday->addWeek())->sole();
        $nextStudy->tasks()->where('position', '>', 1)->get()->each->delete();
        $planFloor->raise($nextStudy);

        $jackWeek = $goals['study'][$jack->id]->weeks()->whereDate('starts_on', $this->monday)->sole();
        $jackWeek->forceFill(['lighter_at' => $this->monday->subDays(2), 'lighter_reason' => 'Grading week'])->save();

        // The visitor's opponent asks about a check-in that has no photo yet. The visitor can settle it.
        $runWeeks = $goals['run'][$you->id]->weeks()->with('tasks')->get();
        $task = $runWeeks->flatMap->tasks
            ->filter(fn (RoadmapTask $task) => $task->completed_at && ! $task->proof_path)
            ->sortByDesc('completed_at')
            ->first();
        $opponent = app(FairPlay::class)->opponentsOf($runWeeks->first(fn (RoadmapWeek $week) => $week->starts_on->isSameDay($this->monday)))->first();

        if ($task && $opponent) {
            $task->disputes()->create(['disputer_id' => $opponent->id, 'league_id' => $running->id, 'reason' => 'Strong week! Mind adding the route or your watch screen?']);
            $task->forceFill(['dispute_status' => RoadmapTask::DISPUTE_OPEN])->saveQuietly();
        }
    }

    /**
     * A drawn proof photo on one of last week's extra sessions, so that week's Smashed result counts in full.
     */
    private function proofLastWeek(Goal $goal, string $title, string $subtitle): void
    {
        $task = RoadmapTask::whereIn('roadmap_week_id', $goal->weeks()->whereDate('starts_on', $this->monday->subWeek())->select('id'))
            ->whereNotNull('completed_at')->where('result_required', true)->whereColumn('result_value', '>', 'result_target')
            ->orderByDesc('points')->firstOrFail();
        $path = "proofs/{$goal->id}/{$task->id}-demo.svg";

        if ($this->store(RoadmapTask::proofDisk(), $path, $this->drawing($title, $subtitle, '#1E2922', '#D4AC58'))) {
            $task->forceFill(['proof_path' => $path, 'proof_mime_type' => 'image/svg+xml', 'proof_uploaded_at' => $task->completed_at])->saveQuietly();
        }
    }

    /**
     * Save a drawn picture. If photo storage is down (S3 not set up yet, say), the demo still opens without it, and
     * the error is logged for the admins.
     */
    private function store(string $disk, string $path, string $contents): bool
    {
        try {
            return (bool) Storage::disk($disk)->put($path, $contents);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
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
     * Check-in posts with photos, cheers, and a comment or two.
     *
     * @param  array<string, array<int, Goal>>  $goals
     */
    private function posts(User $you, User $peter, User $tim, User $steph, User $andrew, User $jack, League $running, League $study, array $goals): void
    {
        $peterLong = $this->post($peter, $goals['run'][$peter->id], $running, 'Long run', PostVisibility::LeagueFriends,
            'Long run done along the river before the day started. Legs are toast, heart is full.', 'Rock Creek Park trail', 'Long run', '12.0 mi · 9:02 /mi', '#176B45', '#43B96A', 2);
        $yourTempo = $this->post($you, $goals['run'][$you->id], $running, 'Tempo run', PostVisibility::LeagueFriends,
            'First tempo run that felt smooth. The process is working.', 'Riverside path', 'Tempo run', '5.0 mi · 8:41 /mi', '#1E2922', '#176B45', 1);
        $andrewStudy = $this->post($andrew, $goals['study'][$andrew->id], $study, 'Practice questions, 40', PostVisibility::League,
            'Forty practice questions before the first meeting. Options are starting to make sense.', 'Busch School, Maloney Hall', 'Practice set', '40 of 40 done', '#D4AC58', '#176B45', 3);
        $jackCards = $this->post($jack, $goals['study'][$jack->id], $study, 'Flashcards', PostVisibility::League,
            'Flashcards between classes. Small reps add up.', null, 'Flashcards', '50 cards · 46 right', '#43B96A', '#1E2922', 1);

        foreach ([[$peterLong, [$you, $tim, $steph]], [$yourTempo, [$peter, $steph, $tim]], [$andrewStudy, [$you, $jack]], [$jackCards, [$andrew, $you]]] as [$post, $fans]) {
            foreach ($fans as $fan) {
                $post->cheers()->create(['user_id' => $fan->id]);
            }
        }

        $yourTempo->comments()->create(['user_id' => $tim->id, 'body' => 'That pace though. Teach me your ways.']);
        $peterLong->comments()->create(['user_id' => $steph->id, 'body' => 'Saturday we go together!']);
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

        // The photo doubles as the task's proof, so the post shows Verified (a task that already has a photo keeps it).
        if ($path && $task && $task->proof_path === null) {
            $task->forceFill(['proof_path' => $path, 'proof_mime_type' => 'image/svg+xml', 'proof_uploaded_at' => $post->created_at])->saveQuietly();
        }

        return $post;
    }

    /**
     * A post's photo, drawn.
     */
    private function photo(Highlight $highlight, string $title, string $subtitle, string $from, string $to): ?string
    {
        $path = "highlights/{$highlight->id}/demo.svg";

        if (! $this->store(Highlight::PHOTO_DISK, $path, $this->drawing($title, $subtitle, $from, $to))) {
            return null;
        }

        $highlight->forceFill(['photo_path' => $path, 'photo_mime_type' => 'image/svg+xml'])->saveQuietly();

        return $path;
    }

    /**
     * A simple drawn picture, so a demo never needs real photos.
     */
    private function drawing(string $title, string $subtitle, string $from, string $to): string
    {
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

        return $svg;
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
     * The visitor's Challenges: reading 10 pages a day (under way), Morning and Evening Prayer every day for a year
     * (under way), and Lent 2027, locked until Ash Wednesday with what to give up still to be decided.
     */
    private function challenges(User $you, User $peter, User $tim, User $steph, User $andrew, User $jack): void
    {
        // Read 10 pages a day: a Target, started 12 days ago, 30 days. Everyone misses the odd day; the visitor's
        // today is still to do.
        $readStart = $this->today->subDays(12);
        $read = new Challenge([
            'title' => 'Read 10 pages a day', 'description' => 'Any book counts. Ten pages, every day for a month.',
            'type' => Challenge::TARGET, 'rules' => ['label' => 'Pages', 'threshold' => 10, 'unit' => 'pages', 'per' => 'day', 'max' => 300],
            'starts_on' => $readStart, 'ends_on' => $readStart->addDays(29), 'mode' => Challenge::FLEXIBLE, 'allowed_misses' => 3,
            'target_days' => 27,
        ]);
        $read->creator()->associate($jack)->save();

        foreach ([$you, $jack, $andrew, $peter] as $index => $member) {
            $read->members()->create(['user_id' => $member->id]);

            for ($day = $readStart; $day->lt($this->today); $day = $day->addDay()) {
                if (($day->day + $index) % 7 !== 0) {
                    $read->checkIns()->create(['user_id' => $member->id, 'checked_on' => $day, 'value' => 10 + (($day->day + $index * 3) % 15)]);
                }
            }
        }

        // Morning and Evening Prayer, every day for a year: a Daily checklist, started three weeks ago. A day counts
        // when both are prayed.
        $prayerStart = $this->today->subDays(20);
        $prayer = new Challenge([
            'title' => 'Morning and Evening Prayer', 'description' => 'Every day for a year. Morning Prayer to start the day, Evening Prayer to close it.',
            'type' => Challenge::DAILY, 'rules' => ['items' => ['Morning Prayer', 'Evening Prayer']],
            'starts_on' => $prayerStart, 'ends_on' => $prayerStart->addDays(364), 'mode' => Challenge::FLEXIBLE, 'allowed_misses' => 10,
            'template' => 'reflection', 'target_days' => 355,
        ]);
        $prayer->creator()->associate($andrew)->save();

        foreach ([$you, $andrew, $peter, $tim, $steph] as $index => $member) {
            $prayer->members()->create(['user_id' => $member->id]);

            for ($day = $prayerStart; $day->lt($this->today); $day = $day->addDay()) {
                // Now and then only Morning Prayer made it in; never a whole day skipped.
                $rules = ($day->day + $index) % 9 === 0 ? [0] : [0, 1];
                $prayer->checkIns()->create(['user_id' => $member->id, 'checked_on' => $day, 'rules_done' => $rules]);
            }
        }

        // Lent 2027: Ash Wednesday to Holy Saturday, Sundays off, locked until it starts. What to give up: TBD.
        $year = Lent::yearFor($this->today);
        $lent = new Challenge([
            'title' => 'Lent '.$year, 'description' => 'Give something up together, Ash Wednesday to Holy Saturday. Sundays off.',
            'type' => Challenge::ABSTAIN, 'rules' => ['label' => 'TBD'],
            'starts_on' => Lent::ashWednesday($year), 'ends_on' => Lent::holySaturday($year), 'mode' => Challenge::FLEXIBLE, 'allowed_misses' => 0,
            'sundays_off' => true, 'template' => 'lent', 'target_days' => 40,
        ]);
        $lent->creator()->associate($you)->save();

        foreach ([$you, $peter, $andrew, $jack, $tim, $steph] as $member) {
            $lent->members()->create(['user_id' => $member->id]);
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

    private function inbox(User $you, User $peter, User $andrew, User $steph, GroupTask $groupTask): void
    {
        $yourPost = $you->highlights()->first();

        foreach ([
            [new FriendRequested($steph), 2],
            [new GroupTaskProposed($groupTask), 4],
            [new Nudged($andrew), 7],
            [new HighlightCheered($yourPost, $peter), 20],
        ] as [$notification, $hoursAgo]) {
            // Sent as of a few hours ago, so the inbox reads like a real day.
            $now = Carbon::getTestNow();
            Carbon::setTestNow(now()->subHours($hoursAgo));
            $you->notifyNow($notification, ['database']);
            Carbon::setTestNow($now);
        }
    }
}
