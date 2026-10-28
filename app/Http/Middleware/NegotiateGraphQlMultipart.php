<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NegotiateGraphQlMultipart
{
    /**
     * Mark a multipart GraphQL request as such, since API Platform picks the request format from the Accept header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('POST')
            && str_starts_with((string) $request->headers->get('Content-Type'), 'multipart/form-data')
        ) {
            $request->attributes->set('_format', 'multipart');
        }

        return $next($request);
    }
}
