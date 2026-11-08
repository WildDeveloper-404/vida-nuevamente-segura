<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class MiddlewarePermiso
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless(in_array($permission, $request->attributes->get('permisos', []), true),403);
        return $next($request);
    }
}
