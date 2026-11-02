# GoalLine: Fair Play, Proof Posts, Friends and Group Tasks

Read docs/SPEC.md and the existing code first. The app already has accounts, goals, check-ins, streaks, nudges and more. Reuse the existing tables, models and naming wherever they already cover something. Add migrations only for what is missing. Do not duplicate features or break existing ones. Keep Blade plus light JS (no new heavy frameworks), mobile-first, in the GoalLine brand: Club Green #176B45, Grass Green #43B96A, Fresh Mint #E5F3E8, Warm Ivory #FAFAF5, Soft Ink #1E2922, Championship Gold #D4AC58, Space Grotesk for headings, Public Sans for body. Keep all scoring math in plain tested PHP services, never in the AI. Use app timezone America/New_York for week boundaries (weeks run Monday 12:00 a.m. to Sunday 11:59 p.m.).

Work in the phases below, in order. After each phase: run the tests, run the app locally, commit with the message given, push, then STOP and give me a short plain-English report (what changed, any new environment variables, how I test it by hand, what is left). Wait for me to say "continue" before the next phase.

## The idea behind all of it (make this a highlight in the product copy)

GoalLine is about the process, not just the end product. The goal is what the daily process builds. Doing the right things every day, consistently, over time, is what gets you there, and doing it with friends makes it stick. Put this in the product voice in a few places, without overdoing it:
- Dashboard banner or header line: "Trust the process. Show up today."
- Weekly recap heading: "Your process this week".
- Empty states and encouragement notes lean on consistency ("One more day on the line.").
- Posts and proof are framed as "showing the process", not bragging.
Tone is always encouraging. No shaming, no roasts, never red warning badges for a missed day.

Scoring model (already decided, keep consistent): each week your percent of planned points sets your band. Short of the Line 0-94% (red, 0 line points), In Line 95-104% (green, 1), Past the Line 105-149% (golden shine, 2), Smashed the Line 150%+ uncapped (blue diamond, 3). Match points vs your weekly opponent: better band wins 2, same band draws 1, worse band 0. Weekly points = match points + line points, 0 to 5. If scoring code exists already, keep it and only adjust for the rules below.

---

## PHASE A: Fair play core and "Show your work" proof

1. Plausibility checks. Tasks with result_required get a result_min and result_max. Reject out-of-range numbers with a friendly message. If a value is allowed but unusually high (for example more than 1.5x the plan for that task, or a new personal best), show a one-time "Are you sure?" confirmation. Do not ask on normal entries.
2. No backfilling. Check-ins are allowed only for today or yesterday (app timezone). Block earlier dates with a friendly message.
3. Opponent visibility. In the weekly matchup view, each side's check-ins appear in a simple timeline, visible to the matchup opponent and the league.
4. Proof on check-ins. Every time a user marks a task done, show an optional button "Show your work (optional)". Next to it, show this short benefit copy: "Add a photo or screenshot. It earns a Verified check, shows your league the process behind your goal, earns kudos, and keeps scoring fair. A Smashed week needs proof or your opponent's OK to count in full."
   - Upload: jpg, png or webp only, max 5 MB. Validate mime type. Re-encode the image on the server (GD or Imagick) so EXIF and location data is stripped. Save through Laravel's filesystem disk named by an env value (default local public disk) so we can switch to S3 later with env variables only.
   - A check-in with proof shows a Verified check mark.
   - Proof is always visible to the matchup opponent and the league owner, whatever the post visibility is.
5. Smashed rule. A week at 150% or more counts as Smashed only if at least one check-in that week has proof OR the matchup opponent taps "Confirm". Otherwise credit the week as Past the Line and label it "Needs proof to count as Smashed". Show the pending state clearly and re-score automatically when proof or confirmation arrives.
6. "Show your work" request. A league member can tap a friendly "Show your work" button on any check-in. The owner of the check-in gets an in-app notification (and a Brevo email if mail is configured). This never changes the score by itself; it only prompts the user, and matters for the Smashed rule above.
7. Tests: scoring bands, boundaries (94/95, 104/105, 149/150), match points matrix, the Smashed proof/confirm rule, range validation, backfill block.

Commit message: "Phase A: fair play, proof and Smashed confirmation"

---

## PHASE B: Posts, feed and friends

1. Media posts. After a user adds media to a completed task, they choose who sees it and write a short caption (max 280 chars). Options:
   - Proof only: visible to the matchup opponent and league owner, not in any feed.
   - League: visible to all members of that league (default).
   - League + friends: also visible to the user's accepted friends, even if those friends are not in that league.
