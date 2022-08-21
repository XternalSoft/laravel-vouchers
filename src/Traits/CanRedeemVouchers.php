<?php

namespace MOIREI\Vouchers\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use MOIREI\Vouchers\Events\VoucherRedeemed;
use MOIREI\Vouchers\Events\VoucherRefunded;
use MOIREI\Vouchers\Exceptions\CannotRedeemVoucher;
use MOIREI\Vouchers\Exceptions\VoucherAlreadyRedeemed;
use MOIREI\Vouchers\Exceptions\VoucherRedeemsExhausted;
use MOIREI\Vouchers\Facades\Vouchers;
use MOIREI\Vouchers\Models\ItemVoucherable;
use MOIREI\Vouchers\Models\Voucher;
use MOIREI\Vouchers\VoucherScheme;

/**
 * @property Collection $vouchers
 */
trait CanRedeemVouchers
{
    /**
     * Redeem a voucher or voucher code
     *
     * @param string $code
     * @param array|null $items
     * @return Voucher
     * @throws CannotRedeemVoucher
     * @throws VoucherAlreadyRedeemed
     * @throws VoucherRedeemsExhausted
     */
    public function redeem(string $code, array|null $items = null): Voucher
    {
        $voucher = Vouchers::check($code);

        if (!$this->canRedeem($voucher, $items)) {
            throw CannotRedeemVoucher::create($voucher, $items);
        }

        if ($voucher->limit_scheme->is(VoucherScheme::ITEM)) {
            $items = $this->filterItems($voucher, $items);
            foreach ($items as $item) {
                $voucher->incrementModelUse($item);
            }
        } elseif ($voucher->limit_scheme->is(VoucherScheme::REDEEMER)) {
            $voucher->incrementModelUse($this);
        } else {
            $voucher->incrementUse();
        }

        $this->vouchers()->attach($voucher, [
            'redeemed_at' => now()
        ]);

        event(new VoucherRedeemed($this, $voucher));

        return $voucher;
    }

    public function refund(Voucher $voucher, array|null $items = null): bool
    {
        if ($voucher->limit_scheme->is(VoucherScheme::ITEM)) {
            $items = $this->filterItems($voucher, $items);
            foreach ($items as $item) {
                $voucher->decrementModelUse($item);
            }
        } elseif ($voucher->limit_scheme->is(VoucherScheme::REDEEMER)) {
            $voucher->decrementModelUse($this);
        } else {
            $voucher->decrementUse();
        }

        event(new VoucherRefunded($this, $voucher));
        return false;
    }

    /**
     * Alias for redeem()
     * Redeem a voucher or voucher code
     *
     * @param string $voucher
     * @return Voucher
     * @throws CannotRedeemVoucher
     * @throws VoucherAlreadyRedeemed
     * @throws VoucherRedeemsExhausted
     */
    public function redeemVoucher(string $voucher): Voucher
    {
        return $this->redeem($voucher);
    }

    /**
     * Check whether the user instance can redeem a voucher or voucher code.
     *
     * @param Voucher|string $voucher
     * @param Model|array|null $items
     * @return bool
     * @throws VoucherAlreadyRedeemed
     * @throws VoucherRedeemsExhausted
     */
    public function canRedeem(Voucher|string $voucher, Model|array|null $items = null): bool
    {
        if (is_string($voucher)) {
            $voucher = Vouchers::check($voucher);
        }

        if ($items === null && $voucher->limit_scheme->is(VoucherScheme::ITEM)) {
            throw new InvalidArgumentException("Please provide an item.");
        }

        if ($items !== null && $voucher->limit_scheme->is(VoucherScheme::ITEM)) {
            if ($items instanceof Model) {
                $items = [$items];
            }
            $items = $this->filterItems($voucher, $items);
            return $voucher->isAnyItem($items);
        }

        $this->checkIsRedemeed($voucher, $items);

        return $voucher->isAllowed($this);
    }

    /**
     * @throws VoucherRedeemsExhausted
     * @throws VoucherAlreadyRedeemed
     */
    protected function checkIsRedemeed(Voucher $voucher, array $items): bool
    {
        $is_redeemed = false;
        if ($voucher->limit_scheme->is(VoucherScheme::ITEM)) {
            $items = $this->filterItems($voucher, $items);
            foreach ($items as $item) {
                $is_redeemed = $voucher->isRedeemed($item);
                if ($is_redeemed) {
                    break;
                }
            }
        } else {
            $is_redeemed = $voucher->isRedeemed($this);
        }

        if ($is_redeemed && $voucher->isDisposable()) {
            throw VoucherAlreadyRedeemed::create($voucher);
        }
        if ($is_redeemed) {
            throw VoucherRedeemsExhausted::create($voucher);
        }

        return false;
    }

    /**
     * Filter the items that are not in the voucher
     * @param Voucher $voucher
     * @param array $items
     * @return array
     */
    protected function filterItems(Voucher $voucher, array $items): array
    {
        return array_filter(
            $items,
            static function (Model $item) use ($voucher) {
                /** @var ItemVoucherable $voucherItem */
                foreach ($voucher->items as $voucherItem) {
                    if (get_class($item) === $voucherItem->item_type && $item->getKey() == $voucherItem->item_id) {
                        return $item;
                    }
                }
                return false;
            }
        );
    }

    /**
     * Get vouchers
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function vouchers(): MorphToMany
    {
        return $this->morphToMany(
            config('vouchers.models.vouchers'),
            'redeemer',
            config('vouchers.tables.redeemer_pivot_table', 'redeemer_voucher'),
            null,
            'voucher_id'
        );
    }
}
