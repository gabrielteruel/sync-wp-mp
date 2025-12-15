<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetMercadoLibreToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mercadolibre:auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get MercadoLibre Access Token via OAuth 2.0';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clientId = config('services.mercadolibre.client_id');
        $clientSecret = config('services.mercadolibre.client_secret');
        $redirectUri = config('services.mercadolibre.redirect_uri');

        if (!$clientId || !$clientSecret) {
            $this->error('Please verify that MERCADOLIBRE_CLIENT_ID and MERCADOLIBRE_CLIENT_SECRET are set in your .env file.');
            return 1;
        }

        $authUrl = "https://auth.mercadolibre.com.ar/authorization?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}";

        $this->info('1. Open this URL in your browser:');
        $this->line($authUrl);
        $this->newLine();

        $this->info('2. Login and authorize the application.');
        $this->info('3. You will be redirected to a URL (e.g., ' . $redirectUri . '?code=...).');
        $code = $this->ask('4. Paste the "code" parameter value here');

        if (!$code) {
            $this->error('Code is required.');
            return 1;
        }

        $this->info('Exchanging code for access token...');

        $response = Http::post('https://api.mercadolibre.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->newLine();
            $this->info('Success! Here are your credentials:');
            $this->table(
                ['Key', 'Value'],
                [
                    ['Access Token', $data['access_token']],
                    ['Refresh Token', $data['refresh_token']],
                    ['Expires In', $data['expires_in'] . ' seconds'],
                    ['User ID', $data['user_id']],
                    ['Scope', $data['scope'] ?? 'N/A'],
                ]
            );
            $this->newLine();
            $this->warn('Update your .env file with the new MERCADOLIBRE_ACCESS_TOKEN if needed.');
        } else {
            $this->error('Failed to get access token.');
            $this->error('Status: ' . $response->status());
            $this->error('Response: ' . $response->body());
        }

        return 0;
    }
}
