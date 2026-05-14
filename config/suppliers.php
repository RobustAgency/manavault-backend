<?php

use App\Suppliers\Tikkery\TikkeryIntegration;

return [
    /*
    |--------------------------------------------------------------------------
    | Supplier Integrations
    |--------------------------------------------------------------------------
    |
    | Map each integration class to the supplier slug(s) it handles. The
    | registry resolves the correct integration for a Supplier by matching
    | its slug against this list.
    |
    */
    'integrations' => [
        TikkeryIntegration::class => ['tikkery'],
    ],
];
