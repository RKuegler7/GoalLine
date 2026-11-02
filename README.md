# GoalLine

**Get in line with your goals. We get more done together.**

**Live site:** http://goalline.us-east-1.elasticbeanstalk.com

GoalLine is a fantasy-sports-style accountability app. It turns real, deadline-driven goals (an exam, a thesis, a marathon) into a friendly league among friends, teammates or classmates. An AI coach builds a weekly roadmap for each goal. Daily check-ins feed a head-to-head matchup each week, and a plain, tested score shows whether you're actually in line to hit your real deadline.

GoalLine is about the process, not just the end result. The goal is what the daily work builds, and doing it with friends makes it stick. **Trust the process. Show up today.**

*Kuegs: CUA Busch School AI Vibe Coding Contest, Fall 2026*

## How fairness works

GoalLine doesn't police anyone. It makes cheating visible, hard to do quietly, and not worth much:

- **You play against your own plan.** Each week is scored on the share of your planned points you earned: Short of the Line (under 90%), In Line (90–100%), Past the Line (over 100%), Smashed the Line (150%+). The AI never decides a score.
- **You can't pad the plan.** Every roadmap edit is logged and checked by the coach. Personal tasks are capped and labeled "Added by [name]". Group tasks need two thirds of the league to vote yes.
- **You can't rewrite the past.** Check-ins are for today or yesterday only, timestamped, and shown to the league. Numbers have to fall in a sensible range.
- **Big claims need proof.** A Smashed week counts only with a photo ("Show your work") or the opponent's OK. Photos have location data stripped.
- **The league keeps it honest.** Matchups are open, anyone can ask to see the work, and posts can be reported.

The full plain-English version is in [docs/FAIRNESS.md](docs/FAIRNESS.md). The exact rules are in [docs/SPEC.md](docs/SPEC.md).

## What's in it

- **Goals and AI roadmaps:** set a goal, answer a few questions written for that goal, upload a study or training plan if you have one, and get weekly GoalWeeks with a focus, a milestone and daily tasks. Refine the plan in your own words with a preview before anything changes, edit it by hand, and restore earlier versions from plan history. You get a 7-day trial before a goal locks in, plus end-of-week reviews with a coach's tip.
- **Clubhouse:** Today's Gameplan, Plan my week, habits, the daily check-in streak, the Kickoff checklist and the Friends feed.
- **GoalLeagues:** a swipeable GoalWeek Matchup page (who leads, bands, points to the next band), round-robin weekly matchups, standings, league chat, recaps, season awards, group tasks with a league vote, personal tasks, and invites by link, code, email or username.
- **Fair play:** range checks, the today-or-yesterday rule, proof photos with a Verified check, the Smashed confirmation, "Show your work" requests and an open matchup timeline.
- **Highlights:** Instagram-style check-in posts with Proof only, League, or League + friends visibility; cheers, comments and @mentions; finished-goal posts, share cards and reports.
- **People:** friends, people search (with a "Let people find me by name" setting), nudges and challenges.
- **My Locker:** profile photo and cover, stats, form guide, activity heatmap, badges and trophy case.
- **Staying on track:** Inbox, email and phone push notifications, morning and evening reminders, a calendar feed, and an installable app.
- **Private beta:** invite codes, AI spend caps, an admin page (usage, AI spend, invites, photo storage check) and in-app feedback.
- **One-click demo:** "Try the demo" opens a private pretend world with friends, two leagues mid-season and a live matchup.

## Build status

| Phase | What | Status |
|---|---|---|
| A | Fair play, proof and Smashed confirmation | Done |
| B | Media posts, feeds, friends and cheers | Done |
| C | Group tasks with a league vote, personal tasks | Done |
| D | Demo seed, fairness docs, deploy checklist | Done |
| E | Goal-to-league flow, league invites, people search | Done |
| F | Goal-specific intake questions feeding the plan | Done |
| G | Refine the plan with prompts, preview, manual edits, plan history | Done |
| H | Objective recurring tasks, Today view, week grid | Next |

The phase details are in [docs/NEXT_BUILD.md](docs/NEXT_BUILD.md) (A–D) and [docs/NEXT_BUILD_2.md](docs/NEXT_BUILD_2.md) (E–H).

## Run it on your computer

