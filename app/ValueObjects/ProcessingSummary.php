<?php

namespace App\ValueObjects;

/**
 * Aggregate counts for a single PaymentService::processPending run.
 * processed equals succeeded + failed + stillPending + errors.
 */
final readonly class ProcessingSummary
{
    public function __construct(
        public int $processed = 0,
        public int $succeeded = 0,
        public int $failed = 0,
        public int $stillPending = 0,
        public int $errors = 0,
    ) {}

    /**
     * Return a copy with the given counters incremented.
     */
    public function with(
        int $processed = 0,
        int $succeeded = 0,
        int $failed = 0,
        int $stillPending = 0,
        int $errors = 0,
    ): self {
        return new self(
            $this->processed + $processed,
            $this->succeeded + $succeeded,
            $this->failed + $failed,
            $this->stillPending + $stillPending,
            $this->errors + $errors,
        );
    }

    /**
     * @return array{processed:int, succeeded:int, failed:int, still_pending:int, errors:int}
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'still_pending' => $this->stillPending,
            'errors' => $this->errors,
        ];
    }
}
