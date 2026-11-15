# Accessibility statement

GoalLine aims to meet the **Web Content Accessibility Guidelines (WCAG) 2.1 at level AA** on phones and desktops. This page says what's in place, how it's checked, and what isn't perfect yet.

## What's in place

- **Contrast:** brand colours are paired to pass AA. Gold text uses a dark gold (`#85651c`) on light backgrounds, and light trophy gold on Club Green; white text on Club Green is 6.4:1.
- **Touch and text size:** tap targets are at least 44 × 44 px on phones (small links get a larger invisible hit area), and at least 24 px anywhere. Text is at least 11 px, and 13 px or more for anything people read. Nothing important is cut off with an ellipsis; long names, titles and captions wrap.
- **Structure:** every page has one main heading, landmarks, labelled forms and buttons, and links whose spoken name starts with their visible text (for voice control).
- **Tables:** the Standings and Challenge tables have captions, row headers and `aria-sort`, scroll sideways inside their own box (the page never scrolls sideways), and the Last 5 circles have text alternatives such as "Last 5: W Past, L Short".
- **Sheets and dialogs:** the Check in sheet, task and player sheets, Help and the Judge's tour open as dialogs, close with Escape or Back, and return focus to where you were.
- **Live updates:** check-ins, sorting and tour progress update in place and are announced to screen readers.
- **Motion:** with "Reduce motion" turned on, the logo animation, celebrations and slides are replaced by simple fades or nothing.
- **Colour is never the only signal:** bands have names, Matchup results have letters, Challenge days have symbols and text.

## How it's checked

- **axe-core** (WCAG 2.1 A and AA) runs on every screen of the Try the demo script in the Playwright QA suite, on an iPhone (WebKit), a small Android phone and a desktop, plus dedicated checks on the landing page, the Standings and Challenge tables, the profile header and the GoalLeagues tabs. Serious and critical findings: none on the screens checked.
- **Lighthouse** accessibility (mobile): 100 on the landing page, Matchup and Standings; 97 on the dashboard for one link inside a sentence, which WCAG exempts from the target-size rule.
- Layout checks at 375, 390 and 430 px wide for sideways scroll, small tap targets, small text and cut-off text.

## Known gaps

- Automated checks can't catch everything; GoalLine hasn't yet had a full manual audit with VoiceOver and TalkBack by people who use them every day.
- Shared result cards are images. The public card page describes the result in its text alternative, but the card's design itself isn't readable by a screen reader.
- The swipeable Matchup pager shows one task name per line and truncates very long ones; tapping a task opens it in full.

## Feedback

If something in GoalLine is hard to use with your setup, please tell us through the feedback form on the [About page](https://ddhxw7holo4vs.cloudfront.net/about#contact). We'll fix what we can.
