<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) config('receiver.basic_user');
        $password = (string) config('receiver.basic_password');

        if ($request->getUser() !== $user || $request->getPassword() !== $password) {
            return response()->json([
                'message' => 'Не авторизован.',
            ], 401, [
                'WWW-Authenticate' => 'Basic realm="Receiver"',
            ]);
        }

        return $next($request);
    }
}
