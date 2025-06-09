<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCorsConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cors-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test CORS and session configuration for production';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing CORS and Session Configuration...');
        $this->newLine();

        // Check environment variables
        $this->checkEnvironmentVariables();
        $this->newLine();

        // Check CORS configuration
        $this->checkCorsConfiguration();
        $this->newLine();

        // Check session configuration
        $this->checkSessionConfiguration();
        $this->newLine();

        // Check Sanctum configuration
        $this->checkSanctumConfiguration();
        $this->newLine();

        $this->info('✅ Configuration check complete!');
    }

    private function checkEnvironmentVariables()
    {
        $this->info('📋 Environment Variables:');

        $vars = [
            'APP_URL' => env('APP_URL'),
            'FRONTEND_URL' => env('FRONTEND_URL'),
            'SESSION_DOMAIN' => env('SESSION_DOMAIN'),
            'SESSION_SECURE_COOKIE' => env('SESSION_SECURE_COOKIE'),
            'SESSION_SAME_SITE_COOKIE' => env('SESSION_SAME_SITE_COOKIE'),
            'SANCTUM_STATEFUL_DOMAINS' => env('SANCTUM_STATEFUL_DOMAINS'),
        ];

        foreach ($vars as $key => $value) {
            $status = $value !== null ? '✅' : '❌';
            $displayValue = $value ?? 'NOT SET';
            $this->line("  {$status} {$key}: {$displayValue}");
        }

        // Check for common issues
        if (env('SESSION_DOMAIN') !== null) {
            $this->warn('  ⚠️  SESSION_DOMAIN should be null for cross-domain setups');
        }

        if (env('SESSION_SAME_SITE_COOKIE') !== 'none') {
            $this->warn('  ⚠️  SESSION_SAME_SITE_COOKIE should be "none" for cross-domain');
        }

        if (env('SESSION_SECURE_COOKIE') !== true && env('APP_ENV') === 'production') {
            $this->warn('  ⚠️  SESSION_SECURE_COOKIE should be true in production');
        }
    }

    private function checkCorsConfiguration()
    {
        $this->info('🌐 CORS Configuration:');

        $corsConfig = config('cors');
        
        $this->line("  Paths: " . implode(', ', $corsConfig['paths']));
        $this->line("  Allowed Origins: " . implode(', ', $corsConfig['allowed_origins']));
        $this->line("  Supports Credentials: " . ($corsConfig['supports_credentials'] ? 'Yes' : 'No'));

        if (in_array('*', $corsConfig['allowed_origins']) && $corsConfig['supports_credentials']) {
            $this->error('  ❌ Cannot use "*" for allowed_origins with supports_credentials=true');
        } else {
            $this->info('  ✅ CORS origins configuration looks good');
        }
    }

    private function checkSessionConfiguration()
    {
        $this->info('🍪 Session Configuration:');

        $sessionConfig = config('session');
        
        $this->line("  Driver: " . $sessionConfig['driver']);
        $this->line("  Domain: " . ($sessionConfig['domain'] ?? 'null'));
        $this->line("  Secure: " . ($sessionConfig['secure'] ? 'true' : 'false'));
        $this->line("  Same Site: " . $sessionConfig['same_site']);
        $this->line("  HTTP Only: " . ($sessionConfig['http_only'] ? 'true' : 'false'));

        if ($sessionConfig['domain'] === null && $sessionConfig['same_site'] === 'none' && $sessionConfig['secure']) {
            $this->info('  ✅ Session configuration is correct for cross-domain');
        } else {
            $this->warn('  ⚠️  Session configuration may need adjustment for cross-domain');
        }
    }

    private function checkSanctumConfiguration()
    {
        $this->info('🔐 Sanctum Configuration:');

        $statefulDomains = config('sanctum.stateful');
        
        $this->line("  Stateful Domains:");
        foreach ($statefulDomains as $domain) {
            $this->line("    - {$domain}");
        }

        $frontendUrl = env('FRONTEND_URL');
        if ($frontendUrl) {
            $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);
            if (in_array($frontendHost, $statefulDomains)) {
                $this->info("  ✅ Frontend domain ({$frontendHost}) is in stateful domains");
            } else {
                $this->error("  ❌ Frontend domain ({$frontendHost}) is NOT in stateful domains");
            }
        }
    }
}
