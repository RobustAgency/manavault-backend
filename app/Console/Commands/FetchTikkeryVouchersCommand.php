<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchTikkeryVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tikkery:fetch-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll pending Tikkery purchase orders and fetch their voucher codes';

    /**
     * Backwards-compatible alias for `suppliers:poll-vouchers tikkery`.
     */
    public function handle(): int
    {
        return $this->call('suppliers:poll-vouchers', ['slug' => 'tikkery']);
    }
}
