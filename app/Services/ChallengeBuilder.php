<?php

namespace App\Services;

use App\ChallengeTemplates;
use App\Lent;
use App\Models\Challenge;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Challenge builder's rules: reads the form (or a template), validates it, and works out the dates. Durations:
 * 7, 30, 40, 75 or 365 days, a calendar month, Lent (Ash Wednesday to Holy Saturday, from Easter), or custom dates
 * up to 400 days. Kickoff is 12:00 a.m. New York time on the start date.
 */
class ChallengeBuilder
{
    public const DURATIONS = [
        '7' => '7 days',
        '30' => '30 days',
        'month' => 'A calendar month',
        '40' => '40 days',
        '75' => '75 days',
        '365' => '365 days',
        'lent' => 'Lent',
        'custom' => 'Custom dates',
    ];

    /**
     * The next Monday (or today, when today is a Monday): the default start.
     */
    public static function defaultStart(): CarbonImmutable
    {
        $today = Challenge::today();

        return $today->isMonday() ? $today : $today->next(CarbonImmutable::MONDAY);
    }

    /**
     * The builder's starting values: from a template, or "Build your own".
     *
     * @return array<string, mixed>
     */
    public static function defaults(?string $templateKey): array
    {
        $template = ChallengeTemplates::find($templateKey);
        $start = self::defaultStart();

        return [
            'template' => $template ? $templateKey : null,
            'title' => $template['name'] ?? '',
            'description' => $template['description'] ?? '',
            'type' => $template['type'] ?? Challenge::DAILY,
            'duration' => $template['duration'] ?? '30',
            'rules' => $template['rules'] ?? ['items' => ['']],
            'mode' => $template['mode'] ?? Challenge::FLEXIBLE,
            'allowed_misses' => $template['allowed_misses'] ?? 2,
            'sundays_off' => $template['sundays_off'] ?? false,
            'starts_on' => $start->toDateString(),
            'month' => $start->addMonthNoOverflow()->startOfMonth()->format('Y-m'),
            'lent_year' => Lent::yearFor(Challenge::today()),
            'ends_on' => $start->addDays(29)->toDateString(),
            'visibility' => 'private',
            'max_members' => null,
        ];
    }

