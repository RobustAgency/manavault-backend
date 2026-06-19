<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Actions\SaleOrderItem\BackfillDigitalProductIdAction;

class BackfillSaleOrderItemDigitalProductId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-sale-order-item-digital-product-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill digital_product_id on existing sale order items so historical orders match the new persisted-supplier state.';

    /**
     * Execute the console command.
     */
    public function handle(BackfillDigitalProductIdAction $action): int
    {
        logger()->info('BackfillSaleOrderItemDigitalProductId: started');

        $action->execute();

        return Command::SUCCESS;
    }
}
