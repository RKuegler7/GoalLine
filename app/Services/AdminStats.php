<?php

namespace App\Services;

use App\GoalOutcome;
use App\MedalType;
use App\Models\GoalResult;
use App\Models\Medal;
use App\Models\User;
use App\Usage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The numbers on the admin page: who's using GoalLine, what they use, and what AI costs.
 */
class AdminStats
{
    /**
     * Members in total, and how many joined recently.
     *
     * @return array{total: int, lastWeek: int}
     */
    public function members(): array
    {
        return [
            'total' => User::whereNull('demo_sandbox')->count(),
            'lastWeek' => User::whereNull('demo_sandbox')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Distinct members active today, in the last 7 days, and in the last 30.
     *
     * @return array{today: int, week: int, month: int, regulars: int}
     */
    public function activeMembers(): array
    {
        $activeSince = fn (CarbonImmutable $since) => DB::table('usage_events')
            ->where('name', 'active')->where('created_at', '>=', $since)->distinct()->count('user_id');

        // Regulars: active on 3 or more of the last 7 days.
        $regulars = DB::table('usage_events')
            ->where('name', 'active')->where('created_at', '>=', CarbonImmutable::today()->subDays(6))
            ->select('user_id')->groupBy('user_id')->havingRaw('count(*) >= 3')->get()->count();

        return [
            'today' => $activeSince(CarbonImmutable::today()),
            'week' => $activeSince(CarbonImmutable::today()->subDays(6)),
            'month' => $activeSince(CarbonImmutable::today()->subDays(29)),
            'regulars' => $regulars,
        ];
    }

    /**
     * Active members per day for the last two weeks, oldest first.
     *
     * @return list<array{date: CarbonImmutable, count: int}>
     */
    public function dailyActive(int $days = 14): array
    {
        $since = CarbonImmutable::today()->subDays($days - 1);
        $counts = DB::table('usage_events')
            ->where('name', 'active')->where('created_at', '>=', $since)
            ->get(['user_id', 'created_at'])
            ->groupBy(fn ($event) => CarbonImmutable::parse($event->created_at)->toDateString())
            ->map(fn ($events) => $events->pluck('user_id')->unique()->count());

        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => ['date' => $since->addDays($offset), 'count' => $counts->get($since->addDays($offset)->toDateString(), 0)])
            ->all();
    }

    /**
     * How often each feature was used in the last 7 days, and by how many members.
     *
     * @return list<array{name: string, label: string, uses: int, members: int}>
     */
    public function featureUse(): array
    {
        $rows = DB::table('usage_events')
            ->where('created_at', '>=', CarbonImmutable::today()->subDays(6))
            ->where('name', '!=', 'active')
            ->select('name', DB::raw('count(*) as uses'), DB::raw('count(distinct user_id) as members'))
            ->groupBy('name')
            ->get()
            ->keyBy('name');

        return collect(Usage::EVENTS)
            ->except('active')
            ->map(fn (string $label, string $name) => [
                'name' => $name,
                'label' => $label,
                'uses' => (int) ($rows[$name]->uses ?? 0),
                'members' => (int) ($rows[$name]->members ?? 0),
            ])
            ->sortByDesc('uses')
            ->values()
            ->all();
    }

    /**
     * Where members' photos and plan files are kept, and whether that storage is working right now. On the server's
     * own disk, uploads vanish on every deploy; in S3 they last.
     *
     * @return array{s3: bool, bucket: ?string, working: bool, problem: ?string}
     */
    public function photoStorage(): array
    {
        $s3 = config('filesystems.disks.uploads.driver') === 's3';

        try {
            $disk = Storage::disk('uploads');
            $disk->put('health/admin-check.txt', now()->toIso8601String());
            $working = $disk->exists('health/admin-check.txt');
            $disk->delete('health/admin-check.txt');
            $problem = null;
        } catch (Throwable $error) {
            report($error);
            $working = false;
            $problem = class_basename($error).': '.Str::limit($error->getMessage(), 160);
        }

        return ['s3' => $s3, 'bucket' => $s3 ? config('filesystems.disks.uploads.bucket') : null, 'working' => $working, 'problem' => $problem];
    }

    /**
     * The background queue (emails, re-scoring after disputes and proof, the coach): how it runs, what's waiting and
     * for how long, and what failed. A worker that's running keeps "waiting" near zero.
     *
     * @return array{connection: string, waiting: int|null, oldestMinutes: int|null, failed: int|null, healthy: bool}
     */
    public function queue(): array
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'database') {
            return ['connection' => $connection, 'waiting' => null, 'oldestMinutes' => null, 'failed' => null, 'healthy' => true];
        }

