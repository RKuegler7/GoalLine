<?php

namespace App\Console\Commands;

use App\Atomic;
use App\Models\League;
use App\Models\Medal;
use App\Services\LeagueTable;
use App\Services\MedalAwarder;
use App\Services\SeasonRecap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckMedals extends Command
{
    /**
     * @var string
     */
    protected $signature = 'goalline:check-medals {--fix : Repair what it finds (idempotent)}';

    /**
     * @var string
     */
    protected $description = 'Report duplicate medals and finished seasons missing their medals; --fix repairs them';

    public function handle(MedalAwarder $awarder): int
    {
        $unique = collect(Schema::getIndexes('medals'))->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['award_key']);
        $this->line('Unique index on medals.award_key: '.($unique ? 'yes' : 'MISSING'));

        $duplicates = Medal::query()->select('award_key', DB::raw('count(*) as copies'))->groupBy('award_key')->having('copies', '>', 1)->get();
        $this->line("Duplicate medals (same award_key): {$duplicates->count()}");

        $missing = League::query()->whereDate('ends_on', '<', now()->toDateString())->with('results', 'members.user')->get()
            ->filter(fn (League $league) => (new SeasonRecap($league, new LeagueTable($league)))->isComplete())
            ->filter(fn (League $league) => ! Medal::where('league_id', $league->id)->exists())
            ->filter(fn (League $league) => $league->results->sum(fn ($result) => $result->result_points + $result->matchup_points) > 0)
            ->values();
        $this->line("Finished seasons missing medals: {$missing->count()}".($missing->isNotEmpty() ? ' (league ids '.$missing->pluck('id')->join(', ').')' : ''));

        if (! $this->option('fix')) {
            $this->line($duplicates->isEmpty() && $missing->isEmpty() ? 'Nothing to fix.' : 'Run again with --fix to repair.');

            return self::SUCCESS;
        }

        foreach ($duplicates as $duplicate) {
            // Keep the first copy of each medal; the rest go.
            $keep = Medal::where('award_key', $duplicate->award_key)->min('id');
            Atomic::run(fn () => Medal::where('award_key', $duplicate->award_key)->whereKeyNot($keep)->delete());
        }

        foreach ($missing as $league) {
            Atomic::exclusively('league-season:'.$league->id, fn () => $awarder->forLeague($league), wait: 15);
        }

        $this->info("Fixed: removed duplicates of {$duplicates->count()} medals, awarded medals in {$missing->count()} seasons.");

        return self::SUCCESS;
    }
}
