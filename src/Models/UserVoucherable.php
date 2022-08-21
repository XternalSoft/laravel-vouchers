<?php

namespace MOIREI\Vouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserVoucherable extends Model
{
    protected $with = ['voucherable'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('vouchers.tables.redeemer_pivot_table', 'redeemer_voucher');
    }

    public function voucherable(): MorphTo
    {
        return $this->morphTo();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
