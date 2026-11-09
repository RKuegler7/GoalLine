# GoalLine QA report

Generated 2026-11-09 18:27 UTC by `npm run qa`. Screenshots are in `qa/screenshots/` (not committed).

| Profile | Demo script | Time |
| --- | --- | --- |
| iPhone 14 (WebKit) | Passed | 100s |
| Small Android (Chromium) | Passed | 58s |
| Desktop (Chromium) | Passed | 46s |

**0 blockers, 0 important, 30 nice to have.**

Checks on every screen: failed requests and 500s, console errors, horizontal overflow, tap targets under 44 px (phones), text under 12 px, axe accessibility (alt text, form labels, contrast, names), and in-place actions that reload the page or jump to the top.

> WebKit in Playwright is close to iPhone Safari but it isn't a real iPhone: no real touch physics, home indicator, Safari toolbars, or iOS photo picker. Use the checklist at the end on a real phone before a release.

## Blockers (0)

None.

## Important (0)

None.

## Nice to have (30)

| Screen | Profiles | Finding | Suggested fix |
| --- | --- | --- | --- |
| Matchup pager | iPhone 14 (WebKit), Small Android (Chromium) | **Tap target under 44 px**: input "Why? (optional)" 18×44 | Give it at least 44 × 44 px (padding, min-h-11) or a larger hit area. |
| Task sheet | iPhone 14 (WebKit), Small Android (Chromium) | **Tap target under 44 px**: input "Why? (optional)" 18×44 | Give it at least 44 × 44 px (padding, min-h-11) or a larger hit area. |
| About | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "Demo mode" 9.6px; "Short of the line" 10.4px; "· 0 pts" 10.4px; "In line" 10.4px; "· 1 pt" 10.4px; "Past the line" 10.4px; "· 2 pts" 10.4px; "Smashed the line" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Dashboard | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "This GoalWeek" 9.92px; "Total points" 9.92px; "In-line streak" 9.92px; "GoalLeague record" 9.92px; "Demo mode" 9.6px; "Academic · 62 days to go" 9.92px; "Not started" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Friends | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "7" 9.6px; "Highlights" 10.88px; "My Locker" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Friends | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "GoalWeek 1" 10.88px; "In line, close call" 10.4px; "· 1 pt" 10.4px; "GoalWeek 2" 10.88px; "In line" 10.4px; "GoalWeek 3" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal after a check-in | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "GoalWeek 1" 10.88px; "In line, close call" 10.4px; "· 1 pt" 10.4px; "GoalWeek 2" 10.88px; "In line" 10.4px; "GoalWeek 3" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Goal after proof | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "GoalWeek 1" 10.88px; "In line, close call" 10.4px; "· 1 pt" 10.4px; "GoalWeek 2" 10.88px; "In line" 10.4px; "GoalWeek 3" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeague | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Live" 9.6px; "Not started" 10.4px; "Short of the line" 10.4px; "· 0 pts" 10.4px; "Voting · GoalWeek 7 · Solid · 2 pts" 9.6px; "Past the line" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeague after a vote | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Live" 9.6px; "Not started" 10.4px; "Short of the line" 10.4px; "· 0 pts" 10.4px; "Past the line" 10.4px; "· 2 pts" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeagues | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Day 13 of 21" 9.6px; "In season" 10.4px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "15" 9.6px; "Highlights" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| GoalLeagues | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Day 13 of 21" 9.6px; "In season" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Highlights | iPhone 14 (WebKit) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "1:28 pm" 9.6px; "Crossed the GoalLine" 9.6px; "Nov 8, 8:28 am" 9.6px; "Nov 6, 10:28 am" 9.6px; "Nov 8, 10:28 am" 9.6px; "Nov 7, 12:28 pm" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Highlights | Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "1:30 pm" 9.6px; "Crossed the GoalLine" 9.6px; "Nov 8, 9:29 am" 9.6px; "Nov 6, 10:29 am" 9.6px; "Nov 8, 8:29 am" 9.6px; "Nov 7, 7:29 am" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Highlights | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "1:31 pm" 9.6px; "Crossed the GoalLine" 9.6px; "Nov 8, 10:30 am" 9.6px; "Nov 6, 8:30 am" 9.6px; "Nov 8, 7:30 am" 9.6px; "Nov 7, 12:30 pm" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Inbox | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "Demo mode" 9.6px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "7" 9.6px; "Highlights" 10.88px; "My Locker" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Inbox | Desktop (Chromium) | **Text under 12 px**: "Demo mode" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Matchup after a dispute | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Your matchup" 9.92px; "Live" 9.6px; "Above minimum pace" 10.4px; "Not started" 10.4px; "Ahead" 10.4px; "Short of the line" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Matchup pager | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Your matchup" 9.92px; "Live" 9.6px; "Above minimum pace" 10.4px; "Not started" 10.4px; "Ahead" 10.4px; "Short of the line" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| My Locker | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "Demo mode" 9.6px; "Less" 10.4px; "More" 10.4px; "Nov 9, 2026" 10.4px; "Smashed the line in a GoalWeek." 10.4px; "10 GoalWeeks in line in a row." 10.4px; "30 habit check-ins." 10.4px; "Crossed the GoalLine on a goal." 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Notifications and calendar | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "7" 9.6px; "Highlights" 10.88px; "My Locker" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Notifications and calendar | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| People search | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "7" 9.6px; "Highlights" 10.88px; "My Locker" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| People search | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Plan my week | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "GoalWeek 6 · ends Sun, Nov 15" 9.92px; "Not started" 10.4px; "Habits" 10.4px; "Clubhouse" 10.88px; "GoalLeagues" 10.88px; "15" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |
| Plan my week | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "GoalWeek 6 · ends Sun, Nov 15" 9.92px; "Not started" 10.4px; "Habits" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Task sheet | iPhone 14 (WebKit), Small Android (Chromium), Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "Your matchup" 9.92px; "Live" 9.6px; "Above minimum pace" 10.4px; "Not started" 10.4px; "Ahead" 10.4px; "Short of the line" 10.4px | Use at least 12 px (text-xs) for anything people need to read. |
| Today | iPhone 14 (WebKit), Small Android (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "M" 9.6px; "T" 9.6px; "W" 9.6px; "F" 9.6px; "S" 9.6px; "Clubhouse" 10.88px | Use at least 12 px (text-xs) for anything people need to read. |
| Today | Desktop (Chromium) | **Text under 12 px**: "9+" 9.92px; "Demo mode" 9.6px; "M" 9.6px; "T" 9.6px; "W" 9.6px; "F" 9.6px; "S" 9.6px | Use at least 12 px (text-xs) for anything people need to read. |

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
