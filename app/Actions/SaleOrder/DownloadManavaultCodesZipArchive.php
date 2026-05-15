<?php

namespace App\Actions\SaleOrder;

use App\Models\SaleOrder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadManavaultCodesZipArchive
{
    public function __construct(
        private BuildManavaultCodesZipArchive $buildManavaultCodesZipArchive,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $codes
     */
    public function execute(SaleOrder $saleOrder, Collection $codes): StreamedResponse
    {
        $archive = $this->buildManavaultCodesZipArchive->execute($saleOrder, $codes);

        return response()->streamDownload(function () use ($archive): void {
            $stream = fopen($archive['path'], 'rb');

            try {
                if ($stream === false) {
                    throw new \RuntimeException('Unable to open generated zip archive.');
                }

                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (is_file($archive['path'])) {
                    @unlink($archive['path']);
                }
            }
        }, $archive['download_name'], [
            'Content-Type' => 'application/zip',
        ]);
    }
}
