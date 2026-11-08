<?php
namespace App\Http\Controllers;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BitacoraController extends Controller
{
    public function index(Request $r, ExportService $export)
    {
        $r->validate(['fecha_inicio'=>'nullable|date','fecha_fin'=>'nullable|date|after_or_equal:fecha_inicio','correo'=>'nullable|string|max:120','rol'=>'nullable|string|max:50','modulo'=>'nullable|string|max:80','accion'=>'nullable|string|max:100','formato'=>'nullable|in:xlsx']);
        $q=DB::table('bitacora as b')->leftJoin('usuario as u','u.id_usuario','=','b.id_usuario')->leftJoin('rol as r','r.id_rol','=','u.id_rol')->select('b.*','u.correo','r.nombre_rol');
        if($r->filled('fecha_inicio')) $q->whereDate('fecha_hora','>=',$r->fecha_inicio);
        if($r->filled('fecha_fin')) $q->whereDate('fecha_hora','<=',$r->fecha_fin);
        if($r->filled('correo')) $q->where('u.correo','ilike','%'.$r->correo.'%');
        if($r->filled('rol')) $q->where('r.nombre_rol',$r->rol);
        if($r->filled('modulo')) $q->where('b.modulo','ilike','%'.$r->modulo.'%');
        if($r->filled('accion')) $q->where('b.accion','ilike','%'.$r->accion.'%');
        if ($r->formato === 'xlsx') {
            $rows = $q->orderByDesc('id_bitacora')->limit(1001)->get();
            abort_if($rows->count() > 1000, 422, 'Acote el período para exportar un máximo de 1000 eventos.');
            return $export->download([
                'columns'=>['fecha_hora'=>'Fecha y hora','correo'=>'Usuario','direccion_ip'=>'IP','modulo'=>'Módulo',
                    'accion'=>'Acción','descripcion'=>'Descripción','valores_anteriores'=>'Antes','valores_nuevos'=>'Después'],
                'rows'=>$rows,
                'totals'=>[],
            ], 'xlsx', 'Bitácora');
        }
        return view('admin.bitacora.index',['eventos'=>$q->orderByDesc('id_bitacora')->paginate(30)->withQueryString()]);
    }
}
