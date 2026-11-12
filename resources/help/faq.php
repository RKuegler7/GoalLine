<?php

/*
 * The Help center's FAQ: groups of short questions and plain answers, rendered by resources/views/help/panel.blade.php.
 * Edit the words here. Scoring numbers are read from the scoring code (ScoringRules, RoadmapWeek), never typed in, so
 * the answers can't drift from the real rules. Each item has a stable id, used to open Help at the right answer.
 */

use App\GoalWeekResult;
use App\MedalType;
use App\Models\GoalResult;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use App\Services\ScoringRules;

$rules = app(ScoringRules::class);
$bands = collect($rules->bands())->map(fn (array $band) => "{$band['label']} ({$band['range']}, {$band['points']} line ".str('star')->plural($band['points']).')')->implode('; ');
$outcomes = collect($rules->outcomes())->map(fn (array $outcome) => "{$outcome['label']} {$outcome['points']}")->implode(', ');
$range = $rules->weekRange();
$inLine = (int) round(GoalWeekResult::IN_LINE_AT * 100);
$smashed = (int) round(GoalWeekResult::SMASHED_AT * 100);
$lighter = RoadmapWeek::LIGHTER_WEEKS_PER_SEASON;
$lighterShare = (int) round(RoadmapWeek::LIGHTER_SHARE * 100);
$grace = RoadmapWeek::CHECK_IN_GRACE_DAYS;