    /**
     * Validate the builder and return the Challenge's attributes. Before kickoff everything can change; after
     * kickoff ($locked) only the name, description and visibility.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function attributes(Request $request, bool $locked = false): array
    {
        $today = Challenge::today();
        $basicRules = [
            'title' => ['required', 'string', 'max:'.Challenge::MAX_TITLE],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', Rule::in(['private', 'public'])],
        ];
        // Before kickoff, the whole builder is checked at once, so every problem shows together.
        $ruleRules = $locked ? [] : [
            'type' => ['required', Rule::in(array_keys(Challenge::TYPES))],
            'duration' => ['required', Rule::in(array_keys(self::DURATIONS))],
            'starts_on' => ['required_unless:duration,month,lent', 'nullable', 'date'],
            'month' => ['required_if:duration,month', 'nullable', 'date_format:Y-m'],
            'lent_year' => ['required_if:duration,lent', 'nullable', 'integer', 'between:'.$today->year.','.($today->year + 1)],
            'ends_on' => ['required_if:duration,custom', 'nullable', 'date'],
            'mode' => ['required', Rule::in([Challenge::STRICT, Challenge::FLEXIBLE])],
            'allowed_misses' => ['nullable', 'integer', 'between:0,'.Challenge::MAX_MISSES],
            'sundays_off' => ['nullable', 'boolean'],
            'max_members' => ['nullable', 'integer', 'between:2,'.Challenge::MAX_MEMBERS],
            'template' => ['nullable', Rule::in(array_keys(ChallengeTemplates::all()))],
            'rules' => ['nullable', 'array'],
            'rules.items' => ['nullable', 'array', 'max:8'],
            'rules.items.*' => ['nullable', 'string', 'max:80'],
            'rules.label' => ['nullable', 'string', 'max:60'],
            'rules.times' => ['nullable', 'integer', 'between:1,7'],
            'rules.days' => ['nullable', 'array', 'max:7'],
            'rules.days.*' => ['integer', 'between:1,7'],
            'rules.weekly' => ['nullable', Rule::in(['times', 'days'])],
            'rules.threshold' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'rules.unit' => ['nullable', 'string', 'max:20'],
            'rules.per' => ['nullable', Rule::in(['day', 'week'])],
            'rules.max' => ['nullable', 'numeric', 'gt:0', 'max:10000000'],
        ];

        $validated = $request->validate($basicRules + $ruleRules, [
            'title.required' => 'Name the Challenge, like "Read 20 pages a day".',
            'title.max' => 'Keep the name to '.Challenge::MAX_TITLE.' characters.',
            'max_members.between' => 'A cap is between 2 and '.Challenge::MAX_MEMBERS.' members, or leave it empty.',
        ]);

        $attributes = [
            'title' => trim($validated['title']),
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'is_public' => $validated['visibility'] === 'public',
        ];

        if ($locked) {
            return $attributes;
        }
        [$start, $end] = $this->dates($validated, $today);

        return $attributes + [
            'type' => $validated['type'],
            'rules' => $this->rules($validated['type'], $validated['rules'] ?? []),
            'mode' => $validated['mode'],
            'allowed_misses' => $validated['mode'] === Challenge::STRICT ? 0 : (int) ($validated['allowed_misses'] ?? 0),
            'sundays_off' => (bool) ($validated['sundays_off'] ?? false) && ! ($validated['type'] === Challenge::WEEKLY),
            'template' => $validated['template'] ?? null,
            'max_members' => $validated['max_members'] ?? null,
            'starts_on' => Challenge::day($start->toDateString()),
            'ends_on' => Challenge::day($end->toDateString()),
        ];
    }

    /**
     * Start and end dates for the chosen duration.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws ValidationException
     */
    public function dates(array $validated, CarbonImmutable $today): array
    {
        $duration = $validated['duration'];

        [$start, $end] = match ($duration) {
            'month' => [($month = CarbonImmutable::createFromFormat('!Y-m', $validated['month'])), $month->endOfMonth()->startOfDay()],
            'lent' => [Lent::ashWednesday((int) $validated['lent_year']), Lent::holySaturday((int) $validated['lent_year'])],
            'custom' => [CarbonImmutable::parse($validated['starts_on'])->startOfDay(), CarbonImmutable::parse($validated['ends_on'])->startOfDay()],
            default => [($from = CarbonImmutable::parse($validated['starts_on'])->startOfDay()), $from->addDays((int) $duration - 1)],
        };

        $errors = [];
        $field = match ($duration) {
            'month' => 'month',
            'lent' => 'lent_year',
            default => 'starts_on',
        };

        if ($start->lt($today) && $end->lt($today)) {
            $errors[$field] = 'That one is already over. Pick dates from today on.';
        } elseif ($start->lt($today)) {
            $errors[$field] = $duration === 'lent'
                ? 'This year\'s Lent has already started. Pick next year, or use custom dates from today.'
                : 'Start today or later.';
        } elseif ($start->gt($today->addDays(Challenge::MAX_DAYS))) {
            $errors[$field] = 'Start within the next year or so.';
        }

        if ($end->lt($start)) {
            $errors['ends_on'] = 'The end date comes after the start date.';
        } elseif ($start->diffInDays($end) + 1 > Challenge::MAX_DAYS) {
            $errors['ends_on'] = 'A Challenge can last up to '.Challenge::MAX_DAYS.' days.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$start, $end];
    }

    /**
     * The rules for the type, cleaned up.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function rules(string $type, array $rules): array
    {
        $label = filled($rules['label'] ?? null) ? trim((string) $rules['label']) : null;
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($type === Challenge::DAILY) {
            $items = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $rules['items'] ?? []), fn (string $item) => $item !== ''));

            return $items === [] ? $fail('rules.items', 'Add at least one rule, like "Read 10 pages".') : ['items' => $items];
        }

        if ($type === Challenge::WEEKLY) {
            if (($rules['weekly'] ?? 'times') === 'days') {
                $days = array_values(array_unique(array_map('intval', $rules['days'] ?? [])));
                sort($days);

                return $days === [] ? $fail('rules.days', 'Pick at least one day.') : Arr::whereNotNull(['label' => $label, 'days' => $days]);
            }

            $times = (int) ($rules['times'] ?? 0);

            return $times < 1 ? $fail('rules.times', 'How many times a week, from 1 to 7?') : Arr::whereNotNull(['label' => $label, 'times' => $times]);
        }

        if ($type === Challenge::ABSTAIN) {
            return $label === null ? $fail('rules.label', 'What are you staying off? For example "No sweets".') : ['label' => $label];
        }

        return $this->targetRules($rules, $label);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function targetRules(array $rules, ?string $label): array
    {
        $threshold = (float) ($rules['threshold'] ?? 0);
        $unit = trim((string) ($rules['unit'] ?? ''));

        if ($threshold <= 0 || $unit === '') {
            throw ValidationException::withMessages(['rules.threshold' => 'Set the number and its unit, like 10000 steps or 20 pages.']);
        }

        // The most anyone could plausibly log in one check-in; a higher number is turned away.
        $max = (float) ($rules['max'] ?? 0) ?: min(10_000_000, $threshold * 20);

        if ($max < $threshold) {
            throw ValidationException::withMessages(['rules.max' => 'The most anyone could log has to be at least the target.']);
        }

        return Arr::whereNotNull([
            'label' => $label,
            'threshold' => $threshold == (int) $threshold ? (int) $threshold : $threshold,
            'unit' => $unit,
            'per' => ($rules['per'] ?? 'day') === 'week' ? 'week' : 'day',
            'max' => $max == (int) $max ? (int) $max : $max,
        ]);
    }
}
