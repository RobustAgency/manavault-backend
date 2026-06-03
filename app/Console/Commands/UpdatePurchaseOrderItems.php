<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrderItem;
use App\Enums\PurchaseOrderItemStatus;

class UpdatePurchaseOrderItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-purchase-order-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // It will work for ezcard only as it is the one settinng this status
        $purchaseOrderItems = PurchaseOrderItem::where('status', PurchaseOrderItemStatus::PROCESSING)->get();
        foreach ($purchaseOrderItems as $item) {
            $item->getSupplier()?->updateOrder($item);
        }

        return Command::SUCCESS;
    }
}
