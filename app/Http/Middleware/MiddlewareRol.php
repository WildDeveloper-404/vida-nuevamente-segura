<?php
namespace App\Http\Middleware;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
class MiddlewareRol
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->session()->has('id_usuario') ? Usuario::with('rol')->find(session('id_usuario')) : null;
        if (!$user || $user->estado !== 'Activo' || (session()->has('auth_version') && !hash_equals(session('auth_version'),hash('sha256',$user->password_hash)))) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) return response()->json(['message'=>'Inicie sesión para continuar.'],401);
            return redirect()->route('login')->withErrors(['sesion'=>'Inicie sesión para continuar.']);
        }
        $role = $user->rol->nombre_rol;
        if(session('rol')!==$role) $request->session()->forget('voice_report');
        abort_unless(in_array($role, ['Administrador','Recepcionista','Medico','Paciente'], true),403);
        if ($roles) abort_unless(in_array($role, $roles, true),403);
        session(['rol'=>$role,'nombre_rol'=>$role,'id_rol'=>$user->id_rol,'id_persona'=>$user->id_persona,'correo'=>$user->correo]);
        return $next($request);
    }
}
