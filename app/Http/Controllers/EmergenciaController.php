<?php
namespace App\Http\Controllers;
use App\Models\{Medico,NotificacionCita};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class EmergenciaController extends Controller
{
    public function index()
    {
        $own=session('rol')==='Medico';
        return view('contingencias.index',['medicos'=>Medico::with('persona')->where('estado','Activo')->when($own,fn($q)=>$q->whereKey(session('id_persona')))->get(),'notificaciones'=>NotificacionCita::with('cita')->where('tipo','Reprogramacion_Emergencia')->when($own,fn($q)=>$q->whereHas('cita',fn($c)=>$c->where('id_medico',session('id_persona'))))->orderByDesc('id_notificacion')->paginate(20)]);
    }
    public function store(Request $r)
    {
        if(session('rol')==='Medico') $r->merge(['id_medico'=>session('id_persona')]);
        $d=$r->validate(['id_medico'=>'required|integer|exists:medico,id_medico','fecha'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','hora_fin'=>'required|date_format:H:i|after:hora_inicio','motivo'=>'required|string|max:255']);
        $result=DB::transaction(function() use($d) {Medico::whereKey($d['id_medico'])->lockForUpdate()->firstOrFail();return DB::select('CALL sp_reprogramar_masivo_emergencia(?::int, ?::date, ?::time, ?::time, ?::varchar, ?::int, NULL)',[$d['id_medico'],$d['fecha'],$d['hora_inicio'],$d['hora_fin'],$d['motivo'],session('id_usuario')])[0];});
        return back()->with('success','Citas trasladadas: '.$result->p_total_citas_trasladadas.'. Los correos quedan pendientes de despacho.');
    }
}
