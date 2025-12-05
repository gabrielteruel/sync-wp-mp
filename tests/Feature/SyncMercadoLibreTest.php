<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\MercadoLibreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMercadoLibreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_products_to_mercadolibre()
    {
        // Create a product
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 100.00,
            'description' => 'Test description',
            'image_url' => 'https://example.com/image.jpg',
            'woocommerce_id' => 123,
        ]);

        // Mock Service
        $this->mock(MercadoLibreService::class, function ($mock) {
            $mock->shouldReceive('predictCategory')
                ->once()
                ->with('Test Product')
                ->andReturn('MLA1234');

            $mock->shouldReceive('createItem')
                ->once()
                ->withArgs(function ($data) {
                    return $data['title'] === 'Test Product'
                        && $data['category_id'] === 'MLA1234'
                        && $data['price'] === 100.00
                        && $data['pictures'][0]['source'] === 'https://example.com/image.jpg';
                })
                ->andReturn([
                    'id' => 'MLA12345678',
                    'permalink' => 'http://mercadolibre.com.ar/item',
                ]);
        });

        // Run command
        $this->artisan('app:sync-mercadolibre')
            ->assertExitCode(0);

        // Assert DB updated
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'mercadolibre_id' => 'MLA12345678',
        ]);
    }
}
