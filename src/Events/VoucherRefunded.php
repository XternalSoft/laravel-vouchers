<?php

namespace MOIREI\Vouchers\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

class VoucherRefunded
{
    use SerializesModels;

    public $refunder;

    /** @var Model */
    public $voucher;

    public function __construct($refunder, Model $voucher)
    {
        $this->refunder = $refunder;
        $this->voucher = $voucher;
    }
}
