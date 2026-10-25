<?php
namespace App\Http\Controllers;
use App\Models\{Rol,Modulo,Permiso};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class RolController extends Controller
{
    public function index() { return view('admin.roles',['roles'=>Rol::with('permisos')->get(),'modulos'=>Modulo::with('permisos')->get()]); }
    public function update(Request $r,Rol $rol)
    {
        $d=$r->validate(['permisos'=>'nullable|array','permisos.*'=>'integer|exists:permiso,id_permiso']);
        abort_if($rol->nombre_rol==='Administrador',422,'Los permisos administrativos básicos se conservan para evitar bloquear la gestión.');
        DB::transaction(function() use($d,$rol) {$rol->permisos()->sync($d['permisos']??[]);Audit::log('Seguridad','PERMISOS','Permisos actualizados para '.$rol->nombre_rol);});
        return back()->with('success','Permisos actualizados; se aplican en la siguiente solicitud.');
    }
    public function modulo(Request $r)
    {
        $d=$r->validate(['nombre_modulo'=>'required|string|max:100|unique:modulo,nombre_modulo']);
        DB::transaction(function() use($d) {Modulo::create($d);Audit::log('Seguridad','ALTA_MODULO',$d['nombre_modulo']);});return back()->with('success','Módulo registrado.');
    }
    public function permiso(Request $r)
    {
        $d=$r->validate(['nombre_permiso'=>'required|string|regex:/^[a-z_]+$/|max:100|unique:permiso,nombre_permiso','id_modulo'=>'required|integer|exists:modulo,id_modulo']);
        DB::transaction(function() use($d) {Permiso::create($d);Audit::log('Seguridad','ALTA_PERMISO',$d['nombre_permiso']);});return back()->with('success','Permiso registrado.');
    }
}
