<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOpeningBalances
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('show_opening_balances') && ! $request->routeIs('opening')) {
            return redirect()->route('opening');
        }

        return $next($request);
    }
}
