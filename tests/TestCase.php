<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment. Views render without a Vite build.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
