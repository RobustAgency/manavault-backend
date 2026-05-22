<?php

namespace App\Services\Voucher;

use App\Models\Voucher;

class VoucherDeduplicationService
{
    private string $key;

    public function __construct()
    {
        $this->key = base64_decode(config('services.voucher.encryption_key'));
    }

    public function computeHash(string $plaintextCode): string
    {
        return hash_hmac('sha256', $plaintextCode, $this->key);
    }

    public function isDuplicate(?int $digitalProductId, string $plaintextCode): bool
    {
        if ($digitalProductId === null) {
            return false;
        }

        return Voucher::where('digital_product_id', $digitalProductId)
            ->where('code_hash', $this->computeHash($plaintextCode))
            ->exists();
    }
}
