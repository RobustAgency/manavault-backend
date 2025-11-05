<?php

namespace App\Clients\Ezcards;

use App\Clients\BaseApiClient;

class Client extends BaseApiClient
{
    /**
     * Get the configuration key prefix for the service.
     */
    protected function getConfigPrefix(): string
    {
        return 'services.ez_cards';
    }

    /**
     * Get the service name for error messages.
     */
    protected function getServiceName(): string
    {
        return 'EZ Cards';
    }
}
