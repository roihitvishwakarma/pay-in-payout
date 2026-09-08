<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    /**
     * Seed a few merchants, each with a single wallet. Idempotent, so it's safe to re-run.
     */
    public function run(): void
    {
        $merchants = [
            [
                'name' => 'Acme Payments Ltd',
                'email' => 'billing@acme-payments.test',
                'api_key' => 'mrc_acme_5f3a2b7c4d1e8090',
                'balance' => '0.00',
                'currency' => 'USD',
            ],
            [
                'name' => 'Globex Commerce Inc',
                'email' => 'finance@globex-commerce.test',
                'api_key' => 'mrc_globex_9a1c6d4e2f7b3058',
                'balance' => '500.00',
                'currency' => 'USD',
            ],
            [
                'name' => 'Initech Digital LLC',
                'email' => 'accounts@initech-digital.test',
                'api_key' => 'mrc_initech_3d8e0f2a6c9b1745',
                'balance' => '1000.00',
                'currency' => 'USD',
            ],
        ];

        foreach ($merchants as $data) {
            $merchant = Merchant::firstOrCreate(
                ['api_key' => $data['api_key']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                ],
            );

            // One wallet per merchant.
            Wallet::updateOrCreate(
                ['merchant_id' => $merchant->id],
                [
                    'balance' => $data['balance'],
                    'currency' => $data['currency'],
                ],
            );
        }
    }
}
