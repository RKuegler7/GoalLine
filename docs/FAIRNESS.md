# How GoalLine Keeps It Fair

GoalLine turns personal goals into a friendly competition. Each week, your progress on your own plan goes head-to-head with a league mate's. Any self-reported game invites one obvious question: what stops people from checking off work they didn't do? GoalLine doesn't try to police anyone. It makes cheating **visible, hard to do quietly, and not worth much**. Every rule below is built into the app's code, not left to an honor code.

## 1. You compete against your own plan, not raw numbers

Everyone is scored on the **percent of their own planned work** they completed that week, not on totals. Someone training for a marathon and someone studying for the CPA can play fairly in the same league. Results fall into four bands:

| Band | Share of planned points | Line stars |
|---|---|---|
| Short of the Line | under 90% | 0 |
| In Line | 90% to 100% | 1 |
| Past the Line | over 100%, under 150% | 2 |
| Smashed the Line | 150% or more | 3 |

A higher band wins the weekly matchup outright: 2 matchup stars to 0. When both sides land in the same band it's a photo finish: the higher credited percent of plan wins it for 1 star, compared exactly (100.4% beats 99.6%), and the other side gets 0. Only truly equal percents tie, and then both get 1. Credited means what counts right now: plan points held in a dispute are out, and a Smashed week still waiting on proof is capped at 149%, the top of Past the line. A week is worth 0 to 5 stars, and more stars always means the higher credited percent. A bye earns line stars only. The math is plain, tested code. **The AI never decides a score.**

## 2. You can't quietly pad your plan

- **The plan is set up front.** An AI coach drafts each roadmap from the goal, and the member looks it over and accepts it. Every change a member makes is logged, sent back to the coach for review, and kept in plan history.
- **Personal tasks are capped.** Members can add at most 3 of their own tasks a week, worth 1 to 5 points each. Together they can't add more than 15% of the coach-planned points. They can only be added until Tuesday night of that week, so nobody adds easy tasks after seeing how the week went. The whole league sees each one labeled "Added by [name]."
- **Targets are locked once the week starts.** A number goal like "run 5 miles" can only be set or changed before the week begins, so nobody lowers the bar mid-week.
- **Group tasks need the league's approval.** A task for everyone only goes on the plan if two thirds of the league vote yes within 24 hours. No single person can add it, and once it passes, nobody can edit or delete it.

## 3. You can't make the plan easy

This answers the obvious question: "If a player asks the AI for an easy plan, what stops them Smashing every week?"

- **A minimum pace, set by code.** Every GoalWeek has a minimum amount of planned work its deadline needs (the "plan floor"). GoalLine's code works it out from the kind of goal, the target date, and what the person said about their starting point and the hours they have. The AI never sets it. A plan can be harder than the minimum, never easier.
- **Easy plans get raised.** If the AI (or anything else) produces a week below the minimum, code adds "minimum-pace sessions" to make up the difference, with a plain note: "We raised this week to the minimum pace your deadline needs."
- **Edits can't sneak under it.** Asking the coach to refine the plan, restoring an older version, or removing or moving tasks can't take a week below its minimum.
- **Lighter weeks are allowed, in the open.** Life happens. Each player gets 2 lighter weeks a season, declared before the week starts with a one-line reason the whole league sees ("Finals week"). A lighter week still needs 70% of the minimum.
- **Plans lock when the week starts.** From Monday morning, planned work can be added to but not taken away, and a habit's target can't be lowered. Every change to future weeks is saved in plan history, which league mates can open.
- **Everyone can see the pace.** The league and the matchup show each player as "At minimum pace", "Above minimum pace" or "Lighter week", and any league mate can open a player's plan summary: planned work against the minimum for each week. Private notes stay private.
## 4. You can't rewrite the past

- **Check-ins are timestamped** the moment they happen, and the league sees those times in each week's matchup.
- **No backdating.** Daily habits can be logged for today or yesterday only. A week's check-ins lock one day after the week ends, and future weeks can't be checked off early.
- **Numbers have to make sense.** A logged result must fall within a sensible range for that task (by default, up to 3 times the plan). A number over 1.5 times the plan, or a new personal best, gets an "Are you sure?" before it's saved. Big days happen, but typos and wild claims get a second look.

## 5. Big claims need proof

- **"Show your work."** Any checked-off task can get a photo or screenshot. It earns a **Verified** check. Location and camera data are stripped from every photo before it's stored, to protect privacy.
- **A Smashed week needs backup.** Reaching 150% only counts as Smashed if at least one check-in that week has proof, or the matchup opponent confirms it. Otherwise it's credited as Past the Line and labeled "Needs proof."
- **The right people can see the proof.** A proof photo is visible to its owner, that week's opponent, and the league owner, and to the wider league or friends only if the member chooses to post it.
- **Anyone in the league can ask.** League mates can tap "Show your work?" on any check-in. It's a friendly request that notifies the member and never changes a score.
- **Anyone can question a check-in.** "Past needs no proof, so a player could claim it every week." Not quietly: the matchup opponent or the league owner can tap **Dispute** on any check-in, Past the Line included, with an optional reason. The check-in is labeled "Disputed" and its owner gets a friendly note.
- **Disputes get settled, not argued.** The owner adds a photo (even after the week closes) or a short explanation, the person who asked can withdraw, or the league owner rules "It counts" or "It doesn't count". The AI never decides.
- **Points wait for the answer.** If a dispute is still open when the week closes, that check-in's points are held out of the score. Once it's settled, the week re-scores on its own. Each player can have at most 3 open disputes per matchup each week, so nobody can bury an opponent in them.

## 6. The community keeps it honest

- **Everything is out in the open.** Each week's matchup shows both players' check-ins, times, numbers and Verified checks to the whole league.
- **Posts can be reported.** A reported post is hidden from the person who reported it, and the league owner sees the report and can take the post down.
- **Leagues are invite-only and small.** Members join by invite link, code, or a personal invite. A league works best with 12 or fewer people who know each other, and social accountability does a lot of the work.

## What GoalLine can't do

No app can prove someone really studied for an hour or ran those miles. GoalLine's approach is to make honest play the easy default. Plans can't drop below the pace the deadline needs, they lock when the week starts, the past can't be edited, big claims need evidence, any check-in can be questioned, and everything is visible to the people you're playing with. Faking a week means padding a plan the whole league can see, posting under your own name, and still only gaining about a point. The real reward is reaching the goal itself, and nobody gets there by faking check-ins.

**Get in line with your goals. We get more done together.**

