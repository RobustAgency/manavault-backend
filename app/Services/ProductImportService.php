<?php

namespace App\Services;

use Exception;
use Maatwebsite\Excel\Excel;
use App\Imports\ProductImport;
use Illuminate\Http\UploadedFile;

class ProductImportService
{
    public function __construct(private Excel $excel) {}

    /**
     * Import products from a CSV file.
     *
     * @throws Exception
     */
    public function importProducts(UploadedFile $file): void
    {
        $this->excel->import(new ProductImport, $file);
    }
}
