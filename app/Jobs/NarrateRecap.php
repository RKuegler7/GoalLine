<?php

namespace App\Jobs;

use App\Clock;
use App\Models\User;
use App\Services\RecapNarrator;
use App\Services\WeeklyRecap;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Write and save the summary for one member's finished week. The narrator never throws, so a failed call saves the
 * template and the page doesn't keep asking again.
 */
class NarrateRecap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Each attempt is a paid API call, and the SDK already retries transient errors.
     */
    public int $tries = 1;

    /**
     * Skip the job if the member was removed while it waited.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user, public string $monday) {}

    public function uniqueId(): string
    {
        return "{$this->user->id}:{$this->monday}";
    }

    /**
     * Count the week on the member's clock, so it's the same week their recap page shows.
     */
    public function handle(RecapNarrator $narrator): void
    {
        Clock::for($this->user, function () use ($narrator) {
            $recap = new WeeklyRecap($this->user, CarbonImmutable::parse($this->monday));
            $facts = RecapNarrator::facts($recap);

            RecapNarrator::save($this->user, $recap->monday, RecapNarrator::hash($facts), $narrator->write($this->user, $facts));
        });
    }
}
