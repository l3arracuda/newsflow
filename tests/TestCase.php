<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test suites must never incur real provider charges, even when local .env enables OpenAI.
        config([
            'services.ai_text.driver' => 'fake',
            'services.image_generation.driver' => 'fake',
        ]);
    }
}
