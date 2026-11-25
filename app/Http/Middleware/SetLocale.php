<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Prefer Latvian when the browser asks for it; otherwise keep Russian.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detect($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function detect(Request $request): string
    {
        $header = (string) $request->header('Accept-Language', '');

        if ($header === '') {
            return 'ru';
        }

        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part, 2)[0]));

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $primary = explode('-', $tag, 2)[0];

            if ($primary === 'lv') {
                return 'lv';
            }

            if ($primary === 'ru') {
                return 'ru';
            }
        }

        return 'ru';
    }
}
