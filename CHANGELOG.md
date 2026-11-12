# Changelog

How GoalLine was built, week by week, from the real commit history (`git log`). Weeks run Monday to Sunday.

## Week of Nov 9, 2026: polish, reliability and the final round

- **Phase O:** a Playwright QA suite (iPhone WebKit, small Android, desktop) with automatic checks on every screen, a generated `qa/REPORT.md`, and a read-only UX reviewer agent.
- **Fixes from real use:** missing uploaded files handled gracefully, invite links that work everywhere (absolute, unguessable, with link previews), the "403 Invalid signature" email-verification bug, and deadlocks (retries, idempotent re-scoring, one open dispute per person per check-in).
- **About page** with how to play, scoring and fair play, rendered from the same code that scores every week.
- **Second UI pass:** compact design, a Matchups tab, Check in from anywhere, a Start menu, an app shell whose bars never reload, and forms that update in place.
- **Phase P:** the live band track and the band-up celebration.
- **Phase R:** the branded line-and-dot loader, a splash on the first page, a calm "Try again" for failed loads, empty states with one clear action, and branded error pages.
- **Phase Q:** shareable weekly result cards (feed and story PNGs), Post to Highlights, and opt-in, revocable public links with link previews.
- **Messaging switches** to pause each kind of email or push without breaking sign-up.
- **Phase S:** `/health`, `goalline:preflight`, a live smoke test, security headers, AI and mail timeouts, query-count tests, WCAG AA text contrast, and the reliability checklist.
- **Phase T:** the MIT license, a complete `.env.example`, CI on GitHub Actions, and the architecture, AI and decisions docs.
- **Security update:** Laravel 13.42 and Symfony 8.1.11, for the Symfony advisories published Nov 4.
- **Phase U:** a "What the AI did, what code did" panel on each goal and in About, and a recap narrator that words a finished week only from numbers code computed, with a template fallback.
- **Navigation round:** Clubhouse Now and History, "Weeks on the line", a Matchups tab that always opens, a people-first matchup scoreboard with the line on a green field, standings with stats and player seasons, and a Back arrow everywhere.
- **Weekly stars:** line stars + matchup stars (Win 2, Photo finish win 1, Tie 1 each, any loss 0), 0 to 5 a week, W–D–L records, byes as line stars only, full-precision photo finishes, all from one config; settled weeks re-scored.
- **Join and help round:** league goal templates, joining with the goal pre-filled (and late joins), invites that survive sign-up and verification, owner goal status with Remind, the "How is GoalLine scored?" sheet, and a Help Center with a searchable FAQ, feedback and a first-run tour.

## Week of Nov 2, 2026: plan honesty, the demo and smooth interactions

- **Phase D:** the demo seed, fairness docs and the deploy checklist.
- **Phase F:** goal-specific intake questions that feed the plan.
- **Typed league goals,** the GoalWeek Matchup page, and group tasks in three sizes.
- **Phase G:** refine a plan in your own words, with a preview to accept or discard, manual edits, and plan history.
- **Phase H:** recurring tasks with clear rules, the Today view and the week grid.
- **Phase I:** the plan floor (minimum pace), plan lock, plan transparency and disputes.
- **Phase J:** one-click Try the demo with isolated, resettable accounts.
- **Phase K:** the README, the fairness docs and final checks.
- **Phase L:** in-place interactions, animations and toasts.
- **Scoring:** 3-2-1-0 match points with the photo finish.
- **Phase M:** the swipeable, fantasy-football-style matchup pager with projections.
- **Phase N:** iPhone and mobile polish.

## Week of Oct 26, 2026: fair play, friends and group tasks

- **Phase A:** fair play, "Show your work" proof, and Smashed confirmation.
- **Phase B:** photo posts, feeds, friends and cheers, with Instagram-style post cards.
- **Phase C:** group tasks voted on by the league, and personal tasks.
- **Phase E:** the goal-to-league flow, league invites and people search.
- **S3 photo storage** with a health check on the admin page, and `FAIRNESS.md`.
- **One-click demo:** a private demo world with friends, two GoalLeagues mid-season and a live matchup.

## Week of Oct 19, 2026: the app takes shape and goes live

- **Deployed** to AWS Elastic Beanstalk with the live URL, then moved to RDS, S3, CloudFront and Brevo for the private beta.
- **Accounts and the GoalLine brand.**
- **Goals and AI roadmaps:** GoalWeeks, lock-in, roadmap editing, coach checks and plan uploads.
- **Check-offs and GoalWeek results,** profiles, goal completion, leagues and league chat.
- **Highlights:** sharing, following, cheers and share cards.
- **Daily life:** Today's Gameplan, weekly planning, habits, the Inbox, email and push reminders, the daily streak, nudges, the calendar feed and an installable app.
- **Community:** badges, GoalLeague recaps and season awards, comments and @mentions, challenges, and the activity heatmap.
- **Beta readiness:** AI spend caps, invite codes, the admin page, privacy and terms, the weekly recap, the Kickoff checklist and beta feedback.

## Week of Sep 21, 2026: the starting line

- The repository and the Week 1 skeleton.
