# Decisions

The choices behind GoalLine, including what we chose not to build, and why.

## What we built, and why this way

**Everyone competes against their own plan.** A student studying for the CPA and a friend training for a marathon can't be compared on raw numbers. So each GoalWeek, a member's share of their own planned points sets their band, and two bands are compared in the matchup. Plain code does the math; the AI never scores.

**Bands at 90%, 100% and 150%.** In line starts at 90%, so a slightly short week still counts as showing up; past the line is anything over the plan; Smashed is 150% and up, and needs proof or the opponent's OK. A later brief suggested 95% and 105%; we kept 90/100/150 because it reads simply (close to your plan, past your plan, half again past it) and leaves room for a hard week.

**Photo finishes instead of ties.** Two members in the same band used to tie. Then the higher credited percent took a close win (2 points) and the other a close loss (1), 0 to 6 a week. **Now (weekly stars):** a win is 2 stars, a photo finish win 1, a tie 1 each, any loss 0, so a week is 0 to 5 stars and more stars always means the higher credited percent. Photo finishes compare credited percents exactly, so a tie only happens when they're truly equal, and byes earn line stars only. All values live in one config.

**Code sets the minimum pace, not the AI.** Asking the coach for an easy plan shouldn't work. Code sets each week's minimum planned points from the goal and the deadline, and raises any week below it. Plans can always be harder. Two lighter weeks a season are allowed, with a reason the league sees.

**Proof and disputes, not policing.** Check-ins are self-reported. Instead of trying to verify everything, GoalLine makes everything visible to the league, asks for proof only for the biggest claims, and lets the opponent or league owner dispute a check-in. People settle disputes; the AI doesn't.

**Server-rendered pages with light JavaScript.** Blade, Turbo Drive and a few small modules instead of a single-page app. Pages work without JavaScript, every number comes from the server, and there's one codebase to test.

**One small server.** A single Elastic Beanstalk instance with RDS, S3 and CloudFront is enough for a private beta, and keeps cost and moving parts low. Sessions, cache and jobs live in the database, so a server replacement loses nothing.

**Brevo for email.** Amazon SES needs a verified domain, which GoalLine doesn't have yet. Brevo's SMTP works with a verified sender address, and moving to SES later is a settings change.

**Plus Jakarta Sans everywhere.** The brief named Space Grotesk and Public Sans, but the GoalLine wordmark is Plus Jakarta Sans. Using the wordmark's typeface throughout makes the app and the brand feel like one thing.

**A demo that can't break or cost money.** Try the demo builds a private, throwaway world per visitor, uses saved sample answers for the coach, sends no email, and deletes itself after a day. Judges and classmates can tap anything.

**Switches for outbound messages.** Each kind of email (reminders, nudges, recaps, other notifications) and push can be turned off on its own, without breaking sign-up, verification or password reset. In-app notifications always work.

**Snapshots for shared cards.** A weekly result card is a frozen snapshot of the owner's own summary, so a shared link never changes or reveals more later, and it can be turned off.

## What we chose not to build

- **No AI judging.** The AI never scores, never rules on disputes and never sets the minimum pace. Those are rules people need to trust, so they're plain, tested code.
- **No GPS, maps or wearable syncing** (Strava, Apple Health). That's more privacy risk than a friendly league needs, and location data in photos is stripped.
- **No money, stakes or betting**, and no public leaderboards or follower counts. GoalLine is about the process with people you know, not an audience.
- **No public user directory.** People find each other by name, username or exact email, and a setting hides you from name search.
- **No medical advice**, and no diagnosing from intake answers. Physical goals get conservative plans, rest days and a "check with a doctor" note.
- **No native apps.** GoalLine is an installable web app with push notifications: one codebase that works on every phone.
- **No AI-written numbers anywhere.** Recaps, share cards and league tables show only what the scoring code computed.
- **No Playwright in CI (for now).** The browser suite needs real WebKit and a running server, so it runs locally (`npm run qa`); CI runs the 600+ PHP tests on every push.

## Known limitations

- Check-ins are self-reported. GoalLine makes faking visible and not worth much, but it can't prove someone studied for an hour.
- The minimum-pace rules are deliberate, documented rules of thumb, not a model of every goal.
- Weeks follow each member's own time zone, so in a league spread across time zones, weeks can close a few hours apart.
- One server, one queue worker and one scheduler: sized for a private beta.
- Some inline event handlers remain, so the Content-Security-Policy allows inline scripts; it still blocks scripts from anywhere else.
