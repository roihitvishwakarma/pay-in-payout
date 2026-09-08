<?php

namespace App\Services;

use App\Models\PayIn;
use App\Models\Payout;
use App\Models\Wallet;
use App\ValueObjects\WalletAdjustmentResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class WalletService
{
    // Balances are decimal(14,2), so all money math runs through BCMath at 2 dp.
    private const SCALE = 2;

    public function __construct(
        private readonly TransactionLogger $logger,
    ) {}

    /**
     * Apply a successful transaction to the merchant's wallet, once. Payins add to the
     * balance, payouts subtract when there are sufficient funds. The whole thing runs in
     * one transaction with row locks, and re-running it on an already-processed row is a
     * no-op.
     */
    public function applyTransaction(PayIn|Payout $transaction): WalletAdjustmentResult
    {
        $transactionType = $transaction instanceof PayIn ? 'payin' : 'payout';

        try {
            return DB::transaction(function () use ($transaction, $transactionType): WalletAdjustmentResult {
                // Lock and re-read the row so we see the current processed flag.
                $lockedTransaction = $transaction->newQuery()
                    ->lockForUpdate()
                    ->findOrFail($transaction->getKey());

                // Handaling the multiple api calls
                if ($lockedTransaction->processed) {
                    $this->logger->log(
                        $transactionType,
                        $lockedTransaction->transaction_id,
                        'already_processed',
                        [
                            'transaction_id' => $lockedTransaction->transaction_id,
                            'reason' => 'Transaction already applied to wallet balance.',
                        ],
                    );

                    return WalletAdjustmentResult::skippedAlreadyProcessed();
                }

                $wallet = Wallet::query()
                    ->where('merchant_id', $lockedTransaction->merchant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = $this->normalize((string) $lockedTransaction->amount);
                $currentBalance = $this->normalize((string) $wallet->balance);

                if ($lockedTransaction instanceof Payout) {
                    // Not enough funds: leave the balance and the processed flag alone.
                    if (bccomp($currentBalance, $amount, self::SCALE) < 0) {
                        $this->logger->log(
                            $transactionType,
                            $lockedTransaction->transaction_id,
                            'insufficient_balance',
                            [
                                'transaction_id' => $lockedTransaction->transaction_id,
                                'amount' => $amount,
                                'balance' => $currentBalance,
                                'reason' => 'Insufficient wallet balance for payout.',
                            ],
                        );

                        return WalletAdjustmentResult::insufficientBalance();
                    }

                    $newBalance = bcsub($currentBalance, $amount, self::SCALE);
                    $adjustment = bcsub('0', $amount, self::SCALE);
                } else {
                    $newBalance = bcadd($currentBalance, $amount, self::SCALE);
                    $adjustment = $amount;
                }

                $wallet->balance = $newBalance;
                $wallet->save();

                $lockedTransaction->processed = true;
                $lockedTransaction->save();

                $this->logger->log(
                    $transactionType,
                    $lockedTransaction->transaction_id,
                    'wallet_updated',
                    [
                        'transaction_id' => $lockedTransaction->transaction_id,
                        'adjustment' => $adjustment,
                        'resulting_balance' => $newBalance,
                    ],
                );

                return WalletAdjustmentResult::applied($adjustment, $newBalance);
            });
        } catch (Throwable $e) {
            // The transaction rolled back, so balance and processed flag are untouched.
            $this->logger->log(
                $transactionType,
                $transaction->transaction_id,
                'processing_failed',
                [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                ],
                'error',
            );

            return WalletAdjustmentResult::failed($e->getMessage());
        }
    }

    /**
     * Normalize a money string to 2 decimal places.
     */
    private function normalize(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
