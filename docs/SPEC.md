# GoalLine Spec

The rules GoalLine runs on, as built. When code and this file disagree, the tests decide, and this file gets fixed.

**Mission:** Get in line with your goals. We get more done together.

**The idea:** GoalLine is about the process, not just the end result. The goal is what the daily process builds. Doing the right things every day, consistently, with friends, is what gets you there. The voice is always encouraging: no shaming, no roasts, never red warning badges for a missed day.

## Names in the app

| Word | Meaning |
|---|---|
| Clubhouse | Home: today's gameplan, your goals, your matchups, the Friends feed |
| GoalLeagues | Leagues of friends playing their own goals head to head each week |
| Highlights | The social feed: check-in posts, finished goals, cheers, comments |
| My Locker | Your profile: photo, stats, form guide, trophy case |
| GoalWeek | One week of a goal's roadmap, Monday 12:00 a.m. to Sunday 11:59 p.m. |
| Cheers | The one-tap reaction on posts (never "kudos" or likes) |
| Coach | The AI that builds and reviews roadmaps. It never decides a score |

Weeks follow each member's own time zone (default America/New_York).

## Goals and roadmaps

1. A member names a goal, a category, a target date and why it matters, and can upload plan files (PDF or image) for the coach to read once.
2. **Intake questions.** Before planning, the coach writes 5 to 8 questions specific to the goal (starting point, time available, constraints, obstacles). Question types are pick one, pick any, a number with a unit, a few words, and a 1-to-5 scale. Each question has "I'm not sure" and Skip, and the member can skip them all. The questions show one at a time with a progress bar.
   - The coach's questions are checked before use. Unknown types, choice questions with fewer than 2 options, and empty text are dropped. Text is stripped of markup and capped. Fewer than 5 usable questions means the built-in set is used.
   - Built-in questions for each category are used right away when there's no API key or an AI cap is reached, when the coach fails, or after 45 seconds of waiting. Failures are logged and never shown to the member.
   - Physical goals (the Fitness category, or a title like "run", "marathon" or "lift") always get one question about injuries or health. Their plans use conservative progression (beginners add no more than about 10% weekly volume), include rest days, and start more cautiously after a recent injury, saying why. The goal page shows: "GoalLine plans are general guidance, not medical advice. Check with a doctor if you have health concerns."
   - The answers go to the coach with every plan, review and weekly tip. The goal page shows "Your plan is built from" with the key answers to the owner only. "Edit answers" re-plans only the GoalWeeks that haven't started, so this week and its check-ins stay as they are.
