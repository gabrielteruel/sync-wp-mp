<?php

namespace Tests\Feature;

use App\Services\WooCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportWooCommerceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_categories_and_products()
    {
        // Mock data
        $mockCategories = [
            (object) [
                'id' => 10,
                'name' => 'T-Shirts',
                'slug' => 't-shirts',
            ],
        ];

        $mockProducts = [
            (object) [
                'id' => 100,
                'name' => 'Cool Shirt',
                'slug' => 'cool-shirt',
                'price' => '19.99',
                'description' => 'A very cool shirt.',
                'short_description' => '',
                'categories' => [
                    (object) ['id' => 10, 'name' => 'T-Shirts', 'slug' => 't-shirts'],
                ],
            ],
        ];

        // Mock Service
        $this->mock(WooCommerceService::class, function ($mock) use ($mockCategories, $mockProducts) {
            $mock->shouldReceive('getCategories')
                ->once()
                ->with(['per_page' => 100])
                ->andReturn($mockCategories);

            $mock->shouldReceive('getProducts')
                ->once()
                ->with(['per_page' => 100])
                ->andReturn($mockProducts);
        });

        // Run command
        $this->artisan('app:import-woocommerce')
            ->assertExitCode(0);

        // Assert Database
        $this->assertDatabaseHas('categories', [
            'woocommerce_id' => 10,
            'name' => 'T-Shirts',
        ]);

        $this->assertDatabaseHas('products', [
            'woocommerce_id' => 100,
            'name' => 'Cool Shirt',
            'price' => 19.99,
        ]);

        // Check relationship
        $product = \App\Models\Product::first();
        $this->assertNotEmpty($product->categories, 'Product should have categories');
        $this->assertTrue($product->categories->contains('woocommerce_id', 10));
    }
}
