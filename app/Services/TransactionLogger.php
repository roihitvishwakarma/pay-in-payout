<?php

namespace App\Services;

use App\Models\TransactionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TransactionLogger
{
    /**
     * Keys whose values get masked before logging. Override via the
     * transaction_logging.sensitive_keys config.
     *
     * @var list<string>
     */
    public const DEFAULT_SENSITIVE_KEYS = [
        'email',
        'api_key',
        'password',
        'secret',
        'token',
    ];

    public const MASK_TOKEN = '[MASKED]';

    /**
     * Write a TransactionLog row and a matching Laravel log entry for an event.
     * Sensitive fields are masked and the timestamp is stored in UTC.
     *
     * @param  array<string, mixed>  $details
     */
    public function log(
        string $transactionType,
        string $transactionId,
        string $eventType,
        array $details,
        string $severity = 'info',
    ): TransactionLog {
        $maskedDetails = $this->mask($details);
        $timestamp = Carbon::now('UTC');

        $transactionLog = TransactionLog::create([
            'transaction_type' => $transactionType,
            'transaction_id' => $transactionId,
            'event_type' => $eventType,
            'details' => $maskedDetails,
            'created_at' => $timestamp,
        ]);

        Log::log($severity, "transaction.{$eventType}", [
            'transaction_type' => $transactionType,
            'transaction_id' => $transactionId,
            'event_type' => $eventType,
            'details' => $maskedDetails,
            'timestamp' => $timestamp->toIso8601String(),
        ]);

        return $transactionLog;
    }

    /**
     * Recursively mask the values of any sensitive keys.
     *
     * @param  array<mixed, mixed>  $details
     * @return array<mixed, mixed>
     */
    private function mask(array $details): array
    {
        $sensitiveKeys = array_map(
            'strtolower',
            (array) config('transaction_logging.sensitive_keys', self::DEFAULT_SENSITIVE_KEYS),
        );

        $masked = [];

        foreach ($details as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $masked[$key] = self::MASK_TOKEN;

                continue;
            }

            $masked[$key] = is_array($value) ? $this->mask($value) : $value;
        }

        return $masked;
    }
}
