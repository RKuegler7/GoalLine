<?php

namespace Tests;

use Anthropic\Client;
use App\Clock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts on the app clock, not the last request's viewer.
        Clock::useTimezoneOf(null);

        // Tests must never make paid API calls. Mock RoadmapPlanner instead.
        $this->app->bind(Client::class, fn () => throw new LogicException('Tests must not call the Claude API.'));
    }
}
