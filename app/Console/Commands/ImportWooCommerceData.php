<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\WooCommerceService;
use Illuminate\Console\Command;

class ImportWooCommerceData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-woocommerce';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products and categories from WooCommerce';

    /**
     * Execute the console command.
     */
    public function handle(WooCommerceService $woocommerceService)
    {
        $this->info('Starting WooCommerce import...');

        try {
            // Import Categories
            $this->info('Importing Categories...');
            // In a real scenario we should handle pagination. For now, fetch 100.
            $categories = $woocommerceService->getCategories(['per_page' => 100]);

            foreach ($categories as $wcCategory) {
                Category::updateOrCreate(
                    ['woocommerce_id' => $wcCategory->id],
                    [
                        'name' => $wcCategory->name,
                        'slug' => $wcCategory->slug,
                    ]
                );
            }
            $this->info('Categories imported.');

            // Import Products
            $this->info('Importing Products...');
            // Fetch specific product for testing
            //$products = $woocommerceService->getProducts(['include' => [11508]]);
            $products = $woocommerceService->getProducts(['per_page' => 100]);

            foreach ($products as $wcProduct) {
                // Collect category IDs
                $categoryIds = [];
                if (!empty($wcProduct->categories)) {
                    foreach ($wcProduct->categories as $catData) {
                        // Ensure category exists locally
                        $localCat = Category::updateOrCreate(
                            ['woocommerce_id' => $catData->id],
                            [
                                'name' => $catData->name,
                                'slug' => $catData->slug,
                            ]
                        );
                        $categoryIds[] = $localCat->id;
                    }
                }

                $imageUrl = null;
                if (!empty($wcProduct->images) && isset($wcProduct->images[0]->src)) {
                    $imageUrl = $wcProduct->images[0]->src;
                }

                $product = Product::updateOrCreate(
                    ['woocommerce_id' => $wcProduct->id],
                    [
                        'name' => $wcProduct->name,
                        'slug' => $wcProduct->slug,
                        'price' => $wcProduct->price,
                        'description' => $wcProduct->short_description ?: $wcProduct->description,
                        'image_url' => $imageUrl,
                        // 'mercadolibre_id' => ... remove this line, let it persist
                    ]
                );

                // Fix: updateOrCreate returns the model. We don't need to manually preserve ML ID if we are finding by WC ID.

                // Sync categories
                $product->categories()->sync($categoryIds);

                // Sync Images
                if (!empty($wcProduct->images)) {
                    // Decide strategy: Delete all and re-create? Or updateOrCreate?
                    // Safe approach for sync: Delete all and re-insert to reflect deletions in WC.
                    $product->images()->delete();

                    foreach ($wcProduct->images as $index => $imgData) {
                        $product->images()->create([
                            'url' => $imgData->src,
                            'is_featured' => $index === 0,
                        ]);
                    }
                }
            }
            $this->info('Products imported.');

            $this->info('Import completed successfully.');
        } catch (\Exception $e) {
            $this->error('Error importing data: ' . $e->getMessage());
        }
    }
}
