<?php

namespace App\Actions\SaleOrder;

use ZipArchive;
use App\Models\SaleOrder;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class BuildManavaultCodesZipArchive
{
    /**
     * @var array<int, string>
     */
    private const DETAIL_COLUMNS = [
        'id',
        'sale_order_id',
        'sale_order_item_id',
        'voucher_id',
        'order_number',
        'product_id',
        'product_name',
        'digital_product_id',
        'digital_product_name',
        'digital_product_sku',
        'digital_product_brand',
        'code_value',
        'pin_code_value',
        'voucher_status',
        'allocated_at',
        'voucher_created_at',
        'voucher_updated_at',
    ];

    /**
     * @param  Collection<int, array<string, mixed>>  $codes
     * @return array{path: string, download_name: string}
     */
    public function execute(SaleOrder $saleOrder, Collection $codes): array
    {
        if ($codes->isEmpty()) {
            throw new \InvalidArgumentException('At least one code entry is required to build archive.');
        }

        $orderSegment = $this->sanitizeFilenameSegment('order-'.$saleOrder->order_number);
        $downloadName = sprintf('%s-codes-%s.zip', $orderSegment, now()->format('YmdHis'));

        $tempFile = tempnam(sys_get_temp_dir(), 'mv_codes_');
        if ($tempFile === false) {
            throw new \RuntimeException('Unable to create temporary file for zip archive.');
        }

        $zipPath = $tempFile.'.zip';
        @unlink($tempFile);

        $zip = new ZipArchive;
        $openStatus = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($openStatus !== true) {
            throw new \RuntimeException('Unable to open zip archive. Status: '.$openStatus);
        }

        $zip->addFromString('codes.csv', $this->buildDetailedCodesCsv($codes));

        $zip->close();

        return [
            'path' => $zipPath,
            'download_name' => $downloadName,
        ];
    }

    private function sanitizeFilenameSegment(string $value): string
    {
        $value = Str::of($value)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '_')
            ->trim('._-')
            ->toString();

        return $value === '' ? 'entry' : $value;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $codes
     */
    private function buildDetailedCodesCsv(Collection $codes): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV generation.');
        }

        fputcsv($stream, self::DETAIL_COLUMNS);

        foreach ($codes as $entry) {
            $row = [];
            foreach (self::DETAIL_COLUMNS as $column) {
                $value = $entry[$column] ?? null;
                $row[] = $value === null ? '' : (string) $value;
            }

            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            throw new \RuntimeException('Unable to read generated CSV content.');
        }

        return $content;
    }
}
