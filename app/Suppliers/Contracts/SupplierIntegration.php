<?php

namespace App\Suppliers\Contracts;

use App\Models\Supplier;
use App\Suppliers\Support\PlacementResult;
use App\Suppliers\Support\PlaceOrderContext;

interface SupplierIntegration
{
    public function supports(Supplier $supplier): bool;

    public function placeOrder(PlaceOrderContext $context): PlacementResult;
}
