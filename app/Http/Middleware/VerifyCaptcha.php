<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyCaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('eduworld.turnstile_secret_key')) {
            $token = $request->validate(['cf-turnstile-response' => ['required', 'string', 'max:2048']])['cf-turnstile-response'];
            try {
                $result = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('eduworld.turnstile_secret_key'), 'response' => $token, 'remoteip' => $request->ip(),
                ])->throw()->json();
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages(['captcha' => 'Verification is temporarily unavailable. Please try again.']);
            }
            if (! ($result['success'] ?? false) || ($result['hostname'] ?? '') !== parse_url(config('app.url'), PHP_URL_HOST)) {
                throw ValidationException::withMessages(['captcha' => 'Please complete the security verification again.']);
            }
        }

        return $next($request);
    }
}
