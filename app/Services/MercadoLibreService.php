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
            throw new \Exception('MercadoLibre API Error (Create): ' . $response->body());
        }

        return $response->json();
    }

    public function updateItem(string $id, array $data)
    {
        $response = Http::withToken($this->token)
            ->put("{$this->baseUrl}/items/{$id}", $data);

        if ($response->failed()) {
            throw new \Exception('MercadoLibre API Error (Update): ' . $response->body());
        }

        return $response->json();
    }

    public function setDescription(string $id, string $plainText)
    {
        // First try to create (POST)
        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/items/{$id}/description", [
                'plain_text' => $plainText
            ]);
        //dump($response->body());
        // If it fails (likely because it exists), try to update (PUT)
        if ($response->failed()) {
            $response = Http::withToken($this->token)
                ->put("{$this->baseUrl}/items/{$id}/description", [
                    'plain_text' => $plainText
                ]);
        }
        //dump($response->body());

        if ($response->failed()) {
            throw new \Exception('MercadoLibre API Error (Description): ' . $response->body());
        }

        return $response->json();
    }

    public function predictCategory(string $title)
    {
        $response = Http::get("{$this->baseUrl}/sites/MLA/domain_discovery/search", [
            'q' => $title,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            // The new endpoint returns an array of objects.
            // We usually want the 'category_id' from the first result.
            if (!empty($data) && isset($data[0]['category_id'])) {
                return $data[0]['category_id'];
            }
        }

        return null;
    }
}
