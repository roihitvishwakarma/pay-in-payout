# Requirements Document

## Introduction

The Pay-in & Payout Module is a backend payment-processing feature for a Laravel 13 application, administered through Laravel Backpack. The module allows registered merchants to initiate pay-in (money received) and payout (money disbursed) transactions through a JSON API. Each transaction is created in a PENDING state, persisted with a unique transaction identifier, and logged with full request and payment details.

A scheduled background job periodically evaluates all PENDING transactions and assigns each a simulated outcome of SUCCESS, FAILED, or PENDING. Transactions that resolve to SUCCESS adjust the owning merchant's wallet balance exactly once, with duplicate-processing protection to guarantee that a balance is never adjusted twice for the same transaction. Transactions that remain PENDING are re-evaluated on the next scheduled run.

The module emphasizes clean Laravel architecture: business logic resides in dedicated service classes rather than controllers, validation is handled through Form Requests, data access uses Eloquent models with defined relationships, and the scheduled processing runs through an Artisan console command driven by the Laravel scheduler. Laravel Backpack provides basic CRUD administration for merchants, wallets, pay-ins, and payouts with filters for status, merchant, and date.

## Glossary

- **Payment_System**: The overall backend feature comprising the API layer, service layer, data layer, and scheduled processing that handles pay-in and payout transactions.
- **Pay_In_API**: The HTTP JSON endpoint that accepts requests to initiate a pay-in transaction.
- **Payout_API**: The HTTP JSON endpoint that accepts requests to initiate a payout transaction.
- **Transaction**: A single pay-in or payout record. Each Transaction has a type (PAYIN or PAYOUT), a status, an amount, an owning Merchant, and a unique Transaction_ID.
- **Transaction_ID**: A system-generated identifier that is unique across all Transactions.
- **Merchant**: A registered business entity that owns a Wallet and initiates Transactions.
- **Wallet**: A record holding the monetary balance and currency for exactly one Merchant.
- **Payment_Status**: The lifecycle state of a Transaction; one of PENDING, SUCCESS, or FAILED.
- **PENDING**: The Payment_Status assigned when a Transaction is created and awaiting processing.
- **SUCCESS**: The Payment_Status indicating a Transaction completed and affected the Wallet balance.
- **FAILED**: The Payment_Status indicating a Transaction terminated without affecting the Wallet balance.
- **Payment_Processor**: The scheduled Artisan console command that evaluates PENDING Transactions and assigns outcomes.
- **Payment_Service**: The service-layer component containing transaction creation and processing business logic.
- **Wallet_Service**: The service-layer component that performs Wallet balance adjustments.
- **Transaction_Log**: A persisted record of an event associated with a Transaction, including event type, details, and timestamp.
- **Admin_Panel**: The Laravel Backpack administration interface for managing Merchants, Wallets, Pay-ins, and Payouts.
- **Scheduler**: The Laravel scheduler that triggers the Payment_Processor at a fixed interval.
- **Processed_Marker**: A persisted value on each Transaction, either "not processed" or "processed", recording whether the Transaction has already adjusted a Wallet balance.
- **Insufficient_Balance**: The condition in which a PAYOUT Transaction resolves to SUCCESS but the owning Merchant Wallet balance is less than the Transaction amount, causing the Wallet_Service to leave the balance unchanged and record a rejection.

## Requirements

### Requirement 1: Database Schema and Relationships

**User Story:** As a developer, I want a well-structured relational schema created through migrations, so that merchants, wallets, and transactions are stored with correct relationships and constraints.

#### Acceptance Criteria

1. THE Payment_System SHALL provide a migration that creates a merchants table with a name column of at most 255 characters, an email column of at most 255 characters, and a unique API identifier column.
2. THE Payment_System SHALL provide a migration that creates a wallets table with a foreign key referencing the merchants table, a decimal balance column with fixed precision holding values from 0.00 to 999,999,999,999.99, and a currency column of at most 3 characters.
3. THE Payment_System SHALL provide a migration that creates a pay_ins table and a migration that creates a payouts table, each with a foreign key referencing the merchants table, a unique Transaction_ID column, a decimal amount column with 2 decimal places holding values from 0.01 to 999,999,999,999.99, and a Payment_Status column restricted to the set {pending, success, failed}.
4. THE Payment_System SHALL provide a migration that creates a transaction_logs table with a column referencing the associated Transaction, an event type column of at most 255 characters, a details column, and a creation timestamp column.
5. THE Payment_System SHALL define a unique database index on the Transaction_ID column of the pay_ins table and on the Transaction_ID column of the payouts table.
6. THE Payment_System SHALL define a database index on the Payment_Status column and on the merchant foreign key column of the pay_ins table and the payouts table.
7. WHERE a wallets record references a merchants record, THE Payment_System SHALL enforce a one-to-one relationship such that each Merchant is associated with at most one Wallet and each Wallet references exactly one Merchant.
8. IF an attempt is made to insert a second wallets record for a Merchant that already has a Wallet, THEN THE Payment_System SHALL reject the insertion and leave the existing Wallet unchanged.
9. WHEN a wallets record is created without an explicit currency or balance, THE Payment_System SHALL set the currency to a default value and the balance to 0.00.

