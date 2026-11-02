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
6. Habits ("Stretch 10 minutes", daily or X times a week, on chosen days) count in scoring.

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

**Matchups.** Each league week pairs members round-robin. The better band wins 2 match points, the same band draws 1 each, the worse band gets 0. A member with a bye plays the league median. A week is worth 0 to 5 points (match + line). Results go final one day after the week ends (`goalline:settle-leagues`, hourly).

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

1. Any member proposes a task for a coming GoalWeek: title, optional description, 1 to 10 points (default 5). The proposer's yes counts automatically.
2. Voting is open for **24 hours from the proposal**. It passes the moment yes votes reach two thirds of the members, rounded up. If not, there's no task. Members can change their vote while voting is open.
3. A passed task joins every member's GoalWeek for the target week (one week only) with the same points, and shows a group icon in matchups.
4. At most 2 passed group tasks per league per week. No duplicate titles in the same week. Members are notified when a task is proposed, passes or fails (`goalline:close-group-votes`, every 5 minutes).

**Personal tasks**

- Up to 3 a week, 1 to 5 points each, together no more than 15% of the coach-planned points.
- Only before the week starts or until Tuesday 11:59 p.m. of it.
- The league sees each one labeled "Added by [name]". They count in planned points.

## Invites and people search

- After setting a goal: "Play it with friends?" with Create a league (prefilled from the goal), Join a league (invite code) or Keep it solo.
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
| roadmap_weeks | GoalWeeks: dates, focus, milestone, milestone hit, Smashed confirmation |
| roadmap_tasks | Tasks: day, points, bonus, personal, group task link, number range and result, proof photo, check-off time |
| habits / habit_logs | Recurring habits (times a week, days) and the days they were logged |
| plan_materials | Uploaded plan files and the coach's digest of each |
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
