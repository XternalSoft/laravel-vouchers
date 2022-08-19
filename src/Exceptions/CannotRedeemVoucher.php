<?php

namespace MOIREI\Vouchers\Exceptions;

use Illuminate\Database\Eloquent\Model;

class CannotRedeemVoucher extends \Exception
{
    protected $message = 'Instance is disallowed to redeem this voucher or provided item not allowed.';

    protected $voucher;
    protected $items;

    public static function create(Model $voucher, Model|array|null $items = null)
    {
        return new static($voucher, $items);
    }

    public function __construct(Model $voucher, Model|array|null $items = null)
    {
        $this->voucher = $voucher;
        $this->items = $items;
    }
}