### Requirement 2: Initiate Pay-in Transaction

**User Story:** As a merchant, I want to initiate a pay-in through an API, so that an incoming payment is recorded for processing.

#### Acceptance Criteria

1. WHEN the Pay_In_API receives a request containing a Merchant identifier that references an existing Merchant and an amount that is numeric, greater than 0, less than or equal to 999,999,999,999.99, and expressed with at most 2 decimal places, THE Payment_System SHALL create a Transaction of type PAYIN with Payment_Status set to PENDING.
2. WHEN the Payment_System creates a PAYIN Transaction, THE Payment_System SHALL assign a Transaction_ID that is unique across all Transactions such that no two Transactions share the same Transaction_ID.
3. WHEN the Payment_System creates a PAYIN Transaction, THE Payment_System SHALL persist the Merchant reference, the amount, the Payment_Status, and the Transaction_ID as a single atomic operation.
4. WHEN the Pay_In_API successfully creates a PAYIN Transaction, THE Pay_In_API SHALL return an HTTP 201 response containing the Transaction_ID and the Payment_Status.
5. IF the Pay_In_API receives a request referencing a Merchant identifier that does not exist, THEN THE Pay_In_API SHALL return an HTTP 422 response containing a validation error message indicating the Merchant identifier is not recognized, and SHALL NOT create any Transaction.
6. IF the Pay_In_API receives a request with an amount that is missing, non-numeric, or expressed with more than 2 decimal places, THEN THE Pay_In_API SHALL return an HTTP 422 response containing a validation error message indicating the amount is invalid, and SHALL NOT create any Transaction.
7. IF the Pay_In_API receives a request with an amount that is less than or equal to 0 or greater than 999,999,999,999.99, THEN THE Pay_In_API SHALL return an HTTP 422 response containing a validation error message indicating the amount is out of range, and SHALL NOT create any Transaction.
8. WHEN the Pay_In_API receives a request, THE Payment_System SHALL record a Transaction_Log entry with event type payment_initiated containing the Transaction_ID and the submitted request details.

### Requirement 3: Initiate Payout Transaction

**User Story:** As a merchant, I want to initiate a payout through an API, so that an outgoing payment is recorded for processing.

#### Acceptance Criteria

1. WHEN the Payout_API receives a request containing a Merchant identifier that references an existing Merchant and an amount that is numeric, greater than 0, less than or equal to 999,999,999,999.99, and expressed with at most 2 decimal places, THE Payment_System SHALL create a Transaction of type PAYOUT with Payment_Status set to PENDING.
2. WHEN the Payment_System creates a PAYOUT Transaction, THE Payment_System SHALL assign a Transaction_ID that is unique across all Transactions such that no two Transactions share the same Transaction_ID.
3. WHEN the Payment_System creates a PAYOUT Transaction, THE Payment_System SHALL persist the Merchant reference, the amount, the Payment_Status, and the Transaction_ID as a single atomic operation.
4. WHEN the Payout_API successfully creates a PAYOUT Transaction, THE Payout_API SHALL return an HTTP 201 response containing the Transaction_ID and the Payment_Status.
5. IF the Payout_API receives a request referencing a Merchant identifier that does not exist, THEN THE Payout_API SHALL return an HTTP 422 response containing a validation error message indicating the Merchant identifier is not recognized, and SHALL NOT create any Transaction.
6. IF the Payout_API receives a request with an amount that is missing, non-numeric, less than or equal to 0, greater than 999,999,999,999.99, or expressed with more than 2 decimal places, THEN THE Payout_API SHALL return an HTTP 422 response containing a validation error message indicating the amount is invalid, and SHALL NOT create any Transaction.
7. WHEN the Payout_API receives a request, THE Payment_System SHALL record a Transaction_Log entry with event type payment_initiated containing the Transaction_ID and the submitted request details.

### Requirement 4: Scheduled Payment Processing

**User Story:** As an operator, I want pending payments processed automatically on a schedule, so that transactions resolve without manual intervention.

