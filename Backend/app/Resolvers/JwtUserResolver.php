<?php

namespace App\Resolvers;

use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\UserResolver;

class JwtUserResolver implements UserResolver
{
    public static function resolve()
    {
        return Auth::guard('api')->user() ?? Auth::user();
    }
}
