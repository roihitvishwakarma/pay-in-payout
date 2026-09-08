# Implementation Plan: Pay-in & Payout Module

## Overview

This plan implements the Pay-in & Payout Module on Laravel 13 (PHP ^8.3) using a strict layered architecture: thin controllers, Form Request validation, service-layer business logic, Eloquent models with explicit relationships, and a scheduled Artisan command for asynchronous processing. Laravel Backpack provides CRUD administration.

The plan is incremental: it installs tooling first, builds the data layer, then services, then the API and console layers, then admin CRUD and seeders, and finally documentation and the full test suite. Each task builds on the previous ones and wires new code into the existing structure so there is no orphaned code. Property-based tests validate the 14 correctness properties from the design; unit and integration tests cover schema, scheduler wiring, CRUD, and fault-injection paths.

Money is handled as `decimal(14,2)` and manipulated as BCMath-safe strings to avoid floating-point drift.

## Tasks

- [x] 1. Install dependencies and scaffold module structure
  - [x] 1.1 Install and configure Laravel Backpack
    - Run `composer require backpack/crud` and `php artisan backpack:install` from the app root
    - Verify the admin auth middleware and `/admin` route group are registered
    - Confirm Backpack CRUD panel is reachable and gated by authentication (foundation for Req 8.1-8.4, 8.9)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.9_

  - [x] 1.2 Install a PHP property-based testing library
    - Run `composer require --dev innmind/black-box` (or `giorgiosironi/eris`) as the PBT library compatible with PHPUnit
    - Add a base test helper/trait for running generated iterations (minimum 100 per property)
    - Confirm `phpunit.xml` picks up the new test suites and the library autoloads under dev
    - _Requirements: 7.x (testability foundation)_

- [x] 2. Create database schema via migrations
  - [x] 2.1 Create the merchants migration
    - Create `merchants` table: `id`, `name` string(255), `email` string(255), `api_key` string(255) unique, timestamps
    - Add the unique index on `api_key`
    - _Requirements: 1.1_

  - [x] 2.2 Create the wallets migration
    - Create `wallets` table: `id`, `merchant_id` foreignId (FK -> merchants.id, cascade on delete) with a unique index enforcing one-to-one, `balance` decimal(14,2) default `0.00`, `currency` string(3) default `'USD'`, timestamps
    - The unique `merchant_id` index rejects a second wallet for the same merchant, leaving the existing wallet unchanged
    - _Requirements: 1.2, 1.7, 1.8, 1.9_

  - [x] 2.3 Create the pay_ins migration
    - Create `pay_ins` table: `id`, `merchant_id` foreignId (FK -> merchants.id) indexed, `transaction_id` string(64) unique index, `amount` decimal(14,2), `status` string(16) default `'pending'` indexed, `processed` boolean default `false`, timestamps
    - _Requirements: 1.3, 1.5, 1.6, 6.1_

  - [x] 2.4 Create the payouts migration
    - Create `payouts` table with identical structure to `pay_ins`: `id`, `merchant_id` foreignId indexed, `transaction_id` string(64) unique index, `amount` decimal(14,2), `status` string(16) default `'pending'` indexed, `processed` boolean default `false`, timestamps
    - _Requirements: 1.3, 1.5, 1.6, 6.1_

  - [x] 2.5 Create the transaction_logs migration
    - Create `transaction_logs` table: `id`, `transaction_type` string(16), `transaction_id` string(64) indexed, `event_type` string(255), `details` json/text, `created_at` timestamp (no `updated_at`)
    - _Requirements: 1.4_

  - [ ]* 2.6 Write integration tests for schema and indexes
    - Assert all five tables exist with expected columns
    - Assert unique indexes on `merchants.api_key`, `wallets.merchant_id`, `pay_ins.transaction_id`, `payouts.transaction_id`
    - Assert regular indexes on `status` and `merchant_id` for both transaction tables
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [x] 3. Define enum and Eloquent models with relationships
  - [x] 3.1 Create the PaymentStatus enum
    - Define `App\Enums\PaymentStatus` backed string enum with cases `Pending = 'pending'`, `Success = 'success'`, `Failed = 'failed'`
    - _Requirements: 1.3, 4.5_

  - [x] 3.2 Create Merchant, Wallet, PayIn, Payout, and TransactionLog models
    - `Merchant`: `wallet()` HasOne, `payIns()` HasMany, `payouts()` HasMany
    - `Wallet`: `merchant()` BelongsTo, cast `balance` => `decimal:2`
    - `PayIn`: `merchant()` BelongsTo, `logs()` HasMany (scoped `transaction_type = 'payin'`), casts `amount` => `decimal:2`, `processed` => `boolean`, `status` => PaymentStatus
    - `Payout`: mirror of `PayIn` with `logs()` scoped `transaction_type = 'payout'`
    - `TransactionLog`: `$timestamps = false`, casts `details` => `array`, `created_at` => `datetime`
    - _Requirements: 7.5_

  - [ ]* 3.3 Write unit tests for model relationships and wallet defaults
    - Assert relationships resolve correctly across all models (Req 7.5)
    - Assert a wallet created without explicit currency/balance defaults to `'USD'` and `0.00` (Req 1.9)
    - Assert inserting a second wallet for a merchant fails and leaves the existing wallet unchanged (Req 1.8)
    - _Requirements: 7.5, 1.9, 1.8_

