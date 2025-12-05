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
            $products = $woocommerceService->getProducts(['per_page' => 100]);

            foreach ($products as $wcProduct) {
                // Collect category IDs
                $categoryIds = [];
                if (! empty($wcProduct->categories)) {
                    foreach ($wcProduct->categories as $catData) {
                        $localCat = Category::where('woocommerce_id', $catData->id)->first();
                        if ($localCat) {
                            $categoryIds[] = $localCat->id;
                        }
                    }
                }

                $imageUrl = null;
                if (! empty($wcProduct->images) && isset($wcProduct->images[0]->src)) {
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
                    ]
                );

                // Sync categories
                $product->categories()->sync($categoryIds);
            }
            $this->info('Products imported.');

            $this->info('Import completed successfully.');
        } catch (\Exception $e) {
            $this->error('Error importing data: '.$e->getMessage());
        }
    }
}
