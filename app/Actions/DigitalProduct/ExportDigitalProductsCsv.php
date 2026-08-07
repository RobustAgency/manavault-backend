<?php

namespace App\Actions\DigitalProduct;

use App\Models\DigitalProduct;
use App\Repositories\DigitalProductRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDigitalProductsCsv
{
    /**
     * @var array<int, string>
     */
    private const HEADINGS = [
        'Sr no.',
        'Digital Product ID',
        'Digital Product Name',
        'Brand',
        'Region',
        'Face Value',
        'Cost Price',
        'Discount Price',
        'Discount Percentage',
        'Status',
    ];

    public function __construct(
        private DigitalProductRepository $repository,
    ) {}

    /**
     * Stream the digital products matching the given filters as a CSV download.
     *
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters): StreamedResponse
    {
        $query = $this->repository->buildFilteredQuery($filters);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open output stream for the CSV export.');
            }

            try {
                // BOM so Excel reads the file as UTF-8.
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, self::HEADINGS);

                $serialNumber = 0;

                foreach ($query->lazy(500) as $digitalProduct) {
                    fputcsv($handle, $this->toRow($digitalProduct, ++$serialNumber));
                }
            } finally {
                fclose($handle);
            }
        }, $this->downloadName($filters), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function toRow(DigitalProduct $digitalProduct, int $serialNumber): array
    {
        $faceValue = (float) $digitalProduct->face_value;
        $costPrice = (float) $digitalProduct->cost_price;

        return [
            $serialNumber,
            $digitalProduct->id,
            $digitalProduct->name,
            $digitalProduct->brand,
            $digitalProduct->region,
            $digitalProduct->face_value,
            $digitalProduct->cost_price,
            number_format(round($faceValue - $costPrice, 2), 2, '.', ''),
            $digitalProduct->cost_price_discount,
            $digitalProduct->is_active ? 'Active' : 'Inactive',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function downloadName(array $filters): string
    {
        return sprintf(
            'digital-products-supplier-%s-%s.csv',
            $filters['supplier_id'],
            now()->format('Y-m-d-His'),
        );
    }
}