- [x] 4. Implement the TransactionLogger service
  - [x] 4.1 Implement TransactionLogger with masking
    - Create `App\Services\TransactionLogger` with `log($transactionType, $transactionId, $eventType, array $details, string $severity = 'info')` that writes a `TransactionLog` row and a Laravel Log entry at the given severity with a UTC timestamp
    - Implement private `mask()` that replaces configured sensitive keys (e.g. `email`, `api_key`) with a masked token before persistence/logging
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [ ]* 4.2 Write property test for masked initiation logging
    - **Property 10: Initiation is logged with sensitive fields masked**
    - **Validates: Requirements 2.8, 3.7, 9.1**
    - Tag: `// Feature: pay-in-payout-module, Property 10`

  - [ ]* 4.3 Write unit tests for logger severity and UTC timestamp
    - Assert INFO severity for events and ERROR severity for exceptions, each with a UTC timestamp (Req 9.2, 9.3, 9.4)
    - _Requirements: 9.2, 9.3, 9.4_

- [x] 5. Implement WalletService (atomic, idempotent balance adjustment)
  - [x] 5.1 Implement WalletService.applyTransaction
    - Create `App\Services\WalletService::applyTransaction(PayIn|Payout $transaction): WalletAdjustmentResult`
    - Wrap in `DB::transaction`: `lockForUpdate()` on the transaction and wallet rows; if `processed` is true, skip and log `already_processed` with no balance change; PAYIN -> balance += amount; PAYOUT -> if balance >= amount decrease, else leave unchanged and log `insufficient_balance` rejection; on a balance change set `processed = true` and log `wallet_updated` in the same transaction; roll both back on failure
    - Define the `WalletAdjustmentResult` value object (applied | skipped_already_processed | insufficient_balance | failed)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.2, 6.3, 6.4, 6.5, 9.3_

  - [ ]* 5.2 Write property test for balance conservation
    - **Property 9: Balance conservation on resolved transactions**
    - **Validates: Requirements 5.1, 5.2, 5.4**
    - Tag: `// Feature: pay-in-payout-module, Property 9`

  - [ ]* 5.3 Write property test for insufficient-balance payout
    - **Property 11: Insufficient-balance payout leaves the balance unchanged and logs a rejection**
    - **Validates: Requirements 5.5**
    - Tag: `// Feature: pay-in-payout-module, Property 11`

  - [ ]* 5.4 Write property test for idempotency / no double deduction
    - **Property 12: A transaction adjusts the wallet at most once**
    - **Validates: Requirements 5.6, 6.2, 6.3, 6.5**
    - Tag: `// Feature: pay-in-payout-module, Property 12`

  - [ ]* 5.5 Write property test for atomic paired wallet_updated log
    - **Property 13: A wallet balance change and its wallet_updated log are atomic and paired**
    - **Validates: Requirements 5.3, 9.3**
    - Tag: `// Feature: pay-in-payout-module, Property 13`

  - [ ]* 5.6 Write unit tests for commit-failure rollback
    - Assert an injected commit failure leaves both the balance and the `processed` marker unchanged and logs a processing failure (Req 6.4)
    - _Requirements: 6.4_

