<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to allow iframe embedding
 * This sets appropriate headers to allow the application to work in iframes
 */
class AllowIframe
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Allow iframe embedding - set X-Frame-Options to SAMEORIGIN or remove it
        // SAMEORIGIN allows embedding in iframes from the same origin
        // For cross-origin iframes, you may need to remove this header entirely
        // or set it to ALLOWALL (not recommended for security)
        
        // Check if request is from iframe context
        $isIframeRequest = $request->header('Sec-Fetch-Dest') === 'iframe' 
            || $request->header('X-Requested-With') === 'iframe'
            || $request->has('iframe') 
            || $request->header('Referer'); // If referer exists, might be iframe

        // Remove X-Frame-Options to allow iframe embedding
        // Or set to SAMEORIGIN for same-origin iframes
        $response->headers->remove('X-Frame-Options');
        
        // Set Content-Security-Policy to allow iframe embedding
        // This is more flexible than X-Frame-Options
        // Allow specific origins via env or default to allow all for maximum compatibility
        $allowedOrigins = env('IFRAME_ALLOWED_ORIGINS', '*');
        $csp = "frame-ancestors 'self' {$allowedOrigins};";
        $response->headers->set('Content-Security-Policy', $csp, false);
        
        // Add P3P header for older browsers (IE) - helps with cookie issues in iframes
        $response->headers->set('P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"', false);

        return $response;
    }
}

