<?php

namespace Tests;

use Anthropic\Client;
use App\Clock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
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

    /**
     * A real (1×1) PNG upload. Unlike UploadedFile::fake()->image(), it doesn't need the GD extension.
     */
    protected function fakeImage(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }
}