Needs PHP 8.4+, Composer and Node.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer run dev
```

Then open http://localhost:8000.

- `php artisan migrate --seed` builds the demo world. Sign in as **john.cardinal@demo.goalline.invalid** with the password **password**. Running `php artisan db:seed` again replaces it with a fresh one dated to today. Demo worlds are deleted after 48 hours, so re-seed if it's gone.
- Without an `ANTHROPIC_API_KEY`, everything works except the AI coach features.
- Emails are written to `storage/logs/laravel.log` (`MAIL_MAILER=log`).
- Run the tests with `php artisan test --compact`.

## Deploying to Elastic Beanstalk

GoalLine runs on one Elastic Beanstalk server (PHP 8.4, Amazon Linux 2023). It uses an RDS MySQL database, a private S3 bucket for photos, Brevo for email, and CloudFront for HTTPS.

### Each deploy

1. Commit everything. If you changed any front-end files, run `npm run build` first and commit `public/build`.
2. Build the zip from the last commit: `git archive --format=zip -o deploy.zip HEAD`. Don't zip with Windows, which writes paths Linux can't read.
3. Elastic Beanstalk console → your environment → **Upload and deploy** → choose `deploy.zip`.
4. The server then does the rest on its own (`.platform/hooks/postdeploy/01-goalline.sh`). It checks the database connection, runs the migrations, caches the config, and restarts the queue worker and the scheduler. There's no separate migrate step.

> **Photos need S3.** The server's own disk is wiped on every deploy. Until S3 is set up, uploaded photos (proof, posts, profile pictures, plan files) vanish on each deploy. The admin page's **Photo storage** card says whether S3 is working.

### Environment properties

Set these in Elastic Beanstalk → **Configuration** → **Updates, monitoring and logging** → **Environment properties**. Never put them in the repo.

| Setting | What to put |
|---|---|
| `APP_KEY` | From `php artisan key:generate --show` |
| `APP_URL` | The site's https address (the CloudFront one) |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The RDS database |
| `AWS_BUCKET`, `AWS_DEFAULT_REGION` | The S3 bucket name and `us-east-1`. Leave the AWS keys blank: the server's role grants access |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Brevo: `smtp-relay.brevo.com`, your Brevo SMTP login and SMTP key, and your verified Brevo sender |
| `ANTHROPIC_API_KEY` | For the AI coach and caption suggestions |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | For phone push notifications |
| `ADMIN_EMAILS` | Who can open `/admin` |

Already set by `.ebextensions/01-laravel.config`: production mode, MySQL, database sessions, cache and queue, `UPLOADS_DISK=s3`, Brevo's port, `BETA_INVITE_ONLY=true` and `BEHIND_CLOUDFRONT=true`.

Optional, with sensible defaults: `ANTHROPIC_MODEL`, `PROOF_DISK` (default `uploads`, the same S3 bucket), `CAPTION_SUGGESTIONS`, `DEMO_ENABLED`, `DEMO_TTL_HOURS`, the AI caps (`AI_DAILY_REQUESTS_PER_MEMBER`, `AI_DAILY_BUDGET_USD`, `AI_MONTHLY_BUDGET_USD`) and the demo AI allowance (`DEMO_AI_REQUESTS_PER_VISITOR`, `DEMO_AI_REQUESTS_PER_DAY`).

Migrations added since Phase A run on the next deploy: `add_fair_play`, `add_friends_posts_and_reports`, `add_group_and_personal_tasks`, `add_league_invites_and_discoverable`, `add_demo_sandbox_to_users`, `create_goal_intakes_table` and `create_plan_versions_and_refinements_tables`.

### Setting up S3 for photos (one time)

1. **Make the bucket.** AWS console → **S3** → **Create bucket**.
   - Name it something like `goalline-uploads-kuegs`, in the region **US East (N. Virginia) us-east-1**.
   - Leave **ACLs disabled** and **Block all public access** turned on (both are the defaults). Photos stay private, and GoalLine shows them only to people allowed to see them.
   - Click **Create bucket**.
2. **Let the server use it.** AWS console → **IAM** → **Roles** → open **aws-elasticbeanstalk-ec2-role** → **Add permissions** → **Create inline policy** → **JSON**. Paste this, with your bucket name in place of `BUCKET`:

   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       { "Effect": "Allow", "Action": ["s3:ListBucket"], "Resource": "arn:aws:s3:::BUCKET" },
       { "Effect": "Allow", "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"], "Resource": "arn:aws:s3:::BUCKET/*" }
     ]
   }
   ```

   Name the policy `goalline-uploads` and save it.
3. **Tell GoalLine the bucket.** In the Elastic Beanstalk environment properties, set `AWS_BUCKET` to the bucket name and `AWS_DEFAULT_REGION` to `us-east-1`. Leave `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` empty. Click **Apply** and wait for the environment to go green.
4. **Check it.** Open `/admin` on the live site. The **Photo storage** card should say S3 is working. Then post a check-in photo, deploy again, and make sure the photo is still there.

### Platform limits to set once

- An Anthropic Console spend limit, the real backstop behind GoalLine's own AI caps.
- An AWS Budget with email alerts.
