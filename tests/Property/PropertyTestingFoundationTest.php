<?php

declare(strict_types=1);

namespace Tests\Property;

use Innmind\BlackBox\Set;
use Tests\PropertyTestCase;

/**
 * Smoke test for the property-based testing setup: black-box autoloads, the
 * PropertyTestCase base and RunsPropertyTests trait wire up, iterations run
 * against a refreshed in-memory SQLite db, and the 100-iteration floor holds.
 */
final class PropertyTestingFoundationTest extends PropertyTestCase
{
    public function test_black_box_generates_and_asserts_across_many_iterations(): void
    {
        $this->forAllProperty(Set::integers()->between(1, 1_000))
            ->then(function (int $value): void {
                $this->assertGreaterThanOrEqual(1, $value);
                $this->assertLessThanOrEqual(1_000, $value);
            });
    }

    public function test_iteration_floor_is_at_least_one_hundred(): void
    {
        $seen = 0;

        $this->forAllProperty(Set::integers())
            ->then(function (int $value) use (&$seen): void {
                $seen++;
                $this->assertIsInt($value);
            });

        $this->assertGreaterThanOrEqual(100, $seen);
    }

    public function test_database_is_available_and_refreshed_per_case(): void
    {
        // RefreshDatabase migrated the in-memory schema; the users table exists.
        $this->assertSame(0, \App\Models\User::query()->count());
    }
}
