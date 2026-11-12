<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A basic check that a public name (like a team name on the scoreboard) has no common profanity or slurs. It
 * normalizes look-alike characters (0 for o, 1 for i, @ for a, $ for s…) and ignores spacing and punctuation, so
 * "f.u.c.k" and "sh1t" are caught. It's a friendly first line, not a full moderation system: reports still apply.
 */
class CleanName implements ValidationRule
{
    /**
     * Blocked stems, matched inside the squashed name.
     *
     * @var list<string>
     */
    private const BLOCKED = [
        'fuck', 'shit', 'bitch', 'cunt', 'asshole', 'bastard', 'dick', 'cock', 'pussy', 'whore', 'slut',
        'nigger', 'nigga', 'faggot', 'fag', 'retard', 'kike', 'spic', 'chink', 'tranny', 'nazi', 'rape',
        'porn', 'penis', 'vagina', 'twat', 'wank', 'jizz', 'dildo',
    ];

    /**
     * Blocked only as whole words, because they hide inside harmless words ("Scunthorpe", "class", "Dickens").
     *
     * @var list<string>
     */
    private const WHOLE_WORDS = ['ass', 'tit', 'tits', 'cum', 'hoe', 'hell'];

    /**
     * Stems allowed through even though they contain a blocked stem.
     *
     * @var list<string>
     */
    private const ALLOWED = ['scunthorpe', 'dickens', 'cockpit', 'peacock', 'hancock', 'shitake', 'cocktail', 'scrape', 'grape', 'drape', 'therapist', 'skyscraper'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isOffensive($value)) {
            $fail('Pick a name everyone in the league would be glad to see on the scoreboard.');
        }
    }

    public static function isOffensive(string $name): bool
    {
        $normalized = strtr(mb_strtolower($name), ['0' => 'o', '1' => 'i', '!' => 'i', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't', '+' => 't']);
        $words = preg_split('/[^a-z]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (array_intersect($words, self::WHOLE_WORDS) !== []) {
            return true;
        }

        $squashed = (string) preg_replace('/[^a-z]/', '', $normalized);
        foreach (self::ALLOWED as $allowed) {
            $squashed = str_replace($allowed, '', $squashed);
        }

        foreach (self::BLOCKED as $stem) {
            if (str_contains($squashed, $stem)) {
                return true;
            }
        }

        return false;
    }
}
