<?php

namespace Tests;

use Anthropic\Client;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never make paid API calls. Mock RoadmapPlanner instead.
        $this->app->bind(Client::class, fn () => throw new LogicException('Tests must not call the Claude API.'));
    }
}
