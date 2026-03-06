<?php

namespace App\Clients\Gift2Games;

class Balance extends Client
{
    public function __construct(string $configPrefix = 'services.gift2games')
    {
        parent::__construct($configPrefix);
    }

    /**
     * Check the account balance from the Gift2Games API.
     *
     **/
    public function checkBalance(): array
    {
        $response = $this->getClient()->get('check_balance');
        $response = $this->handleResponse($response);

        return $response;
    }
}
