# Reliability

How GoalLine stays up and quiet, and the switches you control from AWS.

## Turning on notifications

GoalLine can pause whole kinds of outbound messages without breaking sign-up. Each switch is an Elastic Beanstalk environment property. In production, a switch is **off unless you set it to `true`**. When a switch is off, the email (or push) is skipped, but the notification still lands in the member's Inbox in the app, so nothing is lost.

| Environment property | What it sends when `true` |
|---|---|
| `GOALLINE_EMAIL_REMINDERS` | Daily reminder emails (morning gameplan, evening check-in) |
| `GOALLINE_EMAIL_NUDGES` | Nudges from friends and league mates, and "Show your work" proof requests |
| `GOALLINE_EMAIL_RECAPS` | The Monday weekly recap, GoalWeek results, GoalLeague recaps, challenge results and coach tips |
| `GOALLINE_EMAIL_NOTIFICATIONS` | Everything else: disputes, group tasks, friend requests, invite activity, cheers, comments, mentions, badges |
| `GOALLINE_PUSH` | Phone push notifications (reserved for later) |

Always sent, never behind a switch: **email verification**, **password reset**, and **league invites a member sends by email**. Demo accounts never get email of any kind.

To turn one on:

1. Open the AWS console, go to **Elastic Beanstalk**, and pick the GoalLine environment.
2. Click **Configuration** in the left menu.
3. Under **Updates, monitoring, and logging** (or **Software** on older consoles), click **Edit**.
4. Scroll to **Environment properties** and click **Add environment property**.
5. Name: `GOALLINE_EMAIL_RECAPS` (for example). Value: `true`.
6. Click **Apply** at the bottom. Beanstalk restarts the app with the new setting in a minute or two.
7. Open GoalLine's **Admin** page. The **Outbound messages** card shows each switch as On or Paused.

To pause one again, set its value to `false` (or remove the property) and click **Apply**.

The switches live in `config/goalline.php` (`messaging`), and every email path asks one helper, `App\Outbound`, before sending.

## Is it up? `/health`, preflight and the smoke test

- **`/health`** answers `200 {"status":"ok"}` when the app, database, cache, photo storage (S3) and background queue all work, and `503` with `"failing"` next to whatever doesn't. It never shows a host name, bucket, key or error message, and it skips sessions and cookies, so it still answers when the database is down. (`/up` is Laravel's simpler "the app booted" check.)
- **`php artisan goalline:preflight`** checks every production setting and prints `PASS`, `WARN` or `FAIL` per item, never a secret value: APP_KEY, the https APP_URL, APP_DEBUG off, the MySQL database, database sessions, cache and queue, secure cookies, real mail, S3 bucket and region, the Anthropic key, ADMIN_EMAILS, and whether the server can draw PNG share cards (GD). Every deploy runs it automatically; its output is in the Beanstalk log `eb-hooks.log`. Run it by hand on the server with `sudo /usr/local/bin/goalline-artisan goalline:preflight`.
- **`npm run smoke -- --url https://YOUR-ADDRESS`** (on your computer) checks the live site end to end in a phone-sized browser: `/health`, the home page signed out, Try the demo, checking off a task without a reload, the Matchups tab, and the privacy page. Any 500, failed request or console error fails it. It's safe on production: it only uses a fresh demo account (made-up data that deletes itself).

## What the code does about failures

- **Error pages:** branded 403, 404, 419, 429, 500 and 503 pages with no technical details. If `APP_DEBUG` is ever `true` in production, the app turns it off at startup and logs a critical warning, so stack traces never show.
- **Error emails:** at most one email per distinct error per hour (`App\Services\ErrorAlerts`). Handled cases never email: validation errors, 404s, expired or invalid signed links, sign-in redirects, deadlocks that a retry fixed, and missing photo files (those are logged, and a placeholder is shown).
- **Database:** every multi-step write runs in `App\Atomic` (three tries on a deadlock). The busiest screens (dashboard, matchups, Highlights) have query-count tests, so they don't make one query per goal, post or player. Migrations only ever add; re-running them on a deploy changes nothing.
- **Claude API:** every call has a hard timeout (10 minutes for planning, which runs in the background queue; 20 seconds for the caption helper, which someone is waiting on) and at most 2 retries. Each has a fallback: built-in intake questions, "try again" on a plan, write your own caption. Spend is capped per member per day, per day and per month (`AiBudget`), with an email to admins at 80%. Demo accounts use saved sample answers and never call the live AI (`DEMO_LIVE_AI` stays `false`).
- **Mail:** a 15-second timeout on the mail server, and each notification email is retried up to 3 times. The Inbox copy is saved separately, so a mail outage loses nothing in the app.
- **Security:** a Content-Security-Policy (scripts, styles, fonts and connections from GoalLine only; no plugins; no framing by other sites; forms post only to GoalLine), HSTS, `nosniff`, a strict referrer policy, and no camera, microphone or location access. Session cookies are https-only and `HttpOnly`. Sign-in, sign-up, password reset, people search, invites, demo starts, card links and the AI helpers are rate limited. Uploads are checked for type and size, and photos have their location data stripped.
- **Secrets:** a scan of the whole git history (all 91 commits on 2026-11-10) for Anthropic, AWS, Brevo, GitHub and private keys, filled-in `APP_KEY` or `VAPID_PRIVATE_KEY` values, and committed `.env` files found nothing. Only `.env.example`, with empty values, has ever been committed.

