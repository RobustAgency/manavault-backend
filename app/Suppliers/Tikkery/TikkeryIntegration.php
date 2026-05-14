<?php

namespace App\Suppliers\Tikkery;

use App\Models\Supplier;
use App\Actions\Tikkery\GetOrder;
use App\Suppliers\Support\PollResult;
use App\Suppliers\Support\PollContext;
use App\Suppliers\Support\VoucherDraft;
use App\Suppliers\Support\PlacementResult;
use App\Suppliers\Support\PlaceOrderContext;
use App\Suppliers\Contracts\PollsForVouchers;
use App\Suppliers\Contracts\SupplierIntegration;
use App\Services\Tikkery\TikkeryPlaceOrderService;

class TikkeryIntegration implements PollsForVouchers, SupplierIntegration
{
    private const SLUG = 'tikkery';

    public function __construct(
        private TikkeryPlaceOrderService $placeOrderService,
        private GetOrder $getOrder,
    ) {}

    public function supports(Supplier $supplier): bool
    {
        return $supplier->slug === self::SLUG;
    }

    public function placeOrder(PlaceOrderContext $context): PlacementResult
    {
        $response = $this->placeOrderService->placeOrder(
            $context->items,
            $context->orderNumber,
        );

        $transactionId = $response['transactionId'] ?? null;
        $isCompleted = (bool) ($response['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        if (! $isCompleted || empty($codes)) {
            return PlacementResult::awaiting($transactionId);
        }

        return PlacementResult::ready($transactionId, $this->toDrafts($codes));
    }

    public function pollVouchers(PollContext $context): PollResult
    {
        $tikkeryOrderNumber = $context->purchaseOrderSupplier->transaction_id;

        $response = $this->getOrder->execute($tikkeryOrderNumber);

        $isCompleted = (bool) ($response['order']['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        if (! $isCompleted || empty($codes)) {
            return PollResult::skipped('Tikkery order not yet completed or no codes returned');
        }

        $enriched = $this->placeOrderService->enrichCodes($codes, $context->purchaseOrder->items->all());

        return PollResult::withVouchers($this->toDrafts($enriched));
    }

    /**
     * @param  array<int, array<string, mixed>>  $codes
     * @return array<int, VoucherDraft>
     */
    private function toDrafts(array $codes): array
    {
        $drafts = [];

        foreach ($codes as $code) {
            $purchaseOrderItemId = $code['purchase_order_item_id'] ?? null;
            $digitalProductId = $code['digital_product_id'] ?? null;

            if ($purchaseOrderItemId === null || $digitalProductId === null) {
                continue;
            }

            $serial = $code['serial'] ?? null;

            $drafts[] = new VoucherDraft(
                purchaseOrderItemId: (int) $purchaseOrderItemId,
                digitalProductId: (int) $digitalProductId,
                code: $code['redemptionCode'] ?? null,
                pin: $code['pin'] ?? null,
                serialNumber: $serial,
                dedupeBy: $serial ? ['serial_number' => $serial] : [],
            );
        }

        return $drafts;
    }
}
