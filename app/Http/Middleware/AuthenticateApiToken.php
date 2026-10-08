<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $plainToken = $this->extractToken($request);
        if ($plainToken === null) {
            return $this->unauthorized('Missing API token. Use Authorization: Bearer <token>.');
        }

        $tokenHash = hash('sha256', $plainToken);

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $token || ! $token->isActive()) {
            return $this->unauthorized('Invalid or expired API token.');
        }

        if (! $token->user || $token->user->isBanned()) {
            return response()->json(['message' => 'Account access is disabled.', 'error_code' => 'FORBIDDEN'], 403);
        }

        $requestIp = (string) $request->ip();
        if (! $token->isRequestIpAllowed($requestIp)) {
            return response()->json([
                'message' => 'API token is restricted to a trusted IP address.',
                'error_code' => 'IP_NOT_ALLOWED',
            ], 403);
        }

        $token->markUsed($requestIp);

        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_token_id', (string) $token->id);
        $request->setUserResolver(static fn () => $token->user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return trim($bearer);
        }

        $header = (string) $request->header('X-API-Key', '');

        return $header !== '' ? trim($header) : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }
}
