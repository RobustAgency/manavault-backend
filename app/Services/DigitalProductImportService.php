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
     * Import digital products from an CSV file.
     *
     * @throws Exception
     */
    public function importDigitalProducts(UploadedFile $file, int $supplierID): bool
    {
        try {
            // Import the file
            $this->excel->import(new DigitalProductImport($supplierID), $file);

            return true;
        } catch (Exception $e) {
            throw new Exception('Import failed: '.$e->getMessage());
        }
    }
}
