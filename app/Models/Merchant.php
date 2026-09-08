<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Merchant extends Model
{
    use CrudTrait;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'api_key',
    ];

    /**
     * The Wallet owned by this Merchant (one-to-one).
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * The pay-in transactions initiated by this Merchant.
     */
    public function payIns(): HasMany
    {
        return $this->hasMany(PayIn::class);
    }

    /**
     * The payout transactions initiated by this Merchant.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
