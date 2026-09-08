<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MerchantCrudRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Admin CRUD for merchant records.
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MerchantCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Merchant::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/merchant');
        CRUD::setEntityNameStrings('merchant', 'merchants');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-list-entries
     */
    protected function setupListOperation(): void
    {
        CRUD::column('name')->label('Name');
        CRUD::column('email')->label('Email');
        CRUD::column('api_key')->label('API Key');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     */
    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(MerchantCrudRequest::class);

        CRUD::field('name')->label('Name')->type('text');
        CRUD::field('email')->label('Email')->type('email');
        CRUD::field('api_key')->label('API Key')->type('text');
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
        CRUD::column('name')->label('Name');
        CRUD::column('email')->label('Email');
        CRUD::column('api_key')->label('API Key');
        CRUD::column('created_at')->label('Created At');
        CRUD::column('updated_at')->label('Updated At');
    }
}
