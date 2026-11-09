<?php

namespace Tests\Feature;

use App\Atomic;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\ErrorAlerts;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Deadlock retries. These tests run without the usual wrapping test transaction (DatabaseMigrations), so Atomic's
 * transaction is the outermost one, exactly as in production.
 */
class DeadlockRetryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_deadlock_is_retried_and_the_write_goes_through(): void
    {
        $attempts = 0;

        $user = Atomic::run(function () use (&$attempts) {
            $attempts++;
            $user = User::factory()->create();

            if ($attempts < 3) {
                throw $this->deadlock('insert into `disputes` (`disputer_id`, `league_id`, `roadmap_task_id`)');
            }

            return $user;
        });

        $this->assertSame(3, $attempts);
        $this->assertSame(1, User::count(), 'Failed attempts were rolled back; only the successful one is stored.');
        $this->assertTrue($user->exists);
    }

    public function test_it_gives_up_after_three_attempts_and_keeps_nothing(): void
    {
        $attempts = 0;

        try {
            Atomic::run(function () use (&$attempts) {
                $attempts++;
                User::factory()->create();

                throw $this->deadlock('update `roadmap_tasks`');
            });
            $this->fail('It should give up after three attempts.');
        } catch (QueryException $exception) {
            $this->assertTrue(Atomic::isConcurrencyError($exception));
        }

        $this->assertSame(Atomic::ATTEMPTS, $attempts);
        $this->assertSame(0, User::count());
    }

    public function test_other_errors_are_not_retried(): void
    {
        $attempts = 0;

        $this->expectException(RuntimeException::class);

        try {
            Atomic::run(function () use (&$attempts) {
                $attempts++;

                throw new RuntimeException('Not a deadlock.');
            });
        } finally {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_inside_another_transaction_the_outer_one_decides(): void
    {
        $attempts = 0;

        try {
            DB::transaction(function () use (&$attempts) {
                Atomic::run(function () use (&$attempts) {
                    $attempts++;

                    throw $this->deadlock('insert into `cheers`');
                });
            });
        } catch (Throwable $exception) {
            // A deadlock rolls back the whole outer transaction, so the inner one passes it up instead of retrying.
            $this->assertTrue(Atomic::isConcurrencyError($exception));
        }

        $this->assertSame(1, $attempts);
    }

    public function test_the_same_deadlock_is_emailed_at_most_once_an_hour(): void
    {
        Notification::fake();
        config(['goalline.admin_emails' => ['admin@goalline.app']]);
        $alerts = app(ErrorAlerts::class);

        // The same deadlock on different rows: the SQL values differ, but it's one problem.
        $alerts->report($this->deadlock('insert into `disputes` (`disputer_id`, `league_id`, `roadmap_task_id`) values (12, 3, 481)'));
        $alerts->report($this->deadlock('insert into `disputes` (`disputer_id`, `league_id`, `roadmap_task_id`) values (57, 9, 1204)'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 1);

        $this->travel(61)->minutes();
        $alerts->report($this->deadlock('insert into `disputes` (`disputer_id`, `league_id`, `roadmap_task_id`) values (8, 2, 77)'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 2);
    }

    /**
     * What MySQL sends when it picks this transaction as the deadlock victim.
     */
    private function deadlock(string $sql): QueryException
    {
        $pdo = new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');

        return new QueryException('mysql', $sql, [], $pdo);
    }
}
