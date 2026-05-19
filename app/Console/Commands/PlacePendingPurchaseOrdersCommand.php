<?php

namespace App\Console\Commands;

use App\Actions\PurchaseOrder\PlacePendingPurchaseOrders;
use Illuminate\Console\Command;

class PlacePendingPurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase-order:place-pending';

    protected $description = 'Dispatch order-placement jobs for pending purchase order suppliers registered in the integration layer';

    public function handle(PlacePendingPurchaseOrders $action): int
    {
        return $action->execute() ? Command::FAILURE : Command::SUCCESS;
    }
}
