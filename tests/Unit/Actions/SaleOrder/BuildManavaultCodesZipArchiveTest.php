<?php

namespace Tests\Unit\Actions\SaleOrder;

use Tests\TestCase;
use ZipArchive;
use App\Models\SaleOrder;
use Illuminate\Support\Collection;
use App\Actions\SaleOrder\BuildManavaultCodesZipArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BuildManavaultCodesZipArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_zip_with_single_detailed_codes_csv_file(): void
    {
        $saleOrder = SaleOrder::factory()->create([
            'order_number' => 'SO-2026-000999',
        ]);

        $codes = new Collection([
            [
                'id' => 10,
                'order_number' => $saleOrder->order_number,
                'voucher_id' => 300,
                'product_name' => 'Main Product',
                'digital_product_name' => 'Gift Card 50',
                'digital_product_sku' => 'GC-50-US',
                'code_value' => 'ABCD-1234',
                'pin_code_value' => '4321',
                'voucher_status' => 'COMPLETED',
                'allocated_at' => now()->toISOString(),
                'voucher_updated_at' => now()->toISOString(),
            ],
            [
                'id' => 11,
                'order_number' => $saleOrder->order_number,
                'voucher_id' => 301,
                'product_name' => 'Main Product',
                'digital_product_name' => 'Gift Card 100',
                'digital_product_sku' => 'GC-100-US',
                'code_value' => 'WXYZ-9876',
                'pin_code_value' => null,
                'voucher_status' => 'COMPLETED',
                'allocated_at' => now()->toISOString(),
                'voucher_updated_at' => now()->toISOString(),
            ],
        ]);

        $action = app(BuildManavaultCodesZipArchive::class);
        $result = $action->execute($saleOrder, $codes);

        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.zip', $result['download_name']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($result['path']));

        $codesFileIndex = $zip->locateName('codes.csv', ZipArchive::FL_NODIR);
        $this->assertNotFalse($codesFileIndex);
        $this->assertSame(1, $zip->numFiles);

        $codesFileName = $zip->getNameIndex($codesFileIndex);
        $this->assertIsString($codesFileName);
        $codesContent = $zip->getFromName($codesFileName);
        $this->assertIsString($codesContent);
        $this->assertStringContainsString('ABCD-1234', $codesContent);
        $this->assertStringContainsString('WXYZ-9876', $codesContent);
        $this->assertStringNotContainsString('Order Number:', $codesContent);
        $this->assertStringContainsString('id,sale_order_id,sale_order_item_id,voucher_id,order_number', $codesContent);
        $this->assertStringContainsString('ABCD-1234', $codesContent);
        $this->assertStringContainsString('Gift Card 50', $codesContent);

        $zip->close();

        @unlink($result['path']);
    }
}
