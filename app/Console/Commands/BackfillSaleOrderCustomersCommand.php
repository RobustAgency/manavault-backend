<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use Illuminate\Console\Command;
use App\Clients\ManaStore\Client;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\CustomerRepository;

class BackfillSaleOrderCustomersCommand extends Command
{
    protected $signature = 'sale-orders:backfill-customers';

    protected $description = 'Backfill customers on sale orders that have none by fetching them from ManaStore by order number.';

    public function __construct(
        private readonly Client $manaStore,
        private readonly CustomerRepository $customerRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $processed = 0;
        $synced = 0;
        $missing = 0;
        $failed = 0;

        SaleOrder::whereNull('customer_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $saleOrders) use (&$processed, &$synced, &$missing, &$failed) {
                foreach ($saleOrders as $saleOrder) {
                    $processed++;

                    try {
                        $customerData = $this->manaStore->getSaleOrderCustomer($saleOrder->order_number);

                        // Skip when ManaStore has no order/customer or the payload lacks the keys we key on.
                        if (empty($customerData['external_id']) || empty($customerData['name'])) {
                            $missing++;
                            logger()->warning('Backfill: no customer returned for sale order', [
                                'sale_order_id' => $saleOrder->id,
                                'order_number' => $saleOrder->order_number,
                            ]);

                            continue;
                        }

                        // Reuses the existing customer (matched by external_id) or creates one,
                        // so a customer with several orders is stored once and shared.
                        $customer = $this->customerRepository->updateOrCreateByExternalId($customerData);

                        $saleOrder->customer()->associate($customer);
                        $saleOrder->saveQuietly();

                        $synced++;
                    } catch (\Throwable $e) {
                        $failed++;
                        logger()->error('Backfill: failed to sync customer for sale order', [
                            'sale_order_id' => $saleOrder->id,
                            'order_number' => $saleOrder->order_number,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Done. Processed: {$processed} | Synced: {$synced} | No customer: {$missing} | Failed: {$failed}");

        return Command::SUCCESS;
    }
}
