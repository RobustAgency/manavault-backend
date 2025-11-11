<?php

namespace App\Services;

use ZipArchive;
use App\Models\Voucher;
use App\Imports\VoucherImport;
use Maatwebsite\Excel\Facades\Excel;

class VoucherImportService
{
    private function importVoucherFromSpreadsheet(string $filePath, int $purchaseOrderID): bool
    {
        Excel::import(new VoucherImport($purchaseOrderID), $filePath);

        return true;
    }

    private function extractFilesFromZipAndImportVouchers(string $zipPath, int $purchaseOrderID): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $hasProcessedFiles = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

            if (! in_array($extension, Voucher::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            $tempPath = sys_get_temp_dir().'/'.basename($entryName);

            // Extract and process
            if (copy("zip://{$zipPath}#{$entryName}", $tempPath)) {
                if ($this->importVoucherFromSpreadsheet($tempPath, $purchaseOrderID)) {
                    $hasProcessedFiles = true;
                }
                unlink($tempPath);
            }
        }

        $zip->close();

        return $hasProcessedFiles;
    }

    public function processFile(array $data): bool
    {
        $file = $data['file'];
        $purchaseOrderID = $data['purchase_order_id'];

        $extension = $file->getClientOriginalExtension();
        $originalName = $file->getClientOriginalName();

        // Create a temporary file with the correct extension
        $tempPath = sys_get_temp_dir().'/'.uniqid().'_'.$originalName;
        $file->move(sys_get_temp_dir(), basename($tempPath));

        if ($extension === 'zip') {
            $result = $this->extractFilesFromZipAndImportVouchers($tempPath, $purchaseOrderID);
            unlink($tempPath);

            return $result;
        }

        $result = $this->importVoucherFromSpreadsheet($tempPath, $purchaseOrderID);
        unlink($tempPath);

        return $result;
    }
}
