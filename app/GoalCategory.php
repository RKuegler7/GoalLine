<?php

namespace App;

enum GoalCategory: string
{
    case Academic = 'academic';
    case Career = 'career';
    case Fitness = 'fitness';
    case Creative = 'creative';
    case Personal = 'personal';
    case Other = 'other';

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Academic',
            self::Career => 'Career',
            self::Fitness => 'Fitness & health',
            self::Creative => 'Creative',
            self::Personal => 'Personal',
            self::Other => 'Something else',
        };
    }

    /**
     * A short example that helps people pick the right kind.
     */
    public function example(): string
    {
        return match ($this) {
            self::Academic => 'An exam, thesis, or course',
            self::Career => 'A job search, promotion, or certification',
            self::Fitness => 'A race, lift, or training block',
            self::Creative => 'A book, album, or portfolio',
            self::Personal => 'A habit, language, or life milestone',
            self::Other => 'Anything with a deadline that matters',
        };
    }
}
