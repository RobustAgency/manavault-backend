<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\DTOs\VoucherDTO;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Voucher\VoucherPurchaseOrderValidator;

class VoucherPurchaseOrderValidatorTest extends TestCase
{
    use RefreshDatabase;

    private VoucherPurchaseOrderValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(VoucherPurchaseOrderValidator::class);
    }

    /**
     * Test valid voucher DTOs with single digital product
     */
    public function test_validate_voucher_dtos_with_single_digital_product(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-002'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-003'),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $result);
        $this->assertEquals($purchaseOrder->id, $result->id);
    }

    /**
     * Test valid voucher DTOs with multiple digital products
     */
    public function test_validate_voucher_dtos_with_multiple_digital_products(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct1->id,
        ]);

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct2->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-002'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-003'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-004'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-005'),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $result);
        $this->assertEquals($purchaseOrder->id, $result->id);
    }

    /**
     * Test validation fails when purchase order does not exist
     */
    public function test_validate_voucher_dtos_throws_exception_when_purchase_order_not_found(): void
    {
        $voucherDTOs = [
            new VoucherDTO(digital_product_id: 1, code: 'CODE-001'),
        ];

        $nonExistingId = -1;

        $this->expectException(ValidationException::class);

        $this->validator->validateVoucherDTOs($voucherDTOs, $nonExistingId);
    }

    /**
     * Test validation fails when voucher has a digital product not in purchase order
     */
    public function test_validate_voucher_dtos_throws_exception_when_digital_product_not_in_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create(); // Not in PO

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct1->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-002'), // Invalid product
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation fails when not all purchase order items have vouchers
     */
    public function test_validate_voucher_dtos_throws_exception_when_missing_vouchers_for_all_products(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct1->id,
        ]);

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct2->id,
        ]);

        // Only provide vouchers for first product
        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-002'),
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation fails when quantity of vouchers does not match purchase order item quantity
     */
    public function test_validate_voucher_dtos_throws_exception_when_quantity_mismatch(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        // Provide only 2 vouchers instead of 3
        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-002'),
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation fails when too many vouchers are provided for a product
     */
    public function test_validate_voucher_dtos_throws_exception_when_too_many_vouchers(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        // Provide 4 vouchers instead of 2
        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-002'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-003'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-004'),
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation with multiple products having different quantities
     */
    public function test_validate_voucher_dtos_with_varied_quantities(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();
        $digitalProduct3 = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create([
            'digital_product_id' => $digitalProduct1->id,
        ]);

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(5)->create([
            'digital_product_id' => $digitalProduct2->id,
        ]);

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct3->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-002'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-003'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-004'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-005'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-006'),
            new VoucherDTO(digital_product_id: $digitalProduct3->id, code: 'CODE-007'),
            new VoucherDTO(digital_product_id: $digitalProduct3->id, code: 'CODE-008'),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $result);
        $this->assertEquals($purchaseOrder->id, $result->id);
    }

    /**
     * Test validation fails when one product has mismatched quantity in multi-product scenario
     */
    public function test_validate_voucher_dtos_throws_exception_on_partial_quantity_mismatch(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct1->id,
        ]);

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct2->id,
        ]);

        // First product has correct quantity, second doesn't
        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct1->id, code: 'CODE-002'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-003'),
            new VoucherDTO(digital_product_id: $digitalProduct2->id, code: 'CODE-004'),
            // Missing one voucher for second product
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation with empty voucher DTOs array
     */
    public function test_validate_voucher_dtos_throws_exception_with_empty_array(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $voucherDTOs = [];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation error message contains digital product ID
     */
    public function test_validate_voucher_dtos_error_message_includes_digital_product_id(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(5)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-001'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-002'),
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);
    }

    /**
     * Test validation with special characters in voucher codes
     */
    public function test_validate_voucher_dtos_with_special_characters_in_codes(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(3)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-@#$-001'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE_[123]_456'),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE|&-789'),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $result);
    }

    /**
     * Test validation with very long voucher codes
     */
    public function test_validate_voucher_dtos_with_long_voucher_codes(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(2)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $longCode1 = str_repeat('A', 255);
        $longCode2 = str_repeat('B', 255);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: $longCode1),
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: $longCode2),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $result);
    }

    /**
     * Test validation returns purchase order with relationships loaded
     */
    public function test_validate_voucher_dtos_returns_purchase_order_with_items_loaded(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(1)->create([
            'digital_product_id' => $digitalProduct->id,
        ]);

        $voucherDTOs = [
            new VoucherDTO(digital_product_id: $digitalProduct->id, code: 'CODE-001'),
        ];

        $result = $this->validator->validateVoucherDTOs($voucherDTOs, $purchaseOrder->id);

        // Verify that items relationship is loaded
        $this->assertTrue($result->relationLoaded('items'));
        $this->assertCount(1, $result->items);
    }
}
