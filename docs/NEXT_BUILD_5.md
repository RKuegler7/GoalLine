# GoalLine: Final Polish Round (Celebrations, Share Cards, Branded Loading, Reliability, Repo)

Fifth build file. Read docs/SPEC.md, docs/FAIRNESS.md, docs/NEXT_BUILD.md through NEXT_BUILD_4.md and the existing code first. Tell me in plain English which earlier phases are done and which are not, so I know where we stand. Reuse what exists. This is a polish round: do NOT change scoring rules, fairness rules or the data model unless a phase needs it, and do not remove features. Scoring math stays in plain tested PHP, never in the AI.

Context: I am a finalist in the CUA AI contest. Judges score 100 points: Functionality and technical execution 25, Problem identification and relevance 20, AI integration and innovation 20, Code quality and GitHub transparency 15, UX and design 10, Business impact and presentation 10, plus up to 5 bonus for ethical AI, accessibility or cross-disciplinary work. They also grade basic hygiene: no hard-coded secrets, synthetic data only for judge accounts, a live public URL behind a working login, and a public repo with real incremental commit history. Code freeze is Monday Nov 16, 11:59 p.m. ET; presentation is Nov 20. Before starting, create a git tag named pre-final-polish on the current main so I can revert. Run the full test suite after every phase and never leave it red. IMPORTANT: never rewrite git history (no squash, no force push, no rebase of pushed commits); the commit history is graded. Keep Blade plus light JS, the GoalLine brand (Club Green #176B45, Grass Green #43B96A, Fresh Mint #E5F3E8, Warm Ivory #FAFAF5, Soft Ink #1E2922, Championship Gold #D4AC58, Space Grotesk and Public Sans) and the encouraging, process-focused tone. Do not deploy; I will build and upload deploy.zip myself.

Work in phases in order. After each phase: run tests, run locally, commit with the message given (small commits within the phase are welcome), push, then STOP and report in plain English (what changed, how I test by hand, what is left). Wait for me to say "continue".

---

## PHASE P: Live band progression and the celebration moment

The signature GoalLine moment: you check off a task and watch your band climb.

1. Live band on check-off. When a task is checked or un-checked, instantly update (no reload) the percent of plan, the band chip, "points if the week ended now" and the projected band, using values returned by the server's scoring code (the front end never computes the score itself; it animates between server values). Show a small progress track with the four band thresholds marked (Short, In Line 95, Past 105, Smashed 150) and a marker that slides to the new percent.
2. Band-change celebration. When a check-in moves the user UP a band (including crossing 95, 105 or 150), play a short celebration (under 1.5 seconds, skippable by tap), once per band crossing per week:
   - Short to In Line: the line fills green, the dot pulses, "You're on the line."
   - In Line to Past: golden shine sweeps across the chip, a soft gold burst, "Past the Line."
   - Past to Smashed: the marker passes the end tick, a blue diamond burst, "Smashed it." If proof is needed for Smashed to count in full, show the friendly "Add proof to lock it in" nudge right inside the celebration.
   Going down a band (un-check) changes quietly with no animation and no negative language.
3. Optional haptics on supported phones (navigator.vibrate, tiny and only on a band-up), a short sound is OFF by default. Honor prefers-reduced-motion (replace with a simple fade and color change). aria-live announcement of the new band for screen readers.
4. The same moment appears on the dashboard, Today view and matchup screen from one shared component so it is consistent. Keep it light: CSS/SVG/Canvas, no heavy animation library.
5. Tests: server returns the new band and values after check-in and un-check; celebration is triggered only on upward crossings and only once per crossing; boundaries at 94/95, 104/105, 149/150; reduced-motion path; Playwright check on iPhone WebKit that the page does not reload.

Commit message: "Phase P: live band progression and band-up celebration"

---

## PHASE Q: Shareable weekly result cards

Goal: a beautiful card for the weekly result that can be posted to Highlights, shared to other social apps, or texted.

1. Card content (generated server-side from code-computed results, never by the AI): GoalLine wordmark and line, "Week N", the player's first name or display name and avatar (optional), goal title, percent of plan, band chip and name, the match result with the new scoring (Win 3, Close win 2, Close loss 1, Tie 1, Loss 0 as applicable, and "Photo finish" when same band), weekly points, current streak or weeks on the line, league name, and the tagline "Get in line with your goals." Smashed cards get the blue diamond treatment, Past gets gold, In Line green, Short gets a warm encouraging version ("Showed up. Back on the line next week.") in a calm style, never red shaming.
2. Sizes: 1080x1350 (feed) and 1080x1920 (story). Generate PNGs on the server with GD or Imagick using bundled Space Grotesk and Public Sans font files (add the fonts to the repo with their open licenses). Cache by a hash of the content. A friendly fallback if fonts or GD are missing.
3. Share flow: a "Share your week" button on the weekly recap and matchup result. It opens a sheet with: preview, "Post to Highlights" (creates a normal post with the card as the image and a caption, using existing visibility choices), "Share" (Web Share API with the image file on supported phones), "Copy link", "Download image". Fall back gracefully on desktop.
4. Public share link: an unguessable token URL that shows a public, read-only card page with Open Graph and Twitter meta tags so link previews look great when texted or posted, plus a small "Join GoalLine" button. Privacy: opt-in per share, only the user's own result summary, no email, no private notes, no photos from proof, the token can be revoked, and it respects a user setting to disable public cards. Rate limit generation.
5. Demo data: make sure Try the demo accounts can generate and view a card (synthetic data only).
6. Tests: card content for each band and each match result, no private data in the public page, token unguessable and revocable, authorization (only the owner can create), post-to-highlights visibility, image generated at both sizes.

Commit message: "Phase Q: shareable weekly result cards"

---

## PHASE R: Branded loading and empty states

Goal: every wait and every empty screen feels like GoalLine.

1. Loading animation (the signature): the GoalLine wordmark appears, the line draws left to right under it with the dot traveling along; when loading finishes, the dot runs past the end tick and "smashes the line" with a small blue-diamond burst, then the content fades in. Build it as one reusable component with three sizes (full-screen splash, inline section loader, small button spinner using only the line and dot).
2. Rules so it never slows the app: the full-screen splash shows only on first visit of a session and for at most about 1.2 seconds even if loading is faster (do not artificially hold users longer); page-to-page loads use the thin progress bar and content skeletons, not the splash; show a loader only if loading takes more than about 250 ms to avoid flicker. Skeletons for feed cards, matchup rows, plan lists and league tables in the brand colors. prefers-reduced-motion shows a static wordmark with a simple fade.
3. If loading fails or times out, the same component shows a friendly error with "Try again" and no technical text.
4. Empty states for every list: Today (nothing due), tasks, GoalLeagues, matchup (no opponent yet, bye, season not started), Highlights, friends, search with no results, notifications, plan history, disputes. Each has a small line-and-dot illustration, one process-focused sentence ("One more day on the line."), and one clear primary action. No sad faces, no shaming.
5. Branded 404, 403 and 500 pages with the same style, a link home, and no stack traces in production.
6. Tests: loader not shown for fast responses, shown for slow ones, error state has a retry, every empty state renders with a CTA, error pages return the right status codes.

Commit message: "Phase R: branded loading animation, skeletons and empty states"

---

## PHASE S: Reliability and live-demo safety

Reliability is graded under Functionality, and a broken live demo costs the most.

1. Health and smoke: add a /health route (app, database, storage, cache reachable; no secrets in the output) and a smoke-test script (npm run smoke -- --url https://...) using Playwright that hits the live URL logged out, clicks Try the demo, checks in a task, opens the matchup, opens the privacy page, and verifies there are no 500s or console errors. It must be safe to run against production (demo accounts only, synthetic data).
2. Error handling: friendly pages everywhere, no raw exceptions in production, APP_DEBUG=false assertions in a startup check. Make error EMAILS useful and quiet: de-duplicate and throttle identical errors (for example at most one email per error signature per 15 minutes), and do not email for handled cases (retried deadlocks, validation errors, 404s, expired or invalid signed links, missing avatars).
3. Database and concurrency: re-verify the deadlock fixes, indexes on frequently queried columns (league_id, user_id, week, status), no N+1 queries on the dashboard, matchup pager and highlights (add a test or query-count assertion), and make migrations safe to run twice on deploy.
4. Config for a single Beanstalk instance: sessions and cache in the database (not local files that vanish on deploy), queue handled as decided in the second UI pass, storage on S3 verified, config/route/view caching steps documented, a php artisan goalline:preflight command that checks required environment variables are set (APP_KEY, APP_URL as https, DB, mail, S3 bucket and region, Anthropic key) and prints a clear pass or fail without printing secret values.
5. External failures: every Anthropic API call and mail send has a timeout, a retry limit and a fallback; confirm the demo account never calls the live AI (cached sample responses) and cannot spend money; add a per-user and global rate limit and a daily call cap with a friendly message.
6. Backups and uptime (write these as a plain-English checklist in docs/RELIABILITY.md for me to do in AWS and one free uptime monitor): RDS automated backups on with 7-day retention, a manual snapshot before the freeze and another after the final deploy, Beanstalk environment health alarm email, billing alert kept, a free uptime monitor pinging /health every 5 minutes, and a reminder not to pause or stop the AWS instance until judging is finished.
7. Security pass: authorization on every route and image, secure and httpOnly cookies, CSRF, rate limits on login, signup, search, invites and demo creation, no secrets in the repo or git history (scan with gitleaks or a git grep for key patterns, report results), uploads validated, headers (HSTS, X-Content-Type-Options, a sensible Content-Security-Policy that does not break the app).
8. Accessibility pass (bonus points): labels on all inputs, focus states, color contrast at least WCAG AA on the brand palette (flag any failing pair and fix it), alt text, aria-live for toasts and band changes, keyboard navigation for the matchup pager and sheets, reduced motion. Run axe-core in the Playwright suite and fix serious and critical findings.
9. Tests: /health behavior with a failing dependency, error pages, throttled error emails, preflight command, query counts, rate limits.

Commit message: "Phase S: reliability, security and accessibility hardening"

---

## PHASE T: Repo, documentation and commit hygiene

Judges read the repo. Make it look like a real, careful project, without rewriting history.

1. Do not rewrite or squash history. From now on use clear conventional-style messages (feat:, fix:, docs:, test:, chore:) and small commits.
2. README (top to bottom): one-line description; the 20-second pitch ("Every player is scored against their own plan by plain code, not AI. Big claims need proof or your opponent's sign-off, and plans can't drop below the minimum pace the deadline requires."); live HTTPS link; "Try the demo" steps with no credentials needed; a placeholder line for the 2 to 3 minute demo video; 3 to 4 screenshots or a GIF (matchup, band celebration, share card, plan); the one-page summary in plain English (problem, solution, how AI does the work, what I learned); then the sections from NEXT_BUILD_3 Phase K. Update the scoring section to the final rules: bands (Short 0-94, In Line 95-104, Past 105-149, Smashed 150+), line points 0/1/2/3, match points Win 3, Close win 2, Close loss 1, Tie 1, Loss 0, "Photo finish" for same-band results, weekly total 0-6.
3. docs: update SPEC.md and FAIRNESS.md to the final rules; add docs/ARCHITECTURE.md with a Mermaid diagram (browser, CloudFront, Elastic Beanstalk, RDS, S3, Brevo, Anthropic API), the main data flows, and a table of which parts are AI and which are plain code; add docs/AI.md describing each AI job (intake questions, plan drafting, plan refinement, optional caption), its inputs and outputs, the validation, the fallbacks, what it never does (score, rule on disputes, give medical advice), and cost controls; add docs/DECISIONS.md (what we chose not to build and why); add docs/RELIABILITY.md from Phase S; add a short CHANGELOG.md summarizing the build by week using the real history.
4. Add a LICENSE (MIT unless I say otherwise), a tidy .gitignore (no .env, no qa/screenshots, no vendor, no node_modules, no deploy.zip), a .env.example with every variable and no real values, and a GitHub Actions workflow that runs the PHP tests on push (and Playwright if it can run headless in CI without being flaky; otherwise PHP tests only). Add repo hygiene files that are quick: a pull request template is optional, skip anything that is busywork.
5. Clean up: remove dead code, unused files and stray scripts (list what you removed), make sure there are no TODO notes pointing at unfinished promises in the UI, and run a final secrets scan of the working tree and of git history; report the result honestly, and if anything is found tell me before doing anything (history rewriting needs my decision).
6. GitHub settings text for me to paste: a repo description, topics, and a pinned README top. Tell me the exact steps to make sure the repo is public.
7. Tag and release: after I say "freeze", create the tag final-submission on the final commit, print the exact commit hash, and give me the step-by-step to build deploy.zip from that exact tagged commit, set Beanstalk environment properties, deploy, run php artisan goalline:preflight and the smoke test, and a phone checklist. Remind me that after the freeze I must not redeploy.

Commit message: "docs: final README, architecture, AI and fairness docs, repo hygiene"

---

## PHASE U (OPTIONAL, only if P through T are green and I say "continue"): AI you can see

The rubric gives 20 points for AI integration. Make the AI's role visible and honest.

1. "What the AI did / what code did" panel: a small, friendly section on the plan page and in About that shows, for this user's plan, which parts the AI drafted (intake questions, task list, explanation) and which parts code enforced (minimum pace, plan lock, scoring, disputes, caps), with live examples such as "We raised week 3 to your minimum pace."
2. Weekly recap narrator: on the Monday recap, an AI-written two to three sentence encouraging summary (warm, process-focused, never a roast) built ONLY from numbers the code already computed and passed in; the AI never invents or changes numbers, the output is validated (length, no new numbers beyond those provided), and a template summary is used if the call fails. Cache per recap. Demo accounts use saved samples.
3. Tests: validation rejects invented numbers, fallback template, cached, demo path makes no live call.

Commit message: "feat: visible AI/code split and recap narrator"
