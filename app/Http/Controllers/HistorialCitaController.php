<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Especialidad};
use Illuminate\Http\Request;
class HistorialCitaController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['desde'=>'nullable|date','hasta'=>'nullable|date|after_or_equal:desde','estado'=>'nullable|in:Programada,Confirmada,Atendida,Cancelada,Inasistencia','id_paciente'=>'nullable|integer','id_especialidad'=>'nullable|integer']);
        $q=CitaMedica::with(['paciente.persona','medico.persona','especialidad','pago.comprobante','receta','derivaciones']);
        if(session('rol')==='Paciente') $q->where('id_paciente',session('id_persona'));
        elseif($r->filled('id_paciente')) $q->where('id_paciente',$r->integer('id_paciente'));
        foreach(['estado','id_especialidad'] as $key) if($r->filled($key)) $q->where($key,$r->$key);
        if($r->filled('desde')) $q->whereDate('fecha_cita','>=',$r->desde);
        if($r->filled('hasta')) $q->whereDate('fecha_cita','<=',$r->hasta);
        return view('citas.kardex',['citas'=>$q->orderByDesc('fecha_cita')->orderByDesc('hora_inicio')->paginate(20)->withQueryString(),'especialidades'=>Especialidad::all()]);
    }
}
