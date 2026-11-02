# GoalLine: Plan Honesty, Disputes, "Try the Demo" and Final Submission

Third build file. Read docs/SPEC.md, docs/NEXT_BUILD.md, docs/NEXT_BUILD_2.md and the existing code first. Tell me in plain English which earlier phases are done. Reuse what exists; do not duplicate or break anything. Keep Blade plus light JS, mobile-first, GoalLine brand (Club Green #176B45, Grass Green #43B96A, Fresh Mint #E5F3E8, Warm Ivory #FAFAF5, Soft Ink #1E2922, Championship Gold #D4AC58, Space Grotesk and Public Sans). Scoring math stays in plain tested PHP, never in the AI. Tone is encouraging and process-focused. Do not deploy; I will build and upload deploy.zip myself.

Context: judges evaluate the last commit on main before the code freeze (Mon Nov 16, 11:59 p.m. ET), and the live AWS app must match it. The judges' top question will be "what happens when your AI gets it wrong?" and the professor asked: "If a player asks the AI for an easy plan, he Smashes every week. What keeps the plans honest? Past needs no proof, so a player could claim it every week."

Work in phases in order. After each phase: run the tests, run the app locally, commit with the message given, push, then STOP and give me a short plain-English report (what changed, new environment variables, how I test by hand, what is left). Wait for me to say "continue".

---

## PHASE I: Plan honesty and the dispute button

1. Plan floor. When the Goal Decomposer builds or refreshes a plan, code (not the AI) computes a minimum weekly workload ("plan floor") from the goal's category, the target date and the user's intake answers (starting point, hours available). Use clear, documented formulas per category (for example, for an exam: required weekly study hours to cover the material before the date; for running: a conservative progression that reaches the goal distance by the date). Store a plan_floor_points per week.
   - Validate every AI plan in code: if any week's planned points are below that week's floor, raise it to the floor and show the user a plain note ("We raised this week to the minimum pace your deadline needs").
   - The Phase G "Refine your plan" box and manual edits can never push future weeks below the floor.
   - Allow up to 2 declared "lighter weeks" per season at no less than 70% of the floor, with a required one-line reason, shown to the league as a "Lighter week" label. (Fits the Overload Arbiter idea.)
2. Plan lock. A week's plan locks at Monday 12:00 a.m. (America/New_York). After that, tasks can be added (within the existing personal-task limits) but planned points cannot be lowered or tasks deleted for the current week. Changes to future weeks are logged in plan history and visible to league members ("Plan updated, lighter week declared").
3. Plan transparency. In the league and matchup views, show each member's plan status as a small label: "At minimum pace", "Above minimum pace" or "Lighter week". Members can open any league member's plan summary (weekly planned workload versus floor) but not their private notes.
4. Dispute button on every check-in (including ones that would only earn Past the Line). The matchup opponent and the league owner can tap "Dispute" with an optional reason. Extend the existing "Show your work" request into this.
   - A disputed check-in is labeled "Disputed" and the owner is notified (in-app, plus Brevo email if configured).
   - The owner can resolve it by adding proof, adding an explanation note, or the disputer can withdraw it. The league owner can also rule on it.
   - At week close, points from any still-unresolved disputed check-in are held out of that week's score until it is resolved, then the week re-scores automatically.
   - Limits: each player can have at most 3 open disputes per matchup per week; disputes are always visible to the league owner.
5. Recap. The Monday recap ("Your process this week") lists plan status, disputes opened and resolved, and any held points, in encouraging, non-accusatory language.
6. AI failure behavior. If the Claude API fails or returns invalid JSON, use a fallback template plan built from the category and the floor, and tell the user plainly. A plan is never saved without validation and the user's accept click.
7. Tests: floor calculation per category, floor raise on an AI plan below the floor, refine cannot go below floor, lighter-week limits and reasons, plan lock, plan history visibility, dispute creation, caps, resolution paths, held points at week close and automatic re-score, fallback plan on API failure.

Commit message: "Phase I: plan floor, plan lock, plan transparency, disputes"

---

## PHASE J: "Try the demo" for judges

Judges must never type a username or password.

1. Add a clear "Try the demo" button on the login screen (and the landing page). One click signs the visitor into a fresh demo account.
2. Each click creates a new, isolated demo user and copies a template world for them, so every judge sees the same starting point and judges never interfere with each other:
   - A demo user ("Demo Judge") with a CPA Prep goal, intake answers and a plan at the floor.
   - A four-player league (Demo Judge, Priya, Dave, Sam) in Week 6 of 14, with a live matchup (Demo vs Dave).
   - Check-ins with and without proof (use simple generated placeholder images, no downloads), posts in the feed, one friend outside the league, one group task in voting if that feature exists, a Smashed claim without proof credited as Past with the "Needs proof" label, a Smashed week with proof counting in full, a disputed check-in, a "Lighter week" label and a plan-floor note.
3. Safety and cost: made-up data only; demo accounts cannot send real email or invite real emails; AI calls for the demo goal use saved sample responses (cached) so the demo never fails or spends API money, and live AI calls are rate-limited if enabled; rate limit demo creation per IP.
4. A visible "Demo mode" banner with a "Reset demo" button that rebuilds that visitor's copy. Demo accounts and their data auto-delete after 24 hours using a cleanup that runs lazily when a new demo account is created (do not rely on cron).
5. Real users' data must never be visible from a demo account. Add tests for isolation, cleanup, and that demo accounts cannot reach real users.

Commit message: "Phase J: one-click Try the demo with isolated resettable accounts"

---

## PHASE K: README, final checks and freeze readiness

1. README. Put these at the top, in plain English: a one-line description; the 20-second pitch ("Every player is scored against their own plan by plain code, not AI. Big claims need proof or your opponent's sign-off, and plans can't drop below the minimum pace the deadline requires."); the live HTTPS link; the Try the demo instructions; the demo video link placeholder. Then sections: What the app does; Who it is for (students and friend groups working toward real goals with deadlines); Which AI service it uses and what the AI does (Anthropic's Claude API: builds goal-specific intake questions, drafts and refines plans, optional caption suggestions; and what it never does: it never scores, never decides disputes, and never gives medical advice); How the scoring works (bands and points table); How fairness works (range checks, no backfill, opponent visibility, proof for Smashed, disputes, plan floor, plan lock); What happens when the AI gets it wrong (validation, previews with an accept click, fallback plans, editable plans, floor enforced by code); Safety and privacy design and a disclaimer (plans are general guidance, not medical advice); How to run it locally; What we chose not to build; Known limitations.
2. docs/FAIRNESS.md: update with plan honesty and disputes, in plain English.
3. Final checks: run the full test suite and fix failures; run a security pass (authorization on every route and image, rate limits, secure cookies, no secrets in the repo or history); check mobile layouts at phone width on the main screens; make sure the login page, demo button and dashboard work on a logged-out browser; make sure all links in the README work.
4. Freeze readiness. Make sure everything intended for judging is committed to the main branch. Create a git tag named final-submission on the final commit. Print the exact commit hash. Give me a step-by-step deployment checklist: build deploy.zip from that exact commit, the environment variables to set in Elastic Beanstalk (APP_KEY, APP_URL, APP_ENV, APP_DEBUG, mail settings, Anthropic key, storage settings), migrations and seeders to run on the server, and a smoke test list to run on the live HTTPS site from a phone. Remind me that after the freeze I must not redeploy.
5. Do NOT deploy.

Commit message: "Phase K: README, fairness docs, final checks"
