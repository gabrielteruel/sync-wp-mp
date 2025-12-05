<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\MercadoLibreService;
use Illuminate\Console\Command;

class SyncMercadoLibre extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-mercadolibre';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products to MercadoLibre';

    /**
     * Execute the console command.
     */
    public function handle(MercadoLibreService $service)
    {
        $products = Product::whereNull('mercadolibre_id')->get();

        $this->info("Found {$products->count()} products to sync.");

        foreach ($products as $product) {
            $this->info("Syncing product: {$product->name}");

            // Predict Category
            $categoryId = $service->predictCategory($product->name);
            if (! $categoryId) {
                $this->warn("Could not predict category for '{$product->name}'. Using default MLA3530.");
                $categoryId = 'MLA3530';
            }

            $data = [
                'title' => $product->name,
                'category_id' => $categoryId,
                'price' => (float) $product->price,
                'currency_id' => 'ARS',
                'available_quantity' => 1,
                'buying_mode' => 'buy_it_now',
                'listing_type_id' => 'gold_special',
                'condition' => 'new',
                'pictures' => [],
                'attributes' => [
                    [
                        'id' => 'ITEM_CONDITION',
                        'value_id' => '2230284', // Nuevo
                    ],
                ],
            ];

            if ($product->image_url) {
                $data['pictures'][] = ['source' => $product->image_url];
            }

            try {
                $response = $service->createItem($data);

                if (isset($response['id'])) {
                    $product->update(['mercadolibre_id' => $response['id']]);
                    $this->info("Synced! ID: {$response['id']}");
                } else {
                    $this->error('Failed to sync (No ID returned).');
                }

            } catch (\Exception $e) {
                $this->error('Error: '.$e->getMessage());
            }
        }
    }
}
