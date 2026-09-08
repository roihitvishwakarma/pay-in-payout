<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use CrudTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'merchant_id',
        'transaction_id',
        'amount',
        'status',
        'processed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed' => 'boolean',
            'status' => PaymentStatus::class,
        ];
    }

    /**
     * The Merchant that owns this payout transaction.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * The transaction log entries for this payout, matched by the business
     * transaction_id and constrained to the 'payout' transaction type.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(TransactionLog::class, 'transaction_id', 'transaction_id')
            ->where('transaction_type', 'payout');
    }
}
