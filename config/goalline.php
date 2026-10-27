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

    /*
    | "Suggest a caption" on check-in posts. Shown only when an Anthropic key is set; uses the AI caps above.
    */

    'caption_suggestions' => (bool) env('CAPTION_SUGGESTIONS', true),

    'beta' => [
        'invite_only' => (bool) env('BETA_INVITE_ONLY', true),
    ],

];
