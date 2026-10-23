<?php

namespace App\Services\Payments;

use InvalidArgumentException;

final readonly class GatewayCheckout
{
    public function __construct(public string $url, public string $reference)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https' || $reference === '' || strlen($reference) > 150) {
            throw new InvalidArgumentException('The gateway returned an invalid checkout session.');
        }
    }
}
