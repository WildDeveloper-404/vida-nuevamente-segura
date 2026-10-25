<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Medico};
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class AgendaMedicaController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['fecha'=>'nullable|date','vista'=>'nullable|in:dia,semana','estado'=>'nullable|in:Programada,Confirmada,Atendida,Cancelada,Inasistencia','id_medico'=>'nullable|integer']);
        $date=Carbon::parse($r->input('fecha',today()->toDateString()));
        $start=$r->vista==='semana'?$date->copy()->startOfWeek():$date;
        $end=$r->vista==='semana'?$date->copy()->endOfWeek():$date;
        $q=CitaMedica::with(['paciente.persona','medico.persona','especialidad','receta'])->whereBetween('fecha_cita',[$start->toDateString(),$end->toDateString()]);
        if(session('rol')==='Medico') $q->where('id_medico',session('id_persona'));
        elseif($r->filled('id_medico')) $q->where('id_medico',$r->integer('id_medico'));
        if($r->filled('estado')) $q->where('estado',$r->estado);
        return view('medico.agenda',['citas'=>$q->orderBy('fecha_cita')->orderBy('hora_inicio')->paginate(30)->withQueryString(),'medicos'=>Medico::with('persona')->get()]);
    }
    public function estado(Request $r,CitaMedica $cita)
    {
        Access::cita($cita);$d=$r->validate(['estado'=>'required|in:Confirmada,Atendida,Inasistencia']);
        DB::transaction(function() use($cita,$d) {
            Medico::whereKey($cita->id_medico)->lockForUpdate()->firstOrFail();
            $c=CitaMedica::whereKey($cita->id_cita)->lockForUpdate()->firstOrFail();
            $valid=(session('rol')==='Recepcionista' && $c->estado==='Programada' && $d['estado']==='Confirmada')
                || (session('rol')==='Medico' && $c->estado==='Confirmada' && $d['estado']==='Atendida')
                || (in_array(session('rol'),['Recepcionista','Medico']) && in_array($c->estado,['Programada','Confirmada']) && $d['estado']==='Inasistencia' && Carbon::parse($c->fecha_cita.' '.$c->hora_fin)->isPast());
            if(!$valid) throw ValidationException::withMessages(['estado'=>'Transición no permitida para su rol, estado o fecha de atención.']);
            if($d['estado']==='Atendida' && Carbon::parse($c->fecha_cita.' '.$c->hora_inicio)->isFuture()) throw ValidationException::withMessages(['estado'=>'La consulta todavía no ha comenzado.']);
            $c->update(['estado'=>$d['estado']]);Audit::log('Agenda','ESTADO',$c->codigo_cita.': '.$d['estado']);
        });return back()->with('success','Estado actualizado.');
    }
}
