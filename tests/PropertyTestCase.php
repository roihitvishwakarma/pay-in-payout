<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RunsPropertyTests;

/**
 * Base class for property-based tests: boots the app, refreshes the in-memory
 * SQLite db per case, and runs each property at least 100 times. Extend this
 * and use $this->forAllProperty(...).
 */
abstract class PropertyTestCase extends TestCase
{
    use RefreshDatabase;
    use RunsPropertyTests;
}
