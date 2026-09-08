<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Requests\PayoutCrudRequest;
use App\Models\Merchant;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Admin CRUD for payout records.
 *
 * Filtering is done with plain query string params in setupListOperation instead of
 * Backpack Pro filters (that addon isn't installed):
 *   - ?status=pending|success|failed
 *   - ?merchant_id=<id>
 *   - ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD  (inclusive)
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PayoutCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup(): void
    {
        CRUD::setModel(\App\Models\Payout::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/payout');
        CRUD::setEntityNameStrings('payout', 'payouts');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('merchant')
            ->label('Merchant')
            ->type('closure')
            ->function(fn ($entry) => $entry->merchant?->name ?? '-');
        CRUD::column('transaction_id')->label('Transaction ID');
        CRUD::column('amount')->label('Amount')->type('number')->decimals(2);
        CRUD::column('status')
            ->label('Status')
            ->type('closure')
            ->function(fn ($entry) => $entry->status instanceof PaymentStatus ? $entry->status->value : (string) $entry->status);
        CRUD::column('processed')->label('Processed')->type('boolean');
        CRUD::column('created_at')->label('Created At')->type('datetime');

        $this->applyRequestFilters();
    }

    /**
     * Apply optional status, merchant and date-range filters from the query string.
     */
    protected function applyRequestFilters(): void
    {
        $request = request();

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && PaymentStatus::tryFrom($status) !== null) {
            CRUD::addClause('where', 'status', $status);
        }

        $merchantId = $request->query('merchant_id');
        if (is_numeric($merchantId)) {
            CRUD::addClause('where', 'merchant_id', (int) $merchantId);
        }

        // whereDate compares the date part only, so date_to includes the whole day.
        $dateFrom = $request->query('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            CRUD::addClause('whereDate', 'created_at', '>=', $dateFrom);
        }

        $dateTo = $request->query('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            CRUD::addClause('whereDate', 'created_at', '<=', $dateTo);
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(PayoutCrudRequest::class);

        CRUD::field('merchant_id')
            ->label('Merchant')
            ->type('select')
            ->entity('merchant')
            ->model(Merchant::class)
            ->attribute('name');
        CRUD::field('transaction_id')->label('Transaction ID')->type('text');
        CRUD::field('amount')->label('Amount')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('status')
            ->label('Status')
            ->type('select_from_array')
            ->options($this->statusOptions())
            ->default(PaymentStatus::Pending->value);
        CRUD::field('processed')->label('Processed')->type('checkbox');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     */
    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    /**
     * Define what happens when the Show operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-show
     */
    protected function setupShowOperation(): void
    {
        CRUD::column('merchant')
            ->label('Merchant')
            ->type('closure')
            ->function(fn ($entry) => $entry->merchant?->name ?? '-');
        CRUD::column('transaction_id')->label('Transaction ID');
        CRUD::column('amount')->label('Amount')->type('number')->decimals(2);
        CRUD::column('status')
            ->label('Status')
            ->type('closure')
            ->function(fn ($entry) => $entry->status instanceof PaymentStatus ? $entry->status->value : (string) $entry->status);
        CRUD::column('processed')->label('Processed')->type('boolean');
        CRUD::column('created_at')->label('Created At')->type('datetime');
        CRUD::column('updated_at')->label('Updated At')->type('datetime');
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        $options = [];
        foreach (PaymentStatus::cases() as $case) {
            $options[$case->value] = ucfirst($case->value);
        }

        return $options;
    }
}
