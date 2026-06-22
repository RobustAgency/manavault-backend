<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\PurchaseOrder\PlacePendingPurchaseOrders;

class PlacePendingPurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase-order:place-pending';

    protected $description = 'Dispatch order-placement jobs for pending purchase order suppliers registered in the integration layer';

    public function handle(PlacePendingPurchaseOrders $action): int
    {
        logger()->info('PlacePendingPurchaseOrdersCommand: started');

        $action->execute();

        return Command::SUCCESS;
    }
}
