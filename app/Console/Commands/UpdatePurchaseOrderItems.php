<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\PurchaseOrder\UpdatePurchaseOrderItemsAction;

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
    public function handle(UpdatePurchaseOrderItemsAction $action): int
    {
        logger()->info('UpdatePurchaseOrderItems: started');

        $action->execute();

        return Command::SUCCESS;
    }
}
