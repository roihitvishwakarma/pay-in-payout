<?php

namespace App\Enums;

/**
 * The set of possible outcomes of a WalletService::applyTransaction call.
 *
 * - Applied: the wallet balance was adjusted and the transaction marked processed.
 * - SkippedAlreadyProcessed: the transaction was already processed; no balance change.
 * - InsufficientBalance: a payout could not be applied because the balance was too low.
 * - Failed: the atomic adjustment failed and was rolled back; no balance change.
 */
enum WalletAdjustmentOutcome: string
{
    case Applied = 'applied';
    case SkippedAlreadyProcessed = 'skipped_already_processed';
    case InsufficientBalance = 'insufficient_balance';
    case Failed = 'failed';
}
