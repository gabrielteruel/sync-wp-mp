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
        //$products = Product::whereNull('mercadolibre_id')->skip(1)->take(1)->get();
        $products = Product::with('categories')->skip(1)->take(1)->get();
        $this->info("Found {$products->count()} products to sync.");

        foreach ($products as $product) {
            $this->info("Syncing product: {$product->name}");

            $categoriesList = $product->categories->pluck('name')->join(', ');
            $this->info("Local Categories: {$categoriesList}");

            $plainTitle = strip_tags($product->name);
            $plainDescription = strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $product->description));

            // Extract Brand
            $brand = 'Otras marcas';
            if (preg_match('/Marca:\s*([^\n\r]+)/i', $plainDescription, $matches)) {
                $brand = trim($matches[1]);
            }

            // Predict Category
            $categoryId = $service->predictCategory($plainTitle);
            if (!$categoryId) {
                $this->warn("Could not predict category for '{$plainTitle}'. Using default MLA3530.");
                $categoryId = 'MLA3530';
            }

            $data = [
                'title' => $plainTitle,
                'category_id' => $categoryId,
                'price' => (float) $product->price,
                'currency_id' => 'ARS',
                'available_quantity' => 1,
                'buying_mode' => 'buy_it_now',
                'listing_type_id' => 'gold_special',
                'condition' => 'used',
                'status' => 'not_yet_active',
                'pictures' => [],
                'attributes' => [
                    [
                        'id' => 'ITEM_CONDITION',
                        'value_id' => '2230581', // Usado
                    ],
                    [
                        'id' => 'BRAND',
                        'value_name' => $brand,
                    ],
                    [
                        'id' => 'MODEL',
                        'value_name' => 'Estándar',
                    ],
                ],
            ];

            if ($product->images->isNotEmpty()) {
                foreach ($product->images as $img) {
                    $data['pictures'][] = ['source' => $img->url];
                }
            } elseif ($product->image_url) {
                // Fallback to legacy column
                $data['pictures'][] = ['source' => $product->image_url];
            }

            try {
                $mlId = $product->mercadolibre_id;

                if ($mlId) {
                    // Update existing item
                    $this->info("Updating item {$mlId}...");
                    //$service->updateItem($mlId, $data);
                    $this->info("Item updated!");
                } else {
                    // Create new item
                    $this->info("Creating new item...");
                    $response = $service->createItem($data);

                    if (isset($response['id'])) {
                        $mlId = $response['id'];
                        $product->update(['mercadolibre_id' => $mlId]);
                        $this->info("Created! ID: {$mlId}");
                    } else {
                        $this->error('Failed to create item (No ID returned).');
                        continue;
                    }
                }

                // Sync Description
                if ($plainDescription) {
                    $this->info("Syncing description...");
                    $service->setDescription($mlId, $plainDescription);
                    $this->info("Description synced!");
                }

                $this->newLine();
                $this->info("--------------------------------------------------");
                $this->info("ACTION: " . ($product->wasRecentlyCreated || !$product->getOriginal('mercadolibre_id') ? 'CREATE' : 'UPDATE'));
                $this->info("ID: " . $mlId);
                $this->info("--------------------------------------------------");

                // Show attributes sent
                $this->info("Attributes sent:");
                $headers = ['ID', 'Value'];
                $rows = [];
                foreach ($data['attributes'] as $attr) {
                    $rows[] = [
                        $attr['id'],
                        $attr['value_name'] ?? $attr['value_id'] ?? 'N/A'
                    ];
                }
                $this->table($headers, $rows);

                // Show description
                $this->info("Description sent:");
                $this->line($plainDescription ?: 'No description');
                $this->newLine();

            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
            }
        }
    }
}
