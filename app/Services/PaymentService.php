<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\PayIn;
use App\Models\Payout;
use App\ValueObjects\ProcessingSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentService
{
    private const SUFFIX_HEX_LENGTH = 10;

    private const MAX_ID_ATTEMPTS = 5;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly TransactionLogger $logger,
    ) {}

    /**
     * Create a pending payin or payout transaction.
     *
     * @param  'payin'|'payout'  $type
     */
    public function createTransaction(string $type, int $merchantId, string $amount): Model
    {
        $modelClass = match ($type) {
            'payin' => PayIn::class,
            'payout' => Payout::class,
            default => throw new InvalidArgumentException(
                "Unsupported transaction type [{$type}]; expected 'payin' or 'payout'."
            ),
        };

        $transaction = $this->persistWithUniqueId($type, $modelClass, $merchantId, $amount);

        // Log only after the insert has committed.
        $this->logger->log(
            $type,
            $transaction->transaction_id,
            'payment_initiated',
            [
                'transaction_id' => $transaction->transaction_id,
                'merchant_id' => $merchantId,
                'amount' => (string) $amount,
            ],
        );

        return $transaction;
    }

    /**
     * @param  'payin'|'payout'  $type
     * @param  class-string<PayIn|Payout>  $modelClass
     */
    private function persistWithUniqueId(string $type, string $modelClass, int $merchantId, string $amount): Model
    {
        for ($attempt = 1; $attempt <= self::MAX_ID_ATTEMPTS; $attempt++) {
            $transactionId = $this->generateTransactionId($type);

            try {
                return DB::transaction(function () use ($modelClass, $merchantId, $amount, $transactionId): Model {
                    return $modelClass::create([
                        'merchant_id' => $merchantId,
                        'transaction_id' => $transactionId,
                        'amount' => $amount,
                        'status' => PaymentStatus::Pending,
                    ]);
                });
            } catch (QueryException $e) {
                // Only retry on a duplicate transaction_id; let everything else bubble up.
                if (! $this->isUniqueConstraintViolation($e) || $attempt === self::MAX_ID_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException(
            "Unable to assign a unique Transaction_ID for a {$type} after " . self::MAX_ID_ATTEMPTS . ' attempts.'
        );
    }

    /**
     * SQLState 23000/23505 covers integrity violations across MySQL, Postgres and SQLite.
     * The message check narrows SQLite's broad 23000 down to actual uniqueness collisions.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $message = strtolower($e->getMessage());

        if (! in_array($sqlState, ['23000', '23505'], true)) {
            return false;
        }

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'transaction_id');
    }

    /**
     * Build a transaction id like TXN-PI-20240612-9F3A2B7C4D. The PI/PO prefix keeps
     * payin and payout ids from ever colliding with each other.
     *
     * @param  'payin'|'payout'  $type
     */
    public function generateTransactionId(string $type): string
    {
        $prefix = match ($type) {
            'payin' => 'PI',
            'payout' => 'PO',
            default => throw new InvalidArgumentException(
                "Unsupported transaction type [{$type}]; expected 'payin' or 'payout'."
            ),
        };

        $date = Carbon::now('UTC')->format('Ymd');

        // 5 random bytes -> 10 hex chars.
        $suffix = strtoupper(bin2hex(random_bytes((int) (self::SUFFIX_HEX_LENGTH / 2))));

        return "TXN-{$prefix}-{$date}-{$suffix}";
    }

    /**
     * Pick pending, success or failed with equal probability.
     */
    public function randomOutcome(): PaymentStatus
    {
        $cases = PaymentStatus::cases();

        return $cases[random_int(0, count($cases) - 1)];
    }

    /**
     * Resolve every pending payin and payout with a random outcome and return a summary
     * of the run. Already resolved transactions are left alone.
     */
    public function processPending(): ProcessingSummary
    {
        $summary = new ProcessingSummary;

        $pending = PayIn::query()->where('status', PaymentStatus::Pending)->get()
            ->all();

        $pending = array_merge(
            $pending,
            Payout::query()->where('status', PaymentStatus::Pending)->get()->all(),
        );

        foreach ($pending as $transaction) {
            $summary = $this->processOne($transaction, $summary);
        }

        return $summary;
    }

    /**
     * Resolve one pending transaction. Wrapped in try/catch so a single failure
     * doesn't abort the rest of the run.
     *
     * @param  PayIn|Payout  $transaction
     */
    private function processOne(Model $transaction, ProcessingSummary $summary): ProcessingSummary
    {
        $type = $transaction instanceof PayIn ? 'payin' : 'payout';
        $previousStatus = PaymentStatus::Pending;

        try {
            $outcome = $this->randomOutcome();

            // Still pending: leave it for the next run.
            if ($outcome === PaymentStatus::Pending) {
                return $summary->with(processed: 1, stillPending: 1);
            }

            // Persist the status change and its log together.
            DB::transaction(function () use ($transaction, $outcome, $previousStatus, $type): void {
                $transaction->status = $outcome;
                $transaction->save();

                $this->logger->log(
                    $type,
                    $transaction->transaction_id,
                    'status_changed',
                    [
                        'transaction_id' => $transaction->transaction_id,
                        'previous_status' => $previousStatus->value,
                        'new_status' => $outcome->value,
                    ],
                );
            });

            if ($outcome === PaymentStatus::Failed) {
                // Failed transactions never touch the wallet.
                return $summary->with(processed: 1, failed: 1);
            }

            // Success: let WalletService apply the balance change (it's idempotent).
            $this->walletService->applyTransaction($transaction);

            return $summary->with(processed: 1, succeeded: 1);
        } catch (Throwable $e) {
            // The status change rolled back, so the record keeps its previous state.
            $this->logger->log(
                $type,
                $transaction->transaction_id,
                'processing_failed',
                [
                    'transaction_id' => $transaction->transaction_id,
                    'error' => $e->getMessage(),
                ],
                'error',
            );

            return $summary->with(processed: 1, errors: 1);
        }
    }
}
