# GoalLine: Smooth Interactions, Matchup Pager, iOS Polish, and a QA Reviewer

Fourth build file, based on hands-on feedback: clicking things reloads the page and jumps to the top instead of feeling interactive; the matchup screen should be swipeable, one page per matchup, like fantasy football with tasks in place of players; the app must work great on iPhone; and I want an automated reviewer that gives feedback beyond my own eyes.

Read docs/SPEC.md, docs/NEXT_BUILD.md, docs/NEXT_BUILD_2.md, docs/NEXT_BUILD_3.md and the existing code first. Tell me in plain English what already exists. Reuse it. This is a polish and functionality round: do not change the scoring rules, the fairness rules, or the data model unless a phase needs it, and do not remove features. Code freeze is Monday Nov 16, 11:59 p.m. ET, so be careful: before starting, create a git tag named pre-polish on the current main so I can revert. Run the full test suite after every phase and do not leave it red. Keep Blade plus light JS (Alpine.js and fetch are fine; no heavy frameworks), the GoalLine brand (Club Green #176B45, Grass Green #43B96A, Fresh Mint #E5F3E8, Warm Ivory #FAFAF5, Soft Ink #1E2922, Championship Gold #D4AC58, Space Grotesk and Public Sans), and the encouraging, process-focused tone. Do not deploy; I will build and upload deploy.zip myself.

Work in phases in order. After each phase: run tests, run locally, commit with the message given, push, then STOP and report in plain English (what changed, how I test by hand, what is left). Wait for me to say "continue".

---

## PHASE L: Interactions that feel alive (no jump to the top)

Problem: actions like checking in, sending a friend request, giving kudos, voting, disputing and accepting an invite do a full page reload and send the user to the top of the screen. They should update in place.

1. Convert these actions to in-place updates using fetch/Alpine with progressive enhancement: the forms must still work without JavaScript as a fallback, but with JS the page does not reload and the scroll position is preserved. Cover: task check-in and un-check, proof upload, kudos, friend request send/accept/decline, invite accept, group task votes, disputes and "Show your work", plan accept/discard, and league join.
2. Instant feedback on every action: the button shows a loading state and disables to prevent double submits; on success it changes state immediately (optimistic UI) and rolls back with a friendly message if the server fails.
3. Delight, in the brand: a satisfying completion animation on check-in (the green line fills across and the dot pulses, a soft check pop), a short toast ("Checked in. One more day on the line.") that auto-dismisses, a small count-up on points and progress bars that update live (percent of plan, band chip color change, "points if the week ended now"). Keep it subtle and fast (under 400 ms), and honor prefers-reduced-motion by turning animations off.
4. Update counts, badges and progress elsewhere on the same page without reload (for example the dashboard progress and the matchup header after a check-in).
5. Errors: inline, friendly, and never lose what the user typed. Validation messages appear next to the field. Network failure shows a "Try again" toast.
6. Accessibility: aria-live regions for toasts and status changes, visible focus states, buttons are real buttons.
7. Tests: endpoints return the right JSON/HTML fragments, authorization still enforced, no-JS fallback still works for check-in and friend request, double-submit protection.

Commit message: "Phase L: in-place interactions, animations and toasts"

---

## PHASE M: Swipeable matchup pager, fantasy-football style

Current matchup screen is close, but it should be a pager: one page per matchup in the league's current goal week, swipe left and right between them, and the body shows each player's tasks the way fantasy football shows each team's players.

1. Pager. On the matchup tab for a league, show all of this week's matchups as horizontally swipeable pages (CSS scroll-snap, smooth on touch and trackpad), with dots and "2 of 3" at the top, arrow buttons and left/right keyboard arrows on desktop. The user's own matchup is first. Include a league switcher if the user is in multiple leagues, a week selector (past weeks show final results, read only), a "Bye" page when applicable, and a solo "against the line" page for solo leagues.
2. Header of each page (the scoreboard): both players' avatar, name, W-D-L record, goal title and league; each player's live percent of plan, current band chip (colors: Short red, In Line green, Past golden shine, Smashed blue diamond) and a PROJECTED band based on current pace (computed by plain code: percent earned so far versus the percent of the plan due so far, labeled "Projected, on current pace"); "points if the week ended now" for each side; days left in the week; and a status line (for example "Ends Sunday 11:59 p.m.").
3. Body (where the players would be): the tasks of the week for each player, grouped by day (Mon to Sun), side by side on wide screens and stacked with a sticky player toggle on phones. Each task row shows title, points, status (done, due today, upcoming, missed, rest day), the result value if it has one, a Verified check if it has proof, a small group-task icon if applicable, and a thumbnail or "View proof" link when proof exists. Tapping a task opens a bottom sheet (modal on desktop) with details, proof, the related highlight post, and the actions "Show your work" and "Dispute", plus a kudos button.
4. Links to highlights: each player's name opens their highlights filtered to this league (respecting post visibility rules).
5. Keep everything in plain, tested PHP for the numbers; the view just displays them. Respect privacy rules: only show what the viewer is allowed to see.
6. Phone-first layout, with large tap targets and no horizontal page scroll other than the pager itself.
7. Tests: the pager lists exactly the league's matchups for the week, ordering, bye and solo pages, projected band calculation (boundaries), visibility of tasks and proof for members versus non-members, past-week read-only.

Commit message: "Phase M: swipeable matchup pager with task view and projections"

---

## PHASE N: iPhone and mobile polish

1. Set a correct viewport, theme-color and safe-area padding (env(safe-area-inset-top/bottom)), use dynamic viewport units (dvh, not vh), and make sure bottom navigation and sticky bars do not hide behind the iPhone home indicator or browser toolbar.
2. All tap targets at least 44 by 44 px; inputs at 16px font size or larger so iOS does not zoom; remove hover-only interactions; add -webkit-tap-highlight styling and touch-action where needed; avoid 300 ms tap delay issues.
3. File uploads: use accept="image/jpeg,image/png,image/webp" so iOS converts photos to JPEG, and handle other formats with a friendly message. Make the upload flow smooth on mobile (preview, progress, retry).
4. Add a web app manifest and Apple touch icon so GoalLine can be added to the home screen, with the GL icon already in public/brand.
5. Check every main screen at 375, 390 and 430 px wide and at tablet and desktop widths: login and Try the demo, dashboard, goal creation and intake questions, plan view, today view, matchup pager, league and invite pages, friends and search, highlights feed, privacy and safety page. Fix overflow, clipped text, cramped buttons, and slow-feeling layouts.
6. Performance: compress and lazy-load images, avoid layout shifts, keep pages light.
7. Tests where practical (e.g. responsive smoke checks in Phase O).

Commit message: "Phase N: iPhone and mobile polish"

---

## PHASE O: Automated QA reviewer

I want an automated reviewer that gives feedback beyond my own eyes. Build:

1. A Playwright end-to-end suite (dev dependency only, not shipped to production) that runs against the local app using the Try the demo account. Cover the full demo script: Try the demo, view the dashboard, open a goal and plan, check in a task, attach proof, open the matchup pager and swipe between matchups, open a task sheet, send a friend request, vote on a group task, dispute a check-in, open the privacy page. Run it in these profiles: iPhone 14 (WebKit), a small Android phone (Chromium) and a desktop (Chromium).
2. Automatic checks on every screen and profile: no failed requests or 500s, no console errors, no horizontal page overflow, tap targets under 44 px flagged, text below 12 px flagged, missing alt text or form labels flagged, color contrast problems flagged, and actions that cause a full page reload or jump to the top flagged (compare scroll position and navigation events).
3. Screenshots of every screen in every profile saved under qa/screenshots (git-ignored), and a generated qa/REPORT.md listing findings by severity (blocker, important, nice-to-have) with the screen, profile and a suggested fix.
4. A Claude Code subagent definition at .claude/agents/ux-reviewer.md: a read-only reviewer that, when I ask it to review the app, runs the suite, reads qa/REPORT.md and the screenshots, and gives prioritized, specific, plain-English feedback on clarity, flow, visual polish, mobile usability and encouraging tone, plus the top 5 fixes. Tell me how to invoke it.
5. Add npm scripts: npm run qa (run the suite and generate the report) and document it in the README under "Testing".
6. Be honest in the report that WebKit emulation is close but not a real iPhone, and add a short manual checklist for testing on a real iPhone.
7. Then run the suite once, show me the report, fix the blockers and important findings, and re-run until clean.

Commit message: "Phase O: Playwright QA suite and UX reviewer agent"
