<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ProcessPaymentsCommand extends Command
{
    protected $signature = 'payments:process';

    protected $description = 'Process all PENDING pay-in and payout transactions, assigning each a random outcome.';

    public function handle(PaymentService $paymentService): int
    {
        $summary = $paymentService->processPending();

        $this->info('Payment processing complete.');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $summary->processed],
                ['Succeeded', $summary->succeeded],
                ['Failed', $summary->failed],
                ['Still pending', $summary->stillPending],
                ['Errors', $summary->errors],
            ],
        );

        return Command::SUCCESS;
    }
}
