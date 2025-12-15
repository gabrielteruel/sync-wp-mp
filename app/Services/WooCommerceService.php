<?php

namespace App\Services;

use Automattic\WooCommerce\Client;

class WooCommerceService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(
            env('WOOCOMMERCE_STORE_URL'),
            env('WOOCOMMERCE_CONSUMER_KEY'),
            env('WOOCOMMERCE_CONSUMER_SECRET'),
            [
                'version' => 'wc/v3',
                'timeout' => env('WOOCOMMERCE_WP_TIMEOUT'),
            ]
        );
    }

    public function getCategories(array $params = [])
    {
        return $this->client->get('products/categories', $params);
    }

    public function getProducts(array $params = [])
    {
        return $this->client->get('products', $params);
    }
}
