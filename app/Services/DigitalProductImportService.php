<?php

namespace App\Services;

use Exception;
use Maatwebsite\Excel\Excel;
use Illuminate\Http\UploadedFile;
use App\Imports\DigitalProductImport;

class DigitalProductImportService
{
    public function __construct(private Excel $excel) {}

    /**
     * Import digital products from a CSV file.
     *
     * @throws Exception
     */
    public function importDigitalProducts(UploadedFile $file, int $supplierId): void
    {
        $this->excel->import(new DigitalProductImport($supplierId), $file);
    }
}
