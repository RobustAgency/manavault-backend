<?php

namespace App\Imports;

use App\Models\Brand;
use App\Enums\Currency;
use App\Models\Product;
use Illuminate\Validation\Rule;
use App\Enums\Product\Lifecycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{
    private array $importedSkus = [];

    public function __construct() {}

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
                $rowData['brand_name'] = isset($rowData['brand_name']) ? trim($rowData['brand_name']) : null;
                $rowData['description'] = isset($rowData['description']) ? trim($rowData['description']) : null;
                $rowData['short_description'] = isset($rowData['short_description']) ? trim($rowData['short_description']) : null;
                $rowData['long_description'] = isset($rowData['long_description']) ? trim($rowData['long_description']) : null;
                $rowData['face_value'] = isset($rowData['face_value']) ? (float) $rowData['face_value'] : null;
                $rowData['currency'] = isset($rowData['currency']) ? trim(strtolower($rowData['currency'])) : null;
                $rowData['status'] = isset($rowData['status']) ? trim(strtolower($rowData['status'])) : null;
                $rowData['tags'] = isset($rowData['tags']) ? $this->parseTags($rowData['tags']) : null;
                $rowData['regions'] = isset($rowData['regions']) ? $this->parseRegions($rowData['regions']) : null;

                $rowNumber = $index + 2; // +2 because index is 0-based and row 1 is header

                $this->validateRow($rowData, $rowNumber);

                // Check for duplicate SKU in current batch
                if (in_array($rowData['sku'], $this->importedSkus)) {
                    throw ValidationException::withMessages([
                        'sku' => "SKU '{$rowData['sku']}' is duplicated in the import file.",
                    ]);
                }

                // Check if SKU already exists in database
                $existingProduct = Product::where('sku', $rowData['sku'])->first();
                if ($existingProduct) {
                    throw ValidationException::withMessages([
                        'sku' => "SKU '{$rowData['sku']}' already exists in the database (row {$rowNumber}).",
                    ]);
                }

                // Resolve brand_id from brand_name: find or create brand
                $brand_id = null;
                if (isset($rowData['brand_name']) && ! empty($rowData['brand_name'])) {
                    $brand = Brand::firstOrCreate(
                        ['name' => $rowData['brand_name']],
                        ['name' => $rowData['brand_name']]
                    );
                    $brand_id = $brand->id;
                }

                // Create product
                Product::create([
                    'name' => $rowData['name'],
                    'sku' => $rowData['sku'],
                    'brand_id' => $brand_id,
                    'description' => $rowData['description'] ?? null,
                    'short_description' => $rowData['short_description'] ?? null,
                    'long_description' => $rowData['long_description'] ?? null,
                    'face_value' => $rowData['face_value'],
                    'currency' => $rowData['currency'],
                    'status' => $rowData['status'],
                    'tags' => $rowData['tags'],
                    'regions' => $rowData['regions'],
                ]);

                $this->importedSkus[] = $rowData['sku'];
            }
        });
    }

    private function validateRow(array $rowData, int $rowNumber): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'face_value' => ['required', 'numeric', 'gt:0'],
            'currency' => [
                'required',
                Rule::in(array_map(fn ($c) => $c->value, Currency::cases())),
            ],
            'status' => [
                'required',
                Rule::in(array_map(fn ($c) => $c->value, Lifecycle::cases())),
            ],
            'tags' => ['nullable', 'array'],
            'regions' => ['nullable', 'array'],
        ];

        $messages = [
            'name.required' => "Name is required (row {$rowNumber})",
            'name.string' => "Name must be a string (row {$rowNumber})",
            'name.max' => "Name must not exceed 255 characters (row {$rowNumber})",
            'sku.required' => "SKU is required (row {$rowNumber})",
            'sku.string' => "SKU must be a string (row {$rowNumber})",
            'sku.max' => "SKU must not exceed 100 characters (row {$rowNumber})",
            'face_value.required' => "Face value is required (row {$rowNumber})",
            'face_value.numeric' => "Face value must be a number (row {$rowNumber})",
            'face_value.gt' => "Face value must be greater than 0 (row {$rowNumber})",
            'currency.required' => "Currency is required (row {$rowNumber})",
            'currency.in' => "Currency '{$rowData['currency']}' is not valid (row {$rowNumber})",
            'status.required' => "Status is required (row {$rowNumber})",
            'status.in' => "Status '{$rowData['status']}' is not valid (row {$rowNumber})",
        ];

        $validator = Validator::make($rowData, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Parse tags from CSV format to array.
     *
     * @param  mixed  $tags
     */
    private function parseTags($tags): ?array
    {
        if (empty($tags)) {
            return null;
        }

        if (is_array($tags)) {
            return $tags;
        }

        // Handle JSON format
        if (is_string($tags) && str_starts_with($tags, '[') && str_ends_with($tags, ']')) {
            return json_decode($tags, true) ?? null;
        }

        // Handle comma-separated format
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode('|', $tags)));
        }

        return null;
    }

    /**
     * Parse regions from CSV format to array.
     *
     * @param  mixed  $regions
     */
    private function parseRegions($regions): ?array
    {
        if (empty($regions)) {
            return null;
        }

        if (is_array($regions)) {
            return $regions;
        }

        // Handle JSON format
        if (is_string($regions) && str_starts_with($regions, '[') && str_ends_with($regions, ']')) {
            return json_decode($regions, true) ?? null;
        }

        // Handle comma-separated format
        if (is_string($regions)) {
            return array_filter(array_map('trim', explode('|', $regions)));
        }

        return null;
    }
}
