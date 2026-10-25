<?php
namespace App\Http\Controllers;
use App\Models\Usuario;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public static function home(string $role): string
    {
        return match($role) {'Administrador'=>'admin.dashboard','Recepcionista'=>'recepcion.dashboard','Medico'=>'medico.dashboard',default=>'paciente.dashboard'};
    }
    public function showLogin()
    {
        if(session('id_usuario')) return redirect()->route(self::home(session('rol','Paciente')));
        return view('auth.login');
    }
    public function login(Request $r)
    {
        $d=$r->validate(['correo'=>'required|email|max:120','password'=>'required|string|max:255']);
        $user=DB::transaction(function() use($d,$r) {
            $u=Usuario::whereRaw('lower(correo) = ?', [mb_strtolower(trim($d['correo']))])->lockForUpdate()->first();
            $eligible=$u && $u->estado==='Activo' && (!$u->bloqueado_hasta || $u->bloqueado_hasta->isPast());
            $ok=$eligible && Hash::check($d['password'],$u->password_hash);
            $result=DB::select('SELECT * FROM fn_registrar_intento_login(?::varchar, ?::boolean, ?::varchar)',[$d['correo'],$ok?'true':'false',$r->ip()])[0];
            return ['usuario'=>$u,'resultado'=>$result];
        });
        if(!$user['resultado']->permitido) throw ValidationException::withMessages(['correo'=>$user['resultado']->mensaje]);
        $u=$user['usuario']; $role=$user['resultado']->nombre_rol_out;
        $r->session()->regenerate();
        session(['id_usuario'=>$u->id_usuario,'id_persona'=>$u->id_persona,'correo'=>$u->correo,'id_rol'=>$u->id_rol,'rol'=>$role,'nombre_rol'=>$role,'auth_version'=>hash('sha256',$u->password_hash)]);
        return redirect()->route(self::home($role));
    }
    public function logout(Request $r)
    {
        if(session('id_usuario')) Audit::log('Seguridad','LOGOUT','Cierre de sesión');
        $r->session()->invalidate(); $r->session()->regenerateToken();
        return redirect()->route('login')->with('success','Sesión cerrada.');
    }
    public function showRecuperar() { return view('auth.recuperar'); }
    public function enviarTokenRecuperacion(Request $r)
    {
        $d=$r->validate(['correo'=>'required|email|max:120']);
        $u=Usuario::whereRaw('lower(correo) = ?', [mb_strtolower($d['correo'])])->where('estado','Activo')->first();
        if($u) {
            $token=Str::random(64);
            $u->update(['token_recuperacion'=>hash('sha256',$token),'token_expiracion'=>now()->addMinutes(30)]);
            $url=route('password.reset',['token'=>$token,'correo'=>$u->correo]);
            try { Mail::raw("Restablezca su contraseña en los próximos 30 minutos: $url",fn($m)=>$m->to($u->correo)->subject('Recuperar acceso')); }
            catch(\Throwable $e) { report($e); }
        }
        return back()->with('success','Si el correo corresponde a una cuenta activa, recibirá un enlace temporal.');
    }
    public function reset(string $token) { return view('auth.reset',compact('token')); }
    public function cambiarPasswordConToken(Request $r)
    {
        $d=$r->validate(['correo'=>'required|email','token'=>'required|string|size:64','password'=>'required|string|min:10|max:128|confirmed']);
        DB::transaction(function() use($d) {
            $u=Usuario::whereRaw('lower(correo) = ?', [mb_strtolower($d['correo'])])->lockForUpdate()->first();
            if(!$u || !$u->token_recuperacion || !hash_equals($u->token_recuperacion,hash('sha256',$d['token'])) || !$u->token_expiracion || $u->token_expiracion->isPast() || $u->estado!=='Activo') {
                throw ValidationException::withMessages(['token'=>'El enlace no es válido o ha expirado.']);
            }
            $u->update(['password_hash'=>Hash::make($d['password']),'token_recuperacion'=>null,'token_expiracion'=>null,'intentos_fallidos'=>0,'bloqueado_hasta'=>null]);
            \App\Models\Bitacora::create(['id_usuario'=>$u->id_usuario,'modulo'=>'Seguridad','accion'=>'RECUPERACION','descripcion'=>'Contraseña restablecida','direccion_ip'=>request()->ip()]);
        });
        return redirect()->route('login')->with('success','Contraseña actualizada.');
    }
}
