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
