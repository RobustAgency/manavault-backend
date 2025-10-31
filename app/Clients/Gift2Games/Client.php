<?php

namespace App\Clients\Gift2Games;

use App\Clients\BaseApiClient;

class Client extends BaseApiClient
{
    /**
     * Get the configuration key prefix for the service.
     */
    protected function getConfigPrefix(): string
    {
        return 'services.gift2games';
    }

    /**
     * Get the service name for error messages.
     */
    protected function getServiceName(): string
    {
        return 'Gift2Games';
    }

    /**
     * Gift2Games doesn't use "Bearer" prefix in Authorization header.
     */
    protected function useBearerPrefix(): bool
    {
        return false;
    }

    /**
     * Override retry delay to not use a delay between retries.
     */
    protected function getRetryDelay(): int
    {
        return 0;
    }
}
