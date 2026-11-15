<?php

namespace App;

/**
 * Challenge templates: generic names only. Each fills in the builder (type, duration, rules and mode); everything
 * stays editable until kickoff.
 */
class ChallengeTemplates
{
    /**
     * @return array<string, array{name: string, blurb: string, type: string, duration: string, rules: array<string, mixed>, mode: string, allowed_misses: int, sundays_off: bool, description?: string}>
     */
    public static function all(): array
    {
        return [
            'lent' => [
                'name' => 'Lent', 'blurb' => 'Ash Wednesday to Holy Saturday, worked out for the year.',
                'type' => 'abstain', 'duration' => 'lent', 'rules' => ['label' => 'Give something up for Lent'],
                'mode' => 'flexible', 'allowed_misses' => 0, 'sundays_off' => true,
                'description' => 'Give something up, or swap to a daily practice like prayer or reflection.',
            ],
            'discipline-75' => [
                'name' => '75-Day Discipline Challenge', 'blurb' => 'A daily checklist for 75 days. Miss a day, start the streak again.',
                'type' => 'daily', 'duration' => '75', 'rules' => ['items' => ['Work out 45 minutes', 'Stick to your eating plan', 'Drink your water', 'Read 10 pages']],
                'mode' => 'strict', 'allowed_misses' => 0, 'sundays_off' => false,
            ],
            'steps' => [
                'name' => '10,000 steps a day', 'blurb' => 'Hit the number every day.',
                'type' => 'target', 'duration' => '30', 'rules' => ['label' => 'Steps', 'threshold' => 10000, 'unit' => 'steps', 'per' => 'day', 'max' => 100000],
                'mode' => 'flexible', 'allowed_misses' => 3, 'sundays_off' => false,
            ],
            'no-shave' => [
                'name' => 'No-Shave Month', 'blurb' => 'A whole calendar month, razor-free.',
                'type' => 'abstain', 'duration' => 'month', 'rules' => ['label' => 'No shaving'],
                'mode' => 'flexible', 'allowed_misses' => 0, 'sundays_off' => false,
            ],
            'dry-month' => [
                'name' => 'Dry month', 'blurb' => 'A calendar month without alcohol.',
                'type' => 'abstain', 'duration' => 'month', 'rules' => ['label' => 'No alcohol'],
                'mode' => 'flexible', 'allowed_misses' => 0, 'sundays_off' => false,
            ],
            'read-20' => [
                'name' => 'Read 20 pages a day', 'blurb' => 'A steady habit, page by page.',
                'type' => 'target', 'duration' => '30', 'rules' => ['label' => 'Pages', 'threshold' => 20, 'unit' => 'pages', 'per' => 'day', 'max' => 500],
                'mode' => 'flexible', 'allowed_misses' => 3, 'sundays_off' => false,
            ],
            'workout-4' => [
                'name' => 'Workout 4 times a week', 'blurb' => 'Any four days, every week.',
                'type' => 'weekly', 'duration' => '30', 'rules' => ['label' => 'Workout', 'times' => 4],
                'mode' => 'flexible', 'allowed_misses' => 0, 'sundays_off' => false,
            ],
            'no-social' => [
                'name' => 'No social media after 10 p.m.', 'blurb' => 'Evenings back, one night at a time.',
                'type' => 'abstain', 'duration' => '30', 'rules' => ['label' => 'No social media after 10 p.m.'],
                'mode' => 'flexible', 'allowed_misses' => 2, 'sundays_off' => false,
            ],
            'reflection' => [
                'name' => 'Daily reflection or prayer', 'blurb' => 'A few quiet minutes every day.',
                'type' => 'daily', 'duration' => '40', 'rules' => ['items' => ['Take time to reflect or pray']],
                'mode' => 'flexible', 'allowed_misses' => 3, 'sundays_off' => false,
            ],
            'cold-shower' => [
                'name' => 'Cold shower streak', 'blurb' => 'Every day, no skipping.',
                'type' => 'daily', 'duration' => '30', 'rules' => ['items' => ['Take a cold shower']],
                'mode' => 'strict', 'allowed_misses' => 0, 'sundays_off' => false,
            ],
            'water' => [
                'name' => 'Drink 8 glasses of water', 'blurb' => 'Count them through the day.',
                'type' => 'target', 'duration' => '30', 'rules' => ['label' => 'Water', 'threshold' => 8, 'unit' => 'glasses', 'per' => 'day', 'max' => 40],
                'mode' => 'flexible', 'allowed_misses' => 3, 'sundays_off' => false,
            ],
        ];
    }

    /**
     * @return array{name: string, blurb: string, type: string, duration: string, rules: array<string, mixed>, mode: string, allowed_misses: int, sundays_off: bool, description?: string}|null
     */
    public static function find(?string $key): ?array
    {
        return self::all()[$key ?? ''] ?? null;
    }
}
