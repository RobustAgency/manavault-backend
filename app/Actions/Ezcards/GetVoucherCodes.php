<?php

namespace App\Actions\Ezcards;

use App\Clients\Ezcards\Vouchers;

class GetVoucherCodes
{
    public function __construct(private Vouchers $vouchers) {}

    public function execute(int $transactionID): array
    {
        return $this->vouchers->getCodes($transactionID);
    }
}
