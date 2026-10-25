<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BitacoraController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['fecha_inicio'=>'nullable|date','fecha_fin'=>'nullable|date|after_or_equal:fecha_inicio','correo'=>'nullable|string|max:120','rol'=>'nullable|string|max:50','modulo'=>'nullable|string|max:80']);
        $q=DB::table('bitacora as b')->leftJoin('usuario as u','u.id_usuario','=','b.id_usuario')->leftJoin('rol as r','r.id_rol','=','u.id_rol')->select('b.*','u.correo','r.nombre_rol');
        if($r->filled('fecha_inicio')) $q->whereDate('fecha_hora','>=',$r->fecha_inicio);
        if($r->filled('fecha_fin')) $q->whereDate('fecha_hora','<=',$r->fecha_fin);
        if($r->filled('correo')) $q->where('u.correo','ilike','%'.$r->correo.'%');
        if($r->filled('rol')) $q->where('r.nombre_rol',$r->rol);
        if($r->filled('modulo')) $q->where('b.modulo','ilike','%'.$r->modulo.'%');
        return view('admin.bitacora.index',['eventos'=>$q->orderByDesc('id_bitacora')->paginate(30)->withQueryString()]);
    }
}
