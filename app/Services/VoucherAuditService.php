<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAuditLog;
use App\Enums\VoucherAuditActions;

class VoucherAuditService
{
    /**
     * Log a voucher view action
     */
    public function voucherViewLog(Voucher $voucher, User $user, array $data): VoucherAuditLog
    {
        return $this->createLog($voucher, $user, VoucherAuditActions::VIEWED->value, $data);
    }

    /**
     * Log a voucher copy action
     */
    public function voucherCopyLog(Voucher $voucher, User $user, array $data): VoucherAuditLog
    {
        return $this->createLog($voucher, $user, VoucherAuditActions::COPIED->value, $data);
    }

    /**
     * Create an audit log entry
     */
    private function createLog(Voucher $voucher, User $user, string $action, array $data): VoucherAuditLog
    {
        return VoucherAuditLog::create([
            'voucher_id' => $voucher->id,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);
    }
}
