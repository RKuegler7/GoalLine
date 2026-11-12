<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admins
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails of the people who run GoalLine. They can open the
    | admin pages (beta invites, usage, AI spend) and get alerts.
    |
    */

    'admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))))),

    // The public "email us" address on the About page. Shown only when set, so no personal address is published.
    'contact_email' => env('CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Outbound messaging switches
    |--------------------------------------------------------------------------
    |
    | Pause whole kinds of email (and push) without touching anything else. Off
    | means the email isn't sent, but the in-app Inbox notification still is.
    | In production every switch is OFF unless its environment property is set
    | to true; locally they default to on. Transactional email (verification,
    | password reset, league invites a member sends by email) is never behind a
    | switch. Demo accounts never get email at all. See App\Outbound.
    |
    */

    'messaging' => [
        'email_reminders' => (bool) env('GOALLINE_EMAIL_REMINDERS', env('APP_ENV', 'production') !== 'production'),
        'email_nudges' => (bool) env('GOALLINE_EMAIL_NUDGES', env('APP_ENV', 'production') !== 'production'),
        'email_recaps' => (bool) env('GOALLINE_EMAIL_RECAPS', env('APP_ENV', 'production') !== 'production'),
        'email_notifications' => (bool) env('GOALLINE_EMAIL_NOTIFICATIONS', env('APP_ENV', 'production') !== 'production'),
        'push' => (bool) env('GOALLINE_PUSH', env('APP_ENV', 'production') !== 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI spend limits
    |--------------------------------------------------------------------------
    |
    | Hard caps on Claude API calls. When a cap is reached, AI features pause
    | (roadmaps, coach checks, plan reading, weekly tips) and everything else
    | keeps working. Costs are estimates from token counts; set the prices to
    | match https://www.anthropic.com/pricing for the model in use. Also set a
    | spend limit in the Anthropic Console, which is the real backstop.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Weekly stars
    |--------------------------------------------------------------------------
    |
    | The one place the star values live. The scoring code (GoalWeekResult,
    | MatchupOutcome) and every page, sheet, FAQ answer and card read these, so
    | the rules shown can never drift from the rules scored. A week is worth
    | line stars (your band) plus matchup stars: 0 to 5.
    |
    */

    'stars' => [
        'line' => ['short' => 0, 'in_line' => 1, 'past' => 2, 'smashed' => 3],
        'matchup' => ['win' => 2, 'photo_finish_win' => 1, 'tie' => 1, 'loss' => 0, 'bye' => 0],
    ],

    'ai' => [
        'daily_requests_per_member' => (int) env('AI_DAILY_REQUESTS_PER_MEMBER', 20),
        'daily_budget_usd' => (float) env('AI_DAILY_BUDGET_USD', 2),
        'monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 20),
        'input_usd_per_million_tokens' => (float) env('AI_INPUT_USD_PER_MILLION_TOKENS', 5),
        'output_usd_per_million_tokens' => (float) env('AI_OUTPUT_USD_PER_MILLION_TOKENS', 25),
        'alert_at_share' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Private beta
    |--------------------------------------------------------------------------
    |
    | While invite_only is on, signing up needs an invite code.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Hosting
    |--------------------------------------------------------------------------
    |
    | On when GoalLine is served through CloudFront: every request is treated as
    | arriving at APP_URL (https), whatever address the server itself sees.
    |
    */

    'behind_cloudfront' => (bool) env('BEHIND_CLOUDFRONT', false),

    // How long an email verification link works.
    'verification_link_hours' => (int) env('VERIFICATION_LINK_HOURS', 48),

    /*
    | "Suggest a caption" on check-in posts. Shown only when an Anthropic key is set; uses the AI caps above.
    */

    'caption_suggestions' => (bool) env('CAPTION_SUGGESTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | One-click demo
    |--------------------------------------------------------------------------
    |
    | /demo signs a visitor into their own pretend world: friends, two
    | GoalLeagues mid-season, a challenge, and posts. Demo worlds can't email
    | anyone or reach real members, get a small AI allowance, and are deleted
    | after ttl_hours.
    |
    */

    'demo' => [
        'enabled' => (bool) env('DEMO_ENABLED', true),
        'ttl_hours' => (int) env('DEMO_TTL_HOURS', 24),
        // How many demos one visitor (by IP) can start an hour. The QA suite raises it for its own local server.
        'starts_per_hour' => (int) env('DEMO_STARTS_PER_HOUR', 10),
        // Demo visitors get the coach's saved sample answers, so the demo never fails or spends money. True uses the
        // real coach within the allowance below.
        'live_ai' => (bool) env('DEMO_LIVE_AI', false),
        'ai_requests_per_visitor' => (int) env('DEMO_AI_REQUESTS_PER_VISITOR', 3),
        'ai_requests_per_day' => (int) env('DEMO_AI_REQUESTS_PER_DAY', 40),
    ],

    'beta' => [
        'invite_only' => (bool) env('BETA_INVITE_ONLY', true),
    ],

];
