<?php

namespace App\Services\PurchaseOrder;

class GroupBySupplierIdService
{
    public function groupBySupplierId(array $data): array
    {
        $grouped = [];

        foreach ($data as $item) {
            $supplierId = $item['supplier_id'];

            if (! isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'items' => [],
                ];
            }

            $grouped[$supplierId]['items'][] = [
                'digital_product_id' => $item['digital_product_id'],
                'quantity' => $item['quantity'],
            ];
        }

        return array_values($grouped);
    }
}
