<?php

namespace App\Suppliers\Support;

class UnsupportedSupplierException extends \RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("No supplier integration registered for slug: {$slug}");
    }
}
