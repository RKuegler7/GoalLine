<?php

namespace App;

/**
 * Badges members earn. Personal badges are earned once; GoalLeague awards are won each season,
 * at the end of the season recap.
 */
enum Badge: string
{
    // Personal milestones.
    case FirstStep = 'first-step';
    case InLine = 'in-line';
    case PastTheLine = 'past-the-line';
    case Smashed = 'smashed';
    case OnARoll = 'on-a-roll';
    case IronWill = 'iron-will';
    case HabitHero = 'habit-hero';
    case Finisher = 'finisher';
    case TeamPlayer = 'team-player';
    case HypeSquad = 'hype-squad';
    case WeekWarrior = 'week-warrior';
    case Unstoppable = 'unstoppable';
    case ChallengeFinisher = 'challenge-finisher';

    // GoalLeague season awards.
    case Champion = 'champion';
    case Mvp = 'mvp';
    case ComebackPlayer = 'comeback-player';
    case IronStreak = 'iron-streak';
    case SmashMachine = 'smash-machine';

    public const ON_A_ROLL_WEEKS = 3;

    public const IRON_WILL_WEEKS = 10;

    public const HABIT_HERO_CHECK_INS = 30;

    public const HYPE_SQUAD_CHEERS = 10;

    public const WEEK_WARRIOR_DAYS = 7;

    public const UNSTOPPABLE_DAYS = 30;

    /**
     * The badge's name.
     */
    public function label(): string
    {
        return match ($this) {
            self::FirstStep => 'First Step',
            self::InLine => 'In Line',
            self::PastTheLine => 'Past the Line',
            self::Smashed => 'Line Smasher',
            self::OnARoll => 'On a Roll',
            self::IronWill => 'Iron Will',
            self::HabitHero => 'Habit Hero',
            self::Finisher => 'Finisher',
            self::TeamPlayer => 'Team Player',
            self::HypeSquad => 'Hype Squad',
            self::WeekWarrior => 'Week Warrior',
            self::Unstoppable => 'Unstoppable',
            self::ChallengeFinisher => 'Challenge Finisher',
            self::Champion => 'GoalLeague Champion',
            self::Mvp => 'MVP',
            self::ComebackPlayer => 'Comeback Player',
            self::IronStreak => 'Iron Streak',
            self::SmashMachine => 'Smash Machine',
        };
    }

    /**
     * How it's earned.
     */
    public function description(): string
    {
        return match ($this) {
            self::FirstStep => 'Checked in for the first time.',
            self::InLine => 'Finished a GoalWeek in line.',
            self::PastTheLine => 'Finished a GoalWeek past the line.',
            self::Smashed => 'Smashed the line in a GoalWeek.',
            self::OnARoll => self::ON_A_ROLL_WEEKS.' GoalWeeks in line in a row.',
            self::IronWill => self::IRON_WILL_WEEKS.' GoalWeeks in line in a row.',
            self::HabitHero => self::HABIT_HERO_CHECK_INS.' habit check-ins.',
            self::Finisher => 'Crossed the GoalLine on a goal.',
            self::TeamPlayer => 'Joined a GoalLeague.',
            self::HypeSquad => 'Cheered '.self::HYPE_SQUAD_CHEERS.' posts.',
            self::WeekWarrior => 'A '.self::WEEK_WARRIOR_DAYS.'-day check-in streak.',
            self::Unstoppable => 'A '.self::UNSTOPPABLE_DAYS.'-day check-in streak.',
            self::ChallengeFinisher => 'Finished a challenge.',
            self::Champion => 'Finished first in a GoalLeague season.',
            self::Mvp => 'Most GoalWeek points in a GoalLeague season.',
            self::ComebackPlayer => 'Biggest climb in the standings over the second half of a season.',
            self::IronStreak => 'Longest in-line streak in a GoalLeague season.',
            self::SmashMachine => 'Most smashed GoalWeeks in a GoalLeague season.',
        };
    }

    /**
     * Whether it's won in a GoalLeague season rather than earned personally.
     */
    public function isLeagueAward(): bool
    {
        return in_array($this, self::leagueAwards(), true);
    }

    /**
     * Personal badges, in the order they're usually earned.
     *
     * @return list<self>
     */
    public static function personal(): array
    {
        return array_values(array_filter(self::cases(), fn (self $badge) => ! $badge->isLeagueAward()));
    }

    /**
     * Season awards, Champion first.
     *
     * @return list<self>
     */
    public static function leagueAwards(): array
    {
        return [self::Champion, self::Mvp, self::ComebackPlayer, self::IronStreak, self::SmashMachine];
    }

    /**
     * The emblem drawn on the badge.
     */
    public function emblem(): string
    {
        return match ($this) {
            self::FirstStep => 'step',
            self::InLine, self::PastTheLine => 'line',
            self::Smashed, self::SmashMachine => 'bolt',
            self::OnARoll, self::IronWill, self::IronStreak => 'flame',
            self::HabitHero => 'repeat',
            self::Finisher => 'flag',
            self::TeamPlayer => 'people',
            self::HypeSquad => 'clap',
            self::WeekWarrior, self::Unstoppable => 'flame',
            self::ChallengeFinisher => 'flag',
            self::Champion => 'trophy',
            self::Mvp => 'star',
            self::ComebackPlayer => 'rise',
        };
    }
}
