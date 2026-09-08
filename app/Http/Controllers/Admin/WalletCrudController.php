<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\WalletCrudRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Admin CRUD for wallet records.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class WalletCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Wallet::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/wallet');
        CRUD::setEntityNameStrings('wallet', 'wallets');
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
        CRUD::column('balance')->label('Balance')->type('number')->decimals(2);
        CRUD::column('currency')->label('Currency');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(WalletCrudRequest::class);

        CRUD::field('merchant_id')
            ->label('Merchant')
            ->type('select')
            ->entity('merchant')
            ->model(\App\Models\Merchant::class)
            ->attribute('name');
        CRUD::field('balance')->label('Balance')->type('number')->attributes(['step' => '0.01']);
        CRUD::field('currency')->label('Currency')->type('text')->default('USD');
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
        CRUD::column('balance')->label('Balance')->type('number')->decimals(2);
        CRUD::column('currency')->label('Currency');
        CRUD::column('created_at')->label('Created At');
        CRUD::column('updated_at')->label('Updated At');
    }
}
