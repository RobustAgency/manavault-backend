<?php

namespace Tests\Feature\Imports;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use App\Imports\DigitalProductImport;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductImportTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_import_single_valid_product(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Gaming Card',
                'sku' => 'SKU-001',
                'brand' => 'Nintendo',
                'description' => 'Nintendo gift card',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => ['gaming', 'cards'],
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);

        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Gaming Card',
            'sku' => 'SKU-001',
            'brand' => 'Nintendo',
            'cost_price' => 50.00,
            'currency' => 'usd',
        ]);

        $this->assertCount(1, DigitalProduct::where('supplier_id', $this->supplier->id)->get());
    }

    public function test_import_multiple_products(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Gaming Card',
                'sku' => 'SKU-001',
                'brand' => 'Nintendo',
                'description' => 'Nintendo gift card',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => ['gaming'],
                'metadata' => null,
            ]),
            collect([
                'name' => 'Movie Voucher',
                'sku' => 'SKU-002',
                'brand' => 'Disney',
                'description' => 'Disney movie voucher',
                'cost_price' => 25.00,
                'currency' => 'eur',
                'region' => 'EU',
                'tags' => ['movies'],
                'metadata' => null,
            ]),
            collect([
                'name' => 'Software License',
                'sku' => 'SKU-003',
                'brand' => 'Microsoft',
                'description' => 'Microsoft Office license',
                'cost_price' => 100.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => ['software', 'license'],
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);

        $this->assertCount(3, DigitalProduct::where('supplier_id', $this->supplier->id)->get());
        $this->assertDatabaseHas('digital_products', ['sku' => 'SKU-001']);
        $this->assertDatabaseHas('digital_products', ['sku' => 'SKU-002']);
        $this->assertDatabaseHas('digital_products', ['sku' => 'SKU-003']);
    }

    public function test_import_with_optional_fields(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Basic Product',
                'sku' => 'SKU-001',
                'brand' => null,
                'description' => null,
                'cost_price' => 30.00,
                'currency' => 'usd',
                'region' => null,
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);

        $this->assertDatabaseHas('digital_products', [
            'sku' => 'SKU-001',
            'brand' => null,
            'description' => null,
            'region' => null,
        ]);
    }

    public function test_import_with_metadata(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $metadata = ['external_id' => '12345', 'source' => 'api'];

        $collection = collect([
            collect([
                'name' => 'Product with Metadata',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => ['tag1'],
                'metadata' => $metadata,
            ]),
        ]);

        $import->collection($collection);

        $product = DigitalProduct::where('sku', 'SKU-001')->first();
        $this->assertEquals($metadata, $product->metadata);
    }

    public function test_import_skips_empty_rows(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([]),
            collect([
                'name' => 'Valid Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => ['tag1'],
                'metadata' => null,
            ]),
            collect([]),
        ]);

        $import->collection($collection);

        $this->assertCount(1, DigitalProduct::where('supplier_id', $this->supplier->id)->get());
    }

    public function test_import_fails_with_missing_required_name(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => null,
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_missing_required_sku(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => null,
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_missing_required_cost_price(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => null,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_invalid_currency(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'INVALID',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_duplicate_sku_in_database(): void
    {
        DigitalProduct::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Existing Product',
            'sku' => 'SKU-001',
            'brand' => 'Brand',
            'description' => 'Description',
            'cost_price' => 50.00,
            'currency' => 'usd',
        ]);

        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'New Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 75.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_duplicate_sku_in_batch(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product 1',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
            collect([
                'name' => 'Product 2',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 75.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_invalid_cost_price(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 'invalid',
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_fails_with_negative_cost_price(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => -10.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_with_zero_cost_price(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Free Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 0.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);

        $this->assertDatabaseHas('digital_products', [
            'sku' => 'SKU-001',
            'cost_price' => 0.00,
        ]);
    }

    public function test_import_fails_with_name_exceeding_max_length(): void
    {
        $this->expectException(ValidationException::class);

        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => str_repeat('a', 256),
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'USD',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);
    }

    public function test_import_with_array_tags(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $tags = ['gaming', 'cards', 'gift'];

        $collection = collect([
            collect([
                'name' => 'Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => $tags,
                'metadata' => null,
            ]),
        ]);

        $import->collection($collection);

        $product = DigitalProduct::where('sku', 'SKU-001')->first();
        $this->assertEquals($tags, $product->tags);
    }

    public function test_import_transaction_rollback_on_error(): void
    {
        $import = new DigitalProductImport($this->supplier->id);

        $collection = collect([
            collect([
                'name' => 'Valid Product',
                'sku' => 'SKU-001',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 50.00,
                'currency' => 'usd',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
            collect([
                'name' => 'Invalid Product',
                'sku' => 'SKU-002',
                'brand' => 'Brand',
                'description' => 'Description',
                'cost_price' => 'invalid',
                'currency' => 'usd',
                'region' => 'US',
                'tags' => null,
                'metadata' => null,
            ]),
        ]);

        try {
            $import->collection($collection);
        } catch (ValidationException $e) {
            // Expected exception
        }

        // Both products should not be created due to transaction rollback
        $this->assertCount(0, DigitalProduct::where('supplier_id', $this->supplier->id)->get());
    }
}