        $table = (string) config('queue.connections.database.table', 'jobs');
        $waiting = DB::table($table)->whereNull('reserved_at')->count();
        $oldest = DB::table($table)->whereNull('reserved_at')->min('available_at');
        $oldestMinutes = $oldest ? (int) floor((now()->getTimestamp() - (int) $oldest) / 60) : null;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count() : null;

        return [
            'connection' => $connection,
            'waiting' => $waiting,
            'oldestMinutes' => $oldestMinutes,
            'failed' => $failed,
            // Anything waiting more than five minutes means no worker is picking jobs up.
            'healthy' => $oldestMinutes === null || $oldestMinutes < 5,
        ];
    }

    /**
     * AI spend against the limits, and what it went to this month.
     *
     * @return array{today: float, month: float, dailyBudget: float, monthlyBudget: float, byKind: list<array{kind: string, requests: int, cost: float}>, topMembersToday: list<array{name: string, requests: int}>}
     */
    public function aiSpend(): array
    {
        $budget = app(AiBudget::class);

        return [
            'today' => $budget->spentToday(),
            'month' => $budget->spentThisMonth(),
            'dailyBudget' => (float) config('goalline.ai.daily_budget_usd'),
            'monthlyBudget' => (float) config('goalline.ai.monthly_budget_usd'),
            'byKind' => DB::table('ai_requests')
                ->where('created_at', '>=', today()->startOfMonth())
                ->select('kind', DB::raw('count(*) as requests'), DB::raw('sum(cost_usd) as cost'))
                ->groupBy('kind')
                ->orderByDesc('cost')
                ->get()
                ->map(fn ($row) => ['kind' => $row->kind, 'requests' => (int) $row->requests, 'cost' => (float) $row->cost])
                ->all(),
            'topMembersToday' => DB::table('ai_requests')
                ->join('users', 'users.id', '=', 'ai_requests.user_id')
                ->where('ai_requests.created_at', '>=', today())
                ->select('users.name', DB::raw('count(*) as requests'))
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('requests')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['name' => $row->name, 'requests' => (int) $row->requests])
                ->all(),
        ];
    }

    /**
     * Goal Day and medals, in aggregate only (no names, goals or notes), real members only.
     *
     * @return array{answered: int, achieved: int, partly: int, short: int, verified: int, shared: int, medals: array<string, int>}
     */
    public function goalDay(): array
    {
        $results = GoalResult::whereHas('user', fn ($users) => $users->whereNull('demo_sandbox'));

        return [
            'answered' => (clone $results)->count(),
            'achieved' => (clone $results)->where('outcome', GoalOutcome::Achieved)->count(),
            'partly' => (clone $results)->where('outcome', GoalOutcome::Partly)->count(),
            'short' => (clone $results)->where('outcome', GoalOutcome::Short)->count(),
            'verified' => (clone $results)->whereNotNull('verified_at')->count(),
            'shared' => (clone $results)->where('shared', true)->count(),
            'medals' => Medal::whereHas('user', fn ($users) => $users->whereNull('demo_sandbox'))
                ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')
                ->mapWithKeys(fn ($total, $type) => [(MedalType::tryFrom((string) $type)?->label() ?? $type) => (int) $total])->all(),
        ];
    }
}
