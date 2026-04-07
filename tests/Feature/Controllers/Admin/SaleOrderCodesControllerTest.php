<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use ZipArchive;
use App\Models\User;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\SaleOrderItem;
use App\Enums\UserRole;
use App\Services\Voucher\VoucherCipherService;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderCodesControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    private VoucherCipherService $voucherCipherService;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.voucher.encryption_key', base64_encode(random_bytes(32)));

        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->regularUser = User::factory()->create(['role' => UserRole::USER->value]);
        $this->voucherCipherService = app(VoucherCipherService::class);
    }

    public function test_authorized_admin_can_view_sale_order_codes(): void
    {
        [$saleOrder, $entries] = $this->createOrderWithCodeEntries(2);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/sale-orders/{$saleOrder->id}/codes");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Sale order codes retrieved successfully.',
            ]);

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $entries[0]->id);
        $response->assertJsonPath('data.0.order_number', $saleOrder->order_number);
    }

    public function test_unauthorized_admin_is_blocked_from_viewing_sale_order_codes(): void
    {
        [$saleOrder] = $this->createOrderWithCodeEntries(1);

        $response = $this->actingAs($this->regularUser)
            ->getJson("/api/sale-orders/{$saleOrder->id}/codes");

        $response->assertStatus(403);
    }


    public function test_download_order_codes_downloads_all_codes_for_the_order(): void
    {
        [$saleOrder] = $this->createOrderWithCodeEntries(3);

        $response = $this->actingAs($this->superAdmin)
            ->get("/api/sale-orders/{$saleOrder->id}/codes/download");

        $response->assertStatus(200);
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));

        $streamedContent = $response->streamedContent();
        $zipPath = tempnam(sys_get_temp_dir(), 'all_zip_test_').'.zip';
        file_put_contents($zipPath, $streamedContent);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));

        $codesFileIndex = $zip->locateName('codes.csv', ZipArchive::FL_NODIR);
        $this->assertNotFalse($codesFileIndex);
        $this->assertSame(1, $zip->numFiles);

        $codesFileName = $zip->getNameIndex($codesFileIndex);
        $this->assertIsString($codesFileName);
        $codesContent = $zip->getFromName($codesFileName);
        $this->assertIsString($codesContent);
        $this->assertStringContainsString('CODE-1', $codesContent);
        $this->assertStringContainsString('CODE-2', $codesContent);
        $this->assertStringContainsString('CODE-3', $codesContent);
        $this->assertStringNotContainsString('Order Number:', $codesContent);
        $this->assertStringContainsString('id,sale_order_id,sale_order_item_id,voucher_id,order_number', $codesContent);
        $this->assertStringContainsString('CODE-1', $codesContent);
        $this->assertStringContainsString('CODE-2', $codesContent);
        $this->assertStringContainsString('CODE-3', $codesContent);

        $zip->close();
        @unlink($zipPath);
    }

    public function test_download_order_codes_returns_404_when_order_has_no_codes(): void
    {
        $saleOrder = SaleOrder::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/sale-orders/{$saleOrder->id}/codes/download");

        $response->assertStatus(404)
            ->assertJson([
                'error' => true,
                'message' => 'No code entries found for this sale order.',
            ]);
    }

    public function test_unauthorized_user_cannot_download_order_codes(): void
    {
        [$saleOrder] = $this->createOrderWithCodeEntries(1);

        $response = $this->actingAs($this->regularUser)
            ->get("/api/sale-orders/{$saleOrder->id}/codes/download");

        $response->assertStatus(403);
    }

    /**
     * @return array{0: SaleOrder, 1: array<int, SaleOrderItemDigitalProduct>}
     */
    private function createOrderWithCodeEntries(int $count): array
    {
        $saleOrder = SaleOrder::factory()->create();
        $product = Product::factory()->create();
        $saleOrderItem = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->create();

        $purchaseOrder = PurchaseOrder::factory()->create();

        $entries = [];
        for ($index = 1; $index <= $count; $index++) {
            $digitalProduct = DigitalProduct::factory()->create();

            $voucher = Voucher::factory()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'code' => $this->voucherCipherService->encryptCode('CODE-'.$index),
                'pin_code' => $this->voucherCipherService->encryptPinCode('PIN-'.$index),
            ]);

            $entries[] = SaleOrderItemDigitalProduct::factory()
                ->forSaleOrderItem($saleOrderItem)
                ->forDigitalProduct($digitalProduct)
                ->create([
                    'voucher_id' => $voucher->id,
                    'digital_product_name' => $digitalProduct->name,
                    'digital_product_sku' => $digitalProduct->sku,
                    'digital_product_brand' => $digitalProduct->brand,
                ]);
        }

        return [$saleOrder, $entries];
    }
}
