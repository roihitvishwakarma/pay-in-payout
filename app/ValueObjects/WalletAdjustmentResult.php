<?php

namespace App\ValueObjects;

use App\Enums\WalletAdjustmentOutcome;

/**
 * Result of a WalletService::applyTransaction attempt. When applied, $adjustment is the
 * signed change (e.g. "150.00" or "-150.00") and $resultingBalance is the new balance;
 * both are null otherwise.
 */
final readonly class WalletAdjustmentResult
{
    private function __construct(
        public WalletAdjustmentOutcome $outcome,
        public ?string $adjustment = null,
        public ?string $resultingBalance = null,
        public ?string $reason = null,
    ) {}

    /**
     * The balance was adjusted and the transaction marked processed.
     */
    public static function applied(string $adjustment, string $resultingBalance): self
    {
        return new self(WalletAdjustmentOutcome::Applied, $adjustment, $resultingBalance);
    }

    /**
     * The transaction was already processed; the balance was left unchanged.
     */
    public static function skippedAlreadyProcessed(): self
    {
        return new self(WalletAdjustmentOutcome::SkippedAlreadyProcessed);
    }

    /**
     * A payout could not be applied because the wallet balance was insufficient;
     * the balance was left unchanged.
     */
    public static function insufficientBalance(): self
    {
        return new self(WalletAdjustmentOutcome::InsufficientBalance);
    }

    /**
     * The atomic adjustment failed and was rolled back; the balance was left unchanged.
     */
    public static function failed(?string $reason = null): self
    {
        return new self(WalletAdjustmentOutcome::Failed, reason: $reason);
    }

    public function isApplied(): bool
    {
        return $this->outcome === WalletAdjustmentOutcome::Applied;
    }

    public function isSkippedAlreadyProcessed(): bool
    {
        return $this->outcome === WalletAdjustmentOutcome::SkippedAlreadyProcessed;
    }

    public function isInsufficientBalance(): bool
    {
        return $this->outcome === WalletAdjustmentOutcome::InsufficientBalance;
    }

    public function isFailed(): bool
    {
        return $this->outcome === WalletAdjustmentOutcome::Failed;
    }
}
