<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
        'stripe/*',
        'razorpay/*',
        'subscription/*',
        // 'whatsapp-webhook url set under handle method using route name',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        $this->except[] = route('vendor.whatsapp_webhook', [
            'vendorUid' => '*',
        ]);

        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            // Check if request is from iframe context
            $isIframeRequest = $request->header('Sec-Fetch-Dest') === 'iframe' 
                || $request->header('X-Requested-With') === 'iframe'
                || $request->has('iframe')
                || $request->ajax(); // AJAX requests from iframes

            // For iframe requests, provide more helpful error message
            $message = $isIframeRequest 
                ? __tr('Session expired. Please refresh the page and try again. If the issue persists, ensure cookies are enabled and the site is accessed via HTTPS.')
                : __tr('Token Expired, Please reload and try again.');

            return __apiResponse([
                'message' => $message,
                'auth_info' => getUserAuthInfo(5),
                'show_message' => true,
                'iframe_context' => $isIframeRequest,
                'token_refresh_required' => true,
            ], 2);
        }
    }
}
