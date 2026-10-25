<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Especialidad,Medico,DerivacionMedica};
use App\Services\CitaService;
use App\Support\Access;
use Illuminate\Http\Request;
class CitaController extends Controller
{
    public function create(Request $r)
    {
        $derivacion=null;
        if($r->filled('derivacion')) {
            $derivacion=DerivacionMedica::findOrFail($r->integer('derivacion'));
            Access::cita(CitaMedica::findOrFail($derivacion->id_cita_origen));
        }
        return view('citas.create',['especialidades'=>Especialidad::where('estado','Activo')->orderBy('nombre')->get(),'derivacion'=>$derivacion]);
    }
    public function medicos(Request $r)
    {
        $d=$r->validate(['id_especialidad'=>'required|integer|exists:especialidad,id_especialidad']);
        return response()->json(Medico::with('persona')->where('estado','Activo')->whereHas('especialidades',fn($q)=>$q->where('especialidad.id_especialidad',$d['id_especialidad'])->where('estado','Activo'))->get()->map(fn($m)=>['id_medico'=>$m->id_medico,'nombre'=>$m->persona->nombre_completo]));
    }
    public function getBloquesDisponibles(Request $r,CitaService $service)
    {
        $d=$r->validate(['id_medico'=>'required|integer|exists:medico,id_medico','fecha'=>'required|date|after_or_equal:today']);
        return response()->json($service->slots($d['id_medico'],$d['fecha']));
    }
    public function store(Request $r,CitaService $service)
    {
        if(session('rol')==='Paciente') $r->merge(['id_paciente'=>session('id_persona')]);
        $d=$r->validate(['id_paciente'=>'required|integer|exists:paciente,id_paciente','id_medico'=>'required|integer|exists:medico,id_medico','id_especialidad'=>'required|integer|exists:especialidad,id_especialidad','fecha_cita'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','motivo_consulta'=>'required|string|max:255','id_derivacion'=>'nullable|integer|exists:derivacion_medica,id_derivacion']);
        $c=$service->book($d,session('id_usuario'));
        return redirect()->route(session('rol')==='Paciente'?'paciente.citas':'recepcion.citas',['id_paciente'=>$c->id_paciente])->with('success','Reserva confirmada: '.$c->codigo_cita);
    }
    public function cancelar(Request $r,CitaMedica $cita,CitaService $service)
    {
        Access::cita($cita); $d=$r->validate(['motivo'=>'required|string|max:255']);
        $paid=$cita->pago()->exists();$service->cancel($cita,$d['motivo']); return back()->with('success','Cita cancelada. Horario liberado.'.($paid?' El pago se conserva; contacte a recepción para coordinar su devolución.':''));
    }
    public function reprogramar(Request $r,CitaMedica $cita,CitaService $service)
    {
        Access::cita($cita); $d=$r->validate(['fecha_cita'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','motivo'=>'required|string|max:230']);
        $c=$service->reschedule($cita,$d,session('id_usuario')); return back()->with('success','Nueva reserva: '.$c->codigo_cita);
    }
}
