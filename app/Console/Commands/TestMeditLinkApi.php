<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestMeditLinkApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medit:test {--credential-id= : ID of the API credential to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Medit Link API credentials and connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing Medit Link API Credentials...');
        $this->newLine();

        // Get credential ID from option or find the first Medit Link credential
        $credentialId = $this->option('credential-id');
        
        if ($credentialId) {
            $credential = ApiCredential::where('id', $credentialId)
                ->where('api_name', 'medit_link')
                ->first();
        } else {
            $credential = ApiCredential::where('api_name', 'medit_link')
                ->where('is_active', true)
                ->first();
        }

        if (!$credential) {
            $this->error('❌ No active Medit Link API credentials found.');
            $this->info('Please create Medit Link credentials first using the web interface.');
            return 1;
        }

        $this->info("📋 Testing credentials for: {$credential->api_display_name}");
        $this->info("🆔 Client ID: {$credential->client_id}");
        $this->info("🌐 Base URL: " . ($credential->base_url ?: 'https://openapi-auth.meditlink.com'));
        $this->newLine();

        // Test 1: Check if credentials are valid format
        $this->testCredentialFormat($credential);

        // Test 2: Test OAuth token endpoint
        $this->testOAuthTokenEndpoint($credential);

        // Test 3: Test API connectivity (if we have a token)
        if ($credential->access_token) {
            $this->testApiConnectivity($credential);
        } else {
            $this->warn('⚠️  No access token found. Skipping API connectivity test.');
            $this->info('💡 To get an access token, use the OAuth authorization flow.');
        }

        $this->newLine();
        $this->info('✅ Medit Link API test completed!');
        
        return 0;
    }

    /**
     * Test credential format and validity
     */
    private function testCredentialFormat($credential)
    {
        $this->info('🔍 Testing credential format...');
        
        $errors = [];
        
        // Check client ID format
        if (empty($credential->client_id)) {
            $errors[] = 'Client ID is empty';
        } elseif (strlen($credential->client_id) < 10) {
            $errors[] = 'Client ID seems too short';
        }

        // Check client secret format
        if (empty($credential->client_secret)) {
            $errors[] = 'Client Secret is empty';
        } elseif (strlen($credential->client_secret) < 10) {
            $errors[] = 'Client Secret seems too short';
        }

        // Check base URL format
        if ($credential->base_url && !filter_var($credential->base_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Base URL format is invalid';
        }

        if (empty($errors)) {
            $this->info('✅ Credential format is valid');
        } else {
            $this->error('❌ Credential format issues:');
            foreach ($errors as $error) {
                $this->error("   - {$error}");
            }
        }
        
        $this->newLine();
    }

    /**
     * Test OAuth token endpoint
     */
    private function testOAuthTokenEndpoint($credential)
    {
        $this->info('🔐 Testing OAuth token endpoint...');
        
        $baseUrl = $credential->base_url ?: 'https://openapi-auth.meditlink.com';
        $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token';
        
        try {
            // Test with client credentials grant (if supported)
            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $credential->client_id,
                    'client_secret' => $credential->client_secret,
                    'scope' => 'read'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✅ OAuth token endpoint is accessible');
                $this->info("📊 Response: " . json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->warn('⚠️  OAuth token endpoint returned: ' . $response->status());
                $this->info('📊 Response: ' . $response->body());
                
                // This is expected for Medit Link as it doesn't support client_credentials
                if ($response->status() === 400) {
                    $this->info('💡 This is expected - Medit Link uses Authorization Code flow, not Client Credentials');
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to connect to OAuth token endpoint: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Test API connectivity with access token
     */
    private function testApiConnectivity($credential)
    {
        $this->info('🌐 Testing API connectivity...');
        
        // Test user info endpoint
        $apiBaseUrl = 'https://api.meditlink.com';
        $userEndpoint = $apiBaseUrl . '/v1/user/me';
        
        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $credential->access_token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->get($userEndpoint);

            if ($response->successful()) {
                $this->info('✅ API connectivity test successful');
                $data = $response->json();
                $this->info('📊 User Info: ' . json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ API connectivity test failed: ' . $response->status());
                $this->info('📊 Response: ' . $response->body());
                
                if ($response->status() === 401) {
                    $this->warn('💡 Access token may be expired or invalid');
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to connect to API: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
}