2. Optional location label. A free-text "Where?" field (max 60 chars, for example "Rock Creek Park"). Never use GPS or maps. Add a hint: "Crop out maps that show your home."
3. Post card layout (like Instagram or Strava): top-left the user's profile (avatar or initial plus name); top-right the league name and the goal title; then the image; then the caption; then a footer with the task completed, a Verified check, the optional location, the time, and a kudos button with a count. Kudos is a simple one-tap reaction. No comments in this phase.
4. Feeds. A league feed page (posts from that league's members) and a "Friends" feed on the dashboard (posts shared with friends). Newest first, paginated. Simple empty states with encouraging process-focused copy.
5. Friends system. Simple friend requests by email or username: send, accept, decline, remove. Table friendships (requester, addressee, status). Only accepted friends see "League + friends" posts. Do not expose a user directory or search by anything except exact email or username.
6. Authorization. Use Laravel policies so every post, image and feed query respects visibility. Serve images only through authorized routes or signed URLs, not public guessable paths. Add tests that a non-member and a non-friend cannot see a post or its image.
7. Optional (do last, only if the rest is solid): a "Suggest a caption" button that uses the existing Claude API integration to draft one short, warm, factual caption from the task, the result and the streak. The user can edit it. Never auto-posts. Handle API failure gracefully.
8. Safety: a "Report" link on posts that hides the post from the reporter and records the report for the league owner.

Commit message: "Phase B: media posts, feeds, friends and kudos"

---

## PHASE C: Group tasks and personal tasks

Group tasks (league-wide):
1. Any league member can propose a group task for a coming goalweek: title, optional description, points (1 to 10, default 5), and the target week. Only for leagues with 2 or more members (not solo).
2. The proposer's yes vote counts automatically. Members vote yes or no once, and can change their vote until voting closes.
3. It passes when yes votes reach at least two thirds of the league's members, rounded up (use ceil). Voting closes at 11:59 p.m. on the Sunday before the target week, or immediately when the threshold is met. If it does not pass by then, mark it "Did not pass".
4. When it passes, add that task to every league member's goalweek for the target week, with the same points, and include the points in each person's planned points that week. Each member checks in on it like any other task. It appears in the matchup view as a group task with a small group icon.
5. Limits: max 2 passed group tasks per league per week. Show remaining slots. Prevent duplicate proposals with the same title for the same week.
6. Notify members when a task is proposed, when it passes or fails (in-app, plus Brevo email if configured).

Personal tasks (individual extras):
1. A member can add their own extra tasks to their goalweek (for example "stretch 10 minutes"). Max 3 per week, 1 to 5 points each, and total personal-added points cannot exceed 15% of that week's AI-planned points. They can only be added before the week starts or until Tuesday 11:59 p.m. of that week, so people cannot pad plans after the fact.
2. Personal tasks are visible to the league labeled "Added by [name]" so nobody can quietly inflate their plan. They count in planned points like other tasks.

Tests: vote threshold with 2, 3, 4, 5 and 10 members, vote changes, closing time, the passed-task fan-out to every member, slot limits, personal task caps and time window, solo league disabled.

Commit message: "Phase C: group tasks with league vote, personal tasks"

---

## PHASE D: Demo data, docs and deploy notes

1. Seeder: a four-player demo league (You, Priya, Dave, Sam) with a Week 6 matchup in the CPA Prep League. Include: normal check-ins with and without proof; a few posts with generated placeholder images (draw simple local images, no external downloads); one friend connection outside the league; one group task currently in voting (2 of 3 yes needed); one Smashed claim without proof that is credited as Past with the "Needs proof" label, and one Smashed week with proof that counts in full; one personal task labeled "Added by". Make the seeder safe to re-run.
2. Update docs/SPEC.md with the final rules and tables, and add a short docs/FAIRNESS.md explaining in plain English what stops fake check-ins, in a form I can paste to my professor.
3. Update the README top with the mission line "Get in line with your goals. We get more done together." and a short "How fairness works" section.
4. Give me a deployment checklist: any new environment variables (storage disk, S3 settings if used, Brevo mail settings, Anthropic key), migrations to run on the server, and the note that Elastic Beanstalk's disk is wiped on each deploy so uploaded images vanish until S3 is set up. Include exact steps for the S3 option in plain English.
5. Do NOT deploy. I will build and upload the deploy.zip myself.

Commit message: "Phase D: demo seed, fairness docs, deploy checklist"
