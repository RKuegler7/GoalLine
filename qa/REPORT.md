# GoalLine QA report

Generated 2026-11-09 19:31 UTC by `npm run qa`. Screenshots are in `qa/screenshots/` (not committed).

| Profile | Demo script | Time |
| --- | --- | --- |
| Desktop (Chromium) | Passed | 86s |

**0 blockers, 0 important, 19 nice to have.**

Checks on every screen: failed requests and 500s, console errors, horizontal overflow, tap targets under 44 px (phones), text under 12 px, axe accessibility (alt text, form labels, contrast, names), and in-place actions that reload the page or jump to the top.

> WebKit in Playwright is close to iPhone Safari but it isn't a real iPhone: no real touch physics, home indicator, Safari toolbars, or iOS photo picker. Use the checklist at the end on a real phone before a release.

## Blockers (0)

None.

## Important (0)

None.

## Nice to have (19)

| Screen | Profiles | Finding | Suggested fix |
| --- | --- | --- | --- |
| About | Desktop (Chromium) | **Text under 12 px**: "Demo mode" 11px; "Short of the line" 11px; "· 0 pts" 11px; "In line" 11px; "· 1 pt" 11px; "Past the line" 11px; "· 2 pts" 11px; "Smashed the line" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Dashboard | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "This GoalWeek" 11px; "Total points" 11px; "In-line streak" 11px; "GoalLeague record" 11px; "Demo mode" 11px; "Academic · 62 days to go" 11px; "Not started" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Friends | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "GoalWeek 1" 11px; "In line, close call" 11px; "· 1 pt" 11px; "GoalWeek 2" 11px; "In line" 11px; "GoalWeek 3" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal after a check-in | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "GoalWeek 1" 11px; "In line, close call" 11px; "· 1 pt" 11px; "GoalWeek 2" 11px; "In line" 11px; "GoalWeek 3" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal after proof | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "GoalWeek 1" 11px; "In line, close call" 11px; "· 1 pt" 11px; "GoalWeek 2" 11px; "In line" 11px; "GoalWeek 3" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeague | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Live" 11px; "Not started" 11px; "Short of the line" 11px; "· 0 pts" 11px; "Voting · GoalWeek 7 · Solid · 2 pts" 11px; "Past the line" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeague after a vote | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Live" 11px; "Not started" 11px; "Short of the line" 11px; "· 0 pts" 11px; "Past the line" 11px; "· 2 pts" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeagues | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Day 13 of 21" 11px; "In season" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Highlights | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "2:32 pm" 11px; "Crossed the GoalLine" 11px; "Nov 8, 12:31 pm" 11px; "Nov 6, 11:31 am" 11px; "Nov 8, 8:31 am" 11px; "Nov 7, 9:31 am" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Inbox | Desktop (Chromium) | **Text under 12 px**: "Demo mode" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Matchup after a dispute | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Your matchup" 11px; "Live" 11px; "Above minimum pace" 11px; "Not started" 11px; "Ahead" 11px; "Short of the line" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Matchup pager | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Your matchup" 11px; "Live" 11px; "Above minimum pace" 11px; "Not started" 11px; "Ahead" 11px; "Short of the line" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| My Locker | Desktop (Chromium) | **Text under 12 px**: "Demo mode" 11px; "Less" 11px; "More" 11px; "Nov 9, 2026" 11px; "Smashed the line in a GoalWeek." 11px; "10 GoalWeeks in line in a row." 11px; "30 habit check-ins." 11px; "Crossed the GoalLine on a goal." 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Notifications and calendar | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| People search | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Plan my week | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "GoalWeek 6 · ends Sun, Nov 15" 11px; "Not started" 11px; "Habits" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Task sheet | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "Your matchup" 11px; "Live" 11px; "Above minimum pace" 11px; "Not started" 11px; "Ahead" 11px; "Short of the line" 11px | Use at least 12 px (text-xs) for anything people need to read. |
| Today | Desktop (Chromium) | **Text under 12 px**: "9+" 11px; "Demo mode" 11px; "M" 11px; "T" 11px; "W" 11px; "F" 11px; "S" 11px | Use at least 12 px (text-xs) for anything people need to read. |

## Real iPhone checklist

Run through this on an actual iPhone in Safari, then again after "Add to Home Screen":

- [ ] Try the demo opens the Clubhouse in one tap, no zoom on load.
- [ ] Tapping any text field does not zoom the page.
- [ ] The bottom tab bar and Feedback button sit above the home indicator; nothing hides behind Safari's toolbar.
- [ ] Check in a task: the circle fills in place, the toast shows, the page doesn't jump.
- [ ] Show your work: the photo picker offers Photos and Camera; a photo uploads with the progress bar and turns Verified.
- [ ] Matchup pager: swipe left and right, the counter and dots follow, the player toggle sticks to the top.
- [ ] Tap a task on the pager: the bottom sheet slides up and closes with a tap outside it.
- [ ] Rotate to landscape: content stays clear of the notch.
- [ ] From the home screen: GoalLine opens full screen with the GL icon and green status bar.