3. The coach (Claude, `ANTHROPIC_MODEL`) builds a roadmap of GoalWeeks. Each week has a focus, a milestone and daily tasks with points.
4. For the first 7 days a goal can be removed outright. After that it's locked in and can be retired, never silently deleted.
5. Every edit to a roadmap is logged and sent back to the coach for a check. The member can take the coach's fix or keep their plan.
6. **Refine your plan.** On the goal page, the member tells the coach what to change in their own words (with idea chips like "Make weeks lighter, I have finals"). The coach rewrites only the GoalWeeks that can change and explains why. A preview shows every change week by week (tasks added, removed and moved, focus and milestone, planned points before and after), and nothing is saved until they tap Accept. Discard leaves the plan as it was. Malformed coach output is rejected and shown as a friendly "try again".
7. **What can change.** Only GoalWeeks that haven't started: a week's plan locks when it starts (see Plan honesty). Past check-ins, completed tasks, group tasks and personal tasks are never touched, so scores for finished weeks can't change. New tasks are worth 1 point.
8. **Edit by hand.** Reword or remove tasks, reorder them, move the coach's tasks between upcoming GoalWeeks, and edit an upcoming GoalWeek's focus and milestone, all within the same rules.
9. **Plan history.** Every built plan, refinement, restore and edit is saved with a snapshot and a short summary (edits within 30 minutes share one entry). An earlier version can be restored for the upcoming GoalWeeks, after the same preview.
10. **Habits** are objective recurring tasks that count in scoring.
    - **Cadence:** every day, specific days (like Mon, Wed, Fri, with the other days shown as rest days), or X times a week (any days, shown as progress like "3 of 4 this week", where a day off isn't a miss). The weekly target sets the planned points.
    - **What counts:** "Did it or not", a number with a threshold (like "Run · 2+ miles"), or minutes with a threshold ("Study · 45+ minutes"). The rule shows on the habit. A number under the threshold is saved but doesn't count, and it says so calmly. Going over still counts as one day. Whether a day counted is stored when it's logged, so changing the rule later never rewrites past days. Existing habits are check-offs and score exactly as before.
11. **Today** (`/today`) shows how many days you've been on the line this week, everything due today with one-tap check-offs and quick number entry, and "Mark all done" for simple check-offs (anything with a number needs its number). It also has a week grid: days across, habits down, with today and yesterday tappable, and states for done, under the rule, due, rest day and upcoming. Missed days stay neutral, never red. Tap targets are large on phones. Daily reminders at a time each member picks (with an opt-out) come from the existing reminder settings.

## Scoring

All math is plain, tested PHP (`GoalWeekResult`, `MatchupOutcome`, `RoadmapWeek`, `LeagueTable`). The AI never touches a score.

**Points in a GoalWeek**

- Each task has points (default 1). A task tracking a number earns points × result ÷ target, so 7 of a planned 5 miles earns 1.4×.
- Each planned habit day is 1 point.
- The milestone is worth 2 points.
- **Planned points** = non-bonus tasks + habit targets + milestone.
- **Earned points** = every checked-off task (bonus tasks too) + every habit day logged + the milestone if hit.
- **Share** = earned ÷ planned.

**Bands**

| Band | Share | Line points | Look |
|---|---|---|---|
| Short of the Line | under 90% | 0 | red |
| In Line | 90% to 100% | 1 | green ("close call" under 95%) |
| Past the Line | over 100%, under 150% | 2 | golden shine |
| Smashed the Line | 150% and up, uncapped | 3 | blue diamond |

**Smashed needs backup.** A week at 150% or more counts as Smashed only when at least one check-in that week has proof, or the matchup opponent taps Confirm. Until then it's credited as Past the Line and labeled "Needs proof to count as Smashed". It re-scores on its own when proof or the confirmation arrives.

**Matchups.** Each league week pairs members round-robin. The "GoalWeek X Matchup" page shows every matchup as a card to swipe through (the viewer's first): each side's share of their plan on a line meter marked at 90%, 100% and 150%, their bands, who leads, the points each would take if the week ended now, and the points each needs for the next band. The better band wins 2 match points, the same band draws 1 each, the worse band gets 0. A member with a bye plays the league median. A week is worth 0 to 5 points (match + line). Results go final one day after the week ends (`goalline:settle-leagues`, hourly).

## Fair play

| Rule | Detail |
|---|---|
| Today or yesterday only | Check-ins and habit logs are allowed for today or yesterday. A week locks one day after it ends. Future weeks can't be checked off early. |
| Numbers make sense | A tracked number must fall in its range (0 to 3× the plan by default). Over 1.5× the plan, or a new personal best, asks "Are you sure?" once. |
| Targets lock | Number targets can only be set or changed before the week starts. |
| Show your work | Any checked-off task can get a photo (JPG, PNG or WebP, max 5 MB). It's re-encoded on the server, which strips location and camera data, and earns a Verified check. |
| Who sees proof | Always the owner, that week's opponent and the league owner, whatever the post's visibility. |
| Ask for proof | Any league mate can tap "Show your work" on a check-in. It notifies the owner (in-app, plus email if mail is set up) and never changes a score. |
| Open matchups | The weekly matchup shows both sides' check-ins as a timeline with times, numbers and Verified checks. |

The plain-English version for readers outside the code is [docs/FAIRNESS.md](FAIRNESS.md).

## Plan honesty

The question this answers: "If a player asks the AI for an easy plan, what stops them Smashing every week?"

- **Plan floor.** Code, never the AI, sets each GoalWeek's minimum planned points from the goal's category, the deadline and the intake answers (`App\Services\PlanFloor`). A point is about one focused session: a task is 1, each habit day 1, the milestone 2. Per full week:
  - base: Academic 6, Career 5, Fitness 5, Creative 5, Personal 4, Other 4;
  - × deadline pressure: a plan of 4 weeks or less ×1.25, up to 12 weeks ×1.0, longer ×0.9;
  - × progression for physical goals, from ×0.8 to ×1.1 across the plan, and a ×0.8 taper in the last week of a fitness or academic goal;
  - × starting point (fitness: brand new ×0.8, competitive ×1.15), and ×0.85 for a reported injury or health condition;
  - capped at 80% of the hours a week the person said they have, scaled by the stretch's length, and never below 3 (2 for a short week).
- **Raised by code.** Every plan the coach builds or refreshes is checked. A week below its floor gets "minimum-pace sessions" worth the missing points and the note "We raised this week to the minimum pace your deadline needs." The coach is told each week's minimum when it plans.
- **Nothing goes below it.** "Refine your plan", restores, removing or moving tasks, and the coach's one-click fix can't take a week below its floor.
- **Lighter weeks.** Up to 2 per season, declared before the week starts with a one-line reason the league sees. The minimum drops to 70% of the floor. They can be undone before the week starts.
- **Plan lock.** A GoalWeek's plan locks when it starts (Monday 12:00 a.m. on the member's clock). After that, personal tasks can still be added within their limits, but planned work can't be removed or re-planned, and a habit's target can't be lowered. Every change to future weeks is in plan history, which league mates can see.
- **Transparency.** The league and matchup views show each member's plan status: "At minimum pace", "Above minimum pace" or "Lighter week". Any league member can open a mate's plan summary (planned workload against the minimum for each week, and plan history), never their private notes, intake answers or reflections.
- **When the AI fails.** If the coach errors, times out, returns invalid output or hits an AI cap, a starter plan from the goal's category is saved instead, raised to the floor, and the person is told plainly. A new plan is a draft until the person taps "Accept this plan", and it can be rebuilt from scratch until then. Nothing from the AI is saved without validation.

## Disputes

- The matchup opponent and the league owner can tap "Dispute" on any check-in (including ones that only reach Past the Line), with an optional reason. It's labeled "Disputed" and the owner is notified (in-app, plus email if mail is set up). League owners always see disputes.
- **Settling:** the owner adds proof (allowed even after the week closes) or a short explanation, the disputer withdraws it, or the league owner rules "It counts" or "It doesn't count". The league owner can't rule on their own check-in. The AI never decides a dispute.
- **Held points:** if a dispute is still open when the GoalWeek closes, that check-in's points are held out of the score. Once it's settled, the week re-scores on its own, stored league results included. A check-in ruled out earns nothing.
- **Limits:** at most 3 open disputes per player per matchup per GoalWeek, and one per check-in per person.
- **Recap:** "Your process this week" lists plan status, disputes opened and settled, and any held points, in calm, non-accusatory words.

## Posts, feeds and friends

- After adding a photo to a check-in, the member picks who sees it, writes a caption (up to 280 characters) and can add a free-text "Where?" (up to 60 characters, never GPS, with the hint "Crop out maps that show your home").
- **Visibility:** Proof only (opponent and league owner, no feed), League (default), or League + friends (also the member's accepted friends).
- **Feeds:** a feed per league and a Friends feed on the Clubhouse, newest first, paginated.
- **Post cards** look like Instagram: avatar, name, league and goal, the photo, cheers and Verified, caption, task and time.
- **Friends:** requests by username or exact email: send, accept, decline, remove.
- **Safety:** photos are served only through authorized routes (policies check every post, image and feed). "Report" hides a post from the reporter and tells the league owner.
- **Suggest a caption** drafts one short, warm caption with the coach. The member edits it. It never posts on its own, and it's hidden when there's no Anthropic key.

## Group tasks and personal tasks

**Group tasks** (leagues with 2 or more members)

1. Any member proposes a task for a coming GoalWeek: title, optional description, and a size measured against a regular roadmap task (1 point): Quick (1 point, like one regular task), Solid (2, like two tasks or one long session) or Big (3, a real stretch). The form shows each size as a share of a typical GoalWeek in that league. The proposer's yes counts automatically.
2. Voting is open for **24 hours from the proposal**. It passes the moment yes votes reach two thirds of the members, rounded up. If not, there's no task. Members can change their vote while voting is open.
3. A passed task joins every member's GoalWeek for the target week (one week only) with the same points, and shows a group icon in matchups.
4. At most 2 passed group tasks per league per week. No duplicate titles in the same week. Members are notified when a task is proposed, passes or fails (`goalline:close-group-votes`, every 5 minutes).

**Personal tasks**

- Up to 3 a week, 1 to 5 points each, together no more than 15% of the coach-planned points.
- Only before the week starts or until Tuesday 11:59 p.m. of it.
- The league sees each one labeled "Added by [name]". They count in planned points.

## Invites and people search

- After setting a goal: "Play it with friends?" with Create a league (prefilled from the goal), Join a league (invite code) or Keep it solo.
- Starting or joining a league, a member picks one of their goals or types a new one right there. On the league page, "Your goal in this league" lets them edit it, switch to another goal, or set a new one for that league. Weeks already final keep their results.
- Every league has an Invite section: a share link and short code with copy and share buttons, invites by email or username, and a join page that works for signed-out visitors too.
- Invite tokens are random, can be revoked and regenerated by the league owner, expire after 14 days by default (1, 7, 14, 30 days or never), and can have a max-uses cap.
- Leagues with fewer than 4 members show an "Invite friends" button.
- People search (Highlights, friends page, invite flow) matches name or username, or an exact email. Results show only avatar, name and username, never emails. It's rate limited to 30 searches a minute. "Let people find me by name" (on by default) can be turned off, so only an exact username or email finds you.

## Demo

`/demo` ("Try the demo") signs a visitor into a private pretend world as John Cardinal: 7 friends, two GoalLeagues mid-season (Marathon by March and 4.0 This Fall), a live matchup, posts with cheers, a group task waiting on the visitor's vote, personal tasks, a challenge, league chat and an inbox. Last week includes a Smashed week with proof (Red) and one credited as Past for lack of proof (Kate). Demo worlds can't email anyone or reach real members, get a small AI allowance, and are deleted after `DEMO_TTL_HOURS`. `php artisan db:seed` builds the same world locally (see the README).

## AI limits

Each member gets up to 20 coach requests a day. The whole app is capped at $2 a day and $20 a month, with an admin email at 80%. When a cap is hit, AI features pause and everything else keeps working.

## Scheduled jobs

| Command | When | What |
|---|---|---|
| `goalline:send-reminders` | every 15 minutes | Morning and evening reminders on each member's clock |
| `goalline:settle-leagues` | hourly | Final league results, recaps and awards |
| `goalline:close-group-votes` | every 5 minutes | Closes group task votes that ran out of time |
| prune demos | hourly | Deletes expired demo worlds |

## Tables

| Table | What it holds |
|---|---|
| users | Accounts: profile, username, time zone, visibility, discoverable, notification settings, demo sandbox |
| goals | Goals: title, category, target date, why, status, roadmap and coach check state, completion |
| roadmap_weeks | GoalWeeks: dates, focus, milestone, milestone hit, Smashed confirmation, plan floor and note, lighter week and reason |
| roadmap_tasks | Tasks: day, points, bonus, personal, group task link, number range and result, proof photo, check-off time |
| habits / habit_logs | Recurring habits (cadence, days, rule, threshold, unit) and each day logged (the number, and whether it met the rule) |
| plan_materials | Uploaded plan files and the coach's digest of each |
| plan_versions | Plan history: a snapshot of the roadmap at each build, refinement, restore and edit |
| disputes | Disputes on check-ins: who, why, status, and how each was settled |
| plan_refinements | Proposed plan changes from the coach or a restore, waiting for the member's preview |
| goal_intakes | The intake questions for a goal (coach-written or built-in) and the member's answers |
| week_reviews | End-of-week reflections and the coach's tip |
| leagues | GoalLeagues: name, category, season dates, invite code, owner |
| league_members | Who plays which goal in a league |
| league_results | Each member's weekly band, line points, opponent and match points |
| league_messages | League chat, including system posts |
| league_invites | Invite links, email and username invites: token, expiry, max uses, revoked |
| group_tasks / group_task_votes | Proposed league tasks and members' votes |
| highlights | Posts: check-ins with photos, finished goals, visibility, caption, location |
| cheers / highlight_comments | Reactions and comments on posts |
| post_reports | Reported posts for the league owner |
| proof_requests | "Show your work" requests |
| friendships | Friend requests and accepted friends |
| challenges / challenge_members / challenge_check_ins | Day-streak challenges between friends |
| nudges | Friendly nudges between friends and league mates |
| user_badges | Badges and season awards |
| notifications | The Inbox |
| push_subscriptions | Phones signed up for push notifications |
| sent_reminders | Reminders already sent, so none repeat |
| invites | Private beta invite codes |
| ai_requests | Coach calls with token counts and cost |
| usage_events / feedback | Beta usage tracking and in-app feedback |
