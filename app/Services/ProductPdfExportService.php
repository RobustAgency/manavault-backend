<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductPdfExportService
{
    /**
     * Export a product list to PDF and trigger immediate download.
     *
     * @param  LengthAwarePaginator<int, \App\Models\Product>  $products
     */
    public function exportProductsListToPdf(LengthAwarePaginator $products): Response
    {
        $filename = 'products-list-'.now()->format('Y-m-d-His').'.pdf';

        $pdf = Pdf::loadView('exports.products', [
            'products' => $products->items(),
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'current_page' => $products->currentPage(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Export filtered products to PDF and trigger immediate download.
     *
     * @param  LengthAwarePaginator<int, \App\Models\Product>  $products
     * @param  array<string, mixed>  $filters
     */
    public function exportFilteredProductsToPdf(LengthAwarePaginator $products, array $filters = []): Response
    {
        $filename = 'products-list-'.now()->format('Y-m-d-His').'.pdf';

        $pdf = Pdf::loadView('exports.products', [
            'products' => $products->items(),
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'current_page' => $products->currentPage(),
            'filters' => $filters,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
