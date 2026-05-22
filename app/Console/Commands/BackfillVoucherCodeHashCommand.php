<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Command;
use App\Services\Voucher\VoucherCipherService;

class BackfillVoucherCodeHashCommand extends Command
{
    protected $signature = 'vouchers:backfill-code-hash';

    protected $description = 'Backfill code_hash for existing vouchers using HMAC-SHA256 of the plaintext code';

    public function __construct(private VoucherCipherService $voucherCipherService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $key = base64_decode(config('services.voucher.encryption_key'));
        $updated = 0;
        $skipped = 0;

        $total = Voucher::whereNull('code_hash')->whereNotNull('code')->count();

        if ($total === 0) {
            $this->info('No vouchers require backfilling.');

            return self::SUCCESS;
        }

        $this->info("Backfilling code_hash for {$total} vouchers...");

        Voucher::whereNull('code_hash')
            ->whereNotNull('code')
            ->chunkById(200, function ($vouchers) use ($key, &$updated, &$skipped) {
                foreach ($vouchers as $voucher) {
                    if ($this->voucherCipherService->isEncrypted($voucher->code)) {
                        $plain = $this->voucherCipherService->safeDecrypt($voucher->code);

                        if ($plain === null) {
                            $this->warn("Could not decrypt voucher ID {$voucher->id} — skipping.");
                            $skipped++;

                            continue;
                        }
                    } else {
                        $plain = $voucher->code;
                    }

                    Voucher::where('id', $voucher->id)->update([
                        'code_hash' => hash_hmac('sha256', $plain, $key),
                    ]);

                    $updated++;
                }
            });

        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
