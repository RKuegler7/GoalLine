# GoalLine QA report

Generated 2026-11-09 21:48 UTC by `npm run qa`. Screenshots are in `qa/screenshots/` (not committed).

| Profile | Demo script | Time |
| --- | --- | --- |
| iPhone 14 (WebKit) | Passed | 91s |
| Small Android (Chromium) | Passed | 40s |
| Desktop (Chromium) | Passed | 35s |

**0 blockers, 0 important, 2 nice to have.**

Checks on every screen: failed requests and 500s, console errors, horizontal overflow, tap targets under 44 px (phones), text under 12 px, axe accessibility (alt text, form labels, contrast, names), and in-place actions that reload the page or jump to the top.

> WebKit in Playwright is close to iPhone Safari but it isn't a real iPhone: no real touch physics, home indicator, Safari toolbars, or iOS photo picker. Use the checklist at the end on a real phone before a release.

## Blockers (0)

None.

## Important (0)

None.

## Nice to have (2)

| Screen | Profiles | Finding | Suggested fix |
| --- | --- | --- | --- |
| Matchup pager | iPhone 14 (WebKit), Small Android (Chromium) | **Tap target under 44 px**: input "Why? (optional)" 18×44 | Give it at least 44 × 44 px (padding, min-h-11) or a larger hit area. |
| Task sheet | iPhone 14 (WebKit), Small Android (Chromium) | **Tap target under 44 px**: input "Why? (optional)" 18×44 | Give it at least 44 × 44 px (padding, min-h-11) or a larger hit area. |

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
