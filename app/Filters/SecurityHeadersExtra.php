<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeadersExtra implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // No-op
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $response->hasHeader('Permissions-Policy')) {
            $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }

        if (! $response->hasHeader('X-Robots-Tag')) {
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
