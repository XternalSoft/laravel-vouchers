<?php

namespace MOIREI\Vouchers\Exceptions;

class VoucherIsInactive extends \Exception
{
    protected $code;

    public static function withCode(string $code)
    {
        return new static('The provided code ' . $code . ' is inactive.', $code);
    }

    public function __construct($message, $code)
    {
        $this->message = $message;
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getVoucherCode()
    {
        return $this->code;
    }
}
