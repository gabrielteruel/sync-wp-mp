<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MercadoLibreService
{
    protected $baseUrl = 'https://api.mercadolibre.com';

    protected $token;

    public function __construct()
    {
        $this->token = env('MERCADOLIBRE_ACCESS_TOKEN');
    }

    public function createItem(array $data)
    {
        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/items", $data);

        if ($response->failed()) {
            throw new \Exception('MercadoLibre API Error: '.$response->body());
        }

        return $response->json();
    }

    public function predictCategory(string $title)
    {
        $response = Http::get("{$this->baseUrl}/sites/MLA/category_predictor/predict", [
            'title' => $title,
        ]);

        if ($response->successful() && isset($response[0]['id'])) {
            return $response[0]['id'];
        }

        return null;
    }
}