#### Acceptance Criteria

1. THE Scheduler SHALL invoke the Payment_Processor at a fixed recurring interval.
2. IF the Scheduler triggers the Payment_Processor while a previous Payment_Processor run is still in progress, THEN THE Scheduler SHALL skip the new invocation so that the same PENDING Transaction is not processed by two concurrent runs.
3. WHEN the Payment_Processor runs, THE Payment_Processor SHALL select all Transactions with Payment_Status equal to PENDING.
4. WHEN the Payment_Processor runs and no Transaction has Payment_Status equal to PENDING, THE Payment_Processor SHALL complete the run without changing any Transaction.
5. WHEN the Payment_Processor evaluates a PENDING Transaction, THE Payment_Processor SHALL assign one Payment_Status selected at random from the set {SUCCESS, FAILED, PENDING}.
6. WHEN the Payment_Processor assigns SUCCESS or FAILED to a Transaction, THE Payment_Processor SHALL update the stored Payment_Status of that Transaction to the assigned value.
7. WHERE a Transaction retains Payment_Status PENDING after a Payment_Processor run, THE Payment_Processor SHALL select that Transaction again on the next run.
8. WHEN the Payment_Processor changes the Payment_Status of a Transaction, THE Payment_System SHALL record a Transaction_Log entry with event type status_changed containing the Transaction_ID, the previous Payment_Status, and the new Payment_Status.
9. IF persisting a Payment_Status change fails, THEN THE Payment_Processor SHALL retain the previous Payment_Status for that Transaction and record a Transaction_Log entry describing the processing failure.

### Requirement 5: Wallet Balance Update on Success

**User Story:** As a merchant, I want my wallet balance updated correctly when a transaction succeeds, so that my balance reflects completed pay-ins and payouts.

#### Acceptance Criteria

1. WHEN the Payment_Processor assigns SUCCESS to a PAYIN Transaction, THE Wallet_Service SHALL increase the owning Merchant Wallet balance by the Transaction amount.
2. WHEN the Payment_Processor assigns SUCCESS to a PAYOUT Transaction AND the owning Merchant Wallet balance is greater than or equal to the Transaction amount, THE Wallet_Service SHALL decrease the owning Merchant Wallet balance by the Transaction amount.
3. WHEN the Wallet_Service adjusts a Wallet balance, THE Payment_System SHALL record a Transaction_Log entry with event type wallet_updated containing the Transaction_ID, the adjustment amount, and the resulting balance, and SHALL apply the balance change and the Transaction_Log entry as a single atomic operation such that if either fails both are rolled back and the Wallet balance remains unchanged.
4. WHEN the Payment_Processor assigns FAILED to a Transaction, THE Wallet_Service SHALL leave the owning Merchant Wallet balance unchanged.
5. IF the Payment_Processor assigns SUCCESS to a PAYOUT Transaction AND the owning Merchant Wallet balance is less than the Transaction amount, THEN THE Wallet_Service SHALL leave the Wallet balance unchanged and record a Transaction_Log entry indicating an insufficient-balance rejection for the Transaction_ID.
6. IF the Payment_Processor assigns SUCCESS to a Transaction that has already been applied to the owning Merchant Wallet balance, THEN THE Wallet_Service SHALL leave the Wallet balance unchanged and SHALL NOT record an additional wallet_updated Transaction_Log entry for that Transaction_ID.

### Requirement 6: Duplicate Processing Prevention

**User Story:** As a finance stakeholder, I want each transaction to affect the wallet exactly once, so that balances are never double-counted.

#### Acceptance Criteria

1. THE Payment_System SHALL persist a processed marker on each Transaction whose value is either "not processed" or "processed", recording whether the Transaction has already adjusted a Wallet balance.
2. IF the Payment_Processor evaluates a Transaction whose processed marker is "processed", THEN THE Payment_System SHALL leave the Wallet balance unchanged for that Transaction and record an indication that the Transaction was skipped as already processed.
3. WHEN the Wallet_Service adjusts a Wallet balance for a Transaction, THE Payment_System SHALL perform the balance adjustment and set the processed marker to "processed" within a single database transaction that commits both changes together or rolls both back.
4. IF committing the combined balance adjustment and processed-marker update fails, THEN THE Payment_System SHALL leave both the Wallet balance and the processed marker unchanged and record a processing-failure indication for the Transaction_ID.
5. WHEN the Payment_Processor assigns SUCCESS to a Transaction, THE Payment_System SHALL acquire a row-level lock on the Transaction record before the Wallet adjustment and hold it until the database transaction commits or rolls back, permitting no more than 1 concurrent Wallet adjustment per Transaction.

