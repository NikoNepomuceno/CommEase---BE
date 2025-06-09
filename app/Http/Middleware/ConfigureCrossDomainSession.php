<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureCrossDomainSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the origin from the request
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');
        
        // List of allowed origins
        $allowedOrigins = [
            'https://commease-frontend.vercel.app',
            'http://localhost:3000',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
        ];
        
        // Check if the request is from an allowed origin
        $isAllowedOrigin = in_array($origin, $allowedOrigins) || 
                          ($referer && $this->isRefererFromAllowedOrigin($referer, $allowedOrigins));
        
        if ($isAllowedOrigin) {
            // Configure session for cross-domain
            config([
                'session.domain' => null,
                'session.secure' => true,
                'session.same_site' => 'none',
                'session.http_only' => true,
            ]);
        }
        
        $response = $next($request);
        
        // Add additional headers for cross-domain requests
        if ($isAllowedOrigin) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
        }
        
        return $response;
    }
    
    /**
     * Check if referer is from an allowed origin
     */
    private function isRefererFromAllowedOrigin(string $referer, array $allowedOrigins): bool
    {
        foreach ($allowedOrigins as $origin) {
            if (str_starts_with($referer, $origin)) {
                return true;
            }
        }
        return false;
    }
}
