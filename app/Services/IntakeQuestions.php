<?php

namespace App\Services;

use App\Exceptions\RoadmapPlanningException;
use App\GoalCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Intake questions: the shape the coach must return, checks that keep only safe, complete questions, built-in
 * questions per category for when the coach isn't available, and the person's answers, cleaned up for the roadmap.
 */
class IntakeQuestions
{
    public const SINGLE_CHOICE = 'single_choice';

    public const MULTIPLE_CHOICE = 'multiple_choice';

    public const NUMBER = 'number';

    public const SHORT_TEXT = 'short_text';

    public const SCALE = 'scale';

    public const TYPES = [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE, self::NUMBER, self::SHORT_TEXT, self::SCALE];

    /**
     * Fewest and most questions in a set.
     */
    public const MIN_QUESTIONS = 5;

    public const MAX_QUESTIONS = 8;

    /**
     * Answer states: answered, "I'm not sure", or skipped.
     */
    public const ANSWERED = 'answered';

    public const NOT_SURE = 'not_sure';

    public const SKIPPED = 'skipped';

    /**
     * Length caps, in characters.
     */
    private const MAX_TEXT = 200;

    private const MAX_WHY = 160;

    private const MAX_OPTION = 80;

    private const MAX_OPTIONS = 8;

    private const MAX_UNIT = 20;

    public const MAX_ANSWER = 300;

    /**
     * Largest number answer.
     */
    private const MAX_NUMBER = 100000;

    /**
     * The JSON shape the coach returns.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => self::TYPES],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'unit' => ['type' => 'string'],
                            'why' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'text', 'type', 'options', 'unit', 'why'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['questions'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Keep the coach's usable questions, cleaned and capped. A physical goal always gets a health question.
     *
     * @return list<array{id: string, text: string, type: string, options: list<string>, unit: string|null, why: string|null}>
     *
     * @throws RoadmapPlanningException When too few usable questions came back.
     */
    public static function validated(mixed $raw, bool $physical): array
    {
        $items = is_array($raw) && is_array($raw['questions'] ?? null) ? $raw['questions'] : [];
        $questions = [];

        foreach ($items as $item) {
            if (count($questions) === self::MAX_QUESTIONS) {
                break;
            }

            if ($question = self::clean($item)) {
                $questions[] = $question;
            }
        }

        if (count($questions) < self::MIN_QUESTIONS) {
            throw new RoadmapPlanningException('The intake questions were incomplete.');
        }

        return self::withIds($physical ? self::withHealthQuestion($questions) : $questions);
    }