### Requirement 7: Clean Code Structure

**User Story:** As a developer, I want payment logic organized into services, form requests, and console commands, so that the codebase stays maintainable and testable.

#### Acceptance Criteria

1. THE Payment_System SHALL implement transaction creation logic and processing logic in the Payment_Service, and controller classes SHALL contain no transaction creation or processing logic.
2. THE Payment_System SHALL implement Wallet balance adjustment logic in the Wallet_Service, and controller classes SHALL contain no Wallet balance adjustment logic.
3. WHEN the Pay_In_API or the Payout_API receives a request, THE Payment_System SHALL validate the request using a Laravel Form Request class before any transaction creation or processing logic executes.
4. IF request validation fails, THEN THE Payment_System SHALL reject the request with a validation error response and SHALL NOT record a Transaction_Log entry or change any Wallet balance.
5. THE Payment_System SHALL define Eloquent models for Merchant, Wallet, Pay-in, Payout, and Transaction_Log, each declaring its relationships to the other listed models.
6. THE Payment_System SHALL implement the Payment_Processor as a Laravel Artisan console command registered with the Laravel Scheduler.

### Requirement 8: Admin Panel Management

**User Story:** As an administrator, I want basic Backpack CRUD screens for merchants, wallets, and transactions, so that I can view and manage records without custom UI work.

#### Acceptance Criteria

1. WHILE an administrator is authenticated, THE Admin_Panel SHALL provide list, create, read, update, and delete operations for Merchant records.
2. WHILE an administrator is authenticated, THE Admin_Panel SHALL provide list, create, read, update, and delete operations for Wallet records.
3. WHILE an administrator is authenticated, THE Admin_Panel SHALL provide list, create, read, update, and delete operations for Pay-in records.
4. WHILE an administrator is authenticated, THE Admin_Panel SHALL provide list, create, read, update, and delete operations for Payout records.
5. WHEN an administrator applies a Payment_Status filter on the Pay-in interface or the Payout interface, THE Admin_Panel SHALL display only records whose Payment_Status equals the selected value.
6. WHEN an administrator applies a Merchant filter on the Pay-in interface or the Payout interface, THE Admin_Panel SHALL display only records associated with the selected Merchant.
7. WHEN an administrator applies a date-range filter on the Pay-in interface or the Payout interface, THE Admin_Panel SHALL display only records whose creation date falls within the selected range inclusive of the boundary dates.
8. WHEN an applied filter matches no records, THE Admin_Panel SHALL display an empty result set without error.
9. WHILE a user is not authenticated, THE Admin_Panel SHALL deny access to the Merchant, Wallet, Pay-in, and Payout interfaces.

### Requirement 9: Event Logging

**User Story:** As an operator, I want important payment events logged, so that I can audit and troubleshoot transaction processing.

#### Acceptance Criteria

1. WHEN a Transaction is created, THE Payment_System SHALL write a log entry recording the event, the Transaction_ID, and the request details, with sensitive request fields masked, at INFO severity with a UTC timestamp.
2. WHEN the Payment_Status of a Transaction changes, THE Payment_System SHALL write a log entry recording the Transaction_ID, the previous status, and the new status at INFO severity with a UTC timestamp.
3. WHEN a Wallet balance is adjusted, THE Payment_System SHALL write a log entry recording the Transaction_ID, the adjustment amount, and the resulting balance at INFO severity with a UTC timestamp.
4. IF an exception occurs during Transaction processing, THEN THE Payment_System SHALL write a log entry recording the Transaction_ID and the exception message at ERROR severity, and SHALL leave the Transaction in its pre-exception persisted state.

### Requirement 10: Project Documentation and Setup

**User Story:** As a new contributor, I want setup instructions and API documentation in the repository, so that I can run the project and call the APIs.

#### Acceptance Criteria

1. THE Payment_System SHALL provide a README file at the repository root that documents environment configuration steps, database migration steps, and database seeding steps, where each step includes the exact command to execute and the expected successful outcome of that command.
2. THE Payment_System SHALL provide API documentation for the Pay_In_API and the Payout_API, where each API entry includes at least one sample request showing all required and optional parameters and at least one sample response for a successful outcome.
3. THE Payment_System SHALL provide API documentation for the Pay_In_API and the Payout_API that includes at least one sample response for each documented error outcome, where each error sample identifies the triggering condition and the corresponding error indication returned to the caller.
4. THE Payment_System SHALL provide database seeders that create a minimum of 3 sample Merchant records, where each Merchant record is associated with exactly one Wallet record initialized with a non-negative balance.
