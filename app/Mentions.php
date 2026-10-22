<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * @mentions in chat messages and comments: finding who was mentioned, and linking their names.
 */
final class Mentions
{
    /**
     * "@" then a username (letters, digits, dots, underscores), not inside an email address or word.
     */
    private const PATTERN = '/(?<![\w.@])@([a-z0-9._]{3,30})/i';

    /**
     * The usernames mentioned in some text, lowercased, without a sentence-ending period.
     *
     * @return list<string>
     */
    public static function usernames(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return collect($matches[1])->map(fn (string $name) => strtolower(rtrim($name, '.')))->unique()->values()->all();
    }

    /**
     * Who among the candidates was mentioned.
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, User>
     */
    public static function among(string $text, Collection $candidates): Collection
    {
        $names = self::usernames($text);

        return $candidates->filter(fn (User $user) => in_array(strtolower((string) $user->username), $names, true))->unique('id')->values();
    }

    /**
     * The text, escaped, with each @mention of a real member linked to their locker.
     */
    public static function render(string $text): HtmlString
    {
        $names = self::usernames($text);
        $escaped = e($text);

        if ($names === []) {
            return new HtmlString($escaped);
        }

        $people = User::whereIn('username', $names)->pluck('username')->map(fn (string $name) => strtolower($name))->all();

        return new HtmlString(preg_replace_callback(self::PATTERN, function (array $match) use ($people) {
            $name = rtrim($match[1], '.');
            $trailing = substr($match[1], strlen($name));

            if (! in_array(strtolower($name), $people, true)) {
                return $match[0];
            }

            return '<a href="'.e(route('profiles.show', strtolower($name))).'" class="font-semibold text-club hover:underline">@'.e($name).'</a>'.$trailing;
        }, $escaped));
    }
}
