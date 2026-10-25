<?php
namespace App\Http\Middleware;
use App\Models\Rol;
use Closure;
use Illuminate\Http\Request;
class MiddlewarePermiso
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless(Rol::findOrFail(session('id_rol'))->permisos()->where('nombre_permiso',$permission)->exists(),403);
        return $next($request);
    }
}
