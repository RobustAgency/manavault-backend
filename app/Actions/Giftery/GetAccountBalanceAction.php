<?php

namespace App\Actions\Giftery;

use App\Clients\Giftery\Client;

class GetAccountBalanceAction
{
    public function __construct(private Client $gifteryClient) {}

    /**
     * Get the account balance from Giftery.
     *
     * @return array Account data with available balance, balance, and creditLimit
     *
     * @throws \RuntimeException If fetching account fails
     */
    public function execute(): array
    {
        $response = $this->gifteryClient->getAccount();

        if (($response['statusCode'] ?? -1) !== 0) {
            throw new \RuntimeException(
                'Giftery get account failed: '.($response['message'] ?? 'Unknown error')
            );
        }

        $accounts = $response['data'] ?? [];

        if (empty($accounts)) {
            throw new \RuntimeException('No accounts found in Giftery response');
        }

        // Return the default account or the first one
        $defaultAccount = null;
        foreach ($accounts as $account) {
            if (isset($account['default']) && $account['default'] === true) {
                /** @var array $defaultAccount */
                $defaultAccount = $account;
                break;
            }
        }

        /** @var array $account */
        $account = $defaultAccount ?? $accounts[0];

        return $account;
    }
}
