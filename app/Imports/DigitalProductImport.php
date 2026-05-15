<?php

namespace App\Imports;

use App\Enums\Currency;
use App\Models\DigitalProduct;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DigitalProductImport implements ToCollection, WithHeadingRow
{
    private array $importedSkus = [];

    public function __construct(
        private int $supplierId,
    ) {}

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<string,mixed>>  $collection
     */
    public function collection(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            foreach ($collection as $index => $row) {

                if ($row->filter()->isEmpty()) {
                    continue;
                }

                $rowData = $row->toArray();

                // Normalize data
                $rowData['name'] = isset($rowData['name']) ? trim($rowData['name']) : null;
                $rowData['sku'] = isset($rowData['sku']) ? trim($rowData['sku']) : null;
                $rowData['currency'] = isset($rowData['currency'])
                    ? strtolower(trim($rowData['currency']))
                    : null;

                // Handle tags (comma separated → array)
                $rowData['tags'] = isset($rowData['tags']) && $rowData['tags'] !== ''
                    ? array_map('trim', explode(',', $rowData['tags']))
                    : null;

                // Handle metadata (JSON string → array)
                $rowData['metadata'] = isset($rowData['metadata']) && $rowData['metadata'] !== ''
                    ? json_decode($rowData['metadata'], true)
                    : null;

                $this->validateRow($rowData, $index + 2);

                DigitalProduct::create([
                    'supplier_id' => $this->supplierId,
                    'name' => $rowData['name'],
                    'sku' => $rowData['sku'],
                    'brand' => $rowData['brand'] ?? null,
                    'description' => $rowData['description'] ?? null,
                    'cost_price' => $rowData['cost_price'],
                    'face_value' => $rowData['face_value'],
                    'selling_price' => $rowData['selling_price'],
                    'currency' => $rowData['currency'],
                    'metadata' => $rowData['metadata'],
                    'tags' => $rowData['tags'],
                    'region' => $rowData['region'] ?? null,
                    'last_synced_at' => now(),
                    'source' => 'csv_import',
                ]);

                $this->importedSkus[] = $rowData['sku'];
            }
        });
    }

    private function validateRow(array $row, int $rowNumber): void
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],

            'sku' => [
                'required',
                'string',
                'max:255',
                'unique:digital_products,sku',
                Rule::notIn($this->importedSkus),
            ],

            'brand' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'cost_price' => ['required', 'numeric', 'min:0'],
            'face_value' => ['required', 'numeric', 'gt:0'],
            'selling_price' => ['required', 'numeric', 'gt:0'],

            'currency' => [
                'required',
                Rule::in(array_map(fn ($c) => $c->value, Currency::cases())),
            ],

            'metadata' => ['nullable', 'array'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],

            'region' => ['nullable', 'string', 'max:255'],

        ], [], [
            'name' => "Name (row {$rowNumber})",
            'sku' => "SKU (row {$rowNumber})",
            'cost_price' => "Cost Price (row {$rowNumber})",
            'face_value' => "Face Value (row {$rowNumber})",
            'selling_price' => "Selling Price (row {$rowNumber})",
            'currency' => "Currency (row {$rowNumber})",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business rule: effective selling price must not be less than cost price
        $costPrice = isset($row['cost_price']) ? (float) $row['cost_price'] : null;
        $sellingPrice = isset($row['selling_price']) ? (float) $row['selling_price'] : null;

        if ($costPrice !== null && $sellingPrice !== null && $sellingPrice < $costPrice) {
            $validator = Validator::make([], []);
            $validator->errors()->add(
                'selling_price',
                "Selling Price (row {$rowNumber}): the selling price ({$sellingPrice}) must not be less than the cost price ({$costPrice})."
            );
            throw new ValidationException($validator);
        }
    }
}