- [x] 6. Checkpoint - Ensure all data-layer and wallet tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Implement PaymentService (creation + processing)
  - [x] 7.1 Implement transaction ID generation
    - Implement `PaymentService::generateTransactionId(string $type): string` producing `TXN-<PI|PO>-<UTCdate>-<10 uppercase hex>`, unique across both tables by construction plus DB unique index; retry on unique-constraint violation
    - _Requirements: 2.2, 3.2, 1.5_

  - [ ]* 7.2 Write property test for global transaction ID uniqueness
    - **Property 1: Transaction IDs are globally unique**
    - **Validates: Requirements 1.5, 2.2, 3.2**
    - Tag: `// Feature: pay-in-payout-module, Property 1`

  - [x] 7.3 Implement PaymentService.createTransaction
    - Implement `createTransaction(string $type, int $merchantId, string $amount): Model` that persists merchant ref, amount, status `pending`, and transaction_id atomically inside `DB::transaction`, then logs `payment_initiated` via TransactionLogger
    - Inject `WalletService` and `TransactionLogger` via constructor
    - _Requirements: 2.1, 2.3, 2.8, 3.1, 3.3, 3.7, 7.1_

  - [ ]* 7.4 Write property test for fully-persisted PENDING transaction
    - **Property 2: Valid input creates a fully-persisted PENDING transaction**
    - **Validates: Requirements 2.1, 2.3, 3.1, 3.3**
    - Tag: `// Feature: pay-in-payout-module, Property 2`

  - [x] 7.5 Implement random outcome assignment
    - Implement `randomOutcome(): PaymentStatus` returning one status at random from {success, failed, pending}
    - _Requirements: 4.5_

  - [ ]* 7.6 Write property test for allowed outcome set
    - **Property 7: Assigned outcomes are always within the allowed set**
    - **Validates: Requirements 4.5**
    - Tag: `// Feature: pay-in-payout-module, Property 7`

  - [x] 7.7 Implement PaymentService.processPending
    - Select all `pending` pay-ins and payouts; for each, assign a random outcome inside its own try/catch + DB transaction; PENDING leaves unchanged; FAILED updates status and logs `status_changed`; SUCCESS delegates to `WalletService::applyTransaction`; log `status_changed` with previous and new status; on persistence failure retain previous status and log `processing_failed`; return a `ProcessingSummary`
    - _Requirements: 4.3, 4.4, 4.6, 4.7, 4.8, 4.9, 5.x, 9.2, 9.4_

  - [ ]* 7.8 Write property test for selecting exactly PENDING transactions
    - **Property 6: The processor selects exactly the PENDING transactions**
    - **Validates: Requirements 4.3, 4.7**
    - Tag: `// Feature: pay-in-payout-module, Property 6`

  - [ ]* 7.9 Write property test for persisted and logged status transitions
    - **Property 8: Status transitions persist and are logged with previous and new status**
    - **Validates: Requirements 4.6, 4.8, 9.2**
    - Tag: `// Feature: pay-in-payout-module, Property 8`

  - [ ]* 7.10 Write unit tests for empty pending set and exception handling
    - Assert an empty pending set completes without changing any transaction (Req 4.4, edge case)
    - Assert a persist failure during status change retains the previous status and logs a failure (Req 4.9)
    - Assert an exception during processing writes an ERROR log and leaves persisted state intact (Req 9.4)
    - _Requirements: 4.4, 4.9, 9.4_

- [x] 8. Checkpoint - Ensure all service-layer tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Implement Form Requests and API layer
  - [x] 9.1 Create StorePayInRequest and StorePayoutRequest
    - Both share rules: `merchant_id` required|integer|exists:merchants,id; `amount` required|numeric|gt:0|max:999999999999.99|decimal:0,2
    - Add messages mapping to "not recognized" for merchant and "invalid"/"out of range" for amount
    - _Requirements: 2.5, 2.6, 2.7, 3.5, 3.6, 7.3, 7.4_

  - [x] 9.2 Create thin PayInController and PayoutController
    - `PayInController::store(StorePayInRequest, PaymentService)` calls `createTransaction('payin', ...)` and returns 201 `{transaction_id, status}`
    - `PayoutController::store(...)` mirrors for `'payout'` returning 201
    - Controllers contain no creation, processing, or wallet logic
    - _Requirements: 2.4, 3.4, 7.1, 7.2_

  - [x] 9.3 Register API routes
    - Create `routes/api.php` mapping `POST /api/pay-in` -> PayInController@store and `POST /api/payout` -> PayoutController@store, and ensure it is loaded in `bootstrap/app.php`
    - _Requirements: 2.4, 3.4_

  - [ ]* 9.4 Write property test for 201 success response
    - **Property 3: Successful creation returns 201 with the created identifiers**
    - **Validates: Requirements 2.4, 3.4**
    - Tag: `// Feature: pay-in-payout-module, Property 3`

  - [ ]* 9.5 Write property test for unknown-merchant rejection
    - **Property 4: Unknown merchant is rejected with 422 and no side effects**
    - **Validates: Requirements 2.5, 3.5**
    - Tag: `// Feature: pay-in-payout-module, Property 4`

  - [ ]* 9.6 Write property test for invalid-amount rejection
    - **Property 5: Invalid amounts are rejected with 422 and no side effects**
    - **Validates: Requirements 2.6, 2.7, 3.6**
    - Tag: `// Feature: pay-in-payout-module, Property 5`

  - [ ]* 9.7 Write property test for no side effects on failed validation
    - **Property 14: Failed validation produces no logs and no balance change**
    - **Validates: Requirements 7.3, 7.4**
    - Tag: `// Feature: pay-in-payout-module, Property 14`