    /**
     * Built-in questions for a category, so the flow never waits on the coach.
     *
     * @return list<array{id: string, text: string, type: string, options: list<string>, unit: string|null, why: string|null}>
     */
    public static function fallback(GoalCategory $category, bool $physical): array
    {
        $confidence = self::question('confidence', 'How confident do you feel about reaching this goal?', self::SCALE, why: 'So the plan pushes you the right amount.');
        $days = self::question('days', 'How many days a week can you work on it?', self::SINGLE_CHOICE, ['1 to 2', '3 to 4', '5 to 6', 'Every day']);
        $hours = self::question('hours', 'How many hours a week can you give it?', self::NUMBER, unit: 'hours', why: 'So every GoalWeek fits your real life.');
        $obstacles = self::question('obstacles', "What's most likely to get in the way?", self::SINGLE_CHOICE, ['Time', 'Motivation', 'Not sure where to start', 'Other commitments', 'Something else']);

        $questions = match ($category) {
            GoalCategory::Fitness => [
                self::question('experience', 'How would you describe your experience with this?', self::SINGLE_CHOICE, ['Brand new', 'Some experience', 'I train regularly', 'Competitive'], why: 'So the plan starts at the right level.'),
                self::question('starting_point', 'Where are you starting from? For example, your weekly mileage and longest recent run, or your current max.', self::SHORT_TEXT, why: 'Your plan builds from here, gradually.'),
                self::question('days', 'How many days a week can you train?', self::SINGLE_CHOICE, ['2', '3', '4', '5', '6 or more']),
                self::healthQuestion(),
                self::question('access', 'What do you have access to?', self::MULTIPLE_CHOICE, ['A gym', 'A track or trails', 'Equipment at home', 'A pool', 'A bike', 'Just my shoes']),
                $confidence,
            ],
            GoalCategory::Academic => [
                self::question('structure', 'What are the parts or sections, and when is each one due or tested?', self::SHORT_TEXT, why: 'So the plan lines up with your real dates.'),
                $hours,
                self::question('prior_attempts', 'Have you tried this before? If so, how did it go?', self::SHORT_TEXT),
                self::question('weakest', 'Which area feels weakest right now?', self::SHORT_TEXT, why: 'Your plan spends more time there.'),
                self::question('materials', 'What study materials do you already have?', self::MULTIPLE_CHOICE, ['A review course', 'Textbooks', 'Practice questions or exams', 'Class notes', 'A tutor or study group', 'Nothing yet']),
                self::question('best_time', 'When do you study best?', self::SINGLE_CHOICE, ['Early morning', 'During the day', 'Evenings', 'Late at night', 'Weekends']),
            ],
            GoalCategory::Career => [
                self::question('stage', 'Where are you with this right now?', self::SINGLE_CHOICE, ['Just starting', 'Researching options', 'Actively working on it', 'Close to the finish']),
                $hours,
                self::question('dates', 'Are there deadlines, interviews, or exam dates already set?', self::SHORT_TEXT, why: 'So the plan peaks at the right time.'),
                $obstacles,
                self::question('support', 'Who can help along the way? (a mentor, manager, or friends)', self::SHORT_TEXT),
                $confidence,
            ],
            GoalCategory::Creative => [
                self::question('stage', 'What stage is it at now? For example, your word count, songs written, or pieces finished.', self::SHORT_TEXT, why: 'Your plan picks up from here.'),
                self::question('milestones', 'Any outside deadlines or milestones, like an advisor, editor, or show?', self::SHORT_TEXT),
                $hours,
                self::question('best_time', 'When do you do your best work?', self::SINGLE_CHOICE, ['Early morning', 'During the day', 'Evenings', 'Late at night', 'Weekends']),
                self::question('obstacles', 'What usually gets in the way?', self::SINGLE_CHOICE, ['Getting started', 'Finishing', 'Finding time', 'Perfectionism', 'Getting feedback']),
                $confidence,
            ],
            default => [
                self::question('starting_point', 'Where are you starting from?', self::SHORT_TEXT, why: 'Your plan builds from here.'),
                self::question('prior_attempts', 'Have you tried this before? What happened?', self::SHORT_TEXT),
                $days,
                self::question('minutes', 'How much time a day can you give it?', self::NUMBER, unit: 'minutes'),
                $obstacles,
                $confidence,
            ],
        };

        return $physical ? self::withHealthQuestion($questions) : $questions;
    }

    /**
     * Validation rules for answers to these questions. Every answer is optional.
     *
     * @param  list<array{id: string, type: string, options: list<string>}>  $questions
     * @return array<string, mixed>
     */
    public static function rules(array $questions): array
    {
        $rules = ['answers' => ['nullable', 'array']];

        foreach ($questions as $question) {
            $key = "answers.{$question['id']}";
            $rules["{$key}.status"] = ['nullable', Rule::in([self::NOT_SURE, self::SKIPPED])];

            $rules["{$key}.value"] = match ($question['type']) {
                self::SINGLE_CHOICE => ['nullable', 'string', Rule::in($question['options'])],
                self::MULTIPLE_CHOICE => ['nullable', 'array'],
                self::NUMBER => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_NUMBER],
                self::SCALE => ['nullable', 'integer', 'between:1,5'],
                default => ['nullable', 'string', 'max:'.self::MAX_ANSWER],
            };

            if ($question['type'] === self::MULTIPLE_CHOICE) {
                $rules["{$key}.value.*"] = ['string', Rule::in($question['options'])];
            }
        }