return [
    [
        'id' => 'getting-started',
        'title' => 'Getting started',
        'items' => [
            ['id' => 'what-is-goalline', 'q' => 'What is GoalLine?', 'a' => 'A league for the goals you actually care about. You set a goal and a date, answer a few questions, and get a plan of weekly GoalWeeks. You check in as you go, and each week you can play a friendly matchup with a friend, scored against your own plans.'],
            ['id' => 'how-leagues-work', 'q' => 'How does a GoalLeague work?', 'a' => 'Everyone brings their own goal. Each week you face one league mate, and whoever finishes a bigger share of their own plan wins. Different goals play fine together because nobody is compared on raw effort, only on their own plan.'],
            ['id' => 'join', 'q' => 'How do I join with a code or a link?', 'a' => 'Open the invite link, or go to GoalLeagues and type the code. You can start from the league goal with one tap ("Use this goal"), make it your own, or bring a different goal. Then a few questions, and your plan.'],
            ['id' => 'late-join', 'q' => 'Can I join after the season starts?', 'a' => 'Yes, until the season ends. You play from the current week on (your first week is a bye: line stars only), and the weeks before you joined never count against you.'],
            ['id' => 'solo', 'q' => 'Can I go solo against the line?', 'a' => 'Yes. Without a league you still get your plan, Today\'s Gameplan, weekly results and badges, playing against the line. You can start or join a league any time.'],
        ],
    ],
    [
        'id' => 'plans',
        'title' => 'Your plan',
        'items' => [
            ['id' => 'how-plans-work', 'q' => 'How do the questions and the AI plan work?', 'a' => 'The AI coach asks a few questions written for your goal, then drafts your GoalWeeks: a focus, a milestone and tasks for each. Nothing is final until you look it over and tap Accept.'],
            ['id' => 'refine', 'q' => 'Can I change my plan?', 'a' => 'Yes, for GoalWeeks that haven\'t started. Ask the coach in your own words with "Refine your plan" and preview the change, or edit tasks by hand. Every version is kept in plan history.'],
            ['id' => 'plan-floor', 'q' => 'What is the minimum pace (plan floor)?', 'a' => 'Code, not the AI, works out the least each week needs so your deadline stays realistic. If a week falls short, it\'s raised to the minimum pace and you\'re told. A plan can always be harder, never lighter than that.'],
            ['id' => 'lighter-weeks', 'q' => 'What are lighter weeks?', 'a' => "Life happens. You can declare up to {$lighter} lighter weeks a season, which can go down to {$lighterShare}% of the minimum pace. Your league can see that it was a lighter week."],
            ['id' => 'plan-lock', 'q' => 'When does a week\'s plan lock?', 'a' => 'When the GoalWeek starts: 12:00 a.m. on its first day, on your own time zone. From then on, that week is what you\'re scored on, so nobody can shrink a week they\'re in the middle of.'],
        ],
    ],
    [
        'id' => 'checking-in',
        'title' => 'Checking in',
        'items' => [
            ['id' => 'when-check-in', 'q' => 'When can I check in?', 'a' => "For today or yesterday, from kickoff on. A GoalLeague's season starts Monday at 12:00 a.m. Eastern (or the Monday of the week you joined, if you joined late); a solo goal starts on its plan's first day. Before kickoff you can see and edit your whole plan, but check-ins open at kickoff, and each task opens on its own day. Check-ins close {$grace} day after a GoalWeek ends, then its result is final."],
            ['id' => 'results', 'q' => 'What about tasks with a number?', 'a' => 'Some tasks ask for a result, like miles or pages. Doing more than planned counts extra, and that\'s one way past the line.'],
            ['id' => 'show-your-work', 'q' => 'What is "Show your work"?', 'a' => 'Add a photo to a check-in to show the work behind it. You choose who sees it. A check-in with a photo gets a Verified check.'],
        ],
    ],
    [
        'id' => 'scoring',
        'title' => 'Scoring',
        'items' => [
            ['id' => 'bands', 'q' => 'How do bands and line stars work?', 'a' => "Your percent of your own plan sets your band: {$bands}."],
            ['id' => 'reading-matchup', 'q' => 'How do I read my matchup?', 'a' => 'The scoreboard shows each player\'s percent of plan, band and stars if the week ended now. On the line below it, each player\'s puck sits at their percent, and a dashed ring shows where their current pace is heading. Tap a puck for details.'],
            ['id' => 'match-points', 'q' => 'How do matchup stars and a Photo finish work?', 'a' => "A higher band wins the matchup. In the same band it's a Photo finish: the higher credited percent of plan takes it, compared exactly, and only truly equal percents tie. Matchup stars: {$outcomes}. A week is worth {$range['min']} to {$range['max']} stars, and the player with more stars wins the matchup; equal stars is a draw. A bye earns line stars only."],
            ['id' => 'weeks-on-the-line', 'q' => 'What are weeks on the line?', 'a' => "Finished GoalWeeks in a row at In line or better ({$inLine}% of plan or more). The current week counts once it's final."],
            ['id' => 'table', 'q' => 'How is the league table sorted?', 'a' => implode(', ', array_map('lcfirst', $rules->tableOrder())).'.'],
        ],
    ],
    [
        'id' => 'fairness',
        'title' => 'Fairness',
        'items' => [
            ['id' => 'smashed-proof', 'q' => 'Why does Smashed need proof?', 'a' => "A {$smashed}%+ week is big. It counts as Smashed once a check-in that week has a photo or your opponent taps Confirm. Until then it counts as Past the line."],
            ['id' => 'disputes', 'q' => 'How do disputes work?', 'a' => 'Your opponent or the league owner can ask about a check-in. You answer with a photo or a note, or they withdraw it, or the owner decides. People settle disputes, never the AI.'],
            ['id' => 'held-points', 'q' => 'What are held points?', 'a' => 'Points from a check-in under an open dispute after the week closed. They\'re left out until it\'s settled, then the week re-scores on its own.'],
            ['id' => 'ai-role', 'q' => 'What does the AI do, and never do?', 'a' => 'It writes your intake questions, drafts and refines plans, gives weekly tips and short recap summaries. It never scores a week, decides a matchup or a dispute, or gives medical advice. Scoring is plain, tested code.'],
        ],
    ],
    [
        'id' => 'goal-day',
        'title' => 'Goal Day and medals',
        'items' => [
            ['id' => 'goal-day', 'q' => 'What is Goal Day?', 'a' => 'The day your goal was due. GoalLine asks "Did you reach your goal?" (I did it, Partly, or I came up short), with an optional result, a few words on what was hardest and what helped, and optional proof. It waits on your Clubhouse and in History until you answer, and you can change it for '.GoalResult::EDITABLE_DAYS.' days. Finished sooner? Tap "I finished early" on your goal.'],
            ['id' => 'goal-day-scoring', 'q' => 'Does my Goal Day answer change my stars?', 'a' => 'No. It\'s self-reported, so it never changes stars, standings or the table. The table measures your weeks; Goal Day is about you. Finishing last while hitting your goal is still a win.'],
            ['id' => 'goal-day-verified', 'q' => 'What does Verified mean?', 'a' => 'An achieved goal is Verified when you add proof (a finish photo, score report or certificate, cleaned of location data) or a league mate confirms it with one tap. You can\'t confirm your own.'],
            ['id' => 'goal-day-privacy', 'q' => 'Who sees my Goal Day result?', 'a' => '"Share with my league" is on by default: your result shows in your league\'s Goal Day news, where league mates can cheer it. Choose "Keep private" and only you see it. Nothing is ever posted for you; the season card is shared only if you share it.'],
            ['id' => 'season-finale', 'q' => 'What is the season finale?', 'a' => 'A few cards after Goal Day: your result, your table finish next to your goal, a week-by-week replay, your highlights, league news, medals, a card to share, and ideas for your next goal. Skip any of it, and replay it from History or your goal.'],
            ['id' => 'medals', 'q' => 'How do medals work?', 'a' => 'Code awards medals from settled results once a season is complete, and checks them again only if a dispute changes a week. They never change stars. '.collect(MedalType::cases())->map(fn (MedalType $medal) => $medal->label().': '.$medal->rule())->implode(' ')],
            ['id' => 'next-goal', 'q' => 'What happens after Goal Day?', 'a' => 'Pick a next goal (up to three ideas; after a goal you reached, the first is a step up, never a step down), run it back, start a new season with the same friends, join a league, or take a break.'],
        ],
    ],
    [
        'id' => 'friends',
        'title' => 'Friends and leagues',
        'items' => [
            ['id' => 'inviting', 'q' => 'How do I invite people?', 'a' => 'Share the league link or code, or invite by email or username from the league page. Links can expire or have a use limit, and the owner can switch one off.'],
            ['id' => 'team-names', 'q' => 'Can I have a team name?', 'a' => 'Yes, one per league, up to '.LeagueMember::MAX_TEAM_NAME.' characters. Set it on the league page. It shows on the scoreboard and standings.'],
            ['id' => 'owner', 'q' => 'What does the league owner do?', 'a' => 'Sets the season and the league goal, invites people, can remind members to set their goal, removes members if needed, and makes the final call on disputes. A league can have up to '.League::MAX_MEMBERS.' members.'],
        ],
    ],
    [
        'id' => 'privacy',
        'title' => 'Privacy and safety',
        'items' => [
            ['id' => 'photo-location', 'q' => 'Are my photos cleaned of location data?', 'a' => '"Show your work" photos are re-saved without their hidden data, including GPS location, before they\'re stored. The optional "Where?" label is only what you type.'],
            ['id' => 'who-sees-posts', 'q' => 'Who can see my posts?', 'a' => 'You choose for each post: proof only, your league, or your league and friends. Your plan details, answers and notes are never shared.'],
            ['id' => 'reporting', 'q' => 'How do I report something?', 'a' => 'Use Report on a post. It\'s hidden for you right away, and the league owner can take a look.'],
            ['id' => 'not-medical', 'q' => 'Is my plan medical advice?', 'a' => 'No. Plans are general guidance. Physical goals get careful progression and rest days. Check with a doctor if you have health concerns.'],
        ],
    ],
    [
        'id' => 'account',
        'title' => 'Account',
        'items' => [
            ['id' => 'verify-email', 'q' => 'Why verify my email?', 'a' => 'It keeps leagues real. Verify to join leagues, post and add friends. The link works for 48 hours, and you can ask for a new one.'],
            ['id' => 'notifications', 'q' => 'How do I change notifications?', 'a' => 'Open your photo menu, then "Notifications & calendar". You can turn each kind of reminder on or off and add your plan to your calendar.'],
            ['id' => 'settings', 'q' => 'How do I change my profile or settings?', 'a' => 'Open your photo menu, then "Profile settings" for your name, photo, time zone and who can see your locker.'],
            ['id' => 'delete-data', 'q' => 'Can I delete my data?', 'a' => 'Yes. Profile settings has a "Delete account" option that removes your account and your data.'],
        ],
    ],
];
