<?php

use App\Services\TransactionLogger;

return [
    /*
    |--------------------------------------------------------------------------
    | Sensitive Keys
    |--------------------------------------------------------------------------
    |
    | Keys whose values get masked in transaction log details before they're
    | stored or written to the log. Matching is case-insensitive and recurses
    | into nested arrays. See App\Services\TransactionLogger.
    |
    */
    'sensitive_keys' => TransactionLogger::DEFAULT_SENSITIVE_KEYS,
];