        return $rules;
    }

    /**
     * The person's answers, one per question, as the roadmap reads them. A blank answer counts as skipped.
     *
     * @param  list<array{id: string, text: string, type: string, options: list<string>, unit: string|null}>  $questions
     * @param  array<string, mixed>  $input
     * @return list<array{id: string, question: string, answer: string|null, value: mixed, status: string}>
     */
    public static function answers(array $questions, array $input): array
    {
        return array_map(function (array $question) use ($input) {
            $given = is_array($input[$question['id']] ?? null) ? $input[$question['id']] : [];
            $status = $given['status'] ?? null;
            $value = $status ? null : self::value($question, $given['value'] ?? null);
            $answer = $value === null ? null : self::describe($question, $value);

            return [
                'id' => $question['id'],
                'question' => $question['text'],
                'answer' => $answer,
                'value' => $value,
                'status' => match (true) {
                    $status === self::NOT_SURE => self::NOT_SURE,
                    $answer === null => self::SKIPPED,
                    default => self::ANSWERED,
                },
            ];
        }, $questions);
    }

    /**
     * Whether a physical goal's questions already ask about injuries or health.
     *
     * @param  list<array{text: string}>  $questions
     */
    public static function asksAboutHealth(array $questions): bool
    {
        return collect($questions)->contains(fn (array $question) => preg_match('/injur|pain|health|medical|condition|doctor/i', $question['text']) === 1);
    }

    /**
     * One question, cleaned, or null when it isn't usable.
     *
     * @return array{id: string, text: string, type: string, options: list<string>, unit: string|null, why: string|null}|null
     */
    private static function clean(mixed $item): ?array
    {
        if (! is_array($item) || ! in_array($item['type'] ?? null, self::TYPES, true)) {
            return null;
        }

        $text = self::plain($item['text'] ?? null, self::MAX_TEXT);
        $isChoice = in_array($item['type'], [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE], true);
        $options = $isChoice
            ? collect(is_array($item['options'] ?? null) ? $item['options'] : [])
                ->map(fn ($option) => self::plain($option, self::MAX_OPTION))
                ->filter()
                ->unique(fn (string $option) => Str::lower($option))
                ->take(self::MAX_OPTIONS)
                ->values()
                ->all()
            : [];

        if ($text === '' || ($isChoice && count($options) < 2)) {
            return null;
        }

        return [
            'id' => Str::limit(Str::slug(self::plain($item['id'] ?? null, 60), '_'), 40, ''),
            'text' => $text,
            'type' => $item['type'],
            'options' => $options,
            'unit' => $item['type'] === self::NUMBER ? (self::plain($item['unit'] ?? null, self::MAX_UNIT) ?: null) : null,
            'why' => self::plain($item['why'] ?? null, self::MAX_WHY) ?: null,
        ];
    }

    /**
     * Give every question a unique, safe id, keeping the coach's when it's usable.
     *
     * @param  list<array{id: string}>  $questions
     * @return list<array{id: string}>
     */
    private static function withIds(array $questions): array
    {
        $used = [];

        foreach ($questions as $index => $question) {
            $id = $question['id'];

            for ($n = $index + 1; $id === '' || in_array($id, $used, true); $n++) {
                $id = "q{$n}";
            }

            $questions[$index]['id'] = $used[] = $id;
        }

        return $questions;
    }

    /**
     * @param  list<array{id: string, text: string}>  $questions
     * @return list<array{id: string, text: string}>
     */
    private static function withHealthQuestion(array $questions): array
    {
        if (self::asksAboutHealth($questions)) {
            return $questions;
        }

        return [...array_slice($questions, 0, self::MAX_QUESTIONS - 1), self::healthQuestion()];
    }

    /**
     * @return array{id: string, text: string, type: string, options: list<string>, unit: string|null, why: string|null}
     */
    private static function healthQuestion(): array
    {
        return self::question('health', 'Any injuries, pain, or health conditions your plan should work around?', self::SHORT_TEXT,
            why: 'So your plan starts at a safe level. GoalLine never gives medical advice.');
    }

    /**
     * @param  list<string>  $options
     * @return array{id: string, text: string, type: string, options: list<string>, unit: string|null, why: string|null}
     */
    private static function question(string $id, string $text, string $type, array $options = [], ?string $unit = null, ?string $why = null): array
    {
        return ['id' => $id, 'text' => $text, 'type' => $type, 'options' => $options, 'unit' => $unit, 'why' => $why];
    }

    /**
     * An answer's value in its question's type, or null when it's blank or doesn't fit.
     */
    private static function value(array $question, mixed $value): mixed
    {
        return match ($question['type']) {
            self::SINGLE_CHOICE => in_array($value, $question['options'], true) ? $value : null,
            self::MULTIPLE_CHOICE => array_values(array_intersect($question['options'], is_array($value) ? $value : [])) ?: null,
            self::NUMBER => is_numeric($value) && $value >= 0 && $value <= self::MAX_NUMBER ? $value + 0 : null,
            self::SCALE => is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 ? (int) $value : null,
            default => self::plain($value, self::MAX_ANSWER) ?: null,
        };
    }

    /**
     * An answer as words: "Gym, Pool", "25 miles", "4 of 5".
     */
    private static function describe(array $question, mixed $value): string
    {
        return match ($question['type']) {
            self::MULTIPLE_CHOICE => implode(', ', $value),
            self::NUMBER => trim($value.' '.$question['unit']),
            self::SCALE => "{$value} of 5",
            default => (string) $value,
        };
    }

    /**
     * Plain text on one line: no tags, collapsed spaces, capped.
     */
    private static function plain(mixed $text, int $limit): string
    {
        if (! is_string($text) && ! is_numeric($text)) {
            return '';
        }

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text))), 0, $limit);
    }
}