- [x] 10. Implement scheduled processing command
  - [x] 10.1 Create ProcessPaymentsCommand Artisan command
    - Create `payments:process` command that calls `PaymentService::processPending()` and reports a summary
    - _Requirements: 4.3, 7.6_

  - [x] 10.2 Register the command on the scheduler
    - Register `payments:process` in the scheduler at a fixed recurring interval with `withoutOverlapping()` so a new run is skipped while a previous run is in progress
    - _Requirements: 4.1, 4.2, 7.6_

  - [ ]* 10.3 Write integration tests for scheduler registration and overlap skip
    - Assert `payments:process` is registered at a fixed interval with `withoutOverlapping` (Req 4.1, 4.2, 7.6)
    - Assert overlapping-run skip behavior (Req 4.2)
    - _Requirements: 4.1, 4.2, 7.6_

- [x] 11. Checkpoint - Ensure API and command tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Implement Backpack CRUD administration
  - [x] 12.1 Create MerchantCrudController and WalletCrudController
    - Register CRUD routes; configure list/create/read/update/delete columns and fields for Merchant and Wallet
    - _Requirements: 8.1, 8.2, 8.9_

  - [x] 12.2 Create PayInCrudController and PayoutCrudController with filters
    - Register CRUD routes and configure list/create/read/update/delete for Pay-in and Payout
    - Add status filter, merchant filter, and date-range filter (inclusive of boundary dates); empty filter results render without error
    - _Requirements: 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9_

  - [ ]* 12.3 Write integration tests for CRUD and filters
    - Assert list/create/read/update/delete for all four entities (Req 8.1-8.4)
    - Assert status, merchant, and empty-result filters (Req 8.5, 8.6, 8.8)
    - Assert date-range boundary inclusivity (Req 8.7)
    - Assert unauthenticated access is denied to admin routes (Req 8.9)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9_

- [x] 13. Implement database seeders
  - [x] 13.1 Create merchant and wallet seeders
    - Create at least 3 sample merchants, each associated with exactly one wallet initialized with a non-negative balance; wire into `DatabaseSeeder`
    - _Requirements: 10.4_

  - [ ]* 13.2 Write unit test for seeder output
    - Assert the seeder creates >= 3 merchants each with exactly one wallet and a non-negative balance (Req 10.4)
    - _Requirements: 10.4_

- [x] 14. Write project documentation
  - [x] 14.1 Write README setup section
    - Document environment configuration, migration, and seeding steps at the repository root, each with the exact command and expected successful outcome
    - _Requirements: 10.1_

  - [x] 14.2 Write API documentation
    - Document `/api/pay-in` and `/api/payout` with sample requests (all required/optional params), a sample success (201) response, and a sample error (422) response for each documented error condition identifying its trigger
    - _Requirements: 10.2, 10.3_

  - [ ]* 14.3 Write documentation-verification tests
    - Assert README documents environment/migration/seeding commands with expected outcomes (Req 10.1)
    - Assert API docs include sample requests, success responses, and an error response per documented error (Req 10.2, 10.3)
    - _Requirements: 10.1, 10.2, 10.3_

- [x] 15. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional test sub-tasks and can be skipped for a faster MVP; core implementation tasks are never optional.
- Each task references specific requirements for traceability.
- Property-based tests (one per correctness property, Properties 1-14) run a minimum of 100 generated iterations and carry a `// Feature: pay-in-payout-module, Property {n}` tag.
- Unit and integration tests cover schema, scheduler wiring, Backpack CRUD, fault-injection paths, seeders, and documentation that are not expressible as universal properties.
- Money is handled as `decimal(14,2)` and manipulated as BCMath-safe strings to avoid floating-point drift.
- Checkpoints ensure incremental validation at natural boundaries.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "2.2", "2.3", "2.4", "2.5"] },
    { "id": 2, "tasks": ["2.6", "3.1"] },
    { "id": 3, "tasks": ["3.2"] },
    { "id": 4, "tasks": ["3.3", "4.1"] },
    { "id": 5, "tasks": ["4.2", "4.3", "5.1"] },
    { "id": 6, "tasks": ["5.2", "5.3", "5.4", "5.5", "5.6", "7.1", "7.5"] },
    { "id": 7, "tasks": ["7.2", "7.3", "7.6"] },
    { "id": 8, "tasks": ["7.4", "7.7"] },
    { "id": 9, "tasks": ["7.8", "7.9", "7.10", "9.1"] },
    { "id": 10, "tasks": ["9.2", "10.1"] },
    { "id": 11, "tasks": ["9.3", "10.2", "12.1", "12.2", "13.1"] },
    { "id": 12, "tasks": ["9.4", "9.5", "9.6", "9.7", "10.3", "12.3", "13.2", "14.1", "14.2"] },
    { "id": 13, "tasks": ["14.3"] }
  ]
}
```