## Accessibility

- Brand colors meet WCAG AA for text: Soft Ink on white or ivory (15:1), Club Green on white (6.5:1), ivory on Club Green (6.2:1), ink on Grass Green (6.0:1) and on Championship Gold (7.1:1). Grass Green and Gold are too light for text on white (2.5:1 and 2.1:1), so they're used only as fills and icons. Gold text uses `gold-ink` (#85651c, 5.4:1) on light backgrounds and `gold-light` (#f3dca0, 4.8:1) on Club Green.
- The QA suite runs axe (WCAG 2.1 A and AA) on every screen in every profile; serious and critical findings are reported as "important".
- Toasts, band changes and loaders announce themselves to screen readers (`aria-live`). The matchup pager works with the arrow keys, sheets are native dialogs (Escape closes them), and every animation respects "Reduce motion".

## Your AWS checklist (do these in the console)

Backups:

1. **RDS automated backups on, 7-day retention.** RDS, Databases, `goalline-db`, Modify. Under Backup, set the backup retention period to 7 days. Continue, Apply immediately.
2. **A manual snapshot before the freeze.** RDS, Databases, `goalline-db`, Actions, Take snapshot. Name it `goalline-before-freeze-2026-11-16`.
3. **Another snapshot after the final deploy.** Same steps, named `goalline-final-2026-11-16`.

Alerts:

4. **Beanstalk health email.** Elastic Beanstalk, your environment, Configuration, Updates, monitoring, and logging, Edit. Under Email notifications, enter your email and Apply. Confirm the subscription email AWS sends you.
5. **Keep the billing alert.** Billing and Cost Management, Budgets: the existing budget with an email alert stays on.

Uptime:

6. **A free uptime monitor on `/health`.** For example UptimeRobot (free plan): Add New Monitor, type HTTP(s), URL `https://YOUR-CLOUDFRONT-ADDRESS/health`, interval 5 minutes, alert contact your email. It alerts when `/health` returns anything but 200.

During judging:

7. **Don't pause, stop or terminate the Beanstalk environment or the RDS database until judging is finished (after Nov 20).** A stopped database means a broken live demo.
8. **No redeploys after the freeze** (Monday Nov 16, 11:59 p.m. ET).

## Rate limits behind CloudFront

Every limit needs the visitor's real address, not CloudFront's. `App\Http\Middleware\TrustCloudFront` trusts only the server's direct caller (`TRUSTED_PROXIES=REMOTE_ADDR`, the default), which on the single-instance setup is CloudFront, so the address CloudFront appended to `X-Forwarded-For` is the visitor's and a visitor can't fake it. Check it on the Admin page: "Your address as GoalLine sees it" should be your own device's public address. If a load balancer is ever added in front of the server, set `TRUSTED_PROXIES` to its private range (for example `172.31.0.0/16`), add the `CloudFront-Viewer-Address` header to the CloudFront origin request policy, and set `CLOUDFRONT_VIEWER_ADDRESS=true`.

Signed-out limits are per account plus address (sign in: 10 a minute per email, 100 per address; password reset: 5 per email; sign up: 30 per address), and signed-in limits are per member, so a classroom or a judging session on one network doesn't trip them. "Try the demo" opens the visitor's own demo again when they have one (a cookie remembers it for its 24 hours) and only counts new demos: 30 an hour per address, 10 per browser, 400 in total (`DEMO_STARTS_PER_HOUR`, `DEMO_STARTS_PER_BROWSER`, `DEMO_STARTS_PER_HOUR_TOTAL`). Over a limit, people see a calm "Lots of people are trying GoalLine right now" page with a countdown, and no error email is sent.

## Deploy steps the server does by itself

`.platform/hooks/postdeploy/01-goalline.sh` runs on every deploy (and `confighooks` on every settings change): it saves the environment for command-line tasks, checks that the database answers, runs `migrate --force`, then caches the config, routes, views and events (`config:cache`, `route:cache`, `view:cache`, `event:cache`), runs `goalline:preflight`, restarts the queue worker, and installs the scheduler. After changing an environment property, Beanstalk re-runs it, so the cached config picks up the change.
