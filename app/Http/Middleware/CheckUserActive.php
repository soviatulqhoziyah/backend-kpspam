<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->status === 'non_aktif') {
            return response()->json([
                'status'  => 'error',
                'code'    => 'ACCOUNT_DEACTIVATED',
                'message' => 'Akun Anda telah dinonaktifkan oleh Admin. Silakan hubungi administrator.',
            ], 401);
        }

        return $next($request);
    }
}
