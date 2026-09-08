<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Innmind\BlackBox\PHPUnit\BlackBox;
use Innmind\BlackBox\PHPUnit\Compatibility;
use Innmind\BlackBox\Set;
use Innmind\BlackBox\Set\Provider;

/**
 * Helper for property-based tests with innmind/black-box.
 *
 * Wraps forAll() so every property runs at least 100 iterations. Set
 * BLACKBOX_SET_SIZE to bump that up globally; this keeps the floor at 100.
 *
 *   $this->forAllProperty(Set::integers()->between(1, 100))
 *       ->then(fn (int $amount) => $this->assertGreaterThan(0, $amount));
 */
trait RunsPropertyTests
{
    use BlackBox;

    protected int $minimumPropertyIterations = 100;

    /**
     * @no-named-arguments
     */
    protected function forAllProperty(
        Set|Provider $first,
        Set|Provider ...$rest,
    ): Compatibility {
        $iterations = max(
            $this->minimumPropertyIterations,
            (int) (getenv('BLACKBOX_SET_SIZE') ?: 0),
        );

        return static::forAll($first, ...$rest)->take($iterations);
    }
}
