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
        private int $supplierID,
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

                $this->validateRow($rowData, $index + 1);

                DigitalProduct::create([
                    'supplier_id' => $this->supplierID,
                    'name' => $rowData['name'],
                    'sku' => $rowData['sku'],
                    'brand' => $rowData['brand'] ?? null,
                    'description' => $rowData['description'] ?? null,
                    'cost_price' => $rowData['cost_price'],
                    'currency' => $rowData['currency'],
                    'metadata' => $rowData['metadata'] ? json_encode($rowData['metadata']) : null,
                    'tags' => $rowData['tags'] ? json_encode($rowData['tags']) : null,
                    'region' => $rowData['region'] ?? null,
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
            'currency' => [
                'required',
                Rule::in(array_map(fn ($c) => $c->value, Currency::cases())),
            ],
            'region' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
            'metadata' => ['nullable', 'string'],
        ], [], [
            'sku' => "SKU (row {$rowNumber})",
            'name' => "Name (row {$rowNumber})",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
