<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Signs in the phone app's offline screens with a device token: "Authorization: Bearer <token>". */
class AuthenticateAppToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $row = $token ? DB::table('app_tokens')->where('token_hash', hash('sha256', $token))->first() : null;
        $user = $row ? User::find($row->user_id) : null;
        if (! $user || ! $user->household_id) {
            return response()->json(['message' => 'Sign in to Kitchen HQ again on this phone.'], 401);
        }
        if (! $row->last_used_at || now()->diffInMinutes($row->last_used_at, true) >= 5) {
            DB::table('app_tokens')->where('id', $row->id)->update(['last_used_at' => now()]);
        }
        Auth::setUser($user);

        return $next($request);
    }
}
